<?php declare(strict_types=1);
// file: src/Twig/StepRuntimeExtension.php — load code for DisplayCode paths (artifacts)

namespace Survos\StepBundle\Twig;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StepRuntimeExtension extends AbstractExtension
{
    private const TPL_BASE = '@SurvosStep/step/action/';

    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir, // <-- inject this
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_action', [$this, 'renderAction'], [
                'is_safe' => ['html'],
                'needs_context' => true,
            ]),
            new TwigFunction('render_actions', [$this, 'renderActions'], [
                'is_safe' => ['html'],
                'needs_context' => true,
            ]),
        ];
    }

    /** @param array<string,mixed> $context */
    public function renderAction(array $context, object|array $action): string
    {
        [$type, $vars] = $this->normalize($action);

        if ($type === 'DisplayCode') {
            // resolve "artifact:..." → /artifacts/<task>/<step>/files/<actionKey>/<name>
            $vars = $this->resolveDisplayCodeArtifact($context, $vars);

            // if we have a path, load file contents into "code"
            $path = $vars['path'] ?? null;
            if (is_string($path) && $path !== '') {
                $code = $this->loadCodeFromPath($path);
                if ($code !== null) {
                    $vars['code'] = $code;
                    if (empty($vars['lang'])) {
                        $vars['lang'] = $this->guessLang($path);
                    }
                }
            }
        }

        $tpl = match ($type) {
            'Composer', 'ComposerRequire'      => self::TPL_BASE . 'composer.html.twig',
            'ImportmapRequire'                 => self::TPL_BASE . 'importmap_require.html.twig',
            'YamlWrite'                        => self::TPL_BASE . 'yaml_write.html.twig',
            'Bash'                             => self::TPL_BASE . 'bash.html.twig',
            'DisplayCode'                      => self::TPL_BASE . 'display_code.html.twig',
            'ShowClass'                        => self::TPL_BASE . 'show_class.html.twig',
            default                            => self::TPL_BASE . '_action.html.twig',
        };

        return $this->twig->render($tpl, $vars + ['type' => $type]);
    }

    /** @param array<string,mixed> $context */
    public function renderActions(array $context, iterable $actions): string
    {
        $out = '';
        foreach ($actions as $a) {
            $out .= $this->renderAction($context, $a);
        }
        return $out;
    }

    /**
     * Translate DisplayCode('artifact:<stepTitle>::<actionId>::<filename>')
     * to a public URL path: /artifacts/<safeTask>/<safeStep>/files/<console-<id>>/<filename>
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    private function resolveDisplayCodeArtifact(array $context, array $vars): array
    {
        $target = $vars['path'] ?? $vars['target'] ?? null;
        if (!is_string($target) || !str_starts_with($target, 'artifact:')) {
            return $vars;
        }

        // artifact:<stepTitle>::<actionId>::<filename>
        $spec = substr($target, strlen('artifact:'));
        $parts = explode('::', $spec, 3);
        if (count($parts) !== 3) {
            return $vars;
        }
        [$stepTitleFromSpec, $actionId, $fileName] = $parts;

        // from Twig context (set per slide in slides.html.twig)
        $taskFromSlide = (string)($context['__task_name']  ?? '');
        $stepTitle     = (string)($context['__step_title'] ?? $stepTitleFromSpec);
        $taskFromDeck  = (string)($context['code'] ?? 'slide'); // deck code

        $safe = fn(string $s) => preg_replace('/[^A-Za-z0-9._-]+/', '-', $s) ?: 'x';

        $safeStep   = $safe($stepTitle);
        $safeAction = 'console-' . $safe($actionId);

        // 1) try slide's task name
        if ($taskFromSlide !== '') {
            $safeTask = $safe($taskFromSlide);
            $candidate = "/artifacts/{$safeTask}/{$safeStep}/files/{$safeAction}/{$fileName}";
            if (is_file($this->projectDir . $candidate)) {
                $vars['path'] = $candidate;
                return $vars;
            }
        }

        // 2) fallback to deck code as task
        $safeTask = $safe($taskFromDeck);
        $candidate = "/artifacts/{$safeTask}/{$safeStep}/files/{$safeAction}/{$fileName}";
        if (is_file($this->projectDir . $candidate)) {
            $vars['path'] = $candidate;
            return $vars;
        }

        // leave unresolved if nothing found; DisplayCode partial will show a hint
        $vars['path'] = $candidate; // last attempt (useful for the hint)
        return $vars;
    }

    /** Map a public path (/artifacts/...) to disk and read it. */
    private function loadCodeFromPath(string $path): ?string
    {
        // Map public URL to filesystem (…/public + path)
        if (str_starts_with($path, '/')) {
            $fs = rtrim($this->projectDir, '/') . $path;
            if (is_file($fs)) {
                $code = file_get_contents($fs);
                if ($code !== false) {
                    return (string) $code;
                }
            }
        }

        // Absolute path fallback (rare)
        if (is_file($path)) {
            $code = file_get_contents($path);
            if ($code !== false) {
                return (string) $code;
            }
        }

        return null;
    }

    /** Cheap syntax guess from extension → used for <code class="language-…"> */
    private function guessLang(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php'               => 'php',
            'yml', 'yaml'       => 'yaml',
            'twig'              => 'twig',
            'json'              => 'json',
            'html', 'htm'       => 'html',
            'md', 'markdown'    => 'markdown',
            default             => 'text',
        };
    }


// file: src/Twig/StepRuntimeExtension.php — add normalize() helper

// ... inside the StepRuntimeExtension class (place near the bottom)

    /**
     * Convert an action object or array into [type, vars] for template rendering.
     *
     * @return array{string,array<string,mixed>}
     */
    private function normalize(object|array $a): array
    {
        if (is_object($a)) {
            $rc = new \ReflectionClass($a);
            $type = $rc->getShortName();
            $data = [];

            foreach ($rc->getProperties() as $p) {
                $p->setAccessible(true);
                try {
                    $data[$p->getName()] = $p->getValue($a);
                } catch (\Throwable) {
                    // skip unreadable
                }
            }
        } else {
            $data = $a;
            $type = (string)($data['type'] ?? 'Action');
        }

        // normalize commonly used keys
        $note = $data['note'] ?? null;
        $cwd = $data['cwd'] ?? null;
        $lang = $data['language'] ?? $data['lang'] ?? match (strtolower($type)) {
            'bash' => 'bash',
            'composer', 'composerrequire', 'importmaprequire' => 'bash',
            'yamlwrite' => 'yaml',
            'displaycode' => 'text',
            'showclass' => 'php',
            default => 'text',
        };

        // Shape data per action type so the Twig partials get consistent vars
        switch ($type) {
            case 'Composer':
                $cmd = trim((string)($data['cmd'] ?? ''));
                return [$type, compact('note', 'cwd', 'lang', 'cmd')];

            case 'Console':
                $cmd = (string)($data['cmd'] ?? '');
                return [$type, compact('note','cwd','lang','cmd')];


            case 'ComposerRequire':
                $cmd = (string)($data['multiline'] ?? '');
                return ['Composer', compact('note', 'cwd', 'lang', 'cmd')];

            case 'ImportmapRequire':
                $cmd = (string)($data['multiline'] ?? '');
                return [$type, compact('note', 'cwd', 'lang', 'cmd')];

            case 'YamlWrite':
                $path = (string)($data['path'] ?? '');
                $content = (string)($data['content'] ?? '');
                return [$type, compact('note', 'cwd', 'lang', 'path', 'content')];

            case 'Bash':
                $cmd = (string)($data['cmd'] ?? '');
                return [$type, compact('note', 'cwd', 'lang', 'cmd')];

            case 'DisplayCode':
                $target = $data['target'] ?? $data['path'] ?? null;
                $code = $data['code'] ?? null;
                $path = is_string($target) ? $target : null;
                return [$type, compact('note', 'cwd', 'lang', 'path', 'code', 'target')];

            case 'ShowClass':
                $class = $data['class'] ?? $data['target'] ?? $data['fqcn'] ?? $data['className'] ?? null;
                return [$type, compact('note', 'cwd', 'lang', 'class')];

            default:
                $code = $data['code'] ?? ($data['cmd'] ?? $data['multiline'] ?? null);
                $path = $data['path'] ?? null;
                return [$type, compact('note', 'cwd', 'lang', 'code', 'path')];
        }
    }


}
