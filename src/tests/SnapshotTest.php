<?php

namespace Kiss;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase
{
    private $root;
    private $snapshot;

    public function setUp(): void
    {
        $this->root = vfsStream::setup();
        $path = $this->root->url() . '/snapshot.php';
        $this->snapshot = new Snapshot($path);
    }

    public function testAddUnknownFileMustThrownException()
    {
        $this->expectException(KissException::class);

        $this->snapshot->add($this->root->url() . '/not-a-file.php');
    }

    public function testAddFile()
    {
        vfsStream::create(['a.php' => 'a']);
        $path = $this->root->url() . '/a.php';
        $this->snapshot->add($path);
        $this->assertEquals('c1d04330', $this->snapshot->get($path));
    }

    public function testAddFileInAFolder()
    {
        vfsStream::create(['folder' => ['a.php' => 'a']]);
        $path = $this->root->url() . '/folder/a.php';
        $this->snapshot->add($path);
        $this->assertEquals('c1d04330', $this->snapshot->get($path));
    }

    public function testComplexStructure()
    {
        vfsStream::create([
            'folder' => [
                'a.php' => 'a',
                'subfolder' => [
                    'b.php' => 'b',
                ],
            ],
        ]);
        $path = 'vfs://root/folder';
        $this->snapshot->add($path);

        $expected = [
            'vfs://root/folder/a.php' => 'c1d04330',
            'vfs://root/folder/subfolder/b.php' => 'd280b0c4',
        ];
        $this->assertEquals($expected, $this->snapshot->get());
    }

    public function testSave()
    {
        vfsStream::create(['a.php' => 'a']);
        $path = 'vfs://root/a.php';
        $this->snapshot->add('vfs://root/a.php');
        $this->snapshot->save();

        $expected =
            "<?php return array (\n  'vfs://root/a.php' => 'c1d04330',\n);";
        $this->assertTrue($this->root->hasChild('snapshot.php'));
        $this->assertEquals(
            $expected,
            \file_get_contents('vfs://root/snapshot.php')
        );
    }

    public function testLoad()
    {
        vfsStream::create(['snapshot.php' => '<?php return ["foo" => "bar"];']);
        $this->snapshot->load();

        $this->assertEquals('bar', $this->snapshot->get('foo'));
    }

    public function testDiffAdd()
    {
        $this->snapshot->save();

        vfsStream::create([
            'snapshot.php' => '<?php return [];',
            'a.php' => 'a',
        ]);
        $this->snapshot->add('vfs://root/a.php');
        $diff = $this->snapshot->diff();

        $this->assertEquals(['vfs://root/a.php'], $diff['add']);
    }

    public function testDiffDelete()
    {
        vfsStream::create([
            'snapshot.php' =>
                '<?php return ["vfs://root/a.php" => "c1d04330"];',
        ]);
        $diff = $this->snapshot->diff();

        $this->assertEquals(['vfs://root/a.php'], $diff['delete']);
    }

    public function testDiffUpdate()
    {
        vfsStream::create([
            'snapshot.php' =>
                '<?php return ["vfs://root/a.php" => "c1d04330"];',
            'a.php' => 'b',
        ]);

        $this->snapshot->add('vfs://root/a.php');
        $diff = $this->snapshot->diff();
        $this->assertEquals(['vfs://root/a.php'], $diff['update']);
    }
}
