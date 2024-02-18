<?php

namespace Kiss\DataSource;

use Kiss\DataSource\AbstractLoader;

class PhpLoader extends AbstractLoader
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        try {
            $this->content = require $this->filePath;
        } catch (\Throwable $err) {
            $this->error(
                '"{path}" PHP DataSource has thrown an error',
                ['{path}' => $this->filePath],
                $err
            );
        }

        return $this->content;
    }
}
