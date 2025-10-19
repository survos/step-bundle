<?php declare(strict_types=1);
// File: src/Twig/StepRuntimeExtension.php
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
            new TwigFunction('render_action', [$this, 'renderAction'], ['is_safe' => ['html']]),
            new TwigFunction('render_actions', [$this, 'renderActions'], ['is_safe' => ['html']]),
        ];
    }

    public function renderAction(object|array $action): string
    {
        [$type, $vars] = $this->normalize($action);

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

    public function renderActions(iterable $actions): string
    {
        $out = '';
        foreach ($actions as $a) {
            $out .= $this->renderAction($a);
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
                // exporter provides `multiline`
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
                return [$type, compact('note','cwd','lang','path','code')];

            case 'ShowClass':
                // accept various keys: class, target, fqcn, className
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
}
