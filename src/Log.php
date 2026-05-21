<?php

namespace Kiss;

class Log
{
    public string $colorPath = 'yellow';
    public string $colorDuration = 'green dim';
    public string $colorError = 'red';
    public string $colorVerbose = 'dim';
    public string $colorSuccess = 'green';
    public string $colorWarning = 'yellow';
    public string $colorInfo = 'white dim';
    public bool $verbose = false;
    public bool $silent = false;
    public bool $prependTimestamp = false;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            return;
        }
        $this->colorPath = $config['color']['path'] ?? $this->colorPath;
        $this->colorDuration =
            $config['color']['duration'] ?? $this->colorDuration;
        $this->colorError = $config['color']['error'] ?? $this->colorError;
        $this->verbose = Tools::truthy($config['verbose'] ?? false);
    }

    public function __invoke(string $str = '', array $vars = []): self
    {
        if ($this->silent) {
            return $this;
        }
        $this->write($this->interpolate($str, $vars));
        return $this;
    }

    protected function write(string $str): void
    {
        if ($this->prependTimestamp && trim($str) !== '') {
            $str = self::color('dim', '[' . date('H:i:s') . '] ') . $str;
        }
        echo $str;
    }

    public function error(string $str = '', array $vars = []): self
    {
        if ($this->silent) {
            return $this;
        }
        $this->writeStderr($this->interpolate($str . "\n", $vars));
        return $this;
    }

    protected function writeStderr(string $str): void
    {
        if ($this->prependTimestamp && trim($str) !== '') {
            $str = self::color('dim', '[' . date('H:i:s') . '] ') . $str;
        }
        fwrite(STDERR, $str);
    }

    public function line(string $str = '', array $vars = []): self
    {
        return $this($str . "\n", $vars);
    }

    public function ok(string $process, string $detail, ?string $duration = null): void
    {
        $label = ' ' . str_pad($process, 6);
        $msg = self::color('green', ' ✓') . self::color($this->colorSuccess, $label) . $detail;
        if ($duration !== null) {
            $msg .= self::color($this->colorDuration, " {$duration}");
        }
        $this->line($msg);
    }

    public function fail(string $process, string $detail): void
    {
        $label = ' ' . str_pad($process, 6);
        $this->line(
            self::color('bold red', ' ✗') . self::color($this->colorSuccess, $label) . $detail
        );
    }

    public function info(string $str = '', array $vars = []): self
    {
        return $this->line(self::color($this->colorInfo, $str), $vars);
    }

    public function success(string $str = '', array $vars = []): self
    {
        return $this->line(self::color($this->colorSuccess, $str), $vars);
    }

    public function warning(string $str = '', array $vars = []): self
    {
        return $this->line(self::color($this->colorWarning, $str), $vars);
    }

    public function debug(string $str = '', array $vars = []): self
    {
        if (!$this->verbose || $this->silent) {
            return $this;
        }
        $this->line(self::color($this->colorVerbose, $str), $vars);
        return $this;
    }

    public function message(string $str, array $vars): string
    {
        foreach ($vars as $k => $v) {
            if (\str_starts_with($k, '{path')) {
                $vars[$k] = self::color($this->colorPath, $v);
            }
        }
        return strtr($str, $vars);
    }

    private function interpolate(string $str, array $vars): string
    {
        foreach ($vars as $k => $v) {
            if (\str_starts_with($k, '{path')) {
                $vars[$k] = self::color($this->colorPath, $v);
            }
        }
        return strtr($str, $vars);
    }

    public static function color(string $colors, string $str): string
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
