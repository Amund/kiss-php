<?php

namespace Kiss;

use Symfony\Component\Yaml\Yaml;

/* The `DataSource` class provides methods to load and save data from different file formats such as
 PHP, JSON, and YAML. */
class DataSource
{
    /**
     * The function `load` loads data from a data source file (PHP, JSON, or YAML) and returns the content
     * as an array.
     *
     * @param source The `source` parameter is the path to the data source file that needs to be loaded. It
     * can be a file path to a YAML, JSON, or PHP file.
     *
     * @return mixed the content loaded from the data source file. The content can be of mixed type,
     * depending on the format of the data source file (PHP, JSON, YAML).
     */
    public function load($source): mixed
    {
        if (!\is_file($source)) {
            $this->error('DataSource "{path}" not found', [
                '{path}' => $source,
            ]);
        }
        $ext = $this->normalizeExtension($source);
        match ($ext) {
            'php' => ($content = $this->loadFromPhp($source)),
            'json' => ($content = $this->loadFromJson($source)),
            'yaml', 'yml' => ($content = $this->loadFromYaml($source)),
            default => $this->error(
                '"{path}" is not a valid DataSource format, it must be yaml, json or php file',
                [
                    '{path}' => $source,
                ]
            ),
        };

        if (is_object($content)) {
            $content = (array) $content;
        }

        return $content;
    }

    /**
     * The `save` function saves content to a file with the specified source path, based on the file
     * extension.
     *
     * @param source The `source` parameter represents the path and filename of the file where the content
     * will be saved.
     * @param content The `` parameter is the data that you want to save to a file. It can be a
     * string or any other data type that can be converted to a string.
     */
    public function save($source, $content): void
    {
        $ext = $this->normalizeExtension($source);
        match ($ext) {
            'php' => ($content = $this->saveToPhp($content)),
            'json' => ($content = $this->saveToJson($content)),
            'yaml', 'yml' => ($content = $this->saveToYaml($content)),
            default => $this->error(
                '"{path}" is not a valid DataSource format, it must be yaml, json or php file',
                [
                    '{path}' => $source,
                ]
            ),
        };

        // ensure destination directory exists
        $dir = dirname($source);
        if (!\is_dir($dir)) {
            $written = \mkdir($dir, 0777, \true);
            if ($written === \false) {
                $this->error('Can not create {path-dir} to store {path}', [
                    '{path-dir}' => $dir,
                    '{path}' => $source,
                ]);
            }
        }

        // write file
        $written = \file_put_contents($source, $content);
        if ($written === \false) {
            $this->error('Can not create {path} file', [
                '{path}' => $source,
            ]);
        }
    }

    /**
     * The function "loadFromPhp" loads content from a PHP data source and handles any errors that occur.
     *
     * @param source The `source` parameter is the path to the PHP file that you want to load and include
     * in your code. It is used to specify the location of the PHP file that contains the data you want to
     * load.
     *
     * @return mixed the variable ``.
     */
    private function loadFromPhp($source): mixed
    {
        try {
            $content = $this->include($source);
        } catch (\Throwable $err) {
            $this->error(
                '"{path}" PHP DataSource has thrown an error',
                ['{path}' => $source],
                $err
            );
        }
        return $content;
    }

    /**
     * The function `loadFromJson` reads a JSON file from a given source and returns the decoded data as an
     * associative array, or throws an error if there is a parsing error.
     *
     * @param source The `source` parameter is the path to the JSON file that you want to load and parse.
     *
     * @return mixed the result of decoding the JSON data from the specified source.
     */
    private function loadFromJson($source): mixed
    {
        try {
            return json_decode(file_get_contents($source), true);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" JSON DataSource',
                ['{path}' => $source],
                $err
            );
        }
    }

    /**
     * The function `loadFromYaml` loads data from a YAML file and returns it, or throws an error if there
     * is a parsing error.
     *
     * @param source The `source` parameter is the path to the YAML file that you want to load and parse.
     *
     * @return mixed the result of the Yaml::parseFile() method, which is of type mixed.
     */
    private function loadFromYaml($source): mixed
    {
        try {
            return Yaml::parseFile($source);
        } catch (\Throwable $err) {
            $this->error(
                'Parsing error in "{path}" YAML DataSource',
                ['{path}' => $source],
                $err
            );
        }
    }

    /**
     * The function "saveToPhp" takes a content parameter and returns a string that represents the content
     * in PHP format.
     *
     * @param content The `content` parameter is the data that you want to save to a PHP file. It can be
     * any valid PHP data type such as a string, array, or object.
     *
     * @return string a string that contains a PHP code snippet. The snippet includes the content variable,
     * which is being exported using the var_export() function, and is then concatenated with the necessary
     * PHP syntax to create a valid PHP code.
     */
    private function saveToPhp($content): string
    {
        return '<?php return ' . var_export($content, true) . ';';
    }

    /**
     * The function `saveToJson` takes in content and returns it as a JSON string with pretty printing and
     * unescaped slashes.
     *
     * @param content The  parameter is the data that you want to convert to JSON format and save
     * to a file. It can be an array, object, or any other data type that can be encoded as JSON.
     *
     * @return string a string.
     */
    private function saveToJson($content): string
    {
        return \json_encode(
            $content,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * The function saves the given content to a YAML file and returns the YAML representation as a string.
     *
     * @param content The `` parameter is the data that you want to convert to YAML format and save
     * to a file. It can be an array or an object.
     *
     * @return string a string.
     */
    private function saveToYaml($content): string
    {
        return Yaml::dump(
            $content,
            2,
            4,
            Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE |
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }

    /**
     * The function "normalizeExtension" takes a file path as input and returns the lowercase extension of
     * the file.
     *
     * @param path The `path` parameter is a string representing the file path or URL from which you want
     * to extract the file extension.
     *
     * @return string the normalized extension of the given path as a string.
     */
    private function normalizeExtension($path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        return $ext;
    }

    /**
     * The function "include" is used to include and execute the specified file and returns the result.
     *
     * @param source The parameter "source" is the path to a file that you want to include in your code.
     *
     * @return mixed the result of the `include` statement. The `include` statement is used to include and
     * evaluate the specified file. If the file is successfully included, it will return `1`. If the file
     * cannot be included, it will return `false`.
     */
    private function include($source): mixed
    {
        return include $source;
    }

    /**
     * The error function throws a KissException with a formatted error message and an optional previous
     * exception.
     *
     * @param string str A string representing the error message. This string may contain placeholders that
     * will be replaced with values from the  array using the strtr() function.
     * @param args The `` parameter is an optional array that contains the values to be replaced in
     * the `` string using `strtr()` function. It allows you to dynamically replace placeholders in the
     * error message with actual values.
     * @param previous The `` parameter is an optional parameter of type `\Throwable`. It
     * represents the previous exception that caused the current exception to be thrown. It is used to
     * create a chain of exceptions, where each exception represents a different level of the error or
     * exception.
     */
    private function error(
        string $str,
        ?array $args = [],
        ?\Throwable $previous = null
    ): void {
        throw new KissException(strtr($str, $args), 0, $previous);
    }
}
