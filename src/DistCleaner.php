<?php

namespace Kiss;

class DistCleaner
{
    private string $cache;
    private string $dist;
    private Log $log;

    public function __construct(string $cache, string $dist, Log $log)
    {
        $this->cache = $cache;
        $this->dist = $dist;
        $this->log = $log;
    }

    public function clean(): void
    {
        $known = [];

        $copyManifest = $this->cache . '/copy-manifest.php';
        if (is_file($copyManifest)) {
            foreach (require $copyManifest as $path => $hash) {
                $known[$path] = true;
            }
        }

        $routeManifest = $this->cache . '/route-manifest.php';
        if (is_file($routeManifest)) {
            $grouped = require $routeManifest;
            foreach ($grouped as $name => $paths) {
                foreach ($paths as $p) {
                    $known[$p] = true;
                }
            }
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->dist,
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', $files->getSubPathname());
            if (!isset($known[$rel])) {
                unlink($file->getPathname());
                $this->log->fail('route', $rel);
            }
        }

        $dirs = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->dist,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($dirs as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }
    }
}
