<?php

namespace Kiss;

use Twig\Environment;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\TwigFunction;
use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class TwigExtension extends AbstractExtension
{
    private ?UrlGenerator $urlGenerator = null;
    private ?RouteCollection $routeCollection = null;
    private ?DataTree $dataTree = null;

    public function setUrlGenerator(UrlGenerator $generator): void
    {
        $this->urlGenerator = $generator;
    }

    public function setRouteCollection(RouteCollection $collection): void
    {
        $this->routeCollection = $collection;
    }

    public function setDataTree(DataTree $tree): void
    {
        $this->dataTree = $tree;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', [$this, 'markdownToHtml'], [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function markdownToHtml(string $content): string
    {
        $converter = new GithubFlavoredMarkdownConverter();
        return $converter->convert($content)->getContent();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'dump',
                [$this, 'twigVarDump'],
                [
                    'is_safe' => ['html'],
                    'needs_context' => true,
                    'needs_environment' => true,
                    'is_variadic' => true,
                ]
            ),
            new TwigFunction(
                'path',
                [$this, 'getPath'],
            ),
            new TwigFunction(
                'route',
                [$this, 'getRoute'],
            ),
        ];
    }

    public function getPath(string $name, array $params = []): string
    {
        if ($this->urlGenerator === null) {
            throw new \RuntimeException(
                'UrlGenerator not set on TwigExtension'
            );
        }
        return $this->urlGenerator->path($name, $params);
    }

    public function getRoute(string $name): array
    {
        if ($this->routeCollection === null || $this->dataTree === null) {
            throw new \RuntimeException(
                'RouteCollection and DataTree must be set on TwigExtension'
            );
        }

        $route = $this->routeCollection->get($name);
        if ($route === null) {
            return [];
        }

        $data = $route->getResolvedData($this->dataTree);
        return is_array($data) ? $data : [];
    }

    public function twigVarDump(Environment $env, $context, ...$vars)
    {
        if (!$env->isDebug()) {
            return;
        }

        ob_start();

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        if (!$vars) {
            $vars = [];
            foreach ($context as $key => $value) {
                if (
                    !$value instanceof Template &&
                    !$value instanceof TemplateWrapper
                ) {
                    $vars[$key] = $value;
                }
            }
            $dumper->dump($cloner->cloneVar($vars));
        } else {
            $dumper->dump($cloner->cloneVar(...$vars));
        }

        return ob_get_clean();
    }
}
