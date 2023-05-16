<?php

namespace Kiss;

use Exception;
use Kiss\DataTree;
use Twig\Environment;
use Kiss\KissException;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Yaml\Yaml;
use Twig\Loader\FilesystemLoader;
use Symfony\Component\Filesystem\Path;
use Throwable;

class Kiss
{
    public string $version = '0.1';
    public string $entry = 'kiss.yml';
    public string $root = '';
    public ?Log $log = null;
    private Environment $twig;
    private Compiler $scss;
    private array $preConfigTasks = [];
    private array $route = [];
    public array $config = [
        'path' => [
            'copy' => 'copy',
            'data' => 'data',
            'route' => 'route',
            'scss' => 'scss',
            'template' => 'template',
            'dist' => 'web',
            'cache' => 'tmp',
        ],
        'scss' => [
            'sources' => [],
        ],
        'log' => [
            'verbose' => false,
            'color' => [
                'path' => 'yellow',
                'duration' => 'green dim',
                'error' => 'red dim',
            ],
        ],
        'debug' => false,
    ];

    // handle config creation/loading
    public function __construct(string $root = '')
    {
        $this->root = Path::canonicalize($root);
        $this->root = empty($this->root)
            ? ''
            : $this->root . \DIRECTORY_SEPARATOR;
    }

    // cli version
    public function version()
    {
        $this->log
            ->line('{icon}{kiss} {version} {baseline}', [
                '{icon}' => '💋',
                '{kiss}' => Log::color('green bold', 'Kiss'),
                '{version}' => Log::color('yellow', 'v' . $this->version),
                '{baseline}' => Log::color(
                    'white dim',
                    '- Keep it simply static'
                ),
            ])
            ->line();
        return $this;
    }

    // cli help
    public function help()
    {
        $description = [
            'Kiss is another static site generator, written in php.',
            'It uses Twig as a templating system, compiles scss files, and automatically generates image thumbnails.',
            'It supports yaml, json or php data sources, from local or remote files, maximizing the use of local caches to speed up website building in case of modifications.',
        ];
        $this->log
            ->line(Log::color('white bold', 'DESCRIPTION'))
            ->line('────────────────────')
            ->line(Log::color('white dim', implode(' ', $description)))
            ->line()
            ->line(Log::color('white bold', 'HELP'))
            ->line('────────────────────');
        $help = [
            'build' => 'Build site (launch all tasks: copy, route, css, img)',
            'watch' =>
                'Build site, then watch for modifications (using inotifywait)',
            'reset [dist|cache]' =>
                'Remove dist or cache folder, or all if omitted',
            'copy [path]' =>
                'Copy a single file from copy to dist, or launch a complete mirroring if omitted',
            'scss list|[name]' =>
                'List main scss files, compile a single file or all if omitted',
            'route list|[name]' =>
                'List routes, build a single route or all if omitted',
            'img' => '[TODO] Build images',
        ];
        $pad = array_keys($help);
        $pad = array_map(function ($v) {
            return strlen($v);
        }, $pad);
        $pad = max($pad);
        $pad = $pad + 8;
        foreach ($help as $k => $v) {
            $this->log->line(str_pad($k, $pad) . Log::color('white dim', $v));
        }
        return $this;
    }

    // task objects factory
    public function newTask(bool $verbose = false): Task
    {
        if (!$this->log) {
            // too soon, no log for now
            return new Task($verbose, null);
        } else {
            // config loaded, log available
            return new Task($verbose, $this->log);
        }
    }

    // config creation/loading
    public function config()
    {
        // create default entry file if not exists
        $entry = Path::canonicalize($this->root . $this->entry);
        if (!\is_file($entry)) {
            $task = $this->newTask()->begin(
                'create default entry file "{path}"',
                [
                    '{path}' => $this->entry,
                ]
            );
            $yaml = Yaml::dump($this->config);
            $written = \file_put_contents($entry, $yaml);
            if ($written === \false) {
                throw new KissException('can not write default config file');
            }
            $task->end();
            $this->preConfigTasks[] = $task;
        }

        // load entry
        $task = $this->newTask(true)->begin('load entry file "{path}"', [
            '{path}' => $this->entry,
        ]);
        try {
            $entry = Yaml::parseFile($entry);
        } catch (\Exception $e) {
            throw new KissException(
                'loading or parsing error from config file'
            );
        }
        $task->end();
        $this->preConfigTasks[] = $task;

        // debug: override by env
        $message = 'debug {value}';
        if (\array_key_exists('KISS_DEBUG', $_ENV)) {
            $entry['debug'] = Tools::truthy($_ENV['KISS_DEBUG']);
            $message .= ' (overriden by env variable KISS_DEBUG)';
        }
        $this->preConfigTasks[] = $this->newTask(true)->end($message, [
            '{value}' => $entry['debug'] ? 'on' : 'off',
        ]);

        // verbose: override by env
        $message = 'verbose {value}';
        if (\array_key_exists('KISS_VERBOSE', $_ENV)) {
            $entry['log']['verbose'] = Tools::truthy($_ENV['KISS_VERBOSE']);
            $message .= ' (overriden by env variable KISS_VERBOSE)';
        }
        $this->preConfigTasks[] = $this->newTask(true)->end($message, [
            '{value}' => $entry['debug'] ? 'on' : 'off',
        ]);

        // merge with default values
        $this->config = Tools::merge($this->config, $entry);
        $this->log = new Log($this->config['log']);

        return $this;
    }

    // init kiss environment
    public function warmup()
    {
        // echo saved logs
        if (\count($this->preConfigTasks) > 0) {
            foreach ($this->preConfigTasks as $task) {
                $task->setLog($this->log);
            }
        }

        // sanitize and ensure pathes exists
        $task = $this->newTask(true);
        $created = [];
        foreach ($this->config['path'] as $k => $path) {
            // add subfolder for easying deletion
            if ($k === 'cache') {
                $path .= '/kiss';
            }
            $path = Path::isAbsolute($path) ? $path : $this->root . $path;
            $path = Path::canonicalize($path);

            $this->config['path'][$k] = $path;
            if (!\is_dir($path)) {
                $written = \mkdir($path, 0777, \true);
                if ($written === \false) {
                    $this->error(
                        'can not create "{path}" folder, defined in config "{path-entry}"',
                        [
                            '{path}' => $path,
                            '{path-entry}' => $this->entry,
                        ]
                    );
                }
                $created[] = Log::color($this->log->colorPath, $path);
            }
        }
        if (count($created) > 0) {
            $task->end('create folders "{folders}"', [
                '{folders}' => implode('", "', $created),
            ]);
        }

        // create twig
        $task = $this->newTask(true)->begin('initialize twig environment');
        $twigLoader = new FilesystemLoader($this->config['path']['template']);
        $this->twig = new Environment($twigLoader, [
            'debug' => $this->config['debug'],
        ]);
        $this->twig->addExtension(new TwigExtension());
        $task->end();

        // create scss
        $task = $this->newTask(true);
        $task->begin('initialize scss environment');
        $this->scss = new Compiler();
        $this->scss->setImportPaths($this->config['path']['scss']);
        $task->end();

        // add default .gitignore if none exists
        if (!is_file('.gitignore')) {
            $task = $this->newTask(true)->begin('add default "{path}"', [
                '{path}' => '.gitignore',
            ]);
            $content = ['/.env', '/tmp', '/vendor', '/web'];
            $this->dumpFile('.gitignore', implode("\n", $content) . "\n");
            $task->end();
        }

        // add default .htaccess if none exists
        $path = $this->config['path']['copy'] . '/.htaccess';
        if (!is_file($path)) {
            $task = $this->newTask(true)->begin('add default "{path}"', [
                '{path}' => $path,
            ]);
            $content = [
                'RewriteEngine On',
                'RewriteCond %{REQUEST_FILENAME} !-d',
                'RewriteCond %{REQUEST_FILENAME} !-f',
                'RewriteCond %{REQUEST_FILENAME}.html -f',
                'RewriteRule ^ %{REQUEST_URI}.html [L]',
            ];
            $this->dumpFile($path, implode("\n", $content) . "\n");
            $task->end();
        }

        return $this;
    }

    public function build()
    {
        $this->copy()
            ->scss()
            ->route()
            ->img();
    }

    public function data(string $path = null)
    {
        if ($path) {
            $this->log('Data changed in "{path}"... ', [
                '{path}' => Tools::cliYellow($path),
            ]);
            $this->dt->invalidate($path);
            $this->logLine(Tools::cliLightGreen('ok'));
        } else {
            $this->log('Resolve data tree... ');
            try {
                $this->data = $this->dt->resolve($this->data);
            } catch (KissException $e) {
                $this->logLine(Tools::cliLightRed($e->getMessage()));
                if ($e->getPrevious()) {
                    dump($e->getPrevious());
                }
                die();
            }
            $this->logLine(Tools::cliLightGreen('ok'));
        }
        return $this;
    }

    public function reset(string $arg = 'all')
    {
        $args = ['all', 'dist', 'cache'];
        if (!\in_array($arg, $args)) {
            $this->error(
                'reset accept "all", "dist" or "cache" as argument ("all" as default)'
            );
        } else {
            if ($arg === 'all' || $arg === 'dist') {
                // remove dist
                $path = $this->config['path']['dist'];
                $task = $this->newTask()->begin('remove dist folder "{path}"', [
                    '{path}' => $path,
                ]);
                $this->rm($path);
                $task->end();
            }
            if ($arg === 'all' || $arg === 'cache') {
                // remove caches
                $path = $this->config['path']['cache'];
                $task = $this->newTask()->begin(
                    'remove cache folder "{path}"',
                    [
                        '{path}' => $path,
                    ]
                );
                $this->rm($path);
                $task->end();
            }
        }
        return $this;
    }

    public function copy(string $path = null)
    {
        $copy = $this->config['path']['copy'];
        $dist = $this->config['path']['dist'];
        $copyPath = $copy . '/' . $path;
        $distPath = $dist . '/' . $path;

        if ($path) {
            if (is_file($copyPath)) {
                // regular file, do the job
                $task = $this->newTask()->begin(
                    'copy file "{path-copy}" to "{path-dist}"',
                    [
                        '{path-copy}' => $copyPath,
                        '{path-dist}' => $distPath,
                    ]
                );
                $dest = Path::makeRelative($path, $copy);
                $dest = $dist . \DIRECTORY_SEPARATOR . $dest;
                $this->copyFile($copyPath, $distPath);
                $task->end();
            } else {
                if (is_file($distPath)) {
                    // file exists in dist, remove it
                    $task = $this->newTask()->begin('remove "{path-dist}"', [
                        '{path-dist}' => $distPath,
                    ]);
                    $removed = \unlink($distPath);
                    if (!$removed) {
                        $this->error('error');
                    }
                    $task->end();
                } else {
                    $this->error(
                        'no "{path-copy}" to copy, nor "{path-dist}" to delete',
                        [
                            '{path-copy}' => $copyPath,
                            '{path-dist}' => $distPath,
                        ]
                    );
                }
            }
        } else {
            // copy all
            $task = $this->newTask()->begin(
                'copy all files from "{path-copy}" to "{path-dist}"... ',
                [
                    '{path-copy}' => $copy,
                    '{path-dist}' => $dist,
                ]
            );
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mirror($copy, $dist);
            $task->end();
        }
        return $this;
    }

    public function route(string $arg = 'all')
    {
        $routes = $this->getRoutes();

        dump($routes);

        // create datatree
        // $this->dt = new DataTree([
        //     'cache' => $this->config['path']['cache'] . '/datatree',
        // ]);

        // build
        // $this->log('Build html pages... ');
        // foreach ($this->data['routes'] as $name => $route) {
        //     $route = new Route($name, $route);
        //     $pages = $route->getPages();

        //     foreach ($pages as $file => $data) {
        //         $data['global'] = &$this->data['data'];

        //         try {
        //             $render = $this->twig->render($route->template, $data);
        //         } catch (\Exception $e) {
        //             $this->error('{message} in "{file}":{line}', [
        //                 '{message}' => $e->getMessage(),
        //                 '{file}' => Path::makeRelative(
        //                     $e->getFile(),
        //                     \getcwd()
        //                 ),
        //                 '{line}' => $e->getLine(),
        //             ]);
        //             // $this->error(preg_replace('#\n.+#', '', $e->getMessage()));
        //         }
        //         $file = $this->data['config']['path']['dist'] . '/' . $file;
        //         $this->fs->dumpFile($file, $render);
        //     }
        // }
        // $this->logLine(Tools::cliLightGreen(' ok'));
        return $this;
    }

    public function scss(string $arg = 'all')
    {
        $sources = $this->config['scss']['sources'];
        if (!\is_array($sources) || \count($sources) === 0) {
            $this->log->line(
                'css... ' .
                    Log::color('yellow', 'no sources in config, nothing to do')
            );
            return $this;
        }

        $args = array_merge(['all', 'list'], array_keys($sources));
        if (!\in_array($arg, $args)) {
            $this->error(
                'css accept "all", "list", "{files}" as argument ("all" as default)',
                [
                    '{files}' => implode('", "', array_keys($sources)),
                ]
            );
        }

        if ($arg === 'list') {
            $this->log->line('scss sources available :');
            foreach ($sources as $k => $v) {
                $this->log->line('   ' . $k);
            }
            return $this;
        }

        if ($arg !== 'all') {
            $sources = [$arg => $sources[$arg]];
        }

        $scss = $this->config['path']['scss'];
        $dist = $this->config['path']['dist'];
        foreach ($sources as $k => $v) {
            unset($sources[$k]);
            $src = $scss . '/' . $k;
            $dest = $dist . '/' . $v;
            $sources[$src] = $dest;
        }

        foreach ($sources as $src => $dest) {
            $task = $this->newTask()->begin(
                'css compile "{path-src}" to "{path-dest}"',
                [
                    '{path-src}' => $src,
                    '{path-dest}' => $dest,
                ]
            );
            $this->compileScssFile($src, $dest);
            $task->end();
        }

        return $this;
    }

    public function img()
    {
        $this->log->line(Log::color('dim', 'TODO: img'));
        return $this;
    }

    // public function log(string $str = '', array $vars = [])
    // {
    //     foreach ($vars as $k => $v) {
    //         if (\str_starts_with($k, '{path')) {
    //             $vars[$k] = Tools::color(self::COLOR_PATH, $v);
    //         }
    //     }
    //     echo strtr($str, $vars);
    //     return $this;
    // }

    // public function logLine(string $str = '', array $vars = [])
    // {
    //     if (\array_key_exists('duration', $vars)) {
    //         $str .= Tools::color(
    //             self::COLOR_TIME,
    //             '  ' . $vars['duration'] . ''
    //         );
    //     }
    //     $this->log($str . "\n", $vars);
    //     return $this;
    // }

    // public function info(string $str = '', array $vars = [])
    // {
    //     if (Tools::truthy($this->config['verbose'])) {
    //         $this->logLine(Tools::color('white dim', $str), $vars);
    //     }
    //     return $this;
    // }

    public function error(
        string $str,
        array $vars = [],
        \Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $vars), 0, $previous);
    }

    public function getWatches()
    {
        $watches = [
            $this->entry,
            $this->config['path']['copy'],
            $this->config['path']['data'],
            $this->config['path']['scss'],
            $this->config['path']['template'],
        ];
        return implode(' ', $watches);
    }

    public function watched($path)
    {
        $copy = $this->config['path']['copy'];
        $data = $this->config['path']['data'];
        $scss = $this->config['path']['scss'];
        $template = $this->config['path']['template'];

        try {
            if ($path === $this->entry) {
                $this->build();
            } elseif (str_starts_with($path, $copy)) {
                // copy
                $path = Path::makeRelative($path, $copy);
                $this->copy($path);
            } elseif (
                str_starts_with($path, $data) ||
                str_starts_with($path, 'http:') ||
                str_starts_with($path, 'https:')
            ) {
                // data
                $path = Path::makeRelative($path, $data);
                $this->data($path)
                    ->data()
                    ->route();
            } elseif (str_starts_with($path, $template)) {
                $path = Path::makeRelative($path, $template);
                $this->data()->route();
            } elseif (str_starts_with($path, $scss)) {
                $path = Path::makeRelative($path, $scss);
                $this->scss();
            }
        } catch (KissException $e) {
            $this->log->error($e->getMessage());
        } catch (Throwable $e) {
            $this->log->error(
                '{message} in {file} line {line}' . "\n" . '{trace}',
                [
                    '{message}' => $e->getMessage(),
                    '{file}' => $e->getFile(),
                    '{line}' => $e->getLine(),
                    '{trace}' => $e->getTraceAsString(),
                ]
            );
        }
    }

    private function getRoute(\SplFileInfo $file)
    {
        $routePath = $this->config['path']['route'];
        $item = new \SplFileInfo($routePath . '/' . $file);
        $ext = strtolower($item->getExtension());
        if ($item->isFile() && \in_array($ext, ['yml', 'yaml'])) {
            try {
                $routes[$route] = Yaml::parseFile($routePath . '/' . $route);
            } catch (\Exception $e) {
                $this->error(
                    'loading or parsing error from route file "{path}"',
                    [
                        '{path}' => $route,
                    ]
                );
            }
        }
    }

    private function getRoutes()
    {
        $routePath = $this->config['path']['route'];
        $routes = [];
        foreach (new \FilesystemIterator($routePath) as $file) {
            $ext = strtolower($file->getExtension());
            if ($file->isFile() && \in_array($ext, ['yml', 'yaml'])) {
                $name = $file->getFilename();
                $routes[$name] = $this->getRoute($name);
            }
        }
        return $routes;
    }

    private function compileScssFile($src, $dest)
    {
        $scss = $this->config['path']['scss'];
        // $dist = $this->config['path']['dist'];
        // $src = $scss . '/' . $src;
        // $dest = $dist . '/' . $dest;
        $basename = basename($dest);
        if (!is_file($src)) {
            $this->error('source file not found');
        }
        $content = \file_get_contents($src);
        $this->scss->setOutputStyle('compressed');
        $this->scss->setSourceMap(Compiler::SOURCE_MAP_FILE);
        $this->scss->setSourceMapOptions([
            'sourceMapURL' => $basename . '.map',
            'sourceMapFilename' => $basename,
            'sourceMapBasepath' => realpath($scss),
        ]);
        try {
            $compiled = $this->scss->compileString($content, $src);
        } catch (\Throwable $e) {
            $message = preg_replace('#\n.+#', '', $e->getMessage());
            $this->error($message, [], $e);
        }
        $this->dumpFile($dest, $compiled->getCss());
        $this->dumpFile($dest . '.map', $compiled->getSourceMap());
    }

    private function copyFile(string $from, string $to)
    {
        $basename = basename($from);
        $dirname = dirname($to);
        $this->ensureDir($dirname);
        $tmp = $this->config['path']['cache'] . '/_copy';
        copy($from, $tmp);
        rename($tmp, $to);
    }

    private function dumpFile(string $path, $content)
    {
        $basename = basename($path);
        $dirname = dirname($path);
        $this->ensureDir($dirname);
        $tmpPath = $this->config['path']['cache'];
        $this->ensureDir($tmpPath);
        $tmp = $tmpPath . '/_dump';
        \file_put_contents($tmp, $content);
        rename($tmp, $path);
    }

    private function ensureDir($path)
    {
        if (!\is_dir($path)) {
            \mkdir($path, 0777, true);
        }
    }

    private function rm($path)
    {
        exec(sprintf('rm -rf %s', escapeshellarg($path)));
    }
}
