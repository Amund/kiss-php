<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class SiteTest extends TestCase
{
    public function testA(): void
    {
        $this->assertSame('test', 'test');
    }
}
