<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigExtensionTest extends TestCase
{
    public function testDumpReturnsHtmlInDebugMode()
    {
        $loader = new ArrayLoader([
            'test' => 'before{{ dump() }}after',
        ]);
        $twig = new Environment($loader, [
            'debug' => true,
        ]);
        $twig->addExtension(new TwigExtension());

        $result = $twig->render('test');

        $this->assertStringContainsString('before', $result);
        $this->assertStringContainsString('after', $result);
        $this->assertStringContainsString('sf-dump', $result);
    }

    public function testDumpReturnsEmptyInNonDebugMode()
    {
        $loader = new ArrayLoader([
            'test' => 'before{{ dump() }}after',
        ]);
        $twig = new Environment($loader, [
            'debug' => false,
        ]);
        $twig->addExtension(new TwigExtension());

        $result = $twig->render('test');

        $this->assertSame('beforeafter', $result);
    }

    public function testDumpWithSpecificVars()
    {
        $loader = new ArrayLoader([
            'test' => '{{ dump(foo) }}',
        ]);
        $twig = new Environment($loader, [
            'debug' => true,
        ]);
        $twig->addExtension(new TwigExtension());

        $result = $twig->render('test', ['foo' => 'hello', 'bar' => 'world']);

        $this->assertStringContainsString('sf-dump', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testGetFunctionsReturnsExpectedFunctions()
    {
        $extension = new TwigExtension();
        $functions = $extension->getFunctions();

        $names = array_map(fn($f) => $f->getName(), $functions);
        $this->assertContains('dump', $names);
        $this->assertContains('path', $names);
        $this->assertContains('route', $names);
        $this->assertContains('asset', $names);
    }

    public function testAssetReturnsPathWithHashForExistingFile(): void
    {
        $dir = sys_get_temp_dir() . '/kiss-test-twig-ext-' . uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/style.css', 'body { color: red; }');

        $extension = new TwigExtension();
        $extension->setCopyPath($dir);

        $result = $extension->getAsset('style.css');
        $this->assertMatchesRegularExpression(
            '#^/style\.css\?v=[a-f0-9]{8}$#',
            $result
        );

        unlink($dir . '/style.css');
        rmdir($dir);
    }

    public function testAssetReturnsPlainPathForMissingFile(): void
    {
        $extension = new TwigExtension();
        $extension->setCopyPath('/tmp/nonexistent');

        $this->assertSame('/style.css', $extension->getAsset('style.css'));
    }

    public function testRouteReturnsEmptyForUnknownRoute()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-twig-ext-' . uniqid();
        mkdir($dir, 0777, true);

        $collection = new RouteCollection($dir);
        $tree = new DataTree(['cache' => $dir . '/cache']);

        $extension = new TwigExtension();
        $extension->setRouteCollection($collection);
        $extension->setDataTree($tree);

        $this->assertSame([], $extension->getRoute('nonexistent'));

        rmdir($dir);
    }

    public function testRouteReturnsResolvedData()
    {
        $dir = sys_get_temp_dir() . '/kiss-test-twig-ext-' . uniqid();
        mkdir($dir, 0777, true);

        file_put_contents(
            $dir . '/blog.yml',
            "path: /{slug}.html\ntemplate: post.twig\ndata:\n" .
            "  - slug: hello\n    title: Hello\n  - slug: world\n    title: World"
        );

        $collection = new RouteCollection($dir);
        $tree = new DataTree(['cache' => $dir . '/cache']);

        $extension = new TwigExtension();
        $extension->setRouteCollection($collection);
        $extension->setDataTree($tree);

        $data = $extension->getRoute('blog');
        $this->assertCount(2, $data);
        $this->assertSame('hello', $data[0]['slug']);
        $this->assertSame('World', $data[1]['title']);

        $tree->clear();
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }
}
