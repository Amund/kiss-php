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
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testLoadFromJson()
    {
        vfsStream::create([
            'test.json' => '"ok"',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.json';
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testLoadFromYaml()
    {
        vfsStream::create([
            'test.yml' => 'ok',
        ]);
        $ds = new DataSource();
        $path = $this->root->url() . '/test.yml';
        $this->assertEquals('ok', $ds->load($path));
    }

    public function testLoadFromNotAFileMustThrownException()
    {
        $this->expectException(KissException::class);

        $path = $this->root->url() . '/test.php';
        $this->assertFalse($this->root->hasChild('test.php'));
        $ds = new DataSource();
        $ds->load($path);
    }

    public function testLoadFromUnknownFormatMustThrownException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.unknown' => 'ok']);
        $path = $this->root->url() . '/test.unknown';
        $ds = new DataSource();
        $ds->load($path);
    }

    public function testSaveToPhp()
    {
        $ds = new DataSource();
        $path = $this->root->url() . '/test.php';
        $ds->save($path, 'ok');
        $expected = "<?php return 'ok';";
        $this->assertEquals($expected, \file_get_contents($path));
    }

    public function testSaveToJson()
    {
        $ds = new DataSource();
        $path = $this->root->url() . '/test.json';
        $ds->save($path, 'ok');
        $expected = '"ok"';
        $this->assertEquals($expected, \file_get_contents($path));
    }

    public function testSaveToYaml()
    {
        $ds = new DataSource();
        $path = $this->root->url() . '/test.yml';
        $ds->save($path, 'ok');
        $expected = 'ok';
        $this->assertEquals($expected, \file_get_contents($path));
    }

    public function testSaveToUnknownFormatMustThrownException()
    {
        $this->expectException(KissException::class);

        $ds = new DataSource();
        $path = $this->root->url() . '/test.unknown';
        $ds->save($path, 'ok');
    }
}
