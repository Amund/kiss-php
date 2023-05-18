<?php

namespace Kiss;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private $root;

    public function setUp(): void
    {
        $this->root = vfsStream::setup();
    }

    public function testHasGetSet()
    {
        $registry = new Registry('fake.php');
        $this->assertFalse($registry->has('foo'));
        $this->assertNull($registry->get('foo'));

        $registry->set('foo', 'bar');
        $this->assertTrue($registry->has('foo'));
        $this->assertEquals('bar', $registry->get('foo'));

        $this->assertEquals(['foo' => 'bar'], $registry->get());
    }

    public function testRemove()
    {
        $registry = new Registry('fake.php');
        $registry->set('foo', 'bar');
        $this->assertTrue($registry->has('foo'));

        $registry->remove('foo');
        $this->assertFalse($registry->has('foo'));
    }

    public function testSave()
    {
        $path = $this->root->url() . '/registry.php';
        $this->assertFalse($this->root->hasChild('registry.php'));

        $registry = new Registry($path);
        $registry->save();
        $this->assertTrue($this->root->hasChild('registry.php'));

        $expected = "<?php return array (\n);";
        $content = \file_get_contents($path);
        $this->assertEquals($expected, $content);
    }

    public function testLoad()
    {
        $path = $this->root->url() . '/registry.php';
        \file_put_contents($path, '<?php return ["foo"=>"bar"];');

        $registry = new Registry($path);
        $registry->load();

        $this->assertTrue($registry->has('foo'));
        $this->assertEquals('bar', $registry->get('foo'));
    }
}
