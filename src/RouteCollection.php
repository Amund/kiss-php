<?php

namespace Kiss;

use Kiss\DataSource;
use Kiss\Route;

class RouteCollection
{
    private array $routes = [];

    public const EXTENSIONS = ['yml', 'yaml', 'json', 'php'];

    public function __construct(string $directory)
    {
        $directory = rtrim($directory, '/');

        $files = [];
        foreach (self::EXTENSIONS as $ext) {
            $matches = glob($directory . '/*.' . $ext);
            if ($matches !== false) {
                $files = array_merge($files, $matches);
            }
        }

        sort($files);

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $route = new Route($name, new DataSource($file));
            $this->routes[$name] = $route;
        }
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getCount(): int
    {
        return count($this->routes);
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    public function get(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }
}
