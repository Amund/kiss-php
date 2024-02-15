<?php

namespace Kiss;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

/* The `DataTree` class is a PHP class that resolves references in a given value by recursively merging
 the referenced content with the original value, and caches the resolved values for further usage. */
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
     * The clear function uses the Filesystem class to remove the cache directory specified in the options.
     */
    public function clear()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->options['cache']);
    }

    /**
     * The function "get" retrieves a value from an array called "fragments" based on a given key called
     * "source", and if the value does not exist, it sets it before returning it.
     *
     * @param source The parameter "source" is a variable that represents the key or identifier of a
     * fragment in the "fragments" array.
     *
     * @return the value of `->fragments[]`.
     */
    private function get($source)
    {
        if (!$this->has($source)) {
            $this->set($source);
        }
        return $this->fragments[$source];
    }

    /**
     * The function "set" assigns a value to the "fragments" array using the "source" parameter as the key
     * and the result of the "getCache" function as the value.
     *
     * @param source The parameter "source" is a variable that represents the source of the data being set.
     * It is used as a key to store the data in the "fragments" array.
     */
    private function set($source)
    {
        $this->fragments[$source] = $this->getCache($source);
    }

    /**
     * The function `getCache` checks if a cache file exists for a given source, and if it does, it
     * includes and returns its content, otherwise it creates a new cache file and returns its content.
     *
     * @param source The "source" parameter is a variable that represents the source of the cache. It could
     * be a file path, a URL, or any other identifier for the cache source.
     *
     * @return mixed content of the cache is being returned.
     */
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

    /**
     * The setCache function loads content from a source, creates a cache file with the content, and
     * returns the loaded content.
     *
     * @param source The `source` parameter is the path or URL of the file that you want to load and cache.
     * It could be a local file path or a URL to a remote file.
     * @param to The "to" parameter is the path where the cache file will be saved. It should be a string
     * representing the file path, including the file name and extension. For example,
     * "/path/to/cache/file.php".
     *
     * @return the content that was loaded from the source file.
     */
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

    /**
     * The function `load()` is used to load and parse data from different file formats (PHP, JSON, YAML)
     * and return the content as an array.
     *
     * @param source The `source` parameter is the path to the file that needs to be loaded. It can be a
     * YAML, JSON, or PHP file.
     *
     * @return the content of the file that was loaded. The content can be an array or an object, but if it
     * is an object, it will be cast to an array before being returned.
     */
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

    /**
     * The function checks if a given source exists as a key in the fragments array.
     *
     * @param source The `source` parameter is the key that is being checked for existence in the
     * `->fragments` array.
     *
     * @return the result of the `array_key_exists()` function, which checks if a given key exists in an
     * array.
     */
    private function has($source)
    {
        return \array_key_exists($source, $this->fragments);
    }

    /**
     * The function `getCachePath` returns the path to a cached PHP file based on the source code and cache
     * options.
     *
     * @param source The source parameter is a string that represents the content or data that needs to be
     * cached.
     *
     * @return the path to the cache file.
     */
    private function getCachePath($source)
    {
        $path = $this->options['cache'];
        $hash = $this->options['hash'];
        $hashed = hash($hash, $source);
        return $path . DIRECTORY_SEPARATOR . $hashed . '.php';
    }

    /**
     * The function includes a PHP file and returns the result.
     *
     * @param source The parameter "source" is the path to the file that you want to include in your code.
     *
     * @return the result of the `include` statement.
     */
    private function include($source)
    {
        return include $source;
    }
}
