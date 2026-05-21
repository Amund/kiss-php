<?php

namespace Kiss;

use Kiss\DataSource;
use Kiss\DataTree;
use Kiss\KissException;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

class Route
{
    use Thrower;

    public string $name;
    public string $path;
    public string $template;
    public mixed $data;

    public const SCHEMA = <<<'JSON'
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "properties": {
        "path": {
            "type": "string",
            "minLength": 1
        },
        "template": {
            "type": "string",
            "minLength": 1
        }
    },
    "required": ["path", "template"],
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
                'validation error: {message}',
                ['{message}' => $first[0] ?? 'invalid']
            );
        }

        $path = $content->path;

        if (!str_starts_with($path, '/')) {
            $this->error(
                'path must start with a slash ("/")'
            );
        }
        if (str_ends_with($path, '/')) {
            $this->error(
                'path must not end with a slash ("/")'
            );
        }
        if (!preg_match('#^[-a-z0-9_{}/.]+$#i', $path)) {
            $this->error(
                'path only accepts "a-z", "0-9", "-", "_", "{", "}", "/", "."'
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
                    'data must be an array of arrays (parameters: {params})',
                    [
                        '{params}' => implode('", "', $params),
                    ]
                );
            }
            foreach ($data as $index => $item) {
                if (!is_array($item)) {
                    $this->error(
                        'data[{index}] must be an array',
                        ['{index}' => $index]
                    );
                }
                $file = $relativePath;
                foreach ($params as $placeholder => $key) {
                    if (!array_key_exists($key, $item)) {
                        $this->error(
                            'data[{index}] is missing parameter "{key}"',
                            [
                                '{index}' => $index,
                                '{key}' => $key,
                            ]
                        );
                    }
                    $file = strtr($file, [$placeholder => Tools::slugify($item[$key])]);
                }
                $pages[$file] = $item;
            }
        }

        return $pages;
    }
}
