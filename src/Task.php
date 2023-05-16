<?php

namespace Kiss;

class Task
{
    private ?Log $log = null;
    private ?float $begin = null;
    private ?float $end = null;
    private float $duration;
    private string $formattedDuration;
    private bool $verbose;
    public array $saved;

    public function __construct(bool $verbose = false, Log $log = null)
    {
        $this->begin = \microtime(true);
        $this->verbose = $verbose;
        $this->log = $log;
    }

    public function begin(string $message = '', array $vars = []): Task
    {
        if ($this->log) {
            if (!empty($message)) {
                $message .= '... ';
                if ($this->verbose) {
                    $message = Log::color($this->log->colorVerbose, $message);
                }
                if ($this->log->verbose) {
                    ($this->log)($message, $vars);
                }
            }
        } else {
            $this->saved['begin'] = [$message, $vars];
        }
        return $this;
    }

    public function end(string $message = '', array $vars = []): Task
    {
        if (is_null($this->end)) {
            $this->end = \microtime(true);
        }
        $this->duration = $this->end - $this->begin;
        $this->formattedDuration = self::formatDuration($this->duration);

        // if (empty($message)) {
        //     $message = Log::color('green', 'ok');
        // }

        if ($this->log) {
            if ($this->verbose) {
                $message = Log::color($this->log->colorVerbose, $message);
            }
            $message .= Log::color(
                $this->log->colorDuration,
                ' ' . $this->formattedDuration
            );
            if ($this->log->verbose) {
                $this->log->line($message, $vars);
            }
        } else {
            $this->saved['end'] = [$message, $vars];
        }
        return $this;
    }

    public function setLog(Log $log): void
    {
        $this->log = $log;

        $beginMessage = $this->saved['begin'][0] ?? '';
        $beginVars = $this->saved['begin'][1] ?? [];
        $endMessage = $this->saved['end'][0] ?? '';
        $endVars = $this->saved['end'][1] ?? [];
        $this->begin($beginMessage, $beginVars)->end($endMessage, $endVars);
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

        return '1μs';
    }
}
