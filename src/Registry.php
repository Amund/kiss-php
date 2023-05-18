<?php

namespace Kiss;

class Registry
{
    private string $path;
    private array $collection = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function has($name)
    {
        return \array_key_exists($name, $this->collection);
    }

    public function get(?string $name = null)
    {
        if (is_null($name)) {
            return $this->collection;
        } else {
            return $this->collection[$name] ?? null;
        }
    }

    public function set(string $name, mixed $value)
    {
        $this->collection[$name] = $value;
    }

    public function remove(string $name)
    {
        if ($this->has($name)) {
            unset($this->collection[$name]);
        }
    }

    public function load()
    {
        $this->collection = $this->include($this->path);
    }

    public function save()
    {
        $content = '<?php return ' . var_export($this->collection, true) . ';';
        \file_put_contents($this->path, $content);
    }

    private function include($path)
    {
        return include $path;
    }
}
