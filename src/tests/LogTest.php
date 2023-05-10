<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testInvoke()
    {
        $this->expectOutputString('foo');
        $log = new Log();
        $log('foo');
    }

    public function testInvokeWithParam()
    {
        $this->expectOutputString('foo bar');
        $log = new Log();
        $log('foo {param}', ['{param}' => 'bar']);
    }

    public function testLine()
    {
        $this->expectOutputString('foo' . "\n");
        $log = new Log();
        $log->line('foo');
    }

    public function testLineWithParam()
    {
        $this->expectOutputString('foo bar' . "\n");
        $log = new Log();
        $log->line('foo {param}', ['{param}' => 'bar']);
    }
}
