<?php

namespace Kiss;

use Kiss\KissException;
use Kiss\DataSource\AbstractLoader;
use Kiss\DataSource\PhpLoader;
use Kiss\DataSource\JsonLoader;
use Kiss\DataSource\YamlLoader;

/**
 * The `DataSource` class provides methods to load and save data from different file formats such as
 * PHP, JSON, and YAML.
 */

class DataSource
{
    private AbstractLoader $loader;
    public mixed $content;
    public string $filePath;

    const TYPES = ['php', 'json', 'yaml'];

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

    /**
     * The function `create` creates a new `DataSource` object based on the provided file path and
     * extension.
     *
     * @param string $filePath The `filePath` parameter is a string that represents the path to the file that
     * needs to be loaded or saved.
     *
     * @throws KissException if the file extension is not one of the supported extensions
     *
     * @return AbstractDataSource an instance of the `AbstractDataSource` class
     */
    // public static function create(string $filePath): AbstractDataSource
    // {
    //     $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    //     $extension = strtolower($extension);
    //     match ($extension) {
    //         'php' => ($instance = new PhpDataSource($filePath)),
    //         'json' => ($instance = new JsonDataSource($filePath)),
    //         'yaml', 'yml' => ($instance = new YamlDataSource($filePath)),
    //         default => throw new KissException(
    //             strtr('"{path}" is not a valid DataSource format ({types})', [
    //                 '{path}' => $filePath,
    //                 '{types}' => implode(', ', self::TYPES),
    //             ])
    //         ),
    //     };

    //     return $instance;
    // }
}
