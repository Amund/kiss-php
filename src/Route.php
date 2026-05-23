<?php

namespace Kiss;

use Kiss\DataSource;
use Kiss\DataTree;
use Kiss\KissException;

class Route
{
    use Thrower;

    public string $name;
    public string $path;
    public string $template;
    public mixed $data;
    public mixed $items = null;
    public ?int $paginate = null;

    public const PARAMS_MATCHER = '#{([^}]+)}#';

    public function __construct(string $name, DataSource $datasource)
    {
        $this->name = $name;
        $content = $datasource->content;

        if (is_array($content)) {
            $content = (object) $content;
        }

        if (!isset($content->path) || !is_string($content->path)) {
            $this->error('path is required and must be a string');
        }
        if (!isset($content->template) || !is_string($content->template)) {
            $this->error('template is required and must be a string');
        }
        if (isset($content->paginate) && (!is_int($content->paginate) || $content->paginate < 1)) {
            $this->error('paginate must be a positive integer');
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
        $this->items = $content->items ?? null;
        $this->paginate = $content->paginate ?? null;
    }

    public function getResolvedData(?DataTree $tree = null): mixed
    {
        $data = $this->data;
        $items = $this->items;

        if ($tree !== null) {
            if ($data !== null) {
                $data = $tree->resolve($data);
            }
            if ($items !== null) {
                $items = $tree->resolve($items);
            }
        }

        if ($this->items !== null) {
            $result = is_array($data) ? $data : [];
            $result['items'] = $items;
            return $result;
        }

        return $data;
    }

    public function getPages(?DataTree $tree = null): array
    {
        $relativePath = ltrim($this->path, '/');

        preg_match_all(self::PARAMS_MATCHER, $this->path, $matches, PREG_SET_ORDER);
        $params = [];
        foreach ($matches as $match) {
            $params[$match[0]] = $match[1];
        }

        $hasParams = count($params) > 0;

        if (!$hasParams && $this->paginate === null) {
            $data = $this->data;
            if ($tree !== null && $data !== null) {
                $data = $tree->resolve($data);
            }
            if ($this->items !== null) {
                $items = $this->items;
                if ($tree !== null) {
                    $items = $tree->resolve($items);
                }
                $data = is_array($data) ? $data : [];
                $data['items'] = $items;
            }
            return [$relativePath => $data];
        }

        if ($this->paginate !== null) {
            if ($hasParams) {
                $this->error(
                    'paginate and path parameters cannot be combined'
                );
            }
            if (preg_match('#\.\w+$#', $this->path)) {
                $this->error(
                    'paginate requires a path without file extension; use e.g. "/blog" instead of "/blog.html"'
                );
            }
        }

        if ($this->items === null) {
            $this->error(
                'items property is required when path has parameters or paginate is set'
            );
        }

        $items = $this->items;
        $meta = $this->data;

        if ($tree !== null) {
            $items = $tree->resolve($items);
            if ($meta !== null) {
                $meta = $tree->resolve($meta);
            }
        }

        if ($this->paginate !== null) {
            return $this->getPaginatedPages($items, $params, $meta);
        }

        if (!is_array($items)) {
            $this->error(
                'items must be an array of arrays (parameters: {params})',
                ['{params}' => implode('", "', $params)]
            );
        }

        $pages = [];
        $resolvedMeta = is_array($meta) ? $meta : [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $this->error(
                    'items[{index}] must be an array',
                    ['{index}' => $index]
                );
            }
            if (!empty($resolvedMeta)) {
                $item = array_merge($resolvedMeta, $item);
            }
            $file = $relativePath;
            foreach ($params as $placeholder => $key) {
                if (!array_key_exists($key, $item)) {
                    $this->error(
                        'items[{index}] is missing parameter "{key}"',
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

        return $pages;
    }

    private function getPaginatedPages(mixed $items, array $params, mixed $meta): array
    {
        $relativePath = ltrim($this->path, '/');

        if (!is_array($items)) {
            $this->error(
                'items must be an array when paginate is set'
            );
        }

        $totalItems = count($items);
        if ($totalItems === 0) {
            return [];
        }

        $chunks = array_chunk($items, $this->paginate);
        $totalPages = count($chunks);
        $resolvedMeta = is_array($meta) ? $meta : [];
        $pages = [];

        foreach ($chunks as $i => $chunk) {
            $pageNum = $i + 1;
            $path = $pageNum === 1
                ? $relativePath . '/index.html'
                : $relativePath . '/page/' . $pageNum . '/index.html';

            $pageData = $resolvedMeta;
            $pageData['items'] = $chunk;
            $pageData['page'] = $pageNum;
            $pageData['pageSize'] = $this->paginate;
            $pageData['totalPages'] = $totalPages;
            $pageData['totalItems'] = $totalItems;
            $pageData['prevPage'] = $pageNum > 1 ? $pageNum - 1 : null;
            $pageData['nextPage'] = $pageNum < $totalPages ? $pageNum + 1 : null;
            $pageData['first'] = $pageNum === 1;
            $pageData['last'] = $pageNum === $totalPages;
            $pageData['pages'] = range(1, $totalPages);

            $pages[$path] = $pageData;
        }

        return $pages;
    }
}
