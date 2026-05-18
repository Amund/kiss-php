<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class KissTest extends TestCase
{
    public function testConstruct()
    {
        $kiss = new Kiss();
        $this->assertSame('0.1', $kiss->version);
        $this->assertSame('kiss.yml', $kiss->entry);
    }

    public function testNewTask()
    {
        $kiss = new Kiss();
        $task = $kiss->newTask();
        $this->assertInstanceOf(Task::class, $task);
    }

    public function testConfigCreatesDefaultFile()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-config-' . uniqid();
        mkdir($dir, 0777, true);

        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertFileExists($dir . '/kiss.yml');
        $this->assertInstanceOf(Log::class, $kiss->log);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testConfigLoadsExistingFile()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-config-' . uniqid();
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '/kiss.yml',
            "debug: true\n"
        );

        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertSame(true, $kiss->config['debug']);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testGetWatchesReturnsDefaultDirs()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-watches-' . uniqid();
        mkdir($dir, 0777, true);

        $kiss = new Kiss($dir);
        $kiss->config();

        $watches = $kiss->getWatches();
        $this->assertStringContainsString('kiss.yml', $watches);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testDefaultConfigStructure()
    {
        $kiss = new Kiss();
        $this->assertArrayHasKey('path', $kiss->config);
        $this->assertArrayHasKey('copy', $kiss->config['path']);
        $this->assertArrayHasKey('data', $kiss->config['path']);
        $this->assertArrayHasKey('route', $kiss->config['path']);
        $this->assertArrayHasKey('dist', $kiss->config['path']);
        $this->assertArrayHasKey('cache', $kiss->config['path']);
        $this->assertArrayHasKey('debug', $kiss->config);
    }
}
