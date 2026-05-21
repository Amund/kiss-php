<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class RouteBuilderTest extends TestCase
{
    use RmDirTrait;

    public function testRemoveStaleFilesDeletesKnownFiles()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-routebuilder-' . uniqid();
        mkdir($dir, 0777, true);
        $dist = $dir . '/dist';
        mkdir($dist, 0777, true);
        touch($dist . '/stale.html');
        touch($dist . '/keep.html');

        $log = $this->createStub(Log::class);
        $tree = $this->createStub(DataTree::class);
        $twig = $this->createStub(\Twig\Environment::class);

        $builder = new RouteBuilder($tree, $twig, $log, $dist, $dir . '/data');

        $builder->removeStaleFiles($dist, ['stale.html'], $dist);

        $this->assertFileDoesNotExist($dist . '/stale.html');
        $this->assertFileExists($dist . '/keep.html');

        $this->rmDir($dir);
    }

    public function testRemoveStaleFilesSkipsMissingFiles()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-routebuilder-' . uniqid();
        mkdir($dir, 0777, true);
        $dist = $dir . '/dist';
        mkdir($dist, 0777, true);

        $log = $this->createStub(Log::class);
        $tree = $this->createStub(DataTree::class);
        $twig = $this->createStub(\Twig\Environment::class);

        $builder = new RouteBuilder($tree, $twig, $log, $dist, $dir . '/data');

        $builder->removeStaleFiles($dist, ['nonexistent.html'], $dist);

        $this->assertDirectoryExists($dist);
        $this->rmDir($dir);
    }

    public function testBuildAndLogReturnsGeneratedPaths()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-routebuilder-' . uniqid();
        mkdir($dir . '/template', 0777, true);
        file_put_contents($dir . '/template/page.twig', 'Hello {{ name }}');

        $log = new Log(['log' => ['verbose' => false]]);
        $log->silent = true;

        $twigLoader = new \Twig\Loader\FilesystemLoader($dir . '/template');
        $twig = new \Twig\Environment($twigLoader, ['debug' => false]);

        $tree = new DataTree(['cache' => $dir . '/cache']);

        $builder = new RouteBuilder($tree, $twig, $log, $dir . '/dist', $dir . '/data');
        $route = $this->createRoute($dir, 'page');

        $generated = $builder->buildAndLog($route);

        $this->assertCount(1, $generated);
        $this->assertSame('hello.html', $generated[0]);
        $this->assertFileExists($dir . '/dist/hello.html');
        $this->assertStringContainsString('Hello World', file_get_contents($dir . '/dist/hello.html'));

        $this->rmDir($dir);
    }

    private function createRoute(string $dir, string $name): Route
    {
        $path = $dir . '/route';
        mkdir($path, 0777, true);
        file_put_contents(
            $path . '/' . $name . '.yml',
            "path: /{slug}.html\ntemplate: $name.twig\ndata:\n  - slug: hello\n    name: World"
        );
        $ds = new DataSource($path . '/' . $name . '.yml');
        return new Route($name, $ds);
    }
}
