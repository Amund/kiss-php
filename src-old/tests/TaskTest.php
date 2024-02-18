<?php

namespace Kiss;

use Kiss\Log;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    // public function testTask()
// {
//     $task = new Task();
//     $this->assertIsFloat($task->begin);
//     $this->assertNull($task->end);
//     $task->end();
//     $this->assertIsFloat($task->end);
//     $this->assertTrue($task->end > $task->begin);
//     $this->assertIsFloat($task->duration);
//     $this->assertEquals($task->duration, $task->end - $task->begin);
// }
// public function testTaskOutputOnBegin()
// {
//     $this->expectOutputRegex('#test\.\.\.#');
//     $log = new Log(['verbose' => true]);
//     $task = new Task(true, $log);
//     $task->begin('test');
// }
// public function testTaskOutputOnEnd()
// {
//     $this->expectOutputRegex('#test\.\.\..+\d+(?:μs|ms|s|min|h|d).+#');
//     $log = new Log(['verbose' => true]);
//     $task = new Task(true, $log);
//     $task->begin('test');
//     $task->end();
// }
// public function testFormatDuration()
// {
//     $tests = [
//         [0.000001, '1μs'],
//         [0.000009, '9μs'],
//         [0.00001, '10μs'],
//         [0.000999, '999μs'],
//         [0.001, '1ms'],
//         [0.001001, '1ms'],
//         [0.001049, '1ms'],
//         [0.00105, '1.1ms'],
//         [0.0011, '1.1ms'],
//         [0.0012, '1.2ms'],
//         [0.002, '2ms'],
//         [0.01, '10ms'],
//         [1, '1s'],
//         [9.9, '9.9s'],
//         [10, '10s'],
//         [60, '1min'],
//         [63, '1.1min'],
//         [111, '1.9min'],
//         [120, '2min'],
//         [130, '2.2min'],
//         [594, '9.9min'],
//         [600, '10min'],
//         [3540, '59min'],
//         [3600, '1h'],
//         [3800, '1.1h'],
//         [7200, '2h'],
//         [82800, '23h'],
//         [86400, '1d'],
//         [172800, '2d'],
//         [1728000, '20d'],
//         [8640000, '100d'],
//     ];
//     foreach ($tests as $test) {
//         $this->assertEquals(Task::formatDuration($test[0]), $test[1]);
//     }
// }
}
