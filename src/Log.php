<?php

namespace Kiss;

class Log
{
    public string $colorPath = 'yellow';
    public string $colorDuration = 'green dim';
    public string $colorError = 'red dim';
    public string $colorVerbose = 'dim';
    public bool $verbose = true;

    public function __construct(array $config = null)
    {
        $this->colorPath = $config['color']['path'] ?? $this->colorPath;
        $this->colorDuration =
            $config['color']['duration'] ?? $this->colorDuration;
        $this->colorError = $config['color']['error'] ?? $this->colorError;

        $this->verbose = Tools::truthy($config['verbose'] ?? false);
    }

    public function __invoke(string $str = '', array $vars = [])
    {
        echo $this->message($str, $vars);
        return $this;
    }

    public function line(string $str = '', array $vars = [])
    {
        $this($str . "\n", $vars);
        return $this;
    }

    public function error(string $str = '', array $vars = [])
    {
        $this->line(self::color($this->colorError, $str), $vars);
        return $this;
    }

    public function message($message, $vars)
    {
        foreach ($vars as $k => $v) {
            if (\str_starts_with($k, '{path')) {
                $vars[$k] = self::color($this->colorPath, $v);
            }
        }
        return strtr($message, $vars);
    }

    public static function color(string $colors, string $str)
    {
        $codes = [
            'bold' => ["\033[1m", "\033[22m"],
            'dim' => ["\033[2m", "\033[22m"],
            'italic' => ["\033[3m", "\033[23m"],
            'underline' => ["\033[4m", "\033[24m"],
            'inverse' => ["\033[7m", "\033[27m"],
            'strikethrough' => ["\033[9m", "\033[29m"],
            'hidden' => ["\033[8m", "\033[28m"],

            'black' => ["\033[30m", "\033[39m"],
            'red' => ["\033[31m", "\033[39m"],
            'green' => ["\033[32m", "\033[39m"],
            'yellow' => ["\033[33m", "\033[39m"],
            'blue' => ["\033[34m", "\033[39m"],
            'magenta' => ["\033[35m", "\033[39m"],
            'cyan' => ["\033[36m", "\033[39m"],
            'white' => ["\033[37m", "\033[39m"],
        ];

        $colors = explode(' ', $colors);
        foreach ($colors as $color) {
            $str = \array_key_exists($color, $codes)
                ? $codes[$color][0] . $str . $codes[$color][1]
                : $str;
        }
        return $str;
    }
}
