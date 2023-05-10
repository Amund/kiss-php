<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class ToolsTest extends TestCase
{
    public function testMerge()
    {
        $values = [
            [0, 0, 0],
            [1, 0, 0],
            [0, 1, 1],
            [['a' => 0], 1, 1],
            [0, ['a' => 0], ['a' => 0]],
            [['a' => 0], ['a' => 1], ['a' => 1]],
            [['a' => 0, 'b' => 0], ['a' => 1], ['a' => 1, 'b' => 0]],
            [['a' => 0], ['a' => 1, 'b' => 0], ['a' => 1, 'b' => 0]],
        ];

        foreach ($values as $v) {
            $this->assertEquals(Tools::merge($v[0], $v[1]), $v[2]);
        }
    }
}
