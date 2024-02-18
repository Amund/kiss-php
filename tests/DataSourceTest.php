<?php

namespace Kiss;

use Kiss\DataSource;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Kiss\DataSource\PhpDataSource;
use Kiss\DataSource\JsonDataSource;
use Kiss\DataSource\YamlDataSource;

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

        $ds = DataSource::create($path);
        $this->assertInstanceOf(PhpDataSource::class, $ds);
        $this->assertEquals(null, $ds->content());
        $this->assertEquals('ok', $ds->load());
        $this->assertEquals('ok', $ds->content());
        $this->assertEquals($path, $ds->filePath());
    }

    public function testLoadFromBadPhpFileMustThrownException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.php';

        vfsStream::create(['test.php' => '<?php throw new Exception();']);
        $ds = DataSource::create($path);
        $ds->load();
    }

    public function testLoadFromJson()
    {
        vfsStream::create(['test.json' => '"ok"']);
        $path = $this->root->url() . '/test.json';

        $ds = DataSource::create($path);
        $this->assertInstanceOf(JsonDataSource::class, $ds);
        $this->assertEquals('ok', $ds->load());
    }

    public function testLoadFromBadJsonFileMustThrownException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.json' => '?']);
        $path = $this->root->url() . '/test.json';

        $ds = DataSource::create($path);
        $ds->load();
    }

    public function testLoadFromYaml()
    {
        vfsStream::create(['test.yml' => 'ok']);
        $path = $this->root->url() . '/test.yml';

        $ds = DataSource::create($path);
        $this->assertInstanceOf(YamlDataSource::class, $ds);
        $this->assertEquals('ok', $ds->load());
    }

    public function testLoadFromNotAFileMustThrownException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.php';
        $this->assertFalse($this->root->hasChild('test.php'));

        $ds = DataSource::create($path);
        $ds->load();
    }

    public function testLoadFromUnknownFormatMustThrownException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.unknown' => 'ok']);
        $path = $this->root->url() . '/test.unknown';

        $ds = DataSource::create($path);
        $ds->load();
    }
}
