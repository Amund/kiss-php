<?php

namespace Kiss;

use Kiss\DataSource;
use Symfony\Component\Filesystem\Filesystem;

class DataTree
{
    private array $fragments = [];
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = Tools::merge([
            'cache' => 'tmp/datatree',
            'hash' => 'crc32c',
        ], $options);
    }

    public function resolve(mixed $value, ?string $source = null, array $stack = []): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            if (isset($value['$ref'])) {
                $source = $value['$ref'];
                unset($value['$ref']);
                $source = Tools::normalizeSource($source);
                if (isset($stack[$source])) {
                    throw new KissException(
                        'Circular reference, "' .
                            $source .
                            '" is already loaded',
                        0,
                        null,
                        'Check your $ref chains for loops'
                    );
                }
                $stack[$source] = true;
                $content = $this->get($source);
                if (is_array($content)) {
                    $content = Tools::merge($content, $value);
                }
                $value = $this->resolve($content, $source, $stack);
            } else {
                foreach ($value as $k => $v) {
                    $value[$k] = $this->resolve($v, $source, $stack);
                }
            }
        }
        return $value;
    }

    public function invalidate(string $source): void
    {
        $source = Tools::normalizeSource($source);
        $cachePath = $this->getCachePath($source);
        $filesystem = new Filesystem();
        $filesystem->remove($cachePath);
        if ($this->has($source)) {
            unset($this->fragments[$source]);
        }
    }

    public function clear(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->options['cache']);
    }

    private function get(string $source): mixed
    {
        if (!$this->has($source)) {
            $this->set($source);
        }
        return $this->fragments[$source];
    }

    private function set(string $source): void
    {
        $this->fragments[$source] = $this->getCache($source);
    }

    private function getCache(string $source): mixed
    {
        $cachePath = $this->getCachePath($source);
        $realpath = realpath($cachePath);
        if (!$realpath) {
            $content = $this->setCache($source, $cachePath);
        } else {
            $content = include $realpath;
        }
        return $content;
    }

    private function setCache(string $source, string $to): mixed
    {
        $ds = new DataSource($source);
        $content = $ds->content;
        $dir = dirname($to);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        DataSource::savePhp($to, $content, $source);
        return $content;
    }

    private function getCachePath(string $source): string
    {
        $path = $this->options['cache'];
        $hash = $this->options['hash'];
        $hashed = hash($hash, $source);
        return $path . DIRECTORY_SEPARATOR . $hashed . '.php';
    }

    private function has(string $key): bool
    {
        return array_key_exists($key, $this->fragments);
    }
}
