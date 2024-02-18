<?php

namespace Kiss\DataSource;

use Kiss\DataSource\AbstractDataSource;

class JsonDataSource extends AbstractDataSource
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        try {
            $this->content = json_decode(
                file_get_contents($this->filePath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" JSON DataSource',
                ['{path}' => $this->filePath],
                $err
            );
        }

        return $this->content;
    }
}
