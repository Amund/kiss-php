<?php

namespace Kiss\DataSource;

use Symfony\Component\Yaml\Yaml;
use Kiss\DataSource\AbstractDataSource;

class YamlDataSource extends AbstractDataSource
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        try {
            $this->content = Yaml::parseFile($this->filePath);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" YAML DataSource',
                ['{path}' => $this->filePath],
                $err
            );
        }
        return $this->content;
    }
}
