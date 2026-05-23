<?php

namespace Kiss;

use Kiss\CopySync;
use Kiss\DataTree;
use Kiss\DataSource;
use Kiss\Log;
use Kiss\RouteCollection;
use Kiss\TemplateDeps;
use Kiss\Tools;
use Kiss\KissException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Kiss
{
    use Thrower;

    public string $entry = 'kiss.yml';
    public string $root = '';
    public ?Log $log = null;
    private ?Environment $twig = null;
    private ?TwigExtension $twigExtension = null;
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
                'error' => 'red',
            ],
        ],
        'debug' => false,
    ];

    public function __construct(string $root = '')
    {
        $this->root = $root;
    }

    public function newTask(): Task
    {
        return new Task();
    }

    public function getVersion(): string
    {
        $root = $this->root !== '' ? $this->root : getcwd();
        $path = $root . '/composer.json';

        if (!is_file($path)) {
            return '0.0';
        }

        $composer = json_decode(file_get_contents($path), true);
        return $composer['version'] ?? '0.0';
    }

    public function config()
    {
        $config = new Config($this->root, $this->entry);
        $config->load();
        $this->config = $config->toArray();
        $this->log = new Log($this->config['log']);
        return $this;
    }

    public function version()
    {
        $this->log
            ->line('{icon}{kiss} {version} {baseline}', [
                '{icon}' => '💋',
                '{kiss}' => Log::color('green bold', 'Kiss'),
                '{version}' => Log::color('yellow', 'v' . $this->getVersion()),
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
            ->info('DESCRIPTION')
            ->info('────────────────────')
            ->info($description)
            ->line()
            ->info('HELP')
            ->info('────────────────────');
        $help = [
            'build' => 'Build site (launch all tasks: copy, route, img)',
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
        $twigLoader = new FilesystemLoader(
            $this->config['path']['template']
        );
        $this->twig = new Environment($twigLoader, [
            'debug' => $this->config['debug'],
        ]);
        $this->twigExtension = new TwigExtension();
        $this->twig->addExtension($this->twigExtension);

        return $this;
    }

    public function build()
    {
        $this->copy()->route();
        $cleaner = new DistCleaner(
            $this->config['path']['cache'],
            $this->config['path']['dist'],
            $this->log
        );
        $cleaner->clean();
        $this->log->line();
        return $this;
    }

    public function route(string $arg = 'all')
    {
        $routePath = $this->config['path']['route'];
        if (!is_dir($routePath)) {
            $this->log->fail('route', 'folder not found');
            return $this;
        }

        $tree = new DataTree([
            'cache' => $this->config['path']['cache'] . '/datatree',
        ]);

        $collection = new RouteCollection($routePath);

        $this->twigExtension?->setUrlGenerator(
            new UrlGenerator($collection)
        );
        $this->twigExtension?->setRouteCollection($collection);
        $this->twigExtension?->setDataTree($tree);

        if ($collection->getCount() === 0) {
            $this->log->fail('route', 'no routes to build');
            return $this;
        }

        if ($arg === 'list') {
            $this->log->info(
                ' route  {n} routes available',
                ['{n}' => (string) $collection->getCount()]
            );
            foreach ($collection->getRoutes() as $route) {
                $this->log->line(
                    '  · {name}',
                    ['{name}' => $route->name]
                );
            }
            return $this;
        }

        $routes = $arg === 'all'
            ? $collection->getRoutes()
            : [$arg => $collection->get($arg)];

        if (!$routes || reset($routes) === null) {
            $this->log->fail('route', $arg . ' not found');
            $this->log->info('       Run: kiss route list');
            return $this;
        }

        $builder = new RouteBuilder(
            $tree,
            $this->twig,
            $this->log,
            $this->config['path']['dist'],
            $this->config['path']['data']
        );

        $dist = $this->config['path']['dist'];
        $manifestPath = $this->config['path']['cache'] . '/route-manifest.php';
        $generated = [];
        $totalPages = 0;
        $routeStart = microtime(true);

        foreach ($routes as $route) {
            if ($arg !== 'all') {
                $manifest = is_file($manifestPath) ? (require $manifestPath) : [];
                if (isset($manifest[$route->name])) {
                    $builder->removeStaleFiles($dist, $manifest[$route->name], $dist);
                }
            }

            $paths = $builder->buildAndLog($route);
            $generated[$route->name] = $paths;
            $totalPages += count($paths);
        }

        $manifest = is_file($manifestPath) ? (require $manifestPath) : [];

        if (array_is_list($manifest)) {
            $manifest = [];
        }

        if ($arg === 'all') {
            $generatedFlat = [];
            foreach ($generated as $paths) {
                foreach ($paths as $p) {
                    $generatedFlat[$p] = true;
                }
            }
            foreach ($manifest as $name => $paths) {
                foreach ($paths as $p) {
                    if (!isset($generatedFlat[$p])) {
                        $builder->removeStaleFiles($dist, [$p], $dist);
                        $this->log->fail('clean', $p);
                    }
                }
            }
            $manifest = $generated;
        } else {
            foreach ($generated as $name => $paths) {
                $manifest[$name] = $paths;
            }
        }

        if (count($manifest) > 0) {
            $dir = dirname($manifestPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            DataSource::savePhp($manifestPath, $manifest, 'route manifest');
        } elseif (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        if ($arg === 'all') {
            $dur = Task::formatDuration(microtime(true) - $routeStart);
            $this->log->ok('built', $totalPages . ' page' . ($totalPages > 1 ? 's' : ''), $dur);
        }

        return $this;
    }

    public function copy(?string $path = null)
    {
        $copyPath = $this->config['path']['copy'];
        $distPath = $this->config['path']['dist'];

        if (!is_dir($copyPath)) {
            $this->log->fail('copy', 'folder not found');
            $this->log->info('       create a {path} directory', [
                '{path}' => $copyPath,
            ]);
            return $this;
        }

        $sync = new CopySync($this->config['path']['cache']);

        if ($path === null) {
            $start = microtime(true);
            $sync->sync($copyPath, $distPath);
            $dur = Task::formatDuration(microtime(true) - $start);
            $this->log->ok('copy', $sync->getTotalFiles() . ' files', $dur);
        } else {
            $srcPath = $copyPath . '/' . $path;
            if (is_file($srcPath)) {
                $sync->syncFile($copyPath, $distPath, $path);
                $this->log->ok('copy', $path);
            } else {
                $sync->removeFile($distPath, $path);
                $this->log->fail('copy', $path);
            }
        }

        return $this;
    }

    public function img()
    {
        $this->log->info('TODO: img');
        return $this;
    }

    public function reset(string $arg = 'all')
    {
        $args = ['all', 'dist', 'cache'];
        if (!\in_array($arg, $args)) {
            $this->error(
                'reset accept "all", "dist" or "cache" as argument',
                [],
                null,
                'Try: kiss reset all, kiss reset dist, or kiss reset cache'
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
            $this->config['path']['route'],
            $this->config['path']['template'],
        ];
        return implode(' ', $watches);
    }

    public function watched(string $path)
    {
        $this->log->prependTimestamp = true;

        if ($path === $this->entry) {
            $this->build();
        } elseif (str_starts_with($path, $this->config['path']['copy'])) {
            $rel = Path::makeRelative(
                $path,
                $this->config['path']['copy']
            );
            $sync = new CopySync($this->config['path']['cache']);
            if (is_file($path)) {
                if (
                    $sync->syncFile(
                        $this->config['path']['copy'],
                        $this->config['path']['dist'],
                        $rel
                    )
                ) {
                    $this->log->ok('copy', $rel);
                }
            } else {
                if ($sync->removeFile($this->config['path']['dist'], $rel)) {
                    $this->log->fail('copy', $rel);
                }
            }
        } elseif (str_starts_with($path, $this->config['path']['route'])) {
            $rel = Path::makeRelative(
                $path,
                $this->config['path']['route']
            );
            $name = pathinfo($rel, PATHINFO_FILENAME);
            $this->route($name);
        } elseif (
            str_starts_with($path, $this->config['path']['data']) ||
            str_starts_with($path, 'http:') ||
            str_starts_with($path, 'https:')
        ) {
            $this->route();
        } elseif (str_starts_with($path, $this->config['path']['template'])) {
            $rel = Path::makeRelative(
                $path,
                $this->config['path']['template']
            );
            $deps = new TemplateDeps(
                $this->config['path']['template'],
                $this->config['path']['cache']
            );
            $impacted = $deps->findImpacted($rel);
            $collection = new RouteCollection($this->config['path']['route']);
            $built = 0;
            foreach ($collection->getRoutes() as $route) {
                if (in_array($route->template, $impacted, true)) {
                    $this->route($route->name);
                    $built++;
                }
            }
            if ($built === 0) {
                $this->log->fail('template', $rel . ' — no matching routes');
            }
        }
    }
}
