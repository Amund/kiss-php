<?php

namespace Kiss;

class Route
{
    private string $name;
    private string $path;
    public string $template;
    private mixed $data;

    const PARAMS_MATCHER = '#{([^}]+)}#';

    public function __construct(string $name, array $route)
    {
        if (!is_array($route)) {
            $this->error('Route "{name}" is not an array', ['{name}' => $name]);
        }
        if (!\array_key_exists('path', $route)) {
            $this->error('Missing path in "{name}" route', ['{name}' => $name]);
        }
        if (!\str_starts_with($route['path'], '/')) {
            $this->error(
                'Invalid path in "{name}" route, it must starts with a slash ("/")',
                ['{name}' => $name]
            );
        }
        if (\str_ends_with($route['path'], '/')) {
            $this->error(
                'Invalid path in "{name}" route, it must not ends with a slash ("/")',
                ['{name}' => $name]
            );
        }
        if (
            !is_string($route['path']) ||
            !\preg_match('#[0-9a-z-{}/]+#', $route['path'])
        ) {
            $this->error(
                'Invalid path in "{name}" route, it only accept "a-z", "0-9", "-", "{", "}", "/")',
                ['{name}' => $name]
            );
        }
        if (!\array_key_exists('template', $route)) {
            $this->error('Missing path in "{name}" route', ['{name}' => $name]);
        }
        if (
            !is_string($route['template']) ||
            !\preg_match('#[0-9a-z-/]+#', $route['path'])
        ) {
            $this->error('Invalid path in "{name}" route', ['{name}' => $name]);
        }

        $this->name = $name;
        $this->path = $route['path'];
        $this->template = $route['template'];
        $this->data = &$route['data'] ?? null;
    }

    public function getPages()
    {
        // $this->path = '/blog/{category}/{id}-{slug}';
        // grab parameters from path
        preg_match_all(
            self::PARAMS_MATCHER,
            $this->path,
            $params,
            PREG_SET_ORDER
        );
        $params = array_combine(
            array_column($params, 0),
            array_column($params, 1)
        );

        $pages = [];
        $path = ltrim($this->path, '/');
        if (count($params) === 0) {
            // no params, only one page
            $pages[$path] = &$this->data;
        } else {
            // there are parameters, need an array of array as data
            if (!is_array($this->data)) {
                $message =
                    'Route "{name}" has parameters ("{params}") in its path, its data must be a collection of arrays, each containing these parameters as keys';
                $this->error($message, [
                    '{name}' => $this->name,
                    '{params}' => implode('", "', $params),
                ]);
            }
            foreach ($this->data as $index => $data) {
                $file = $path;
                if (!is_array($data)) {
                    $message =
                        'Route "{name}" has parameters ("{params}") in its path, its data[{index}] must be an array containing these params as keys';
                    $this->error($message, [
                        '{name}' => $this->name,
                        '{params}' => implode('", "', $params),
                        '{index}' => $index,
                    ]);
                }
                foreach ($params as $param => $key) {
                    if (!\array_key_exists($key, $data)) {
                        $message =
                            'Route "{name}" has a parameter "{param}" in its path, its data[{index}]["{key}"] must exists';
                        $this->error($message, [
                            '{name}' => $this->name,
                            '{param}' => $param,
                            '{key}' => $key,
                            '{index}' => $index,
                        ]);
                    } else {
                        $file = strtr($file, [$param => $data[$key]]);
                    }
                }
                $pages[$file] = $data;
            }
        }
        return $pages;
    }

    private function error(string $str, array $vars = [])
    {
        throw new KissException(strtr($str, $vars));
    }
}
