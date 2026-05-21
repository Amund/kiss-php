<?php

namespace Kiss\DataSource;

interface LoaderInterface
{
    public function load(): mixed;
    public function filePath(): string;
    public function content(): mixed;
}
