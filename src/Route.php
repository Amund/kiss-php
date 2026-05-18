<?php

namespace Kiss;

use Kiss\DataSource;
use Kiss\DataTree;
use Kiss\KissException;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

class Route
{
    public string $name;
    public string $path;
    public string $template;
    public mixed $data;

    public const SCHEMA = <<<'JSON'
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "properties": {
        "type": {
            "type": "string",
            "minLength": 1
        },
        "path": {
            "type": "string",
            "minLength": 1
        },
        "template": {
            "type": "string",
            "minLength": 1
        }
    },
    "required": ["type", "path", "template"],
    "additionalProperties": true
}
JSON;

    public const PARAMS_MATCHER = '#{([^}]+)}#';

    public function __construct(string $name, DataSource $datasource)
    {
        $this->name = $name;
        $content = $datasource->content;

        if (is_array($content)) {
            $content = (object) $content;
        }

        $validator = new Validator();
        $result = $validator->validate($content, self::SCHEMA);

        if (!$result->isValid()) {
            $errors = (new ErrorFormatter())->format($result->error());
            $first = reset($errors);
            $this->error(
                'Route "{name}" validation error: {message}',
                ['{name}' => $name, '{message}' => $first[0] ?? 'invalid']
            );
        }

        $path = $content->path;

        if (!str_starts_with($path, '/')) {
            $this->error(
                'Route "{name}" path must start with a slash ("/")',
                ['{name}' => $name]
            );
        }
        if (str_ends_with($path, '/')) {
            $this->error(
                'Route "{name}" path must not end with a slash ("/")',
                ['{name}' => $name]
            );
        }
        if (!preg_match('#^[-a-z0-9_{}/.]+$#i', $path)) {
            $this->error(
                'Route "{name}" path only accepts "a-z", "0-9", "-", "_", "{", "}", "/", "."',
                ['{name}' => $name]
            );
        }

        $this->path = $path;
        $this->template = $content->template;
        $this->data = $content->data ?? null;
    }

    public function getPages(?DataTree $tree = null): array
    {
        $data = $this->data;
        if ($tree !== null && $data !== null) {
            $data = $tree->resolve($data);
        }

        preg_match_all(self::PARAMS_MATCHER, $this->path, $matches, PREG_SET_ORDER);
        $params = [];
        foreach ($matches as $match) {
            $params[$match[0]] = $match[1];
        }

        $relativePath = ltrim($this->path, '/');
        $pages = [];

        if (count($params) === 0) {
            $pages[$relativePath] = $data;
        } else {
            if (!is_array($data)) {
                $this->error(
                    'Route "{name}" has parameters ("{params}") in its path, its data must be a collection of arrays',
                    [
                        '{name}' => $this->name,
                        '{params}' => implode('", "', $params),
                    ]
                );
            }
            foreach ($data as $index => $item) {
                if (!is_array($item)) {
                    $this->error(
                        'Route "{name}" has parameters in its path, its data[{index}] must be an array',
                        ['{name}' => $this->name, '{index}' => $index]
                    );
                }
                $file = $relativePath;
                foreach ($params as $placeholder => $key) {
                    if (!array_key_exists($key, $item)) {
                        $this->error(
                            'Route "{name}" has parameter "{param}" in its path, but data[{index}]["{key}"] is missing',
                            [
                                '{name}' => $this->name,
                                '{param}' => $key,
                                '{index}' => $index,
                                '{key}' => $key,
                            ]
                        );
                    }
                    $file = strtr($file, [$placeholder => $item[$key]]);
                }
                $pages[$file] = $item;
            }
        }

        return $pages;
    }

    private function error(
        string $str,
        ?array $args = [],
        ?\Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $args), 0, $previous);
    }
}
