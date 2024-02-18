<?php

namespace Kiss;

class KissException extends \Exception
{
    /**
     * Throw a `KissException`.
     *
     * @param string $str The error message
     * @param int $code The error code
     * @param \Throwable|null $previous Previous exception
     * @return void
     */
    public function __construct(
        $message,
        $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function throw(
        string $str,
        array $vars = [],
        \Throwable $previous = null
    ) {
        throw new KissException(strtr($str, $vars), 0, $previous);
    }
}
