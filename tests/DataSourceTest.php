<?php

namespace Kiss;

use Kiss\DataSource;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class DataSourceTest extends TestCase
{
    private $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup();
    }

    public function testLoadFromPhp()
    {
        vfsStream::create(['test.php' => '<?php return "ok";']);
        $path = $this->root->url() . '/test.php';

        $ds = new DataSource($path);
        $this->assertEquals('ok', $ds->content);
        $this->assertEquals($path, $ds->filePath);
    }

    public function testLoadFromBadPhpFileMustThrowException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.php';
        vfsStream::create(['test.php' => '<?php throw new Exception();']);
        $ds = new DataSource($path);
    }

    public function testLoadFromJson()
    {
        vfsStream::create(['test.json' => '"ok"']);
        $path = $this->root->url() . '/test.json';

        $ds = new DataSource($path);
        $this->assertEquals('ok', $ds->content);
        $this->assertEquals($path, $ds->filePath);
    }

    public function testLoadFromBadJsonFileMustThrowException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.json';
        vfsStream::create(['test.json' => '?']);
        $ds = new DataSource($path);
    }

    public function testLoadFromYaml()
    {
        vfsStream::create(['test.yml' => 'ok']);
        $path = $this->root->url() . '/test.yml';

        $ds = new DataSource($path);
        $this->assertEquals('ok', $ds->content);
        $this->assertEquals($path, $ds->filePath);
    }

    public function testLoadFromBadYamlFileMustThrowException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.yml';
        vfsStream::create(['test.yml' => "'''"]);
        $ds = new DataSource($path);
    }

    public function testLoadFromNotAFileMustThrowException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.php';
        $this->assertFalse($this->root->hasChild('test.php'));

        $ds = new DataSource($path);
    }

    public function testLoadFromUnknownFormatMustThrowException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.unknown' => 'ok']);
        $path = $this->root->url() . '/test.unknown';

        $ds = new DataSource($path);
    }
}
