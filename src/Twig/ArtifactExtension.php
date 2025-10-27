<?php

declare(strict_types=1);

namespace Survos\StepBundle\Twig;

use Survos\StepBundle\Util\PhpArtifactUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ArtifactExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('artifact_php_declaration', [self::class, 'phpDeclaration']),
            new TwigFunction('artifact_php_method', [self::class, 'phpMethod']),
            new TwigFunction('artifact_php_methods', [self::class, 'phpMethods']),
        ];
    }

    public static function phpDeclaration(string $code): ?string
    {
        return PhpArtifactUtil::classDeclaration($code);
    }

    public static function phpMethod(string $code, string $method): ?string
    {
        return PhpArtifactUtil::method($code, $method);
    }

    public static function phpMethods(string $code): array
    {
        return PhpArtifactUtil::methods($code);
    }
}
