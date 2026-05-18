<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testConstruct()
    {
        $task = new Task();
        $this->assertInstanceOf(Task::class, $task);
    }

    public function testEndReturnsTask()
    {
        $task = new Task();
        $result = $task->end();
        $this->assertSame($task, $result);
    }

    public function testSetLogReturnsTask()
    {
        $log = new Log();
        $task = new Task();
        $result = $task->setLog($log);
        $this->assertSame($task, $result);
    }

    public function testEndWithVerboseLogOutputsMessage()
    {
        $this->expectOutputRegex('/test message/');

        $log = new Log(['verbose' => true]);
        $task = new Task();
        $task->setLog($log)->end('test message');
    }
}
