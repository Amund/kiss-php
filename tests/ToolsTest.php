<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class ToolsTest extends TestCase
{
    public function testSlugify()
    {
        $values = [
            ['Poésie Française', 'poesie-francaise'],
            ['C\'est le cœur du problème ?', 'c-est-le-coeur-du-probleme'],
            ['ÄÖÜ äöü ß', 'aou-aou-ss'],
            ['Привет мир', 'privet-mir'],
        ];
        foreach ($values as $v) {
            $this->assertEquals(Tools::slugify($v[0]), $v[1]);
        }
    }

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

    public function testNormalizeSource()
    {
        $cwd = getcwd();
        $values = [
            ['test', null, $cwd . \DIRECTORY_SEPARATOR . 'test'],
            ['test', '/base', '/base' . \DIRECTORY_SEPARATOR . 'test'],
            ['http://test', null, 'http://test'],
        ];

        foreach ($values as $v) {
            $this->assertEquals(Tools::normalizeSource($v[0], $v[1]), $v[2]);
        }
    }
}
