<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

use PhpParser\ParserFactory;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;

final class PhpArtifactUtil
{
    /** return just "class Foo extends Bar" line */
    public static function classDeclaration(string $code): ?string
    {
        if (!class_exists(ParserFactory::class)) {
            return null;
        }

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        try { $ast = $parser->parse($code) ?? []; } catch (\Throwable) { return null; }

        $finder = new NodeFinder();
        $class = $finder->findFirstInstanceOf($ast, \PhpParser\Node\Stmt\Class_::class);
        if (!$class) return null;

        $parts = [];
        if ($class->isFinal()) $parts[] = 'final';
        if ($class->isAbstract()) $parts[] = 'abstract';
        $parts[] = 'class';
        $parts[] = $class->name?->toString() ?? '';
        if ($class->extends) $parts[] = 'extends ' . $class->extends->toString();
        if ($class->implements) {
            $impl = array_map(fn($n) => $n->toString(), $class->implements);
            $parts[] = 'implements ' . implode(', ', $impl);
        }
        return trim(implode(' ', $parts));
    }

    /** return one method as PHP code */
    public static function method(string $code, string $name): ?string
    {
        if (!class_exists(ParserFactory::class)) {
            return null;
        }

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        try { $ast = $parser->parse($code) ?? []; } catch (\Throwable) { return null; }

        $finder = new NodeFinder();
        $node = $finder->findFirst($ast, fn($n) => $n instanceof \PhpParser\Node\Stmt\ClassMethod && $n->name->toString() === $name);
        return $node ? "<?php\n" . (new Standard())->prettyPrint([$node]) . "\n" : null;
    }

    /** list of all method names */
    public static function methods(string $code): array
    {
        if (!class_exists(ParserFactory::class)) {
            return [];
        }

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        try { $ast = $parser->parse($code) ?? []; } catch (\Throwable) { return []; }

        $finder = new NodeFinder();
        $nodes = $finder->findInstanceOf($ast, \PhpParser\Node\Stmt\ClassMethod::class);
        return array_values(array_unique(array_map(fn($n) => $n->name->toString(), $nodes)));
    }
}
