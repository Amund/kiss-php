<?php

namespace Kiss;

use Kiss\DataSource\AbstractLoader;
use Kiss\DataSource\PhpLoader;
use Kiss\DataSource\JsonLoader;
use Kiss\DataSource\YamlLoader;
use Symfony\Component\Yaml\Yaml;

class DataSource
{
    private AbstractLoader $loader;
    public mixed $content;
    public string $filePath;

    const TYPES = ['php', 'json', 'yaml'];
    const INLINE = 6;
    const INDENT = 2;

    public function __construct(string $filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        match ($extension) {
            'php' => ($this->loader = new PhpLoader($filePath)),
            'json' => ($this->loader = new JsonLoader($filePath)),
            'yaml', 'yml' => ($this->loader = new YamlLoader($filePath)),
            default => throw new KissException(
                strtr('"{path}" is not a valid DataSource format ({types})', [
                    '{path}' => $filePath,
                    '{types}' => implode(', ', self::TYPES),
                ])
            ),
        };

        $this->filePath = $filePath;
        $this->content = $this->loader->load();
    }

    public function save(mixed $content = null, ?string $filePath = null): void
    {
        $content = $content ?? $this->content;
        $path = $filePath ?? $this->filePath;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        match ($extension) {
            'php' => self::savePhp($path, $content),
            'yaml', 'yml' => self::saveYaml($path, $content),
            'json' => self::saveJson($path, $content),
            default => throw new KissException(
                strtr('"{path}" is not a valid DataSource format ({types})', [
                    '{path}' => $path,
                    '{types}' => implode(', ', self::TYPES),
                ])
            ),
        };
    }

    public static function savePhp(string $path, mixed $content, ?string $source = null): void
    {
        $php = '<?php';
        if ($source !== null) {
            $php .= ' // ' . $source;
        }
        $php .= "\n\nreturn " . var_export($content, true) . ";\n";
        file_put_contents($path, $php);
    }

    public static function saveYaml(string $path, mixed $content): void
    {
        $yaml = Yaml::dump($content, self::INLINE, self::INDENT);
        file_put_contents($path, $yaml);
    }

    public static function saveJson(string $path, mixed $content): void
    {
        $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($path, $json);
    }
}
