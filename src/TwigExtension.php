<?php

namespace craft\cloud;

use Illuminate\Support\Collection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return Collection::make(get_class_methods(Helper::class))
            ->map(function ($methodName): TwigFunction {
                return new TwigFunction($methodName, [Helper::class, $methodName]);
            })
            ->all();
    }
}
