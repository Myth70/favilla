<?php

declare(strict_types=1);

namespace App\Cli\Commands;

/**
 * Verify translation completeness against the Italian (canonical) baseline.
 *
 * Usage:
 *   php favilla lang:check                 # check all locales vs `it`
 *   php favilla lang:check --locale=fr     # check one locale
 *   php favilla lang:check --strict        # exit 1 also on EXTRA/EMPTY keys
 *
 * For every namespace file present in resources/lang/it, each target locale is
 * checked for MISSING keys (present in it, absent in target), EXTRA keys
 * (present in target, absent in it) and EMPTY values. Exit code 1 when any
 * locale has missing keys (CI-friendly).
 */
class LangCheckCommand
{
    private string $langDir;

    public function handle(array $args): void
    {
        $this->langDir = BASE_PATH . '/resources/lang';

        $only   = null;
        $strict = in_array('--strict', $args, true);
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--locale=')) {
                $only = substr($arg, strlen('--locale='));
            }
        }

        $config    = require BASE_PATH . '/app/Config/localization.php';
        $base      = (string) ($config['fallback'] ?? 'it');
        $supported = (array) ($config['supported'] ?? ['it']);

        $targets = $only !== null && $only !== 'all'
            ? [$only]
            : array_values(array_filter($supported, fn ($l) => $l !== $base));

        $baseline = $this->loadLocale($base);
        if ($baseline === []) {
            echo "[ERRORE] Nessun file di lingua nella baseline '{$base}' ({$this->langDir}/{$base}).\n";
            exit(1);
        }

        $baseKeyCount = array_sum(array_map('count', $baseline));

        echo "\nlang:check — baseline '{$base}' ({$baseKeyCount} chiavi in " . count($baseline) . " namespace)\n";
        echo str_repeat('=', 64) . "\n";

        $hadMissing = false;
        $hadIssue   = false;

        foreach ($targets as $locale) {
            if (!in_array($locale, $supported, true)) {
                echo "\n[{$locale}] non è tra i locale supportati — salto.\n";
                continue;
            }

            $target = $this->loadLocale($locale);
            $missing = [];
            $extra   = [];
            $empty   = [];

            foreach ($baseline as $ns => $keys) {
                $tns = $target[$ns] ?? [];
                foreach ($keys as $key => $val) {
                    if (!array_key_exists($key, $tns)) {
                        $missing[] = "{$ns}.{$key}";
                    } elseif ($tns[$key] === '') {
                        $empty[] = "{$ns}.{$key}";
                    }
                }
                foreach ($tns as $key => $val) {
                    if (!array_key_exists($key, $keys)) {
                        $extra[] = "{$ns}.{$key}";
                    }
                }
            }

            $status = empty($missing) ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
            printf(
                "\n[%s] %s  missing=%d  extra=%d  empty=%d\n",
                $locale,
                $status,
                count($missing),
                count($extra),
                count($empty)
            );

            $this->printList('MISSING', $missing);
            if ($strict) {
                $this->printList('EXTRA', $extra);
                $this->printList('EMPTY', $empty);
            }

            if (!empty($missing)) {
                $hadMissing = true;
            }
            if (!empty($missing) || !empty($extra) || !empty($empty)) {
                $hadIssue = true;
            }
        }

        echo "\n" . str_repeat('-', 64) . "\n";
        if ($hadMissing || ($strict && $hadIssue)) {
            echo "[ATTENZIONE] Sono presenti chiavi mancanti o incongruenze.\n";
            exit(1);
        }
        echo "[OK] Tutte le traduzioni richieste sono presenti.\n";
    }

    /**
     * @return array<string,array<string,mixed>> namespace => flattened keys
     */
    private function loadLocale(string $locale): array
    {
        $dir = $this->langDir . '/' . $locale;
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (glob($dir . '/*.php') as $file) {
            $ns   = basename($file, '.php');
            $data = require $file;
            $out[$ns] = is_array($data) ? $this->flatten($data) : [];
        }
        return $out;
    }

    /**
     * @param array<int|string,mixed> $arr
     * @return array<string,mixed>
     */
    private function flatten(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $path);
            } else {
                $out[$path] = $v;
            }
        }
        return $out;
    }

    /**
     * @param list<string> $items
     */
    private function printList(string $label, array $items): void
    {
        if (empty($items)) {
            return;
        }
        $shown = array_slice($items, 0, 40);
        foreach ($shown as $item) {
            echo "    {$label}: {$item}\n";
        }
        if (count($items) > count($shown)) {
            echo '    ... e altre ' . (count($items) - count($shown)) . " chiavi {$label}\n";
        }
    }
}
