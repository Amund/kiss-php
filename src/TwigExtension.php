<?php

namespace Kiss;

use Twig\Environment;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class TwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'dump',
                [$this, 'twig_var_dump'],
                [
                    'is_safe' => ['html'],
                    'needs_context' => true,
                    'needs_environment' => true,
                    'is_variadic' => true,
                ]
            ),
        ];
    }

    function twig_var_dump(Environment $env, $context, ...$vars)
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
