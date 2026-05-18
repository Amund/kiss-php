<?php

namespace Kiss;

class Task
{
    private ?Log $log = null;
    private float $start;

    public function __construct()
    {
        $this->start = \microtime(true);
    }

    public function end(string $message = '', array $vars = []): Task
    {
        $duration = \microtime(true) - $this->start;
        if ($this->log && $this->log->verbose) {
            $message .= Log::color($this->log->colorDuration, ' ' . self::formatDuration($duration));
            $this->log->line($message, $vars);
        }
        return $this;
    }

    public function setLog(Log $log): Task
    {
        $this->log = $log;
        return $this;
    }

    private static function formatDuration(float $seconds): string
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

        return '0μs';
    }
}
