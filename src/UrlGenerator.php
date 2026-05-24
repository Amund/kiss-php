<?php

namespace Kiss;

class UrlGenerator
{
    private array $routes;

    public function __construct(RouteCollection $collection)
    {
        $this->routes = $collection->getRoutes();
    }

    public function path(string $name, array $params = []): string
    {
        if (!isset($this->routes[$name])) {
            throw new KissException("Route '$name' not found");
        }

        $route = $this->routes[$name];
        $path = $route->path;

        if ($route->paginate !== null && isset($params['page'])) {
            $page = (int) max(1, $params['page']);
            unset($params['page']);
            $path = $page === 1
                ? $route->path
                : self::cleanIndex($route->path) . '/page/' . $page;
        }

        if (empty($params)) {
            if (preg_match(Route::PARAMS_MATCHER, $route->path)) {
                throw new KissException(
                    "Route '$name' requires parameters: " . $this->extractParamNames($route->path)
                );
            }
            return self::cleanIndex($path);
        }

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', Tools::slugify($value), $path);
        }

        if (preg_match(Route::PARAMS_MATCHER, $path, $missing)) {
            throw new KissException(
                "Missing param '{" . $missing[1] . "}' for route '$name'"
            );
        }

        return self::cleanIndex($path);
    }

    private function extractParamNames(string $path): string
    {
        preg_match_all(Route::PARAMS_MATCHER, $path, $matches);
        return implode(', ', array_map(fn($m) => '{' . $m . '}', $matches[1]));
    }

    private static function cleanIndex(string $path): string
    {
        if (str_ends_with($path, '/index.html')) {
            $path = substr($path, 0, -11);
            if ($path === '') {
                $path = '/';
            }
        }
        return $path;
    }
}
