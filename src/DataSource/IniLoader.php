<?php

namespace Kiss\DataSource;

class IniLoader extends AbstractLoader
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        $result = @parse_ini_file($this->filePath, true);

        if ($result === false) {
            $this->error(
                'Parsing error in "{path}" INI DataSource',
                ['{path}' => $this->filePath]
            );
        }

        $this->content = $result;
        return $this->content;
    }
}
