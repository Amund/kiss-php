<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class CopySyncTest extends TestCase
{
    private string $sourceDir;
    private string $destDir;
    private string $cacheDir;
    private CopySync $sync;

    public function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/kiss-copy-src-' . uniqid();
        $this->destDir = sys_get_temp_dir() . '/kiss-copy-dst-' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/kiss-copy-cache-' . uniqid();
        mkdir($this->sourceDir, 0777, true);
        mkdir($this->destDir, 0777, true);
        $this->sync = new CopySync($this->cacheDir);
    }

    public function tearDown(): void
    {
        $this->rmDir($this->sourceDir);
        $this->rmDir($this->destDir);
        $this->rmDir($this->cacheDir);
    }

    public function testSyncCopiesAllFiles()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        file_put_contents($this->sourceDir . '/b.txt', 'bbb');

        $this->sync->sync($this->sourceDir, $this->destDir);

        $this->assertFileExists($this->destDir . '/a.txt');
        $this->assertFileExists($this->destDir . '/b.txt');
        $this->assertSame('aaa', file_get_contents($this->destDir . '/a.txt'));
    }

    public function testSyncSkipsUnchangedFiles()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        $this->sync->sync($this->sourceDir, $this->destDir);

        $mtime = filemtime($this->destDir . '/a.txt');
        sleep(1);

        $this->sync->sync($this->sourceDir, $this->destDir);

        $this->assertSame($mtime, filemtime($this->destDir . '/a.txt'));
    }

    public function testSyncDetectsModifiedFiles()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        $this->sync->sync($this->sourceDir, $this->destDir);

        file_put_contents($this->sourceDir . '/a.txt', 'bbb');
        $this->sync->sync($this->sourceDir, $this->destDir);

        $this->assertSame('bbb', file_get_contents($this->destDir . '/a.txt'));
    }

    public function testSyncRemovesStaleFiles()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        $this->sync->sync($this->sourceDir, $this->destDir);
        $this->assertFileExists($this->destDir . '/a.txt');

        unlink($this->sourceDir . '/a.txt');
        $this->sync->sync($this->sourceDir, $this->destDir);

        $this->assertFileDoesNotExist($this->destDir . '/a.txt');
    }

    public function testSyncHandlesSubdirectories()
    {
        mkdir($this->sourceDir . '/sub', 0777, true);
        file_put_contents($this->sourceDir . '/sub/deep.txt', 'deep');

        $this->sync->sync($this->sourceDir, $this->destDir);

        $this->assertFileExists($this->destDir . '/sub/deep.txt');
        $this->assertSame('deep', file_get_contents($this->destDir . '/sub/deep.txt'));
    }

    public function testSyncFileCopiesNewFile()
    {
        file_put_contents($this->sourceDir . '/new.txt', 'new');

        $this->sync->syncFile($this->sourceDir, $this->destDir, 'new.txt');

        $this->assertFileExists($this->destDir . '/new.txt');
        $this->assertSame('new', file_get_contents($this->destDir . '/new.txt'));
    }

    public function testSyncFileUpdatesChangedFile()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        $this->sync->syncFile($this->sourceDir, $this->destDir, 'a.txt');
        $this->assertSame('aaa', file_get_contents($this->destDir . '/a.txt'));

        file_put_contents($this->sourceDir . '/a.txt', 'bbb');
        $this->sync->syncFile($this->sourceDir, $this->destDir, 'a.txt');
        $this->assertSame('bbb', file_get_contents($this->destDir . '/a.txt'));
    }

    public function testRemoveFileRemovesFromDest()
    {
        file_put_contents($this->sourceDir . '/a.txt', 'aaa');
        $this->sync->syncFile($this->sourceDir, $this->destDir, 'a.txt');
        $this->assertFileExists($this->destDir . '/a.txt');

        $this->sync->removeFile($this->destDir, 'a.txt');
        $this->assertFileDoesNotExist($this->destDir . '/a.txt');
    }

    public function testSyncWithEmptySourceDirDoesNothing()
    {
        $this->sync->sync($this->sourceDir, $this->destDir);
        $this->assertDirectoryExists($this->destDir);
        $files = array_diff(scandir($this->destDir), ['.', '..']);
        $this->assertSame([], $files);
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
