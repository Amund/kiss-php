<?php

namespace Kiss;

use Symfony\Component\Yaml\Yaml;

class DataSource
{
    public function load($source)
    {
        $ext = pathinfo($source, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        switch ($ext) {
            case 'php':
                $content = $this->loadFromPhp($source);
                break;

            case 'json':
                $content = $this->loadFromJson($source);
                break;

            case 'yaml':
            case 'yml':
                $content = $this->loadFromYaml($source);
                break;

            default:
                throw new KissException(
                    '"' .
                        $source .
                        '" is not a valid data format, it must be yaml, json or php file.'
                );
        }

        if (is_object($content)) {
            $content = (array) $content;
        }

        return $content;
    }

    public function loadFromPhp($source)
    {
        if (!\is_file($source)) {
            throw new KissException('file not found');
        }
        try {
            return $this->include($source);
        } catch (\Throwable $err) {
            throw new KissException(
                'File "' . $source . '" has thrown an error',
                0,
                $err
            );
        }
    }

    private function include($source)
    {
        return include $source;
    }

    public function loadFromJson($source)
    {
        if (!\is_file($source)) {
            throw new KissException('file not found');
        }
        try {
            return json_decode(file_get_contents($source), true);
        } catch (\Throwable $err) {
            throw new KissException('Parsing error in "' . $source . '"');
        }
    }

    public function loadFromYaml($source)
    {
        if (!\is_file($source)) {
            throw new KissException('file not found');
        }
        try {
            return Yaml::parseFile($source);
        } catch (\Throwable $err) {
            throw new KissException(
                'Loading or parsing error in "' . $source . '"'
            );
        }
    }
}
