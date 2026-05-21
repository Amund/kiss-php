<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    private $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/kiss-test-collection-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    public function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }

    public function testLoadRoutesFromYaml()
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog/{slug}.html\ntemplate: blog.twig\ndata:\n  - slug: hello\n"
        );
        file_put_contents(
            $this->tempDir . '/about.yml',
            "path: /about\ntemplate: page.twig\n"
        );

        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(2, $collection->getCount());
        $this->assertTrue($collection->has('blog'));
        $this->assertTrue($collection->has('about'));

        $routes = $collection->getRoutes();
        $this->assertInstanceOf(Route::class, $routes['blog']);
        $this->assertSame('blog', $routes['blog']->name);
        $this->assertSame('/blog/{slug}.html', $routes['blog']->path);
        $this->assertSame('blog.twig', $routes['blog']->template);
    }

    public function testLoadRoutesFromJson()
    {
        file_put_contents(
            $this->tempDir . '/blog.json',
            '{"path":"/blog/{slug}.html","template":"blog.twig"}'
        );

        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(1, $collection->getCount());
        $this->assertSame('/blog/{slug}.html', $collection->get('blog')->path);
    }

    public function testLoadRoutesFromPhp()
    {
        file_put_contents(
            $this->tempDir . '/blog.php',
            '<?php return ["path"=>"/blog/{slug}.html","template"=>"blog.twig"];'
        );

        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(1, $collection->getCount());
        $this->assertSame('/blog/{slug}.html', $collection->get('blog')->path);
    }

    public function testLoadRoutesFromMixedFormats()
    {
        file_put_contents(
            $this->tempDir . '/a.yml',
            "path: /a\ntemplate: a.twig\n"
        );
        file_put_contents(
            $this->tempDir . '/b.json',
            '{"path":"/b","template":"b.twig"}'
        );
        file_put_contents(
            $this->tempDir . '/c.php',
            '<?php return ["path"=>"/c","template"=>"c.twig"];'
        );

        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(3, $collection->getCount());
    }

    public function testEmptyDirectoryReturnsZeroRoutes()
    {
        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(0, $collection->getCount());
    }

    public function testNonRouteFilesAreIgnored()
    {
        file_put_contents($this->tempDir . '/blog.yml', "path: /blog\ntemplate: blog.twig\n");
        file_put_contents($this->tempDir . '.DS_Store', '');
        file_put_contents($this->tempDir . '/readme.md', '# readme');

        $collection = new RouteCollection($this->tempDir);
        $this->assertSame(1, $collection->getCount());
        $this->assertTrue($collection->has('blog'));
    }

    public function testGetUnknownRouteReturnsNull()
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog\ntemplate: blog.twig\n"
        );

        $collection = new RouteCollection($this->tempDir);
        $this->assertNull($collection->get('nonexistent'));
    }

    public function testInvalidRouteFileMustThrowException()
    {
        $this->expectException(KissException::class);

        file_put_contents(
            $this->tempDir . '/bad.yml',
            "path: /blog\n"
        );

        new RouteCollection($this->tempDir);
    }
}
