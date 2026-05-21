<?php

namespace Kiss\DataSource;

use Kiss\KissException;

/**
 * The `AbstractDataSource` class is an abstract class that provides common functionality for
 * `DataSource` classes.
 */
abstract class AbstractLoader implements LoaderInterface
{
    use \Kiss\Thrower;

    protected ?string $filePath = null;
    protected mixed $content = null;

    /**
     * Constructor for the class.
     *
     * @param string $filePath The file path for the data source
     * @throws KissException if the file path does not exist
     * @return void
     */
    protected function __construct(string $filePath)
    {
        if (!\is_file($filePath)) {
            $this->error('DataSource "{path}" not found', [
                '{path}' => $filePath,
            ]);
        }

        $this->filePath = $filePath;
    }

    /**
     * Load the data from the file.
     *
     * @return mixed The content of the file
     */
    abstract public function load(): mixed;

    /**
     * Get the file path.
     *
     * @return string The file path
     */
    public function filePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the parsed content from the file.
     *
     * @return mixed The content
     */
    public function content(): mixed
    {
        return $this->content;
    }
}
