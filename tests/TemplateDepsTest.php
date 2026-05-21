<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class TemplateDepsTest extends TestCase
{
    use RmDirTrait;

    private string $templateDir;
    private string $cacheDir;

    public function setUp(): void
    {
        $this->templateDir = sys_get_temp_dir() . '/kiss-tpl-' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/kiss-tpl-cache-' . uniqid();
        mkdir($this->templateDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);
    }

    public function tearDown(): void
    {
        $this->rmDir($this->templateDir);
        $this->rmDir($this->cacheDir);
    }

    public function testTemplateWithoutDeps()
    {
        file_put_contents($this->templateDir . '/page.twig', 'Hello {{ name }}');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('page.twig');

        $this->assertEquals(['page.twig'], $impacted);
    }

    public function testTemplateWithExtends()
    {
        file_put_contents(
            $this->templateDir . '/layout.twig',
            '<html>{% block body %}{% endblock %}</html>'
        );
        file_put_contents(
            $this->templateDir . '/page.twig',
            '{% extends "layout.twig" %}{% block body %}Hello{% endblock %}'
        );

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('layout.twig');

        $this->assertContains('layout.twig', $impacted);
        $this->assertContains('page.twig', $impacted);
    }

    public function testTemplateWithInclude()
    {
        file_put_contents($this->templateDir . '/header.twig', '<header>Header</header>');
        file_put_contents($this->templateDir . '/page.twig', '{% include "header.twig" %}Body');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('header.twig');

        $this->assertContains('header.twig', $impacted);
        $this->assertContains('page.twig', $impacted);
    }

    public function testTemplateWithEmbed()
    {
        file_put_contents(
            $this->templateDir . '/card.twig',
            '<div>{% block content %}{% endblock %}</div>'
        );
        file_put_contents(
            $this->templateDir . '/page.twig',
            '{% embed "card.twig" %}{% block content %}Hello{% endblock %}{% endembed %}'
        );

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('card.twig');

        $this->assertContains('card.twig', $impacted);
        $this->assertContains('page.twig', $impacted);
    }

    public function testMultiLevelChain()
    {
        file_put_contents(
            $this->templateDir . '/base.twig',
            '<html>{% block body %}{% endblock %}</html>'
        );
        file_put_contents(
            $this->templateDir . '/layout.twig',
            '{% extends "base.twig" %}{% block body %}{% block content %}{% endblock %}{% endblock %}'
        );
        file_put_contents(
            $this->templateDir . '/page.twig',
            '{% extends "layout.twig" %}{% block content %}Hello{% endblock %}'
        );

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('base.twig');

        $this->assertContains('base.twig', $impacted);
        $this->assertContains('layout.twig', $impacted);
        $this->assertContains('page.twig', $impacted);
    }

    public function testTemplateNotFound()
    {
        file_put_contents($this->templateDir . '/page.twig', 'Hello');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('nonexistent.twig');

        $this->assertEquals(['nonexistent.twig'], $impacted);
    }

    public function testClearRemovesCache()
    {
        file_put_contents($this->templateDir . '/page.twig', 'Hello');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $this->assertFileExists($this->cacheDir . '/template-deps.php');

        $deps->clear();
        $this->assertFileDoesNotExist($this->cacheDir . '/template-deps.php');
    }

    public function testRebuildAfterModification()
    {
        file_put_contents(
            $this->templateDir . '/layout.twig',
            '<html>{% block body %}{% endblock %}</html>'
        );
        file_put_contents(
            $this->templateDir . '/page.twig',
            '{% extends "layout.twig" %}{% block body %}Hello{% endblock %}'
        );

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);

        // Add a new file that extends layout.twig
        file_put_contents(
            $this->templateDir . '/other.twig',
            '{% extends "layout.twig" %}{% block body %}Other{% endblock %}'
        );

        $deps->rebuild();
        $impacted = $deps->findImpacted('layout.twig');

        $this->assertContains('page.twig', $impacted);
        $this->assertContains('other.twig', $impacted);
    }

    public function testFindImpactedReturnsUnique()
    {
        file_put_contents($this->templateDir . '/base.twig', '{% block body %}{% endblock %}');
        file_put_contents($this->templateDir . '/a.twig', '{% extends "base.twig" %}{% block body %}A{% endblock %}');
        file_put_contents($this->templateDir . '/b.twig', '{% extends "base.twig" %}{% block body %}B{% endblock %}');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);
        $impacted = $deps->findImpacted('base.twig');

        $this->assertCount(3, $impacted);
        $this->assertContains('base.twig', $impacted);
        $this->assertContains('a.twig', $impacted);
        $this->assertContains('b.twig', $impacted);
    }

    public function testIsFreshAfterRebuild()
    {
        file_put_contents($this->templateDir . '/page.twig', 'Hello');

        $deps = new TemplateDeps($this->templateDir, $this->cacheDir);

        $manifestPath = $this->cacheDir . '/template-deps.php';
        $mtime = filemtime($manifestPath);

        // Create a new TemplateDeps — should be fresh, reuse cache
        $deps2 = new TemplateDeps($this->templateDir, $this->cacheDir);
        $this->assertSame($mtime, filemtime($manifestPath));
    }
}
