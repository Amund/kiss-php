<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class DistCleanerTest extends TestCase
{
    use RmDirTrait;

    public function testCleanRemovesOrphanedFiles()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-distcleaner-' . uniqid();
        mkdir($dir, 0777, true);
        $cache = $dir . '/cache';
        $dist = $dir . '/dist';
        mkdir($cache, 0777, true);
        mkdir($dist, 0777, true);

        touch($dist . '/known.txt');
        touch($dist . '/orphan.txt');

        DataSource::savePhp($cache . '/copy-manifest.php', [
            'known.txt' => 'abc123',
        ]);

        $log = $this->createStub(Log::class);

        $cleaner = new DistCleaner($cache, $dist, $log);
        $cleaner->clean();

        $this->assertFileExists($dist . '/known.txt');
        $this->assertFileDoesNotExist($dist . '/orphan.txt');

        $this->rmDir($dir);
    }

    public function testCleanRemovesEmptyDirsAfterRemovingOrphans()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-distcleaner-' . uniqid();
        mkdir($dir, 0777, true);
        $cache = $dir . '/cache';
        $dist = $dir . '/dist';
        mkdir($cache, 0777, true);
        mkdir($dist . '/subdir', 0777, true);

        touch($dist . '/subdir/orphan.txt');

        $log = $this->createStub(Log::class);

        $cleaner = new DistCleaner($cache, $dist, $log);
        $cleaner->clean();

        $this->assertDirectoryDoesNotExist($dist . '/subdir');

        $this->rmDir($dir);
    }

    public function testCleanWithNoManifestRemovesAllFiles()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-distcleaner-' . uniqid();
        mkdir($dir, 0777, true);
        $cache = $dir . '/cache';
        $dist = $dir . '/dist';
        mkdir($cache, 0777, true);
        mkdir($dist, 0777, true);

        touch($dist . '/file1.txt');
        touch($dist . '/file2.txt');

        $log = $this->createStub(Log::class);

        $cleaner = new DistCleaner($cache, $dist, $log);
        $cleaner->clean();

        $this->assertFileDoesNotExist($dist . '/file1.txt');
        $this->assertFileDoesNotExist($dist . '/file2.txt');

        $this->rmDir($dir);
    }

    public function testCleanWithRouteManifestKeepsKnownFiles()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-distcleaner-' . uniqid();
        mkdir($dir, 0777, true);
        $cache = $dir . '/cache';
        $dist = $dir . '/dist';
        mkdir($cache, 0777, true);
        mkdir($dist, 0777, true);

        touch($dist . '/index.html');
        touch($dist . '/orphan.html');

        DataSource::savePhp($cache . '/route-manifest.php', [
            'page' => ['index.html'],
        ]);

        $log = $this->createStub(Log::class);

        $cleaner = new DistCleaner($cache, $dist, $log);
        $cleaner->clean();

        $this->assertFileExists($dist . '/index.html');
        $this->assertFileDoesNotExist($dist . '/orphan.html');

        $this->rmDir($dir);
    }
}
