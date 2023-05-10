<?php

namespace Kiss;

use Kiss\DataTree;
use Twig\Environment;
use Kiss\KissException;
use ScssPhp\ScssPhp\Compiler;
use Symfony\Component\Yaml\Yaml;
use Twig\Loader\FilesystemLoader;
use Symfony\Component\Filesystem\Path;

class Kiss
{
    public string $version = '0.1';
    private string $entry = 'kiss.yml';
    private Environment $twig;
    private Compiler $scss;
    public Log $log;
    private array $route = [];
    private array $config = [
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

    public function __construct()
    {
        // create default entry file if not exists
        if (!\is_file($this->entry)) {
            \file_put_contents(
                $this->entry,
                \Symfony\Component\Yaml\Yaml::dump($this->config)
            );
            if (!\is_file($this->entry)) {
                throw new KissException('can not write default config file');
            }
        }

        // load entry
        try {
            $entry = Yaml::parseFile($this->entry);
        } catch (\Exception $e) {
            throw new KissException(
                'loading or parsing error from config file'
            );
        }

        // overrides by env
        if (Tools::truthy($_ENV['KISS_DEBUG'] ?? '')) {
            $entry['debug'] = true;
        }
        if (Tools::truthy($_ENV['KISS_VERBOSE'] ?? '')) {
            $entry['log']['verbose'] = true;
        }

        // merge with default values
        $this->config = Tools::merge($this->config, $entry);

        $this->log = new Log($this->config['log']);
    }

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
            'css list|[name]' =>
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

    // launch initialization of kiss
    public function init()
    {
        // sanitize and ensure pathes exists
        $task = $this->newTask(true);
        $created = [];
        foreach ($this->config['path'] as $k => $path) {
            // add subfolder for easying deletion
            if ($k === 'cache') {
                $path .= '/kiss';
            }
            $path = \rtrim($path, '/');
            $this->config['path'][$k] = $path;
            if (!\is_dir($path)) {
                \mkdir($path, 0777, true);
                if (!\is_dir($path)) {
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

    public function remove(string $arg = 'all')
    {
        $args = ['all', 'dist', 'cache'];
        if (!\in_array($arg, $args)) {
            $this->error(
                'remove accept "all", "dist" or "cache" as argument ("all" as default)'
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
        $this->timer('copy', true);
        $copy = $this->config['path']['copy'];
        $dist = $this->config['path']['dist'];
        $copyPath = $copy . '/' . $path;
        $distPath = $dist . '/' . $path;

        if ($path) {
            if (is_file($copyPath)) {
                // regular file, do the job
                $this->log('copy file "{path-copy}" to "{path-dist}"... ', [
                    '{path-copy}' => $copyPath,
                    '{path-dist}' => $distPath,
                ]);
                $dest = Path::makeRelative($path, $copy);
                $dest = $dist . \DIRECTORY_SEPARATOR . $dest;
                $this->copyFile($copyPath, $distPath);
            } else {
                if (is_file($distPath)) {
                    // file exists in dist, remove it
                    $this->log('remove "{path-dist}"... ', [
                        '{path-dist}' => $distPath,
                    ]);
                    $removed = \unlink($distPath);
                    if (!$removed) {
                        $this->error('error');
                    }
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
            $this->log(
                'copy all files from "{path-copy}" to "{path-dist}"... ',
                [
                    '{path-copy}' => $copy,
                    '{path-dist}' => $dist,
                ]
            );
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mirror($copy, $dist);
        }
        $this->logLine(Tools::color('green', 'ok'), [
            'duration' => $this->timer('copy'),
        ]);
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

    public function css(string $arg = 'all')
    {
        $sources = $this->config['scss']['sources'];
        if (!\is_array($sources) || \count($sources) === 0) {
            $this->logLine('css... ' . Tools::color('yellow', 'nothing to do'));
            return $this;
        }

        $args = array_merge(['all'], array_keys($sources));

        if (!\in_array($arg, $args)) {
            $this->error(
                'css accept "all", "{files}" as argument ("all" as default)',
                [
                    '{files}' => implode('", "', array_keys($sources)),
                ]
            );
        } else {
            if ($arg !== 'all') {
                $sources = [$arg => $sources[$arg]];
            }
            foreach ($sources as $src => $dest) {
                $task = 'css compile "{path-src}" to "{path-dest}"... ';
                $this->timer($task, true);
                $this->log('css compile "{path-src}" to "{path-dest}"... ', [
                    '{path-src}' => $src,
                    '{path-dest}' => $dest,
                ]);
                $this->compileScssFile($src, $dest);
                $this->logLine(Tools::color('green', 'ok'), [
                    'duration' => $this->timer($task),
                ]);
            }
        }

        return $this;
    }

    public function img()
    {
        $this->logLine(Tools::cliDim('TODO: img'));
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

    public function error(string $str, array $vars = [])
    {
        throw new KissException(strtr($str, $vars));
    }

    public function getWatches()
    {
        $watches = [
            $this->entry,
            $this->data['config']['path']['copy'],
            $this->data['config']['path']['data'],
            $this->data['config']['path']['scss'],
            $this->data['config']['path']['template'],
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
                    ->html();
            } elseif (str_starts_with($path, $template)) {
                $path = Path::makeRelative($path, $template);
                $this->data()->html();
            } elseif (str_starts_with($path, $scss)) {
                $path = Path::makeRelative($path, $scss);
                $this->css();
            }
        } catch (KissException $e) {
            $this->logLine(Tools::color('red', $e->getMessage()));
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
        $dist = $this->config['path']['dist'];
        $src = $scss . '/' . $src;
        $dest = $dist . '/' . $dest;
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
            $this->error(preg_replace('#\n.+#', '', $e->getMessage()));
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
