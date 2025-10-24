<?php declare(strict_types=1);

/**
 * file: src/Twig/StepRuntimeExtension.php
 * Robust action normalizer + renderer with field-based type inference and inline debugging.
 */

namespace Survos\StepBundle\Twig;

use Survos\StepBundle\Action\AbstractAction;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StepRuntimeExtension extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_action',  [$this, 'renderAction'],  ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('render_actions', [$this, 'renderActions'], ['is_safe' => ['html'], 'needs_context' => true]),
        ];
    }

    /** @param array<string,mixed> $context */
    public function renderAction(array $context, AbstractAction $action): string
    {
        [$type, $vars, $dbg] = $this->normalize($action);

        if ($type === 'DisplayCode') {
            $vars = $this->resolveDisplayCodeArtifact($context, $vars);
            $path = $vars['path'] ?? $vars['target'] ?? null;
            if (is_string($path) && $path !== '') {
                if (($code = $this->loadCodeFromPath($path)) !== null) {
                    $vars['code'] = $code;
                    if (empty($vars['lang'])) {
                        $vars['lang'] = $this->guessLang($path);
                    }
                }
            }
        }

        $base = '@SurvosStep/step/action/';
        $t    = strtolower(trim($type));
        $tpl  = match ($t) {
            'composer','composerrequire' => $base.'composer.html.twig',
            'importmaprequire'           => $base.'importmap_require.html.twig',
            'yamlwrite'                  => $base.'yaml_write.html.twig',
            'bash'                       => $base.'bash.html.twig',
            'displaycode'                => $base.'display_code.html.twig',
            'showclass'                  => $base.'show_class.html.twig',
            'console'                    => $base.'console.html.twig',   // ← force console partial
            'section'                    => $base.'section.html.twig',
            default                      => $base.'_action.html.twig',
        };

        $loader = $this->twig->getLoader();
        if (!method_exists($loader, 'exists') || !$loader->exists($tpl)) {
            throw new \InvalidArgumentException(sprintf(
                'Twig template "%s" not found. Loader: %s. Action type: %s',
                $tpl,
                get_class($loader),
                $type
            ));
        }

        // Old way injected the data at the top level, but let's be verbose and pass in the action instead.
        $html = $this->twig->render($tpl, $vars + ['action' => $action, 'type' => $type]);

        // Inline debug (opt-in): append a tiny panel describing how we chose the template
        $dbgOn = (($_GET['__step_dbg'] ?? '') === '1') || (($_ENV['STEP_TWIG_DEBUG'] ?? getenv('STEP_TWIG_DEBUG') ?? '') === '1');
        if ($dbgOn) {
            $meta = [
                'rawType'      => $dbg['rawType'],
                'inferredType' => $dbg['inferredType'],
                'selectedTpl'  => $tpl,
                'keys'         => array_keys($vars),
            ];
            $html = sprintf(
                '<div style="border:1px dashed #556; padding:6px; margin:6px 0; font:12px/1.3 monospace; color:#9ecbff">'
                .'DEBUG render_action → type=%s, inferred=%s, tpl=%s<br/>keys=%s</div>',
                htmlspecialchars((string)$meta['rawType']),
                htmlspecialchars((string)$meta['inferredType']),
                htmlspecialchars($tpl),
                htmlspecialchars(json_encode($meta['keys']))
            ) . $html;
        }

        // Prepend tpl path like you had before
        return ($dbgOn ? ($tpl . '<br />') : '') . $html;
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
     * Normalize an action (object or serialized array) into [type, vars, dbg] for Twig.
     * - preserves an object's own "type" if present
     * - trims/case-normalizes
     * - infers type from fields if needed (Console, ComposerRequire, FileWrite, Bash)
     * @return array{0:string,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function normalize(object|array $action): array
    {
        $vars    = is_array($action) ? $action : get_object_vars($action);
        $rawType = (string)($vars['type'] ?? (is_object($action) ? (new \ReflectionClass($action))->getShortName() : 'Action'));
        $type    = ucfirst(strtolower(trim($rawType)));

        // Heuristic inference if type is generic/missing
        $inferred = $type;
        if ($inferred === 'Action' || $inferred === '' || $inferred === 'Stdclass') {
            // Console-like?
            if (isset($vars['command']) && is_string($vars['command']) && isset($vars['args']) && is_array($vars['args'])) {
                $inferred = 'Console';
            }
            // Composer-like?
            elseif (isset($vars['packages']) && is_array($vars['packages'])) {
                $inferred = 'ComposerRequire';
            }
            // FileWrite-like?
            elseif (isset($vars['content']) && isset($vars['path'])) {
                $inferred = 'FileWrite';
            }
            // Bash-like?
            elseif (isset($vars['cmd']) || isset($vars['multiline'])) {
                $inferred = 'Bash';
            }
        }
        $type = $inferred;
        $vars['type'] = $type;

        // Standardize fields per type
        switch (strtolower($type)) {
            case 'bash':
                $vars['code'] = $vars['multiline'] ?? $vars['command'] ?? $vars['cmd'] ?? $vars['code'] ?? '';
                $vars['lang'] = $vars['lang'] ?? 'bash';
                break;

            case 'console':
                $prefer      = (string)($vars['prefer'] ?? 'auto');
                $phpBinary   = (string)($vars['phpBinary'] ?? 'php');
                $consolePath = (string)($vars['consolePath'] ?? 'bin/console');

                $prefix = match ($prefer) {
                    'php'     => trim($phpBinary . ' ' . $consolePath),
                    'symfony' => 'symfony console',
                    default   => 'symfony console',
                };

                $cmd  = (string)($vars['command'] ?? '');
                $args = array_map('strval', (array)($vars['args'] ?? []));

                $prettyArgs = array_map(
                    static fn(string $a) => preg_match('/^[A-Za-z0-9._:=\/-]+$/', $a) ? $a : escapeshellarg($a),
                    $args
                );

                $vars['code'] = trim($prefix . ' ' . $cmd . ' ' . implode(' ', $prettyArgs));
                $vars['lang'] = $vars['lang'] ?? 'bash';
                break;

            case 'composer':
            case 'composerrequire':
                $vars['code'] = $vars['multiline'] ?? $vars['code'] ?? '';
                $vars['lang'] = $vars['lang'] ?? 'bash';
                break;

            case 'yamlwrite':
                $vars['code'] = $vars['content'] ?? $vars['code'] ?? '';
                $vars['lang'] = 'yaml';
                break;

            case 'filewrite':
                $vars['code'] = $vars['content'] ?? $vars['code'] ?? '';
                $vars['lang'] = $vars['lang'] ?? $this->guessLang((string)($vars['path'] ?? ''));
                break;

            case 'displaycode':
                $vars['code'] = $vars['code'] ?? null;
                $vars['lang'] = $vars['lang'] ?? null;
                break;

            case 'showclass':
            case 'section':
                // as-is
                break;

            default:
                foreach (['multiline','command','cmd','content'] as $k) {
                    if (!empty($vars[$k]) && empty($vars['code'])) {
                        $vars['code'] = $vars[$k];
                        break;
                    }
                }
                $vars['lang'] = $vars['lang'] ?? 'text';
                break;
        }

        $vars['note'] = $vars['note'] ?? null;
        $vars['cwd']  = $vars['cwd']  ?? null;
        if (!array_key_exists('code', $vars)) {
            $vars['code'] = '';
        }

        return [$type, $vars, [
            'rawType'      => $rawType,
            'inferredType' => $type,
        ]];
    }

    /** Map artifact:spec to /artifacts/<task>/<step>/files/<actionKey>/<file> using context. */
    private function resolveDisplayCodeArtifact(array $context, array $vars): array
    {
        $val = $vars['path'] ?? $vars['target'] ?? null;
        if (!is_string($val) || !str_starts_with($val, 'artifact:')) return $vars;

        $spec  = substr($val, strlen('artifact:'));
        $parts = explode('::', $spec, 3);
        if (count($parts) !== 3) return $vars;

        [$stepTitleFromSpec, $actionId, $fileName] = $parts;
        $taskName  = (string)($context['__task_name']  ?? $context['code'] ?? 'slide');
        $stepTitle = (string)($context['__step_title'] ?? $stepTitleFromSpec);

        $safeTask   = preg_replace('/[^A-Za-z0-9._-]+/','-',$taskName)  ?: 'slide';
        $safeStep   = preg_replace('/[^A-Za-z0-9._-]+/','-',$stepTitle) ?: 'step';
        $safeAction = 'console-' . (preg_replace('/[^A-Za-z0-9._-]+/','-',$actionId) ?: 'action');

        $vars['path'] = "/artifacts/{$safeTask}/{$safeStep}/files/{$safeAction}/{$fileName}";
        return $vars;
    }

    /** Read from <projectDir>/public + /artifacts/... ; return null if missing. */
    private function loadCodeFromPath(string $path): ?string
    {
        $abs = null;
        if (str_starts_with($path, '/')) {
            $abs = rtrim($this->projectDir, '/') . '/public' . $path;
        } elseif (is_file($path)) {
            $abs = $path;
        }
        if ($abs && is_file($abs)) {
            $c = @file_get_contents($abs);
            return $c === false ? null : (string)$c;
        }
        return null;
    }

    private function guessLang(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => 'php', 'yml','yaml' => 'yaml', 'twig' => 'twig', 'json' => 'json',
            'html','htm' => 'html', 'md','markdown' => 'markdown', default => 'text',
        };
    }
}
