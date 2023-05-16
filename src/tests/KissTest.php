<?php

namespace Kiss;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class KissTest extends TestCase
{
    private $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup();
    }

    public function testConstruct(): void
    {
        $kiss = new Kiss();
        $this->assertEquals('', $kiss->root);

        $kiss = new Kiss('./');
        $this->assertEquals('', $kiss->root);

        $kiss = new Kiss($this->root->url());
        $this->assertEquals('vfs://root/', $kiss->root);
    }

    public function testConfig(): void
    {
        $kiss = new Kiss($this->root->url());

        $this->assertFalse(is_file('vfs://root/' . $kiss->entry));
        $kiss->config();
        $this->assertTrue(is_file('vfs://root/' . $kiss->entry));
    }

    public function testWarmup(): void
    {
        $kiss = new Kiss($this->root->url());
        $kiss->config()->warmup();

        foreach ($kiss->config['path'] as $path) {
            $this->assertTrue(\is_dir($path));
        }
        // dump(
        //     vfsStream::inspect(
        //         new \org\bovigo\vfs\visitor\vfsStreamStructureVisitor()
        //     )
        // )->getStructure();
    }
}
