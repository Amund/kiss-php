<?php

namespace Kiss;

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
        vfsStream::create([
            'test.php' => '<?php return "ok";',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.php';
        $this->assertEquals('ok', $ds->loadFromPhp($path));
    }

    public function testLoadFromJson()
    {
        vfsStream::create([
            'test.json' => '"ok"',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.json';
        $this->assertEquals('ok', $ds->loadFromJson($path));
    }

    public function testLoadFromYaml()
    {
        vfsStream::create([
            'test.yml' => 'ok',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.yml';
        $this->assertEquals('ok', $ds->loadFromYaml($path));
    }

    public function testDetectLoadFromPhp()
    {
        vfsStream::create([
            'test.php' => '<?php return "ok";',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.php';
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testDetectLoadFromJson()
    {
        vfsStream::create([
            'test.json' => '"ok"',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.json';
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testDetectLoadFromYaml()
    {
        vfsStream::create([
            'test.yml' => 'ok',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.yml';
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testExceptionforUnknownFileType()
    {
        $this->expectException(KissException::class);
        vfsStream::create([
            'test.unknown' => 'ok',
        ]);
        $path = $this->root->url() . '/test.unknown';
        $ds = new DataSource();
        $ds->load($path);
    }
}
