<?php

namespace Kiss\DataSource;

use Kiss\KissException;

/**
 * The `AbstractDataSource` class is an abstract class that provides common functionality for
 * `DataSource` classes.
 */
abstract class AbstractDataSource
{
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
            self::error('DataSource "{path}" not found', [
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

    /**
     * Throw a `KissException`.
     *
     * @param string $str The error message, containing placeholders
     * @param array|null $args Variables to interpolate in the message
     * @param \Throwable|null $previous Previous exception
     * @throws KissException description of exception
     * @return void
     */
    protected function error(
        string $str,
        ?array $args = [],
        ?\Throwable $previous = null
    ): void {
        throw new KissException(strtr($str, $args), 0, $previous);
    }
}
