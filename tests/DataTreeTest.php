<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class DataTreeTest extends TestCase
{
    private string $fixturesDir;
    private $tree;

    public function setUp(): void
    {
        $this->fixturesDir = sys_get_temp_dir() . '/kiss-test-fixtures-' . uniqid();
        mkdir($this->fixturesDir, 0777, true);

        $this->tree = new DataTree([
            'cache' => sys_get_temp_dir() . '/kiss-test-datatree',
        ]);
    }

    public function tearDown(): void
    {
        $this->tree->clear();
        if (is_dir($this->fixturesDir)) {
            array_map('unlink', glob($this->fixturesDir . '/*'));
            rmdir($this->fixturesDir);
        }
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
        $this->assertEquals(
            $this->tree->resolve((object) ['foo' => 'bar']),
            ['foo' => 'bar']
        );
    }

    public function testResolveMustReplaceExternalReferencesByTheirContent()
    {
        file_put_contents($this->fixturesDir . '/data.php', '<?php return "php";');
        file_put_contents($this->fixturesDir . '/data.yml', 'yml');
        file_put_contents($this->fixturesDir . '/data.json', '"json"');
        file_put_contents(
            $this->fixturesDir . '/dataArray.php',
            '<?php return ["foo" => "bar"];'
        );
        file_put_contents(
            $this->fixturesDir . '/dataObject.php',
            '<?php return (object) ["foo" => "bar"];'
        );

        $dir = $this->fixturesDir;

        $this->assertEquals(
            $this->tree->resolve(['$ref' => $dir . '/data.php']),
            'php'
        );

        $this->assertEquals(
            $this->tree->resolve(['$ref' => $dir . '/data.yml']),
            'yml'
        );

        $this->assertEquals(
            $this->tree->resolve(['$ref' => $dir . '/data.json']),
            'json'
        );

        $this->assertEquals(
            $this->tree->resolve(['$ref' => $dir . '/dataArray.php']),
            ['foo' => 'bar']
        );

        $this->assertEquals(
            $this->tree->resolve(['$ref' => $dir . '/dataObject.php']),
            ['foo' => 'bar']
        );
    }

    public function testResolveMustReplaceNestedExternalReferencesByTheirContent()
    {
        $dir = $this->fixturesDir;
        file_put_contents($dir . '/data.php', '<?php return "php";');
        file_put_contents(
            $dir . '/dataArrayWithRef.php',
            '<?php return [\'$ref\' => \'' . $dir . '/data.php\'];'
        );

        $value = ['$ref' => $dir . '/dataArrayWithRef.php'];
        $this->assertEquals($this->tree->resolve($value), 'php');
    }

    public function testResolveMustMergeExternalReference()
    {
        file_put_contents(
            $this->fixturesDir . '/dataArray.php',
            '<?php return ["foo" => "bar"];'
        );

        $dir = $this->fixturesDir;

        $value = ['$ref' => $dir . '/dataArray.php', 'a' => 1];
        $this->assertEquals(
            $this->tree->resolve($value),
            ['foo' => 'bar', 'a' => 1]
        );

        $value = ['$ref' => $dir . '/dataArray.php', 'foo' => 'baz'];
        $this->assertEquals(
            $this->tree->resolve($value),
            ['foo' => 'baz']
        );
    }

    public function testResolveMustAcceptMultipleReferencesToSameFragmentOnDifferentBranch()
    {
        $dir = $this->fixturesDir;
        file_put_contents($dir . '/data.php', '<?php return "php";');
        file_put_contents(
            $dir . '/dataArrayWithRef.php',
            '<?php return [\'$ref\' => \'' . $dir . '/data.php\'];'
        );

        $dir = $this->fixturesDir;
        $value = [
            ['$ref' => $dir . '/dataArrayWithRef.php'],
            ['$ref' => $dir . '/dataArrayWithRef.php'],
        ];
        $this->assertEquals($this->tree->resolve($value), ['php', 'php']);
    }

    public function testResolveMustThrowExceptionOnCircularReference()
    {
        $this->expectException(KissException::class);

        $dir = $this->fixturesDir;
        file_put_contents(
            $dir . '/dataCircular.php',
            '<?php return [\'$ref\' => \'' . $dir . '/dataCircular.php\'];'
        );

        $value = ['$ref' => $dir . '/dataCircular.php'];
        $this->tree->resolve($value);
    }

    public function testCachedFragmentCanBeInvalidated()
    {
        file_put_contents(
            $this->fixturesDir . '/dataRandom.php',
            '<?php return rand(0, 999999);'
        );

        $source = $this->fixturesDir . '/dataRandom.php';
        $value = ['$ref' => $source];

        $get = $this->tree->resolve($value);
        $getCached = $this->tree->resolve($value);
        $this->assertEquals($get, $getCached);

        $this->tree->invalidate($source);
        $getNew = $this->tree->resolve($value);

        $this->assertNotEquals($get, $getNew);
    }

    public function testClearMustRemoveCacheDirectory()
    {
        $fixturesDir = sys_get_temp_dir() . '/kiss-test-dt-clear-' . uniqid();
        mkdir($fixturesDir, 0777, true);
        file_put_contents(
            $fixturesDir . '/data.php',
            '<?php return "test";'
        );

        $this->tree->resolve(['$ref' => $fixturesDir . '/data.php']);

        $cacheDir = sys_get_temp_dir() . '/kiss-test-datatree';
        $this->assertDirectoryExists($cacheDir);

        $this->tree->clear();
        $this->assertDirectoryDoesNotExist($cacheDir);

        array_map('unlink', glob($fixturesDir . '/*'));
        rmdir($fixturesDir);
    }
}
