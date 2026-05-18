<?php

namespace Kiss;

use Kiss\CopySync;
use Kiss\DataTree;
use Kiss\DataSource;
use Kiss\Log;
use Kiss\RouteCollection;
use Kiss\Tools;
use Kiss\KissException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Kiss
{
    public string $version = '0.1';
    public string $entry = 'kiss.yml';
    public string $root = '';
    public ?Log $log = null;
    private ?Environment $twig = null;
    public array $config = [
        'path' => [
            'copy' => 'copy',
            'data' => 'data',
            'route' => 'route',
            'template' => 'template',
            'dist' => 'web',
            'cache' => 'tmp',
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

    public function __construct(string $root = '')
    {
        $this->root = Path::canonicalize($root);
        $this->root = empty($this->root)
            ? ''
            : $this->root . \DIRECTORY_SEPARATOR;
    }

    public function newTask(): Task
    {
        return new Task();
    }

    public function config()
    {
        $entry = Path::canonicalize($this->root . $this->entry);
        if (!\is_file($entry)) {
            DataSource::saveYaml($entry, $this->config);
        }

        $parsed = Yaml::parseFile($entry);
        $this->config = Tools::merge($this->config, $parsed);

        if (\array_key_exists('KISS_DEBUG', $_ENV)) {
            $this->config['debug'] = Tools::truthy($_ENV['KISS_DEBUG']);
        }
        if (\array_key_exists('KISS_VERBOSE', $_ENV)) {
            $this->config['log']['verbose'] = Tools::truthy(
                $_ENV['KISS_VERBOSE']
            );
        }

        $this->log = new Log($this->config['log']);
        return $this;
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
        $description =
            'Kiss is another static site generator, written in php.' .
            'It uses Twig as a templating system and automatically generates image thumbnails.' .
            'It supports yaml, json or php data sources, from local or remote files,' .
            ' maximizing the use of local caches to speed up website building in case of modifications.';
        $this->log
            ->line(Log::color('white bold', 'DESCRIPTION'))
            ->line('────────────────────')
            ->line(Log::color('white dim', $description))
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
            'route list|[name]' =>
                'List routes, build a single route or all if omitted',
            'img' => '[TODO] Build images',
        ];
        $pad = max(array_map('strlen', array_keys($help))) + 8;
        foreach ($help as $k => $v) {
            $this->log->line(str_pad($k, $pad) . Log::color('white dim', $v));
        }
        return $this;
    }

    public function warmup()
    {
        $fs = new Filesystem();

        $root = rtrim($this->root, '/');
        $projectHash = md5($root ?: getcwd());

        foreach ($this->config['path'] as $k => $path) {
            if ($k === 'cache') {
                if ($path === 'tmp') {
                    $path = '/tmp/kiss/' . $projectHash;
                }
                $path .= '/kiss';
            }
            $path = Path::isAbsolute($path) ? $path : $this->root . $path;
            $path = Path::canonicalize($path);
            $this->config['path'][$k] = $path;
            if (!\is_dir($path)) {
                $fs->mkdir($path, 0777);
            }
        }

        $twigLoader = new FilesystemLoader(
            $this->config['path']['template']
        );
        $this->twig = new Environment($twigLoader, [
            'debug' => $this->config['debug'],
        ]);
        $this->twig->addExtension(new TwigExtension());

        return $this;
    }

    public function build()
    {
        $this->copy()->route();
        return $this;
    }

    public function route(string $arg = 'all')
    {
        $routePath = $this->config['path']['route'];
        if (!is_dir($routePath)) {
            $this->log->line(
                'route... ' . Log::color('yellow', 'no route folder')
            );
            return $this;
        }

        $tree = new DataTree([
            'cache' => $this->config['path']['cache'] . '/datatree',
        ]);

        $collection = new RouteCollection($routePath);

        if ($collection->getCount() === 0) {
            $this->log->line(
                'route... ' . Log::color('yellow', 'no routes to build')
            );
            return $this;
        }

        if ($arg === 'list') {
            $this->log->line(
                'route... ' .
                    Log::color('bold', (string) $collection->getCount()) .
                    ' routes available'
            );
            foreach ($collection->getRoutes() as $route) {
                $this->log->line(
                    '  - ' . Log::color('yellow', $route->name)
                );
            }
            return $this;
        }

        $routes = $arg === 'all'
            ? $collection->getRoutes()
            : [$arg => $collection->get($arg)];

        if (!$routes || reset($routes) === null) {
            $this->log->line(
                'route... ' . Log::color('yellow', 'route "' . $arg . '" not found')
            );
            return $this;
        }

        $global = $this->loadGlobalData($tree);
        $dist = $this->config['path']['dist'];
        $manifestPath = $this->config['path']['cache'] . '/route-manifest.php';
        $generated = [];

        $this->log
            ->line(
                'route... ' .
                    Log::color('bold', (string) count($routes)) .
                    ' route' . (count($routes) > 1 ? 's' : '')
            )
            ->line();

        foreach ($routes as $route) {
            $this->log->line(
                '  ' . Log::color('yellow', $route->name) . ':'
            );

            try {
                $pages = $route->getPages($tree);
            } catch (KissException $e) {
                $this->log->error(
                    '    ' . $e->getMessage()
                );
                continue;
            }

            foreach ($pages as $path => $data) {
                $data['global'] = $global;
                $render = $this->twig->render($route->template, $data);
                $outPath = $dist . '/' . $path;
                $fs = new Filesystem();
                $fs->dumpFile($outPath, $render);
                $generated[$path] = true;
                $this->log->line(
                    '    ✔ ' . Log::color('dim', $path)
                );
            }
            $this->log->line();
        }

        if ($arg === 'all') {
            $previous = is_file($manifestPath) ? (require $manifestPath) : [];

            foreach ($previous as $path) {
                if (!isset($generated[$path])) {
                    $stalePath = $dist . '/' . $path;
                    if (is_file($stalePath)) {
                        unlink($stalePath);
                        $this->log->line(
                            '    ✗ ' . Log::color('yellow', 'removed stale ' . $path)
                        );
                    }
                }
            }
        }

        $manifest = $arg === 'all'
            ? array_keys($generated)
            : array_values(array_unique(array_merge(
                is_file($manifestPath) ? (require $manifestPath) : [],
                array_keys($generated)
            )));

        if (count($manifest) > 0) {
            $dir = dirname($manifestPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            DataSource::savePhp($manifestPath, $manifest, 'route manifest');
        } elseif (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        return $this;
    }

    private function loadGlobalData(DataTree $tree): array
    {
        $dataPath = $this->config['path']['data'];
        if (!is_dir($dataPath)) {
            return [];
        }

        $raw = [];
        $extensions = ['yml', 'yaml', 'json', 'php'];

        foreach ($extensions as $ext) {
            $files = glob($dataPath . '/*.' . $ext);
            if ($files === false) {
                continue;
            }
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $ds = new DataSource($file);
                $raw[$name] = $ds->content;
            }
        }

        return $tree->resolve($raw);
    }

    public function copy(string $path = null)
    {
        $copyPath = $this->config['path']['copy'];
        $distPath = $this->config['path']['dist'];

        if (!is_dir($copyPath)) {
            $this->log->line(
                'copy... ' . Log::color('yellow', 'no copy folder')
            );
            return $this;
        }

        $sync = new CopySync($this->config['path']['cache']);

        if ($path === null) {
            $sync->sync($copyPath, $distPath);
            $this->log->line(
                'copy... ' .
                    Log::color('green', 'ok')
            );
        } else {
            $srcPath = $copyPath . '/' . $path;
            if (is_file($srcPath)) {
                $sync->syncFile($copyPath, $distPath, $path);
                $this->log->line(
                    'copy... ' . Log::color('green', $path)
                );
            } else {
                $sync->removeFile($distPath, $path);
                $this->log->line(
                    'copy... ' . Log::color('yellow', 'removed ' . $path)
                );
            }
        }

        return $this;
    }

    public function img()
    {
        $this->log->line(Log::color('dim', 'TODO: img'));
        return $this;
    }

    public function reset(string $arg = 'all')
    {
        $args = ['all', 'dist', 'cache'];
        if (!\in_array($arg, $args)) {
            $this->error(
                'reset accept "all", "dist" or "cache" as argument'
            );
        }

        $fs = new Filesystem();

        if ($arg === 'all' || $arg === 'dist') {
            $path = $this->config['path']['dist'];
            $fs->remove($path);
        }
        if ($arg === 'all' || $arg === 'cache') {
            $path = $this->config['path']['cache'];
            $fs->remove($path);
        }

        return $this;
    }

    public function getWatches()
    {
        $watches = [
            $this->entry,
            $this->config['path']['copy'],
            $this->config['path']['data'],
            $this->config['path']['template'],
        ];
        return implode(' ', $watches);
    }

    public function watched($path)
    {
        if ($path === $this->entry) {
            $this->build();
        } elseif (str_starts_with($path, $this->config['path']['copy'])) {
            $rel = Path::makeRelative(
                $path,
                $this->config['path']['copy']
            );
            $sync = new CopySync($this->config['path']['cache']);
            if (is_file($path)) {
                $sync->syncFile(
                    $this->config['path']['copy'],
                    $this->config['path']['dist'],
                    $rel
                );
            } else {
                $sync->removeFile($this->config['path']['dist'], $rel);
            }
        } elseif (
            str_starts_with($path, $this->config['path']['data']) ||
            str_starts_with($path, 'http:') ||
            str_starts_with($path, 'https:')
        ) {
            $this->route();
        } elseif (str_starts_with($path, $this->config['path']['template'])) {
            $this->route();
        }
    }

    public function error(
        string $str,
        array $vars = [],
        Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $vars), 0, $previous);
    }
}
