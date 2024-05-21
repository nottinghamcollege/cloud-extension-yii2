<?php

namespace craft\cloud\twig;

use craft\cloud\Helper;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'isCraftCloud' => Helper::isCraftCloud(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('artifactUrl', [Helper::class, 'artifactUrl']),
        ];
    }
}
