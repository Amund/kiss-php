<?php

namespace Kiss;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Config
{
    private const EXTENSIONS = ['yml', 'yaml', 'php', 'json', 'xml', 'ini'];

    private array $config = [
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

    private string $root;
    private string $basename;

    public function __construct(string $root, string $entry = 'kiss.yml')
    {
        $this->root = Path::canonicalize($root);
        $this->root = empty($this->root)
            ? ''
            : $this->root . \DIRECTORY_SEPARATOR;
        $dir = dirname($entry);
        if ($dir !== '.') {
            $this->root .= $dir . \DIRECTORY_SEPARATOR;
        }
        $this->basename = pathinfo($entry, PATHINFO_FILENAME);
    }

    public function load(): void
    {
        $loaded = false;

        foreach (self::EXTENSIONS as $ext) {
            $entry = Path::canonicalize($this->root . $this->basename . '.' . $ext);
            if (\is_file($entry)) {
                $ds = new DataSource($entry);
                $this->config = Tools::merge($this->config, (array) $ds->content);
                $loaded = true;
                break;
            }
        }

        if (!$loaded) {
            $entry = Path::canonicalize($this->root . $this->basename . '.yml');
            DataSource::saveYaml($entry, $this->config);
        }

        $debug = getenv('KISS_DEBUG');
        if ($debug !== false) {
            $this->config['debug'] = Tools::truthy($debug);
        }
        $verbose = getenv('KISS_VERBOSE');
        if ($verbose !== false) {
            $this->config['log']['verbose'] = Tools::truthy($verbose);
        }
        $baseUrl = getenv('KISS_BASE_URL');
        if ($baseUrl !== false) {
            $this->config['base_url'] = rtrim($baseUrl, '/');
        }

        $this->resolvePaths();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function logConfig(): array
    {
        return $this->config['log'];
    }

    public function toArray(): array
    {
        return $this->config;
    }

    private function resolvePaths(): void
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
            $this->config['path'][$k] = Path::canonicalize($path);
            if (!\is_dir($this->config['path'][$k])) {
                $fs->mkdir($this->config['path'][$k], 0777);
            }
        }
    }
}
