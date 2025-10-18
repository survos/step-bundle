<?php

namespace Survos\StepBundle\Metadata;

use Attribute;

/**
 * Neutral, renderer-agnostic step descriptor.
 * Renderers (README/Reveal/JSON) consume this metadata; runtime can execute actions later.
 */
#[Attribute(Attribute::TARGET_FUNCTION)]
final class Step
{
    /**
     * @param list<string>    $bullets
     * @param list<Action>    $actions
     * @param list<string>    $notes
     */
    public function __construct(
        public string $title,
        public string $description = '',
        public array $bullets = [],
        public array $actions = [],
        public ?string $website = null,
        public ?string $image = null,
        public array $notes = [],
        public ?string $group = null,
        public ?string $tags = null,
    ) {}
}

/**
 * Base Action (data-only; no side-effects here).
 * Execution/rendering will be provided by a separate runtime/renderer.
 */
abstract class Action
{
    public function __construct(
        public ?string $note = null,
        public ?string $cwd = null,
    ) {}
}

/** Terminal commands via /bin/sh (login shell compatible). */
final class Bash extends Action
{
    public function __construct(
        public string $cmd,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Symfony Console commands (auto-detects bin/console vs symfony console at runtime). */
final class Console extends Action
{
    public function __construct(
        public string $cmd,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Composer commands (e.g., "require foo/bar:^1.2"). */
final class Composer extends Action
{
    public function __construct(
        public string $cmd,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Write or update a key/value in an env file (.env, .env.local, etc.). */
final class Env extends Action
{
    public function __construct(
        public string $key,
        public string $value,
        public ?string $file = '.env.local',
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Open an external URL or resolve a Symfony route + params (when available). */
final class OpenUrl extends Action
{
    /**
     * @param array<string, scalar|array|null> $params
     */
    public function __construct(
        public string $urlOrRoute,
        public array $params = [],
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Write YAML to a path. */
final class YamlWrite extends Action
{
    public function __construct(
        public string $path,
        public string $content,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Write arbitrary file content. */
final class FileWrite extends Action
{
    public function __construct(
        public string $path,
        public string $content,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Copy a file. */
final class FileCopy extends Action
{
    public function __construct(
        public string $from,
        public string $to,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/**
 * Display a code snippet (extracted by file+lines, file+markers, or class+method).
 * Execution-time extractors will honor these hints.
 */
final class DisplayCode extends Action
{
    public function __construct(
        public string $target,              // path or FQCN
        public ?string $method = null,      // when FQCN
        public ?int $start = null,          // explicit line range
        public ?int $end = null,
        public ?string $markerStart = null, // e.g. "// [demo:start]"
        public ?string $markerEnd = null,
        public ?string $lang = null,        // syntax hint
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Visual grouping/cue in renderers (no-op for execution). */
final class Section extends Action
{
    public function __construct(
        public string $title,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Minimal browser actions (optional; handy for quick UI checks). */
final class BrowserVisit extends Action
{
    public function __construct(
        public string $url,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

final class BrowserClick extends Action
{
    public function __construct(
        public string $target, // text/selector
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

final class BrowserAssert extends Action
{
    public function __construct(
        public string $selectorOrArea, // e.g., "title", "h1", "#comments"
        public ?string $contains = null,
        public ?string $equals = null,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}

/** Escape hatch: run an inline PHP closure when executing steps. */
final class PhpClosure extends Action
{
    public function __construct(
        public \Closure $fn,
        ?string $note = null,
        ?string $cwd = null,
    ) { parent::__construct($note, $cwd); }
}
