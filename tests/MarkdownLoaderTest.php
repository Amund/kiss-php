<?php

namespace Kiss;

use Kiss\DataSource\MarkdownLoader;
use PHPUnit\Framework\TestCase;

final class MarkdownLoaderTest extends TestCase
{
    public function testLoadWithoutFrontmatter(): void
    {
        $path = sys_get_temp_dir() . '/kiss-test-md-' . uniqid() . '.md';
        file_put_contents($path, 'Just **markdown** content.');

        $loader = new MarkdownLoader($path);
        $result = $loader->load();

        $this->assertEquals(['content' => 'Just **markdown** content.'], $result);
        unlink($path);
    }

    public function testLoadWithValidFrontmatter(): void
    {
        $path = sys_get_temp_dir() . '/kiss-test-md-' . uniqid() . '.md';
        file_put_contents($path, "---\ntitle: Hello\n---\nBody text");

        $loader = new MarkdownLoader($path);
        $result = $loader->load();

        $this->assertEquals(['title' => 'Hello', 'content' => 'Body text'], $result);
        unlink($path);
    }

    public function testLoadWithMalformedFrontmatterReturnsContent(): void
    {
        $path = sys_get_temp_dir() . '/kiss-test-md-' . uniqid() . '.md';
        $raw = "---\ntitle: Hello";
        file_put_contents($path, $raw);

        $loader = new MarkdownLoader($path);
        $result = $loader->load();

        $this->assertEquals(['content' => $raw], $result);
        unlink($path);
    }

    public function testLoadWithInvalidYamlFrontmatterThrows(): void
    {
        $this->expectException(KissException::class);

        $path = sys_get_temp_dir() . '/kiss-test-md-' . uniqid() . '.md';
        file_put_contents($path, "---\n'''\n---\nBody");

        $loader = new MarkdownLoader($path);
        $loader->load();

        unlink($path);
    }

    public function testFileAndContentAccessors(): void
    {
        $path = sys_get_temp_dir() . '/kiss-test-md-' . uniqid() . '.md';
        file_put_contents($path, "---\nkey: val\n---\nBody");

        $loader = new MarkdownLoader($path);
        $loader->load();

        $this->assertSame($path, $loader->filePath());
        $this->assertEquals(['key' => 'val', 'content' => 'Body'], $loader->content());

        unlink($path);
    }
}
