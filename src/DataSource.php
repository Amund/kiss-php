<?php

namespace Kiss;

use Kiss\DataSource\AbstractLoader;
use Kiss\DataSource\PhpLoader;
use Kiss\DataSource\JsonLoader;
use Kiss\DataSource\YamlLoader;
use Kiss\DataSource\IniLoader;
use Kiss\DataSource\XmlLoader;
use Kiss\DataSource\MarkdownLoader;
use Symfony\Component\Yaml\Yaml;

class DataSource
{
    private AbstractLoader $loader;
    public mixed $content;
    public string $filePath;

    public const TYPES = ['php', 'json', 'yaml', 'xml', 'ini', 'md'];
    public const INLINE = 6;
    public const INDENT = 2;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $extension = self::detectFormat($filePath);

        $this->loader = match ($extension) {
            'php' => new PhpLoader($filePath),
            'json' => new JsonLoader($filePath),
            'yaml', 'yml' => new YamlLoader($filePath),
            'xml' => new XmlLoader($filePath),
            'ini' => new IniLoader($filePath),
            'md' => new MarkdownLoader($filePath),
            default => throw new \LogicException('Unhandled extension: ' . $extension),
        };

        $this->content = $this->loader->load();
    }

    public function save(mixed $content = null, ?string $filePath = null): void
    {
        $content = $content ?? $this->content;
        $path = $filePath ?? $this->filePath;
        $extension = self::detectFormat($path);

        match ($extension) {
            'php' => self::savePhp($path, $content),
            'yaml', 'yml' => self::saveYaml($path, $content),
            'json' => self::saveJson($path, $content),
            'xml' => self::saveXml($path, $content),
            'ini' => self::saveIni($path, $content),
            'md' => throw new \LogicException('Saving to Markdown (.md) is not supported'),
            default => throw new \LogicException('Unhandled extension: ' . $extension),
        };
    }

    private static function detectFormat(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::TYPES, true) && $extension !== 'yml') {
            throw new KissException(
                strtr('"{path}" is not a valid DataSource format ({types})', [
                    '{path}' => $filePath,
                    '{types}' => implode(', ', self::TYPES),
                ]),
                0,
                null,
                'Use .php, .json, .yaml, .xml, .ini or .md extension'
            );
        }

        return $extension;
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

    public static function saveXml(string $path, mixed $content): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        if (is_array($content)) {
            $root = $dom->createElement('root');
            $dom->appendChild($root);
            self::arrayToXml($dom, $root, $content);
        } else {
            $dom->appendChild($dom->createElement('root', htmlspecialchars((string) $content)));
        }

        file_put_contents($path, $dom->saveXML());
    }

    public static function saveIni(string $path, mixed $content): void
    {
        $lines = [];
        if (is_array($content)) {
            self::arrayToIni($lines, $content);
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    private static function arrayToXml(\DOMDocument $dom, \DOMElement $parent, array $array): void
    {
        foreach ($array as $key => $value) {
            $child = $dom->createElement(is_string($key) ? $key : 'item');
            $parent->appendChild($child);

            if (is_array($value)) {
                self::arrayToXml($dom, $child, $value);
            } else {
                $child->appendChild($dom->createTextNode((string) $value));
            }
        }
    }

    private static function arrayToIni(array &$lines, array $array, ?string $section = null): void
    {
        $hasSubArrays = false;
        $simple = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $hasSubArrays = true;
            } else {
                $simple[$key] = $value;
            }
        }

        if ($section !== null) {
            $lines[] = '[' . $section . ']';
        }
        foreach ($simple as $key => $value) {
            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            if (is_numeric($key)) {
                $lines[] = $value;
            } else {
                $lines[] = $key . ' = ' . $value;
            }
        }
        if (!empty($simple) && $hasSubArrays) {
            $lines[] = '';
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::arrayToIni($lines, $value, is_string($key) ? $key : null);
            }
        }
    }
}
