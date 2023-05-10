<?php

namespace Kiss;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

class DataTree
{
    private $fragments = [];
    private $options = [
        'cache' => 'tmp/datatree',
        'hash' => 'crc32c',
    ];

    public function __construct($options = [])
    {
        $this->options = Tools::merge($this->options, $options);
    }

    /**
     * This function resolves references in a given value by recursively merging the referenced content with
     * the original value.
     * References are arrays with a key '$ref' and a string value representing the source file.
     * Each resolved reference is cached as a php file for further usage.
     *
     * @param value The value to be resolved, which can be a scalar, an object or an array.
     * @param source The source of the value being resolved, represented as a string. It is optional and
     * can be null.
     * @param stack An optional array parameter that keeps track of the references that have been resolved
     * to prevent circular references. It is initially an empty array and is passed recursively to each
     * call of the `resolve` function.
     *
     * @return the resolved value.
     */
    public function resolve($value, ?string $source = null, ?array $stack = [])
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
                            '" is already loaded'
                    );
                } else {
                    $stack[$source] = true;
                }
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

    /**
     * This function invalidates a cache entry and removes it from the cache.
     *
     * @param source The source is a string that represents the cache key or identifier for the cached
     * data. It is used to retrieve and manipulate the cached data.
     */
    public function invalidate($source)
    {
        $source = Tools::normalizeSource($source);
        $cachePath = $this->getCachePath($source);
        $filesystem = new Filesystem();
        $filesystem->remove($cachePath);
        if ($this->has($source)) {
            unset($this->fragments[$source]);
        }
    }

    /**
     * This function clears a cache directory entirely.
     */
    public function clear()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->options['cache']);
    }

    private function get($source)
    {
        if (!$this->has($source)) {
            $this->set($source);
        }
        return $this->fragments[$source];
    }

    private function set($source)
    {
        $this->fragments[$source] = $this->getCache($source);
    }

    private function getCache($source)
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

    private function setCache($source, $to)
    {
        $content = $this->load($source);
        $cacheContent =
            '<?php // ' .
            $source .
            "\n\n" .
            'return ' .
            var_export($content, true) .
            ';';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($to, $cacheContent);
        return $content;
    }

    private function load($source)
    {
        $ext = pathinfo($source, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        switch ($ext) {
            case 'php':
                try {
                    $content = $this->include($source);
                } catch (\Throwable $err) {
                    throw new KissException(
                        'File "' . $source . '" has thrown an error',
                        0,
                        $err
                    );
                }
                break;

            case 'json':
                try {
                    $str = file_get_contents($source);
                    $content = json_decode($str, true);
                } catch (\Throwable $err) {
                    throw new KissException(
                        'Parsing error in "' . $source . '"'
                    );
                }
                break;

            case 'yaml':
            case 'yml':
                try {
                    $content = Yaml::parseFile($source);
                } catch (\Throwable $err) {
                    throw new KissException(
                        'Loading or parsing error in "' . $source . '"'
                    );
                }
                break;

            default:
                throw new KissException(
                    '"' .
                        $source .
                        '" is not a valid data format, it must be yaml, json or php file.'
                );
        }

        if (is_object($content)) {
            $content = (array) $content;
        }

        return $content;
    }

    private function has($source)
    {
        return \array_key_exists($source, $this->fragments);
    }

    private function getCachePath($source)
    {
        $path = $this->options['cache'];
        $hash = $this->options['hash'];
        $hashed = hash($hash, $source);
        return $path . DIRECTORY_SEPARATOR . $hashed . '.php';
    }

    private function include($source)
    {
        return include $source;
    }
}
