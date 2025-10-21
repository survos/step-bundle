<?php

declare(strict_types=1);

namespace Survos\StepBundle\Util;

final class ApiKeyUtil
{
    /**
     * Ensure API keys for a named provider are present in .env.local.
     *
     * @param string        $provider   e.g. 'ip2location', 'bunny', 'flickr'
     * @param string        $projectDir absolute or relative dir with .env.local
     * @param callable|null $ask        fn(string $prompt, bool $hidden): ?string
     * @param callable|null $out        fn(string $message): void
     * @param array|null    $override   provider spec override/extension
     *
     * @return array Newly written key=>value pairs
     */
    public static function ensureFor(
        string $provider,
        string $projectDir,
        ?callable $ask = null,
        ?callable $out = null,
        ?array $override = null
    ): array {
        $registry = self::getRegistry();

        if ($override) {
            $registry[$provider] = array_replace_recursive($registry[$provider] ?? [], $override);
        }

        if (!isset($registry[$provider])) {
            throw new \InvalidArgumentException("Unknown API provider: {$provider}");
        }

        $spec = $registry[$provider];

        // 1) Show helpful link/instructions
        if ($out) {
            if (!empty($spec['docs_url'])) {
                $out(sprintf("Open to create/retrieve API key:\n\n %s", $spec['docs_url']));
            }
            if (!empty($spec['note'])) {
                $out($spec['note']);
            }
        }

        // 2) Build ensure spec for EnvUtil
        $ensure = [];
        foreach ($spec['env'] as $envVar => $cfg) {
            if ($cfg === true) {
                $ensure[$envVar] = ['prompt' => "Enter {$envVar}", 'hidden' => true];
            } elseif (is_string($cfg)) {
                $ensure[$envVar] = ['prompt' => $cfg, 'hidden' => false];
            } elseif (is_array($cfg)) {
                $ensure[$envVar] = [
                    'prompt' => $cfg['prompt'] ?? "Enter {$envVar}",
                    'hidden' => (bool)($cfg['hidden'] ?? true),
                ];
            } else {
                throw new \InvalidArgumentException("Invalid env spec for {$envVar}");
            }
        }

        return EnvUtil::ensureEnvVars($projectDir, $ensure, $ask);
    }

    /**
     * Default registry loader with optional app-level override.
     *
     * Looks for:
     *  - bundle default:   <bundle>/resources/apikey/providers.php
     *  - app override (optional): project config file returned by self::getAppOverridePath()
     */
    public static function getRegistry(): array
    {
        $defaultsPath = \dirname(__DIR__, 2) . '/resources/apikey/providers.php';
        $registry = is_file($defaultsPath) ? (require $defaultsPath) : [];

        // Optional app override file: returns array just like defaults
        $overridePath = self::getAppOverridePath();
        if ($overridePath && is_file($overridePath)) {
            $over = require $overridePath;
            if (is_array($over)) {
                // deep merge provider-by-provider
                foreach ($over as $name => $spec) {
                    $registry[$name] = array_replace_recursive($registry[$name] ?? [], $spec);
                }
            }
        }

        return is_array($registry) ? $registry : [];
    }

    /**
     * Where apps can place an override/extension file.
     * Change this if you prefer a different path.
     */
    public static function getAppOverridePath(): ?string
    {
        // Conventional app-level path; safe to change to suit your monorepo
        $candidate = \getcwd() . '/config/survos_step/apikey.php';
        return $candidate;
    }
}
