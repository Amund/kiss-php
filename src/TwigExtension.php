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
        ];
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
