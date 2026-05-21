<?php

namespace Kiss;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TemplateDeps
{
    private string $templateDir;
    private string $manifestPath;
    private array $graph;

    public function __construct(string $templateDir, string $cacheDir)
    {
        $this->templateDir = rtrim($templateDir, '/');
        $this->manifestPath = rtrim($cacheDir, '/') . '/template-deps.php';

        if (is_file($this->manifestPath)) {
            $this->graph = require $this->manifestPath;
            if (!$this->isFresh()) {
                $this->build();
            }
        } else {
            $this->build();
        }
    }

    public function findImpacted(string $changed): array
    {
        $impacted = [];
        $visited = [];
        $this->walkUp($changed, $impacted, $visited);
        return $impacted;
    }

    public function rebuild(): void
    {
        $this->build();
    }

    public function clear(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        $this->graph = [];
    }

    private function build(): void
    {
        $raw = [];
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->templateDir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'twig') {
                $rel = str_replace('\\', '/', $iterator->getSubPathname());
                $files[$rel] = file_get_contents($file->getPathname());
            }
        }

        // Graph: template → [templates that reference it directly]
        $this->graph = [];

        foreach ($files as $name => $content) {
            $refs = $this->extractRefs($content);
            foreach ($refs as $ref) {
                if (!isset($this->graph[$ref])) {
                    $this->graph[$ref] = [];
                }
                if (!in_array($name, $this->graph[$ref], true)) {
                    $this->graph[$ref][] = $name;
                }
            }
        }

        $this->save();
    }

    private function isFresh(): bool
    {
        $cacheMtime = is_file($this->manifestPath) ? filemtime($this->manifestPath) : 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->templateDir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'twig') {
                if ($file->getMTime() > $cacheMtime) {
                    return false;
                }
            }
        }

        return true;
    }

    private function extractRefs(string $content): array
    {
        $refs = [];
        preg_match_all(
            '/{%\s+(extends|include|embed)\s+([\'"])([^\'"]+)\2/',
            $content,
            $matches
        );
        foreach ($matches[3] as $ref) {
            if (!in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }
        return $refs;
    }

    private function walkUp(string $current, array &$impacted, array &$visited): void
    {
        if (isset($visited[$current])) {
            return;
        }
        $visited[$current] = true;
        $impacted[] = $current;

        if (!isset($this->graph[$current])) {
            return;
        }

        foreach ($this->graph[$current] as $parent) {
            $this->walkUp($parent, $impacted, $visited);
        }
    }

    private function save(): void
    {
        $dir = dirname($this->manifestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        DataSource::savePhp($this->manifestPath, $this->graph, 'template deps');
    }
}
