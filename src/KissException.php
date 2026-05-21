<?php

namespace Kiss;

class KissException extends \Exception
{
    private ?string $fix;

    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $fix = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->fix = $fix;
    }

    public function getFix(): ?string
    {
        return $this->fix;
    }

    public function isUserCodeError(): bool
    {
        return $this->getPrevious() !== null;
    }
}
