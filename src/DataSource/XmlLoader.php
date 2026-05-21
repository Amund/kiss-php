<?php

namespace Kiss\DataSource;

class XmlLoader extends AbstractLoader
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        try {
            $xml = @simplexml_load_file($this->filePath);
            if ($xml === false) {
                $this->error(
                    'Parsing error in "{path}" XML DataSource',
                    ['{path}' => $this->filePath]
                );
            }
            $this->content = json_decode(json_encode($xml), true);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" XML DataSource',
                ['{path}' => $this->filePath],
                $err
            );
        }

        return $this->content;
    }
}
