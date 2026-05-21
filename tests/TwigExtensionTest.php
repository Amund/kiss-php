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

    public function testGetFunctionsReturnsDumpFunction()
    {
        $extension = new TwigExtension();
        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('dump', $functions[0]->getName());
    }
}
