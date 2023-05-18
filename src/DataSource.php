<?php

namespace Kiss;

use Symfony\Component\Yaml\Yaml;

class DataSource
{
    public function load($source)
    {
        if (!\is_file($source)) {
            $this->error('DataSource "{path}" not found', [
                '{path}' => $source,
            ]);
        }
        $ext = $this->normalizeExtension($source);
        match ($ext) {
            'php' => ($content = $this->loadFromPhp($source)),
            'json' => ($content = $this->loadFromJson($source)),
            'yaml', 'yml' => ($content = $this->loadFromYaml($source)),
            default => $this->error(
                '"{path}" is not a valid DataSource format, it must be yaml, json or php file',
                [
                    '{path}' => $source,
                ]
            ),
        };

        if (is_object($content)) {
            $content = (array) $content;
        }

        return $content;
    }

    public function save($source, $content)
    {
        $ext = $this->normalizeExtension($source);
        match ($ext) {
            'php' => ($content = $this->saveToPhp($content)),
            'json' => ($content = $this->saveToJson($content)),
            'yaml', 'yml' => ($content = $this->saveToYaml($content)),
            default => $this->error(
                '"{path}" is not a valid DataSource format, it must be yaml, json or php file',
                [
                    '{path}' => $source,
                ]
            ),
        };

        // ensure destination directory exists
        $dir = dirname($source);
        if (!\is_dir($dir)) {
            $written = \mkdir($dir, 0777, \true);
            if ($written === \false) {
                $this->error('Can not create {path-dir} to store {path}', [
                    '{path-dir}' => $dir,
                    '{path}' => $source,
                ]);
            }
        }

        // write file
        $written = \file_put_contents($source, $content);
        if ($written === \false) {
            $this->error('Can not create {path} file', [
                '{path}' => $source,
            ]);
        }
    }

    private function loadFromPhp($source)
    {
        try {
            $content = $this->include($source);
        } catch (\Throwable $err) {
            $this->error(
                '"{path}" PHP DataSource has thrown an error',
                ['{path}' => $source],
                $err
            );
        }
        return $content;
    }

    private function loadFromJson($source)
    {
        try {
            return json_decode(file_get_contents($source), true);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" JSON DataSource',
                ['{path}' => $source],
                $err
            );
        }
    }

    private function loadFromYaml($source)
    {
        try {
            return Yaml::parseFile($source);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" YAML DataSource',
                ['{path}' => $source],
                $err
            );
        }
    }

    private function saveToPhp($content)
    {
        return '<?php return ' . var_export($content, true) . ';';
    }

    private function saveToJson($content)
    {
        return \json_encode(
            $content,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    private function saveToYaml($content)
    {
        return Yaml::dump(
            $content,
            2,
            4,
            Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE |
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }

    private function normalizeExtension($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        return $ext;
    }

    private function include($source)
    {
        return include $source;
    }

    private function error(
        string $str,
        ?array $args = [],
        ?\Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $args), 0, $previous);
    }
}
