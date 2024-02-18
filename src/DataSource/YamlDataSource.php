<?php

namespace Kiss\DataSource;

use Kiss\DataSource\AbstractDataSource;
use Symfony\Component\Yaml\Yaml;

class YamlDataSource extends AbstractDataSource
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        $this->content = Yaml::parseFile($this->filePath);
        return $this->content;
    }
}
