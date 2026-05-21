<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class KissTest extends TestCase
{
    use RmDirTrait;

    private $tmpDirs = [];

    public function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            if (is_dir($dir)) {
                $this->rmDir($dir);
            }
        }
        $this->tmpDirs = [];
    }

    public function testConstruct()
    {
        $kiss = new Kiss();
        $expected = json_decode(file_get_contents('composer.json'), true)['version'];
        $this->assertSame($expected, $kiss->getVersion());
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
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertFileExists($dir . '/kiss.yml');
        $this->assertInstanceOf(Log::class, $kiss->log);
    }

    public function testConfigLoadsExistingFile()
    {
        $dir = $this->makeTempDir();
        file_put_contents($dir . '/kiss.yml', "debug: true\n");

        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertSame(true, $kiss->config['debug']);
    }

    public function testGetWatchesReturnsDefaultDirs()
    {
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();

        $watches = $kiss->getWatches();
        $this->assertStringContainsString('kiss.yml', $watches);
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

    public function testVersionOutputsKiss()
    {
        $this->expectOutputRegex('/Kiss/');

        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->version();
    }

    public function testHelpOutputsBuild()
    {
        $this->expectOutputRegex('/build/');

        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->help();
    }

    public function testErrorThrowsKissException()
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('test error');

        $kiss = new Kiss();
        $kiss->error('test error');
    }

    public function testImgOutputsTodo()
    {
        $this->expectOutputRegex('/TODO/');

        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->img();
    }

    public function testResetAllRemovesDistAndCache()
    {
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        $dist = $kiss->config['path']['dist'];
        $cache = $kiss->config['path']['cache'];

        $this->assertDirectoryExists($dist);
        $this->assertDirectoryExists($cache);

        $kiss->reset('all');

        $this->assertDirectoryDoesNotExist($dist);
        $this->assertDirectoryDoesNotExist($cache);
    }

    public function testResetWithInvalidArgThrows()
    {
        $this->expectException(KissException::class);

        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->reset('invalid');
    }

    public function testResetDistOnly()
    {
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        $dist = $kiss->config['path']['dist'];
        $cache = $kiss->config['path']['cache'];

        $kiss->reset('dist');

        $this->assertDirectoryDoesNotExist($dist);
        $this->assertDirectoryExists($cache);
    }

    public function testResetCacheOnly()
    {
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        $dist = $kiss->config['path']['dist'];
        $cache = $kiss->config['path']['cache'];

        $kiss->reset('cache');

        $this->assertDirectoryExists($dist);
        $this->assertDirectoryDoesNotExist($cache);
    }

    public function testWarmupCreatesDirectories()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        $this->assertDirectoryExists($kiss->config['path']['copy']);
        $this->assertDirectoryExists($kiss->config['path']['data']);
        $this->assertDirectoryExists($kiss->config['path']['route']);
        $this->assertDirectoryExists($kiss->config['path']['dist']);
        $this->assertDirectoryExists($kiss->config['path']['cache']);
        $this->assertDirectoryExists($kiss->config['path']['template']);
    }

    public function testCopyOutputsResult()
    {
        $this->expectOutputRegex('/✓/');

        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->copy();
    }

    public function testCopySyncsFiles()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/test.txt', 'hello');

        $kiss->copy();

        $this->assertFileExists($kiss->config['path']['dist'] . '/test.txt');
        $this->assertSame('hello', file_get_contents($kiss->config['path']['dist'] . '/test.txt'));
    }

    public function testBuildRunsFullPipeline()
    {
        $dir = $this->makeTempDir();
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/static.txt', 'static');

        $kiss->build();

        $this->assertFileExists($kiss->config['path']['dist'] . '/static.txt');
    }

    public function testRouteWithNoRoutesOutputsMessage()
    {
        $this->expectOutputRegex('/no routes to build/');

        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->route();
    }

    public function testWatchedWithDataDirCallsRoute()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/kiss.yml', "debug: true\n");

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        $dataDir = $kiss->config['path']['data'];
        file_put_contents($dataDir . '/test.yaml', "key: value\n");

        $kiss->watched($dataDir . '/test.yaml');

        $this->assertDirectoryExists($kiss->config['path']['dist']);
    }

    public function testConfigWithEnvDebugOverrides()
    {
        $dir = $this->makeTempDir();
        $_ENV['KISS_DEBUG'] = 'true';
        file_put_contents($dir . '/kiss.yml', "debug: false\n");

        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertSame(true, $kiss->config['debug']);
        unset($_ENV['KISS_DEBUG']);
    }

    public function testConfigWithEnvVerboseOverrides()
    {
        $dir = $this->makeTempDir();
        $_ENV['KISS_VERBOSE'] = 'true';
        file_put_contents($dir . '/kiss.yml', "log:\n  verbose: false\n");

        $kiss = new Kiss($dir);
        $kiss->config();

        $this->assertSame(true, $kiss->config['log']['verbose']);
        unset($_ENV['KISS_VERBOSE']);
    }

    public function testCopySingleFile()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/single.txt', 'single');
        $kiss->copy('single.txt');

        $this->assertFileExists($kiss->config['path']['dist'] . '/single.txt');
        $this->assertSame('single', file_get_contents($kiss->config['path']['dist'] . '/single.txt'));
    }

    public function testCopyRemovesDeletedFile()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/gone.txt', 'gone');
        $kiss->copy('gone.txt');
        $this->assertFileExists($kiss->config['path']['dist'] . '/gone.txt');

        unlink($kiss->config['path']['copy'] . '/gone.txt');
        $kiss->copy('gone.txt');

        $this->assertFileDoesNotExist($kiss->config['path']['dist'] . '/gone.txt');
    }

    public function testWatchedWithCopyFileSyncs()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/watch.txt', 'watch');
        $kiss->watched($kiss->config['path']['copy'] . '/watch.txt');

        $this->assertFileExists($kiss->config['path']['dist'] . '/watch.txt');
    }

    public function testWatchedWithTemplateDirCallsRoute()
    {
        $this->expectOutputRegex('/no matching routes/');

        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();

        $kiss->watched($kiss->config['path']['template'] . '/page.twig');
    }

    public function testWatchedWithConfigEntryBuilds()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents($kiss->config['path']['copy'] . '/built.txt', 'built');
        $kiss->watched('kiss.yml');

        $this->assertFileExists($kiss->config['path']['dist'] . '/built.txt');
    }

    public function testRouteGeneratesPages()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', 'Hello {{ name }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/page.yml',
            "path: /hello.html\ntemplate: page.twig\ndata:\n  name: World"
        );

        $kiss->route();

        $dist = $kiss->config['path']['dist'];
        $this->assertFileExists($dist . '/hello.html');
        $this->assertStringContainsString(
            'Hello World',
            file_get_contents($dist . '/hello.html')
        );
    }

    public function testRouteWithPathParams()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/post.twig', '{{ title }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n" .
            "  - slug: hello\n    title: Hello\n  - slug: world\n    title: World"
        );

        $kiss->route();

        $dist = $kiss->config['path']['dist'];
        $this->assertFileExists($dist . '/hello.html');
        $this->assertFileExists($dist . '/world.html');
        $this->assertStringContainsString(
            'Hello',
            file_get_contents($dist . '/hello.html')
        );
    }

    public function testRouteWithGlobalDataInjectsGlobal()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents(
            $dir . '/template/page.twig',
            '{{ global.site.name }} - {{ title }}'
        );

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['data'] . '/site.yml',
            "name: MySite"
        );
        file_put_contents(
            $kiss->config['path']['route'] . '/page.yml',
            "path: /index.html\ntemplate: page.twig\ndata:\n  title: Home"
        );

        $kiss->route();

        $dist = $kiss->config['path']['dist'];
        $this->assertFileExists($dist . '/index.html');
        $this->assertStringContainsString(
            'MySite - Home',
            file_get_contents($dist . '/index.html')
        );
    }

    public function testRouteWithDataRefResolvesReference()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', '{{ name }}');

        $refDir = sys_get_temp_dir() . '/kiss-ref-data-' . uniqid();
        mkdir($refDir, 0777, true);
        $this->tmpDirs[] = $refDir;
        file_put_contents(
            $refDir . '/people.yml',
            "- name: Alice\n- name: Bob"
        );

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/page.yml',
            "path: /{name}.html\ntemplate: page.twig\ndata:\n  \$ref: " . $refDir . "/people.yml"
        );

        $kiss->route();

        $dist = $kiss->config['path']['dist'];
        $this->assertFileExists($dist . '/alice.html');
        $this->assertFileExists($dist . '/bob.html');
        $this->assertStringContainsString(
            'Alice',
            file_get_contents($dist . '/alice.html')
        );
    }

    public function testRouteManifestCleansStaleFiles()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', '{{ name }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/a.yml',
            "path: /a.html\ntemplate: page.twig\ndata:\n  name: A"
        );
        file_put_contents(
            $kiss->config['path']['route'] . '/b.yml',
            "path: /b.html\ntemplate: page.twig\ndata:\n  name: B"
        );

        $kiss->route();
        $dist = $kiss->config['path']['dist'];
        $this->assertFileExists($dist . '/a.html');
        $this->assertFileExists($dist . '/b.html');

        unlink($kiss->config['path']['route'] . '/b.yml');

        $kiss->route();

        $this->assertFileExists($dist . '/a.html');
        $this->assertFileDoesNotExist($dist . '/b.html');
    }

    public function testGetWatchesReturnsWatchableDirs()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        mkdir($dir . '/copy', 0777, true);
        mkdir($dir . '/data', 0777, true);
        mkdir($dir . '/route', 0777, true);

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();

        $watches = $kiss->getWatches();

        $this->assertStringContainsString('kiss.yml', $watches);
        $this->assertStringContainsString('copy', $watches);
        $this->assertStringContainsString('data', $watches);
        $this->assertStringContainsString('route', $watches);
        $this->assertStringContainsString('template', $watches);
    }

    public function testWatchedConfigChangeRebuilds()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', 'Hello {{ name }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/page.yml',
            "path: /hello.html\ntemplate: page.twig\ndata:\n  name: World"
        );

        $kiss->watched($kiss->entry);

        $this->assertFileExists($kiss->config['path']['dist'] . '/hello.html');
    }

    public function testWatchedRouteChangeRebuildsSingleRoute()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/a.twig', '{{ name }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['route'] . '/a.yml',
            "path: /a.html\ntemplate: a.twig\ndata:\n  name: RouteA"
        );

        $kiss->watched($kiss->config['path']['route'] . '/a.yml');

        $this->assertFileExists($kiss->config['path']['dist'] . '/a.html');
        $this->assertStringContainsString(
            'RouteA',
            file_get_contents($kiss->config['path']['dist'] . '/a.html')
        );
    }

    public function testWatchedDataChangeRebuildsAllRoutes()
    {
        $dir = $this->makeTempDir();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', '{{ global.site.name }} - {{ title }}');

        $kiss = new Kiss($dir);
        $kiss->config();
        $kiss->warmup();
        $kiss->log->silent = true;

        file_put_contents(
            $kiss->config['path']['data'] . '/site.yml',
            "name: MySite"
        );
        file_put_contents(
            $kiss->config['path']['route'] . '/page.yml',
            "path: /index.html\ntemplate: page.twig\ndata:\n  title: Home"
        );

        $kiss->watched($kiss->config['path']['data'] . '/site.yml');

        $this->assertFileExists($kiss->config['path']['dist'] . '/index.html');
        $this->assertStringContainsString(
            'MySite',
            file_get_contents($kiss->config['path']['dist'] . '/index.html')
        );
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/kiss-test-' . uniqid();
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;
        return $dir;
    }
}
