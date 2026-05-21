<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    private $tempDirs = [];

    public function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*'));
                rmdir($dir);
            }
        }
        $this->tempDirs = [];
    }

    public function testLoadFromPhp()
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/test.html',
            'template' => 'test',
            'data' => 'my-data',
        ];

        $route = new Route('test', $ds);
        $this->assertSame('test', $route->name);
        $this->assertSame('/test.html', $route->path);
        $this->assertSame('test', $route->template);
        $this->assertSame('my-data', $route->data);
    }

    public function testLoadFromPhpWithoutData()
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/test.html',
            'template' => 'test',
        ];

        $route = new Route('test', $ds);
        $this->assertNull($route->data);
    }

    public function testMissingPathMustThrowException()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'type' => 'page',
            'template' => 'test',
        ];

        new Route('test', $ds);
    }

    public function testMissingTemplateMustThrowException()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/test.html',
        ];

        new Route('test', $ds);
    }

    public function testGetPagesWithoutParams()
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/foo/bar/baz.html',
            'template' => 'my-template',
            'data' => 'my-data',
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();
        $this->assertEquals(['foo/bar/baz.html' => 'my-data'], $pages);
    }

    public function testGetPagesWithParams1()
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'data' => [['a' => '1'], ['a' => '2'], ['a' => '3']],
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();
        $this->assertEquals([
            '1.html' => ['a' => '1'],
            '2.html' => ['a' => '2'],
            '3.html' => ['a' => '3'],
        ], $pages);
    }

    public function testGetPagesWithParams2()
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{category}/{slug}-{id}.html',
            'template' => 'my-template',
            'data' => [
                [
                    'id' => 1,
                    'slug' => 'my-first-article',
                    'category' => 'news',
                    'title' => 'My first article',
                ],
                [
                    'id' => 2,
                    'slug' => 'my-other-article',
                    'category' => 'events',
                    'title' => 'My other article',
                ],
            ],
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();
        $this->assertEquals([
            'news/my-first-article-1.html' => [
                'id' => 1,
                'slug' => 'my-first-article',
                'category' => 'news',
                'title' => 'My first article',
            ],
            'events/my-other-article-2.html' => [
                'id' => 2,
                'slug' => 'my-other-article',
                'category' => 'events',
                'title' => 'My other article',
            ],
        ], $pages);
    }

    public function testGetPagesWithDataTreeResolvesRef()
    {
        $fixturesDir = sys_get_temp_dir() . '/kiss-test-route-' . uniqid();
        mkdir($fixturesDir, 0777, true);
        $this->tempDirs[] = $fixturesDir;
        file_put_contents(
            $fixturesDir . '/data.php',
            '<?php return [["a" => "1"], ["a" => "2"]];'
        );

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'data' => ['$ref' => $fixturesDir . '/data.php'],
        ];

        $cacheDir = sys_get_temp_dir() . '/kiss-test-route-cache-' . uniqid();
        $this->tempDirs[] = $cacheDir;
        $tree = new DataTree(['cache' => $cacheDir]);
        $route = new Route('test', $ds);
        $pages = $route->getPages($tree);
        $this->assertEquals([
            '1.html' => ['a' => '1'],
            '2.html' => ['a' => '2'],
        ], $pages);
        $tree->clear();
    }

    public function testGetPagesWithParamsButDataIsNotArrayMustThrowException()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'data' => 'not-an-array',
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithParamsButDataItemIsNotArrayMustThrowException()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'data' => ['not-an-array'],
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithParamsButDataItemMissingKeyMustThrowException()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'data' => [['b' => '1']],
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testPathMustStartWithSlash()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => 'no-leading-slash.html',
            'template' => 'tpl',
        ];

        new Route('test', $ds);
    }

    public function testPathMustNotEndWithSlash()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/trailing-slash/',
            'template' => 'tpl',
        ];

        new Route('test', $ds);
    }

    public function testPathMustOnlyContainValidChars()
    {
        $this->expectException(KissException::class);

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/bad chars.html',
            'template' => 'tpl',
        ];

        new Route('test', $ds);
    }

    public function testPathWithValidPatterns()
    {
        $paths = [
            '/simple.html',
            '/blog/{slug}.html',
            '/{category}/{id}-{slug}',
            '/about',
            '/some/deep/path.html',
        ];

        foreach ($paths as $path) {
            $ds = $this->createStub(DataSource::class);
            $ds->content = (object) [
                'type' => 'page',
                'path' => $path,
                'template' => 'tpl',
            ];

            $route = new Route('test', $ds);
            $this->assertSame($path, $route->path);
        }
    }
}
