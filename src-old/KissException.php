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
}
