<?php declare(strict_types=1);
// file: src/Twig/StepRuntimeExtension.php — now resolves DisplayCode 'artifact:' targets via context

namespace Survos\StepBundle\Twig;

use ReflectionClass;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfony\Bridge\Twig\Attribute\AsTwigExtension;

#[AsTwigExtension]
final class StepRuntimeExtension extends AbstractExtension
{
    private const TPL_BASE = '@SurvosStep/step/action/';

    public function __construct(private readonly Environment $twig) {}

    public function getFunctions(): array
    {
        return [
            // needs_context=true so we can read current task/step to resolve artifacts
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

        // Resolve DisplayCode("artifact:…") → public/artifacts/<safeTask>/<safeStep>/files/<actionKey>/<name>
        if ($type === 'DisplayCode') {
            $vars = $this->resolveDisplayCodeArtifact($context, $vars);
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

    private function normalize(object|array $a): array
    {
        if (is_object($a)) {
            $rc = new ReflectionClass($a);
            $type = $rc->getShortName();
            $data = [];
            foreach ($rc->getProperties() as $p) {
                $p->setAccessible(true);
                $data[$p->getName()] = $p->getValue($a);
            }
        } else {
            $data = $a;
            $type = (string)($data['type'] ?? 'Action');
        }

        $note = $data['note'] ?? null;
        $cwd  = $data['cwd']  ?? null;

        $lang = $data['language'] ?? $data['lang'] ?? match (strtolower($type)) {
            'bash'               => 'bash',
            'composer', 'composerrequire', 'importmaprequire' => 'bash',
            'yamlwrite'          => 'yaml',
            'displaycode'        => $data['language'] ?? 'text',
            'showclass'          => 'php',
            default              => $data['language'] ?? 'text'
        };

        switch ($type) {
            case 'Composer':
                $cmd = trim((string)($data['cmd'] ?? ''));
                return [$type, compact('note','cwd','lang','cmd')];

            case 'ComposerRequire':
                $cmd = (string)($data['multiline'] ?? '');
                return ['Composer', compact('note','cwd','lang','cmd')];

            case 'ImportmapRequire':
                $cmd = (string)($data['multiline'] ?? '');
                return [$type, compact('note','cwd','lang','cmd')];

            case 'YamlWrite':
                $path    = (string)($data['path'] ?? '');
                $content = (string)($data['content'] ?? '');
                return [$type, compact('note','cwd','lang','path','content')];

            case 'Bash':
                $cmd = (string)($data['cmd'] ?? '');
                return [$type, compact('note','cwd','lang','cmd')];

            case 'DisplayCode':
                $target  = $data['target'] ?? null;
                $code    = $data['code']   ?? null;
                $path    = is_string($target) ? $target : null;
                return [$type, compact('note','cwd','lang','path','code','target')];

            case 'ShowClass':
                $class = $data['class'] ?? $data['target'] ?? $data['fqcn'] ?? $data['className'] ?? null;
                $code = null; $path = null; $warning = null; $signature = null;

                if (is_string($class) && class_exists($class)) {
                    $rc = new \ReflectionClass($class);
                    $path = $rc->getFileName() ?: null;
                    if ($path && is_file($path)) {
                        $code = (string)file_get_contents($path);
                    } else {
                        $warning = "Cannot locate file for {$class}";
                    }
                } else {
                    $warning = is_string($class)
                        ? "Class not found: {$class} (run the previous step?)"
                        : 'No class provided';
                }

                return [$type, compact('note','cwd','lang','class','code','path','warning','signature')];

            default:
                $code = $data['code'] ?? ($data['cmd'] ?? $data['multiline'] ?? null);
                $path = $data['path'] ?? null;
                return [$type, compact('note','cwd','lang','code','path')];
        }
    }

    /**
     * Translate DisplayCode('artifact:<stepTitle>::<actionId>::<filename>')
     *   → /artifacts/<safeTask>/<safeStep>/files/<actionKey>/<filename>
     *
     * @param array{path?:?string,target?:mixed} $vars
     * @return array<string,mixed>
     */
    private function resolveDisplayCodeArtifact(array $context, array $vars): array
    {
        $path = $vars['path'] ?? null;
        if (!is_string($path) || !str_starts_with($path, 'artifact:')) {
            return $vars;
        }

        // Expected: artifact:<stepTitle>::<actionId>::<filename>
        $spec = substr($path, strlen('artifact:'));
        $parts = explode('::', $spec, 3);
        if (count($parts) !== 3) {
            return $vars; // leave it as-is
        }
        [$stepTitle, $actionId, $fileName] = $parts;

        // We need the logical task name and the actual step title from the template context
        // slides.html.twig should set these when looping:
        //   {% set __task_name = task.name|default(code) %}
        //   {% set __step_title = task.title|default(task.name|default(code)) %}
        $taskName  = (string)($context['__task_name']  ?? $context['code'] ?? 'slide');
        $stepTitle = (string)($context['__step_title'] ?? $stepTitle);

        $safeTask = preg_replace('/[^A-Za-z0-9._-]+/', '-', $taskName);
        $safeStep = preg_replace('/[^A-Za-z0-9._-]+/', '-', $stepTitle);
        $safeAction = 'console-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $actionId);

        $resolved = "/artifacts/{$safeTask}/{$safeStep}/files/{$safeAction}/{$fileName}";

        $vars['path'] = $resolved;
        return $vars;
    }
}
