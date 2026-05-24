<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    private $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/kiss-test-urlgen-' . uniqid();
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

    public function testPathWithStaticRoute(): void
    {
        file_put_contents(
            $this->tempDir . '/about.yml',
            "path: /about\ntemplate: page.twig\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/about', $generator->path('about'));
    }

    public function testPathWithParams(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n  - slug: hello\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame(
            '/hello.html',
            $generator->path('blog', ['slug' => 'hello'])
        );
    }

    public function testPathSlugifiesParams(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n  - slug: hello\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame(
            '/my-article.html',
            $generator->path('blog', ['slug' => 'My Article!'])
        );
    }

    public function testPathThrowsOnUnknownRoute(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage("Route 'nonexistent' not found");

        file_put_contents(
            $this->tempDir . '/about.yml',
            "path: /about\ntemplate: page.twig\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $generator->path('nonexistent');
    }

    public function testPathThrowsWhenParamsRequiredButNoneGiven(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage("Route 'blog' requires parameters");

        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n  - slug: hello\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $generator->path('blog');
    }

    public function testPathThrowsOnMissingParam(): void
    {
        $this->expectException(KissException::class);
        $this->expectExceptionMessage("Missing param '{slug}' for route 'blog'");

        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n  - slug: hello\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $generator->path('blog', ['title' => 'My Title']);
    }

    public function testPathWithMultipleParams(): void
    {
        file_put_contents(
            $this->tempDir . '/post.yml',
            "path: /{category}/{slug}-{id}.html\ntemplate: post.twig\ndata:\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame(
            '/news/my-article-42.html',
            $generator->path('post', [
                'category' => 'News',
                'slug' => 'My Article',
                'id' => 42,
            ])
        );
    }

    public function testPathWithPaginationReturnsBaseForPage1(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog\ntemplate: blog.twig\npaginate: 3\ndata:\n  - slug: a\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/blog', $generator->path('blog'));
        $this->assertSame('/blog', $generator->path('blog', ['page' => 1]));
    }

    public function testPathWithPaginationReturnsPageUrl(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog\ntemplate: blog.twig\npaginate: 3\ndata:\n  - slug: a\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/blog/page/2', $generator->path('blog', ['page' => 2]));
        $this->assertSame('/blog/page/10', $generator->path('blog', ['page' => 10]));
    }

    public function testPathWithPaginationClampsPageToMinimum1(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog\ntemplate: blog.twig\npaginate: 3\ndata:\n  - slug: a\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/blog', $generator->path('blog', ['page' => 0]));
        $this->assertSame('/blog', $generator->path('blog', ['page' => -1]));
    }

    public function testPathWithIndexHtmlReturnsSlash(): void
    {
        file_put_contents(
            $this->tempDir . '/index.yml',
            "path: /index.html\ntemplate: page.twig\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/', $generator->path('index'));
    }

    public function testPathWithSubdirIndexHtml(): void
    {
        file_put_contents(
            $this->tempDir . '/blog.yml',
            "path: /blog/index.html\ntemplate: blog.twig\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/blog', $generator->path('blog'));
    }

    public function testPathWithRegularHtmlUnchanged(): void
    {
        file_put_contents(
            $this->tempDir . '/about.yml',
            "path: /about.html\ntemplate: page.twig\n"
        );
        $collection = new RouteCollection($this->tempDir);
        $generator = new UrlGenerator($collection);

        $this->assertSame('/about.html', $generator->path('about'));
    }
}
