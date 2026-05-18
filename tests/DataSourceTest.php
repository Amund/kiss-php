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

    public function testSavePhp()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-php-' . uniqid() . '.php';

        DataSource::savePhp($path, ['foo' => 'bar']);
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString("'foo' => 'bar'", $content);

        $loaded = require $path;
        $this->assertEquals(['foo' => 'bar'], $loaded);

        unlink($path);
    }

    public function testSavePhpWithSource()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-php-src-' . uniqid() . '.php';

        DataSource::savePhp($path, 'hello', 'data/test.yml');
        $content = file_get_contents($path);
        $this->assertStringContainsString('// data/test.yml', $content);

        unlink($path);
    }

    public function testSaveYaml()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-yaml-' . uniqid() . '.yml';

        DataSource::saveYaml($path, ['foo' => 'bar', 'list' => [1, 2]]);
        $this->assertFileExists($path);

        $loaded = \Symfony\Component\Yaml\Yaml::parseFile($path);
        $this->assertEquals(['foo' => 'bar', 'list' => [1, 2]], $loaded);

        unlink($path);
    }

    public function testSaveJson()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-json-' . uniqid() . '.json';

        DataSource::saveJson($path, ['foo' => 'bar']);
        $this->assertFileExists($path);

        $loaded = json_decode(file_get_contents($path), true);
        $this->assertEquals(['foo' => 'bar'], $loaded);

        unlink($path);
    }

    public function testSaveInstanceWritesToOriginalPath()
    {
        $srcPath = sys_get_temp_dir() . '/kiss-test-save-instance-' . uniqid() . '.json';
        file_put_contents($srcPath, '"original"');

        $ds = new DataSource($srcPath);
        $ds->save('modified');

        $this->assertStringEqualsFile($srcPath, "\"modified\"\n");

        unlink($srcPath);
    }

    public function testSaveInstanceWithCustomPath()
    {
        $srcPath = sys_get_temp_dir() . '/kiss-test-save-custom-src-' . uniqid() . '.json';
        $dstPath = sys_get_temp_dir() . '/kiss-test-save-custom-dst-' . uniqid() . '.yml';
        file_put_contents($srcPath, '"original"');

        $ds = new DataSource($srcPath);
        $ds->save(['key' => 'value'], $dstPath);

        $loaded = \Symfony\Component\Yaml\Yaml::parseFile($dstPath);
        $this->assertEquals(['key' => 'value'], $loaded);

        unlink($srcPath);
        unlink($dstPath);
    }
}
