<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    // public function testLoadFromPhp()
// {
//     $return = ['path' => 'Path', 'template' => 'Template'];
//     $datasource = $this->createStub(DataSource::class);
//     $datasource
//         ->method('load')
//         ->willReturn(['path' => 'Path', 'template' => 'Template']);
//     // $root = vfsStream::create([
//     //     // 'route1.yml' => '{path:test1,template:test1}',
//     //     // 'route2.json' => '{"path":"test2","template":"test2"}',
//     //     'route3.php' =>
//     //         '<?php return ["path"=>"test2","template"=>"test3"];',
//     // ]);
//     // $route = new Route('path');
//     $this->assertSame($return, $datasource->load(''));
// }
// public function testGetPagesWithoutParams()
// {
//     $input = [
//         'path' => '/foo/bar/baz.html',
//         'template' => 'my-template',
//         'data' => 'my-data',
//     ];
//     $output = [
//         'foo/bar/baz.html' => 'my-data',
//     ];
//     $route = new Route('test', $input);
//     $pages = $route->getPages();
//     $this->assertEquals($pages, $output);
// }
// public function testGetPagesWithParams1()
// {
//     $input = [
//         'path' => '/{a}.html',
//         'template' => 'my-template',
//         'data' => [['a' => '1'], ['a' => '2'], ['a' => '3']],
//     ];
//     $output = [
//         '1.html' => ['a' => '1'],
//         '2.html' => ['a' => '2'],
//         '3.html' => ['a' => '3'],
//     ];
//     $route = new Route('test', $input);
//     $pages = $route->getPages();
//     $this->assertEquals($pages, $output);
// }
// public function testGetPagesWithParams2()
// {
//     $input = [
//         'path' => '/{category}/{slug}-{id}.html',
//         'template' => 'my-template',
//         'data' => [
//             [
//                 'id' => 1,
//                 'slug' => 'my-first-article',
//                 'category' => 'news',
//                 'title' => 'My first article',
//             ],
//             [
//                 'id' => 2,
//                 'slug' => 'my-other-article',
//                 'category' => 'events',
//                 'title' => 'My other article',
//             ],
//         ],
//     ];
//     $output = [
//         'news/my-first-article-1.html' => [
//             'id' => 1,
//             'slug' => 'my-first-article',
//             'category' => 'news',
//             'title' => 'My first article',
//         ],
//         'events/my-other-article-2.html' => [
//             'id' => 2,
//             'slug' => 'my-other-article',
//             'category' => 'events',
//             'title' => 'My other article',
//         ],
//     ];
//     $route = new Route('test', $input);
//     $pages = $route->getPages();
//     $this->assertEquals($pages, $output);
// }
}
