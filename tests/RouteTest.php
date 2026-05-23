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
            'items' => [['a' => '1'], ['a' => '2'], ['a' => '3']],
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
            'items' => [
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
            'items' => ['$ref' => $fixturesDir . '/data.php'],
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

    public function testGetPagesWithParamsButItemsIsNotArrayMustThrowException()
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('items must be an array of arrays');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'items' => 'not-an-array',
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithParamsButItemIsNotArrayMustThrowException()
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('items[0] must be an array');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'items' => ['not-an-array'],
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithParamsButItemMissingKeyMustThrowException()
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('items[0] is missing parameter');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{a}.html',
            'template' => 'my-template',
            'items' => [['b' => '1']],
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetResolvedDataReturnsRawData(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'my-template',
            'data' => [
                ['slug' => 'a', 'title' => 'A'],
                ['slug' => 'b', 'title' => 'B'],
            ],
        ];

        $route = new Route('test', $ds);
        $this->assertEquals([
            ['slug' => 'a', 'title' => 'A'],
            ['slug' => 'b', 'title' => 'B'],
        ], $route->getResolvedData());
    }

    public function testGetResolvedDataReturnsNullWhenNoData(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/about',
            'template' => 'page.twig',
        ];

        $route = new Route('test', $ds);
        $this->assertNull($route->getResolvedData());
    }

    public function testGetResolvedDataResolvesRefWithTree(): void
    {
        $fixturesDir = sys_get_temp_dir() . '/kiss-test-route-' . uniqid();
        mkdir($fixturesDir, 0777, true);
        $this->tempDirs[] = $fixturesDir;
        file_put_contents(
            $fixturesDir . '/data.php',
            '<?php return [["slug" => "a", "title" => "A"]];'
        );

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'my-template',
            'data' => ['$ref' => $fixturesDir . '/data.php'],
        ];

        $cacheDir = sys_get_temp_dir() . '/kiss-test-route-cache-' . uniqid();
        $this->tempDirs[] = $cacheDir;
        $tree = new DataTree(['cache' => $cacheDir]);
        $route = new Route('test', $ds);
        $data = $route->getResolvedData($tree);
        $this->assertEquals([
            ['slug' => 'a', 'title' => 'A'],
        ], $data);
        $tree->clear();
    }

    public function testGetPagesWithItemsNoParams(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'data' => ['title' => 'Mon Blog'],
            'items' => [
                ['slug' => 'a', 'title' => 'A'],
                ['slug' => 'b', 'title' => 'B'],
            ],
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(1, $pages);
        $this->assertArrayHasKey('blog', $pages);
        $this->assertSame('Mon Blog', $pages['blog']['title']);
        $this->assertCount(2, $pages['blog']['items']);
        $this->assertSame('A', $pages['blog']['items'][0]['title']);
    }

    public function testGetPagesWithItemsAndParams(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'post.twig',
            'data' => ['blog_title' => 'Mon Blog'],
            'items' => [
                ['slug' => 'hello', 'title' => 'Hello'],
                ['slug' => 'world', 'title' => 'World'],
            ],
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(2, $pages);

        $this->assertArrayHasKey('hello.html', $pages);
        $this->assertSame('Hello', $pages['hello.html']['title']);
        $this->assertSame('Mon Blog', $pages['hello.html']['blog_title']);

        $this->assertArrayHasKey('world.html', $pages);
        $this->assertSame('World', $pages['world.html']['title']);
        $this->assertSame('Mon Blog', $pages['world.html']['blog_title']);
    }

    public function testGetPagesWithItemsAndParamsItemOverridesMeta(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'post.twig',
            'data' => ['title' => 'Archive', 'blog_title' => 'Mon Blog'],
            'items' => [
                ['slug' => 'hello', 'title' => 'Hello'],
            ],
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(1, $pages);
        $this->assertSame('Hello', $pages['hello.html']['title']);
        $this->assertSame('Mon Blog', $pages['hello.html']['blog_title']);
    }

    public function testGetResolvedDataWithItems(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'post.twig',
            'data' => ['title' => 'Mon Blog'],
            'items' => [
                ['slug' => 'hello', 'title' => 'Hello'],
            ],
        ];

        $route = new Route('test', $ds);
        $result = $route->getResolvedData();

        $this->assertIsArray($result);
        $this->assertSame('Mon Blog', $result['title']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Hello', $result['items'][0]['title']);
    }

    public function testGetResolvedDataWithItemsNoData(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'post.twig',
            'items' => [
                ['slug' => 'hello', 'title' => 'Hello'],
            ],
        ];

        $route = new Route('test', $ds);
        $result = $route->getResolvedData();

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('title', $result);
        $this->assertCount(1, $result['items']);
    }

    public function testGetPagesWithItemsAndPagination(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'data' => ['title' => 'Mon Blog'],
            'items' => [
                ['slug' => 'a', 'title' => 'A'],
                ['slug' => 'b', 'title' => 'B'],
                ['slug' => 'c', 'title' => 'C'],
            ],
            'paginate' => 2,
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(2, $pages);

        $this->assertArrayHasKey('blog/index.html', $pages);
        $this->assertSame('Mon Blog', $pages['blog/index.html']['title']);
        $this->assertCount(2, $pages['blog/index.html']['items']);
        $this->assertSame(1, $pages['blog/index.html']['page']);

        $this->assertArrayHasKey('blog/page/2/index.html', $pages);
        $this->assertSame('Mon Blog', $pages['blog/page/2/index.html']['title']);
        $this->assertCount(1, $pages['blog/page/2/index.html']['items']);
        $this->assertSame(2, $pages['blog/page/2/index.html']['page']);
    }

    public function testGetPagesWithPagination(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'items' => [
                ['slug' => 'a', 'title' => 'A'],
                ['slug' => 'b', 'title' => 'B'],
                ['slug' => 'c', 'title' => 'C'],
                ['slug' => 'd', 'title' => 'D'],
                ['slug' => 'e', 'title' => 'E'],
            ],
            'paginate' => 2,
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(3, $pages);

        $this->assertArrayHasKey('blog/index.html', $pages);
        $this->assertSame(1, $pages['blog/index.html']['page']);
        $this->assertCount(2, $pages['blog/index.html']['items']);
        $this->assertSame('A', $pages['blog/index.html']['items'][0]['title']);
        $this->assertSame(3, $pages['blog/index.html']['totalPages']);
        $this->assertNull($pages['blog/index.html']['prevPage']);
        $this->assertSame(2, $pages['blog/index.html']['nextPage']);
        $this->assertTrue($pages['blog/index.html']['first']);
        $this->assertFalse($pages['blog/index.html']['last']);

        $this->assertArrayHasKey('blog/page/2/index.html', $pages);
        $this->assertSame(2, $pages['blog/page/2/index.html']['page']);
        $this->assertCount(2, $pages['blog/page/2/index.html']['items']);
        $this->assertSame('C', $pages['blog/page/2/index.html']['items'][0]['title']);
        $this->assertSame(1, $pages['blog/page/2/index.html']['prevPage']);
        $this->assertSame(3, $pages['blog/page/2/index.html']['nextPage']);
        $this->assertFalse($pages['blog/page/2/index.html']['first']);
        $this->assertFalse($pages['blog/page/2/index.html']['last']);

        $this->assertArrayHasKey('blog/page/3/index.html', $pages);
        $this->assertSame(3, $pages['blog/page/3/index.html']['page']);
        $this->assertCount(1, $pages['blog/page/3/index.html']['items']);
        $this->assertSame('E', $pages['blog/page/3/index.html']['items'][0]['title']);
        $this->assertSame(2, $pages['blog/page/3/index.html']['prevPage']);
        $this->assertNull($pages['blog/page/3/index.html']['nextPage']);
        $this->assertFalse($pages['blog/page/3/index.html']['first']);
        $this->assertTrue($pages['blog/page/3/index.html']['last']);
    }

    public function testGetPagesWithPaginationSinglePage(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'items' => [
                ['slug' => 'a', 'title' => 'A'],
            ],
            'paginate' => 5,
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(1, $pages);
        $this->assertArrayHasKey('blog/index.html', $pages);
        $this->assertSame(1, $pages['blog/index.html']['page']);
        $this->assertCount(1, $pages['blog/index.html']['items']);
        $this->assertNull($pages['blog/index.html']['prevPage']);
        $this->assertNull($pages['blog/index.html']['nextPage']);
        $this->assertTrue($pages['blog/index.html']['first']);
        $this->assertTrue($pages['blog/index.html']['last']);
    }

    public function testGetPagesWithPaginationEmptyItemsReturnsNoPages(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'items' => [],
            'paginate' => 5,
        ];

        $route = new Route('test', $ds);
        $pages = $route->getPages();

        $this->assertCount(0, $pages);
    }

    public function testGetPagesWithPaginationAndItemsNotArrayThrows(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('items must be an array when paginate is set');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'items' => 'not-an-array',
            'paginate' => 5,
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithPaginationAndPathParamsThrows(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('paginate and path parameters cannot be combined');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/{slug}.html',
            'template' => 'post.twig',
            'items' => [['slug' => 'a', 'title' => 'A']],
            'paginate' => 5,
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testGetPagesWithPaginationAndFileExtensionThrows(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage('paginate requires a path without file extension');

        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog.html',
            'template' => 'blog.twig',
            'items' => [['slug' => 'a']],
            'paginate' => 3,
        ];

        $route = new Route('test', $ds);
        $route->getPages();
    }

    public function testRouteWithPaginateProperty(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/blog',
            'template' => 'blog.twig',
            'data' => [['slug' => 'a']],
            'paginate' => 3,
        ];

        $route = new Route('test', $ds);
        $this->assertSame(3, $route->paginate);
    }

    public function testRouteWithoutPaginateIsNull(): void
    {
        $ds = $this->createStub(DataSource::class);
        $ds->content = (object) [
            'path' => '/about',
            'template' => 'page.twig',
        ];

        $route = new Route('test', $ds);
        $this->assertNull($route->paginate);
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
