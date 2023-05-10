<?php

namespace Kiss;

class Timer
{
    public string $name;
    public float $start;

    public function __construct()
    {
        $this->start = \microtime(true);
    }

    public function getRaw()
    {
        return \microtime(true) - $this->start;
    }

    public function get()
    {
        return self::formatDuration($this->getRaw());
    }

    public static function formatDuration($seconds)
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
