<?php

namespace Kiss;

/* The Registry class is a PHP class that provides methods for managing a collection of key-value pairs
 and saving/loading the collection to/from a file. */
class Registry
{
    private string $path;
    private array $collection = [];

    /**
     * The function is a constructor that takes a string parameter and assigns it to the class property
     * "path".
     *
     * @param string path The "path" parameter is a string that represents the file path or directory path.
     * It is used to specify the location of the file or directory that the class will be working with.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * The function checks if a given name exists as a key in the collection array and returns a boolean
     * value.
     *
     * @param string name A string representing the name of the key to check in the collection.
     *
     * @return bool a boolean value, indicating whether the given name exists as a key in the collection
     * array.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->collection);
    }

    /**
     * The function "get" returns the value of a given key from a collection, or the entire collection if
     * no key is provided.
     *
     * @param name The parameter "name" is a string that represents the key of an element in the
     * collection.
     *
     * @return mixed The method is returning a mixed value. If the `` parameter is null, it returns
     * the entire collection. If the `` parameter is not null, it returns the value corresponding to
     * that key in the collection, or null if the key does not exist.
     */
    public function get(?string $name = null): mixed
    {
        if (is_null($name)) {
            return $this->collection;
        } else {
            return $this->collection[$name] ?? null;
        }
    }

    /**
     * The function sets a value in a collection using a given name.
     *
     * @param string name A string representing the name of the value being set in the collection.
     * @param mixed value The value parameter can be of any data type. It is a mixed type, which means it
     * can be a string, integer, boolean, array, object, or any other data type.
     */
    public function set(string $name, mixed $value): void
    {
        $this->collection[$name] = $value;
    }

    /**
     * The function removes an element from a collection if it exists.
     *
     * @param string name The parameter "name" is a string that represents the name of the item to be
     * removed from the collection.
     */
    public function remove(string $name): void
    {
        if ($this->has($name)) {
            unset($this->collection[$name]);
        }
    }

    /**
     * The function "load" loads a collection by including a file specified by the path.
     */
    public function load(): void
    {
        $this->collection = $this->include($this->path);
    }

    /**
     * The save function saves the collection data to a file in PHP format.
     */
    public function save(): void
    {
        $content = '<?php return ' . var_export($this->collection, true) . ';';
        file_put_contents($this->path, $content);
    }

    /**
     * The function "include" is used to include and execute the code from a specified file path in PHP.
     *
     * @param string path The parameter "path" is a string that represents the file path of the file that
     * you want to include.
     *
     * @return mixed the result of the `include` statement, which could be any value depending on the
     * included file.
     */
    private function include(string $path): mixed
    {
        return include $path;
    }
}
