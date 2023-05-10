<?php

namespace Kiss;

class Task
{
    private ?Log $log = null;
    private ?float $begin = null;
    private float $end;
    private float $duration;
    private string $formattedDuration;
    private bool $verbose;

    public function __construct(bool $verbose = false, Log $log = null)
    {
        $this->begin = \microtime(true);
        $this->verbose = $verbose;
        $this->log = $log;
    }

    public function begin(string $message = '', array $vars = []): Task
    {
        if (!empty($message)) {
            if ($this->log) {
                $message .= '... ';
                if ($this->verbose) {
                    $message = Log::color($this->log->colorVerbose, $message);
                }
                if ($this->log->verbose) {
                    ($this->log)($message, $vars);
                }
            }
        }
        return $this;
    }

    public function end(string $message = '', array $vars = []): Task
    {
        $this->end = \microtime(true);
        $this->duration = $this->end - $this->begin;
        $this->formattedDuration = self::formatDuration($this->duration);

        if (empty($message)) {
            $message = Log::color('green', 'ok');
        }

        if ($this->log) {
            if ($this->verbose) {
                $message = Log::color($this->log->colorVerbose, $message);
            }
            $message .= Log::color(
                $this->log->colorDuration,
                '  ' . $this->formattedDuration
            );
            if ($this->log->verbose) {
                $this->log->line($message, $vars);
            }
        } else {
            // throw new KissException('too soon, no Log provided for now');
        }
        return $this;
    }

    public function setLog(Log $log): Task
    {
        $this->log = $log;
        return $this;
    }

    public static function formatDuration(float $seconds): string
    {
        $units = [
            'd' => 86400,
            'h' => 3600,
            'min' => 60,
            's' => 1,
            'ms' => 0.001,
            'μs' => 0.000001,
        ];

        foreach ($units as $unit => $value) {
            if ($seconds >= $value) {
                $count = \floor($seconds / $value);
                $seconds -= $count * $value;
                $result = \round($count + $seconds / $value, 1);
                return $result == (int) $result
                    ? (int) $result . $unit
                    : $result . $unit;
            }
        }

        return '0s';
    }
}
