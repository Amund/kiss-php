<?php

namespace Kiss;

use Kiss\DataSource;
use Kiss\DataSource\PhpLoader;
use Kiss\DataSource\JsonLoader;
use Kiss\DataSource\YamlLoader;
use Kiss\DataSource\IniLoader;
use Kiss\DataSource\XmlLoader;
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

    public function testPhpLoaderPublicMethods()
    {
        $path = sys_get_temp_dir() . '/kiss-test-loader-php-' . uniqid() . '.php';
        file_put_contents($path, '<?php return "data";');

        $loader = new PhpLoader($path);
        $loader->load();
        $this->assertSame($path, $loader->filePath());
        $this->assertSame('data', $loader->content());

        unlink($path);
    }

    public function testJsonLoaderPublicMethods()
    {
        $path = sys_get_temp_dir() . '/kiss-test-loader-json-' . uniqid() . '.json';
        file_put_contents($path, '"data"');

        $loader = new JsonLoader($path);
        $loader->load();
        $this->assertSame($path, $loader->filePath());
        $this->assertSame('data', $loader->content());

        unlink($path);
    }

    public function testYamlLoaderPublicMethods()
    {
        $path = sys_get_temp_dir() . '/kiss-test-loader-yaml-' . uniqid() . '.yml';
        file_put_contents($path, 'data');

        $loader = new YamlLoader($path);
        $loader->load();
        $this->assertSame($path, $loader->filePath());
        $this->assertSame('data', $loader->content());

        unlink($path);
    }

    public function testLoadFromXml()
    {
        vfsStream::create(['test.xml' => '<?xml version="1.0"?><root><item>ok</item></root>']);
        $path = $this->root->url() . '/test.xml';

        $ds = new DataSource($path);
        $this->assertEquals(['item' => 'ok'], $ds->content);
        $this->assertEquals($path, $ds->filePath);
    }

    public function testLoadFromBadXmlFileMustThrowException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.xml' => 'not xml']);
        $path = $this->root->url() . '/test.xml';

        $ds = new DataSource($path);
    }

    public function testLoadFromIni()
    {
        vfsStream::create([
            'test.ini' => <<<INI
greeting = hello
[section]
key = value
INI
        ]);
        $path = $this->root->url() . '/test.ini';

        $ds = new DataSource($path);
        $this->assertEquals([
            'greeting' => 'hello',
            'section' => ['key' => 'value'],
        ], $ds->content);
        $this->assertEquals($path, $ds->filePath);
    }

    public function testLoadFromBadIniFileMustThrowException()
    {
        $this->expectException(KissException::class);

        vfsStream::create(['test.ini' => "  = invalid"]);
        $path = $this->root->url() . '/test.ini';

        $ds = new DataSource($path);
    }

    public function testSaveXml()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-xml-' . uniqid() . '.xml';

        DataSource::saveXml($path, ['item' => 'value', 'nested' => ['key' => 'val']]);
        $this->assertFileExists($path);

        $loaded = simplexml_load_file($path);
        $this->assertEquals('value', (string) $loaded->item);
        $this->assertEquals('val', (string) $loaded->nested->key);

        unlink($path);
    }

    public function testSaveIni()
    {
        $path = sys_get_temp_dir() . '/kiss-test-save-ini-' . uniqid() . '.ini';

        DataSource::saveIni($path, [
            'greeting' => 'hello',
            'section' => ['key' => 'value'],
        ]);
        $this->assertFileExists($path);

        $loaded = parse_ini_file($path, true);
        $this->assertEquals('hello', $loaded['greeting']);
        $this->assertEquals('value', $loaded['section']['key']);

        unlink($path);
    }

    public function testIniLoaderPublicMethods()
    {
        $path = sys_get_temp_dir() . '/kiss-test-loader-ini-' . uniqid() . '.ini';
        file_put_contents($path, "key = value\n[section]\nfoo = bar");

        $loader = new IniLoader($path);
        $loader->load();
        $this->assertSame($path, $loader->filePath());
        $this->assertSame(['key' => 'value', 'section' => ['foo' => 'bar']], $loader->content());

        unlink($path);
    }

    public function testXmlLoaderPublicMethods()
    {
        $path = sys_get_temp_dir() . '/kiss-test-loader-xml-' . uniqid() . '.xml';
        file_put_contents($path, '<?xml version="1.0"?><root><item>data</item></root>');

        $loader = new XmlLoader($path);
        $loader->load();
        $this->assertSame($path, $loader->filePath());
        $this->assertSame(['item' => 'data'], $loader->content());

        unlink($path);
    }
}
