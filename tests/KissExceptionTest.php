<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class KissExceptionTest extends TestCase
{
    public function testCanGetMessage()
    {
        $e = new KissException('test');
        $this->assertSame('test', $e->getMessage());
    }

    public function testCanGetFix()
    {
        $e = new KissException('test', 0, null, 'Try this');
        $this->assertSame('Try this', $e->getFix());
    }

    public function testFixIsNullWhenNotSet()
    {
        $e = new KissException('test');
        $this->assertNull($e->getFix());
    }

    public function testIsUserCodeErrorReturnsFalseWithoutPrevious()
    {
        $e = new KissException('test');
        $this->assertFalse($e->isUserCodeError());
    }

    public function testIsUserCodeErrorReturnsTrueWithPrevious()
    {
        $prev = new \RuntimeException('db error');
        $e = new KissException('test', 0, $prev);
        $this->assertTrue($e->isUserCodeError());
    }

    public function testCanGetPrevious()
    {
        $prev = new \RuntimeException('db error');
        $e = new KissException('test', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
