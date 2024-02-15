<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class DataTreeTest extends TestCase
{
    private $tree;

    public function setUp(): void
    {
        $options = ['cache' => 'tmp/test'];
        $this->tree = new DataTree($options);
    }

    public function tearDown(): void
    {
        $this->tree->clear();
    }

    public function testMustHaveMethods()
    {
        $methods = ['resolve', 'invalidate', 'clear'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->tree, $method),
                'Datatree does not have method "' . $method . '"'
            );
        }
    }

    public function testResolveMustReturnSimpleDataStructure()
    {
        $this->assertEquals($this->tree->resolve('foo'), 'foo');
        $this->assertEquals($this->tree->resolve(42), 42);
        $this->assertEquals($this->tree->resolve(null), null);
        $this->assertEquals($this->tree->resolve(['foo']), ['foo']);
        $this->assertEquals($this->tree->resolve((object) ['foo' => 'bar']), [
            'foo' => 'bar',
        ]);
    }

    public function testResolveMustReplaceExternalReferencesByTheirContent()
    {
        $value = ['$ref' => __DIR__ . '/fixture/data.php'];
        $this->assertEquals($this->tree->resolve($value), 'php');

        $value = ['$ref' => __DIR__ . '/fixture/data.yml'];
        $this->assertEquals($this->tree->resolve($value), 'yml');

        $value = ['$ref' => __DIR__ . '/fixture/data.json'];
        $this->assertEquals($this->tree->resolve($value), 'json');

        $value = ['$ref' => __DIR__ . '/fixture/dataArray.php'];
        $this->assertEquals($this->tree->resolve($value), ['foo' => 'bar']);

        $value = ['$ref' => __DIR__ . '/fixture/dataObject.php'];
        $this->assertEquals($this->tree->resolve($value), ['foo' => 'bar']);
    }

    public function testResolveMustReplaceNestedExternalReferencesByTheirContent()
    {
        $value = ['$ref' => __DIR__ . '/fixture/dataArrayWithRef.php'];
        $this->assertEquals($this->tree->resolve($value), 'php');
    }

    public function testResolveMustMergeExternalReference()
    {
        $value = [
            '$ref' => __DIR__ . '/fixture/dataArray.php',
            'a' => 1,
        ];
        $this->assertEquals($this->tree->resolve($value), [
            'foo' => 'bar',
            'a' => 1,
        ]);

        $value = [
            '$ref' => __DIR__ . '/fixture/dataArray.php',
            'foo' => 'baz',
        ];
        $this->assertEquals($this->tree->resolve($value), ['foo' => 'baz']);
    }

    public function testResolveMustAcceptMultipleReferencesToSameFragmentOnDifferentBranch()
    {
        $value = [
            ['$ref' => __DIR__ . '/fixture/dataArrayWithRef.php'],
            ['$ref' => __DIR__ . '/fixture/dataArrayWithRef.php'],
        ];
        $this->assertEquals($this->tree->resolve($value), ['php', 'php']);
    }

    public function testResolveMustThrowExceptionOnCircularReference()
    {
        $this->expectException(KissException::class);
        $value = ['$ref' => __DIR__ . '/fixture/dataCircular.php'];
        $this->tree->resolve($value);
    }

    public function testCachedFragmentCanBeInvalidated()
    {
        $source = __DIR__ . '/fixture/dataRandom.php';
        $value = ['$ref' => $source];

        // load and set cache
        $get = $this->tree->resolve($value);
        // load from cache
        $getCached = $this->tree->resolve($value);
        $this->assertEquals($get, $getCached);

        $this->tree->invalidate($source);
        // load fresh version
        $getNew = $this->tree->resolve($value);

        $this->assertNotEquals($get, $getNew);
    }
}
