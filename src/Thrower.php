<?php

namespace Kiss;

trait Thrower
{
    public function error(
        string $str,
        ?array $vars = [],
        ?\Throwable $previous = null,
        ?string $fix = null
    ): void {
        throw new \Kiss\KissException(strtr($str, $vars), 0, $previous, $fix);
    }
}
