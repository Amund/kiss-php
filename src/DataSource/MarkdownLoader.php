<?php

namespace Kiss\DataSource;

use Symfony\Component\Yaml\Yaml;

class MarkdownLoader extends AbstractLoader
{
    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
    }

    public function load(): mixed
    {
        $raw = file_get_contents($this->filePath);

        if (!str_starts_with($raw, '---')) {
            $this->content = ['content' => $raw];
            return $this->content;
        }

        $parts = explode('---', $raw, 3);

        if (count($parts) < 3) {
            $this->content = ['content' => $raw];
            return $this->content;
        }

        $frontmatter = trim($parts[1]);
        $body = ltrim($parts[2]);

        $data = [];
        if ($frontmatter !== '') {
            try {
                $data = Yaml::parse($frontmatter);
            } catch (\Throwable $err) {
                $this->error(
                    'Parsing error in "{path}" Markdown frontmatter',
                    ['{path}' => $this->filePath],
                    $err
                );
            }
        }

        if (!is_array($data)) {
            $data = [];
        }

        $data['content'] = $body;
        $this->content = $data;

        return $this->content;
    }
}
