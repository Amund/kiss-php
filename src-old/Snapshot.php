<?php

namespace Kiss;

class Snapshot
{
    private string $algo;
    private string $path;
    private array $registry = [];

    public function __construct(string $path, string $algo = 'crc32c')
    {
        $this->path = $path;

        if (!\in_array($algo, \hash_algos())) {
            $this->error('Hash {hash} is not valid', [
                '{hash}' => $algo,
            ]);
        } else {
            $this->algo = $algo;
        }
    }

    public function get(?string $name = null)
    {
        if (is_null($name)) {
            return $this->registry;
        } else {
            return $this->registry[$name] ?? null;
        }
    }

    public function add($path)
    {
        if (!\file_exists($path)) {
            $this->error('File {path} not found', [
                '{path}' => $path,
            ]);
        }

        if (\is_file($path)) {
            $this->registry[$path] = \hash_file($this->algo, $path);
        } elseif (\is_dir($path)) {
            $handler = opendir($path);
            while (($file = readdir($handler)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $this->add($path . '/' . $file);
                }
            }
            closedir($handler);
        }

        return $this;
    }

    public function save()
    {
        $content = '<?php return ' . var_export($this->registry, true) . ';';
        \file_put_contents($this->path, $content);
    }

    public function load()
    {
        $this->registry = $this->include($this->path);
    }

    public function diff()
    {
        $lastRegistry = $this->include($this->path);
        return array_map('array_keys', [
            'add' => array_diff_key($this->registry, $lastRegistry),
            'delete' => array_diff_key($lastRegistry, $this->registry),
            'update' => array_diff_assoc($this->registry, $lastRegistry),
        ]);
    }

    private function include($path)
    {
        return include $path;
    }

    private function error(
        string $str,
        ?array $args = [],
        ?\Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $args), 0, $previous);
    }
}
