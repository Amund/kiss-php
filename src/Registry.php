<?php

namespace Kiss;

/* The `Registry` class is a PHP class that provides methods for managing a collection of key-value
 pairs and saving/loading the collection to/from a file. */
class Registry
{
    private string $filePath;
    private array $collection = [];

    /**
     * The function is a constructor that takes a file path as a parameter and assigns it to the object's
     * path property.
     *
     * @param string filePath The `filePath` parameter is a string that represents the path to a file.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * The function checks if a given key exists in the collection.
     *
     * @param string key The key parameter is a string that represents the key to check for in the
     * collection.
     *
     * @return bool a boolean value, indicating whether the given key exists in the collection.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->collection);
    }

    /**
     * The function `get` returns the value associated with a given key from a collection, or the entire
     * collection if no key is provided.
     *
     * @param key The `key` parameter is an optional string parameter that represents the key of the
     * element you want to retrieve from the collection. If the `key` parameter is not provided or is set
     * to `null`, the method will return the entire collection. If a `key` is provided, the method will
     *
     * @return mixed The method is returning a mixed value. If the  parameter is null, it returns the
     * entire collection. If  is not null, it returns the value associated with that key in the
     * collection, or null if the key does not exist.
     */
    public function get(?string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->collection;
        } else {
            return $this->collection[$key] ?? null;
        }
    }

    /**
     * The function sets a value in a collection using a specified key.
     *
     * @param string key The key parameter is a string that represents the key of the value being set in
     * the collection.
     * @param mixed value The value parameter can be of any data type. It is a mixed type, which means it
     * can be a string, integer, boolean, array, object, or any other data type.
     */
    public function set(string $key, mixed $value): void
    {
        $this->collection[$key] = $value;
    }

    /**
     * The function removes an element from a collection if it exists.
     *
     * @param string key The parameter "key" is a string that represents the key of the element to be
     * removed from the collection.
     */
    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($this->collection[$key]);
        }
    }

    /**
     * The function "load" loads a collection by including a file specified by the file path.
     */
    public function load(): void
    {
        $this->collection = $this->include($this->filePath);
    }

    /**
     * The `save` function saves the contents of the `` variable to a file in PHP format.
     */
    public function save(): void
    {
        $content = '<?php return ' . var_export($this->collection, true) . ';';
        file_put_contents($this->filePath, $content);
    }

    /**
     * The function includes a PHP file and returns the result of the inclusion.
     *
     * @param string filePath The parameter `` is a string that represents the path to a file that
     * you want to include in your code.
     *
     * @return mixed the result of the `include` statement, which could be any value depending on the
     * contents of the included file.
     */
    private function include(string $filePath): mixed
    {
        return include $filePath;
    }
}
