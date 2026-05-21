<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    use RmDirTrait;

    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            if (is_dir($dir)) {
                $this->rmDir($dir);
            }
        }
        $this->tmpDirs = [];
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/kiss-test-config-' . uniqid();
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;
        return $dir;
    }

    private function writeConfig(string $dir, string $filename, string $content): void
    {
        file_put_contents($dir . '/' . $filename, $content);
    }

    public function testGetReturnsConfigValue()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.yml', "debug: true\n");

        $config = new Config($dir);
        $config->load();

        $this->assertTrue($config->get('debug'));
    }

    public function testGetReturnsDefaultForMissingKey()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.yml', "debug: true\n");

        $config = new Config($dir);
        $config->load();

        $this->assertNull($config->get('nonexistent'));
        $this->assertSame('fallback', $config->get('nonexistent', 'fallback'));
    }

    public function testLogConfigReturnsLogSubset()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.yml', "log:\n  verbose: true\n");

        $config = new Config($dir);
        $config->load();

        $log = $config->logConfig();
        $this->assertArrayHasKey('verbose', $log);
        $this->assertTrue($log['verbose']);
    }

    public function testToArrayReturnsFullConfig()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.yml', "debug: true\n");

        $config = new Config($dir);
        $config->load();

        $arr = $config->toArray();
        $this->assertArrayHasKey('path', $arr);
        $this->assertArrayHasKey('log', $arr);
        $this->assertTrue($arr['debug']);
    }

    public function testDetectsJsonConfigFile()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.json', json_encode(['debug' => true]));

        $config = new Config($dir);
        $config->load();

        $this->assertTrue($config->get('debug'));
    }

    public function testDetectsPhpConfigFile()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig($dir, 'kiss.php', '<?php return ["debug" => true];');

        $config = new Config($dir);
        $config->load();

        $this->assertTrue($config->get('debug'));
    }

    public function testDetectsXmlConfigFile()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig(
            $dir,
            'kiss.xml',
            '<?xml version="1.0"?><root><custom>xml-value</custom></root>'
        );

        $config = new Config($dir);
        $config->load();

        $this->assertSame('xml-value', $config->get('custom'));
    }

    public function testDetectsIniConfigFile()
    {
        $dir = $this->makeTempDir();
        $this->writeConfig(
            $dir,
            'kiss.ini',
            "custom = ini-value\n"
        );

        $config = new Config($dir);
        $config->load();

        $this->assertSame('ini-value', $config->get('custom'));
    }
}
