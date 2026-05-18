<?php

namespace Kiss;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CopySync
{
    private string $manifestPath;
    private array $manifest;

    public function __construct(string $cacheDir)
    {
        $this->manifestPath = rtrim($cacheDir, '/') . '/copy-manifest.php';
        $this->manifest = $this->load();
    }

    public function sync(string $sourceDir, string $destDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        $files = $this->scanFiles($sourceDir);
        $currentPaths = [];

        foreach ($files as $relative => $hash) {
            $currentPaths[$relative] = true;
            $destPath = $destDir . '/' . $relative;

            if (
                !isset($this->manifest[$relative]) ||
                $this->manifest[$relative] !== $hash
            ) {
                $destDirname = dirname($destPath);
                if (!is_dir($destDirname)) {
                    mkdir($destDirname, 0777, true);
                }
                copy($sourceDir . '/' . $relative, $destPath);
                $this->manifest[$relative] = $hash;
            }
        }

        foreach ($this->manifest as $relative => $hash) {
            if (!isset($currentPaths[$relative])) {
                $destPath = $destDir . '/' . $relative;
                if (is_file($destPath)) {
                    unlink($destPath);
                }
                unset($this->manifest[$relative]);
            }
        }

        $this->save();
    }

    public function syncFile(string $sourceDir, string $destDir, string $relative): void
    {
        $srcPath = $sourceDir . '/' . $relative;
        $destPath = $destDir . '/' . $relative;

        if (!is_file($srcPath)) {
            return;
        }

        $hash = hash_file('crc32c', $srcPath);

        if (
            !isset($this->manifest[$relative]) ||
            $this->manifest[$relative] !== $hash
        ) {
            $destDirname = dirname($destPath);
            if (!is_dir($destDirname)) {
                mkdir($destDirname, 0777, true);
            }
            copy($srcPath, $destPath);
            $this->manifest[$relative] = $hash;
            $this->save();
        }
    }

    public function removeFile(string $destDir, string $relative): void
    {
        if (isset($this->manifest[$relative])) {
            $destPath = $destDir . '/' . $relative;
            if (is_file($destPath)) {
                unlink($destPath);
            }
            unset($this->manifest[$relative]);
            $this->save();
        }
    }

    private function scanFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace(
                    '\\',
                    '/',
                    $iterator->getSubPathname()
                );
                $files[$relative] = hash_file('crc32c', $file->getPathname());
            }
        }

        ksort($files);
        return $files;
    }

    private function load(): array
    {
        if (is_file($this->manifestPath)) {
            return require $this->manifestPath;
        }
        return [];
    }

    private function save(): void
    {
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        DataSource::savePhp($this->manifestPath, $this->manifest, 'copy manifest');
    }
}
