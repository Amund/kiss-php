<?php

namespace Kiss;

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class RouteBuilder
{
    private DataTree $tree;
    private Environment $twig;
    private Log $log;
    private string $dist;
    private string $dataPath;

    public function __construct(
        DataTree $tree,
        Environment $twig,
        Log $log,
        string $dist,
        string $dataPath
    ) {
        $this->tree = $tree;
        $this->twig = $twig;
        $this->log = $log;
        $this->dist = $dist;
        $this->dataPath = $dataPath;
    }

    public function build(Route $route): array
    {
        $global = $this->loadGlobalData();

        try {
            $pages = $route->getPages($this->tree);
        } catch (KissException $e) {
            $fix = $e->getFix();
            $prefix = $route->name . ': ';
            $this->log->fail('route', $prefix . $e->getMessage());
            if ($fix) {
                $this->log->info('       ' . $fix);
            }
            return [];
        }

        $generated = [];
        foreach ($pages as $path => $data) {
            $data['global'] = $global;
            $render = $this->twig->render($route->template, $data);
            $outPath = $this->dist . '/' . $path;
            $fs = new Filesystem();
            $fs->dumpFile($outPath, $render);
            $generated[] = $path;
        }

        return $generated;
    }

    public function buildAndLog(Route $route): array
    {
        $start = microtime(true);
        $generated = $this->build($route);
        $count = count($generated);

        if ($count > 0) {
            $dur = Task::formatDuration(microtime(true) - $start);
            $label = $route->name . ': ' . $count . ' page' . ($count > 1 ? 's' : '') . ''
                . ' → ' . $route->template;
            $this->log->ok('route', $label, $dur);
        }

        return $generated;
    }

    public function removeStaleFiles(string $distPath, array $stalePaths, string $stopAt): void
    {
        foreach ($stalePaths as $stale) {
            $stalePath = $distPath . '/' . $stale;
            if (is_file($stalePath)) {
                unlink($stalePath);
                $this->rmdirEmptyParents($stalePath, $stopAt);
            }
        }
    }

    private function loadGlobalData(): array
    {
        if (!is_dir($this->dataPath)) {
            return [];
        }

        $raw = [];
        $extensions = ['yml', 'yaml', 'json', 'php', 'md'];

        foreach ($extensions as $ext) {
            $files = glob($this->dataPath . '/*.' . $ext);
            if ($files === false) {
                continue;
            }
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $ds = new DataSource($file);
                $raw[$name] = $ds->content;
            }
        }

        return $this->tree->resolve($raw);
    }

    private function rmdirEmptyParents(string $filePath, string $stopAt): void
    {
        $dir = dirname($filePath);
        while ($dir !== $stopAt && $dir !== '.' && $dir !== '/') {
            if (@rmdir($dir)) {
                $dir = dirname($dir);
            } else {
                break;
            }
        }
    }
}
