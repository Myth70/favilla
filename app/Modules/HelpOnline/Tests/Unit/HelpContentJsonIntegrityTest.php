<?php

namespace App\Modules\HelpOnline\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test di integrità dei file `database/help/*.json` versionati: struttura,
 * parità delle 5 locale per ogni entry canonica, e validità di
 * permission_slug/route_name contro i cataloghi reali (project_context.json
 * e context/<Modulo>.json). Previene la reintroduzione di slug stantii
 * (es. `attivita.view` invece di `tasks.view`).
 */
class HelpContentJsonIntegrityTest extends TestCase
{
    private const LOCALES = ['it', 'en', 'fr', 'de', 'es'];

    /** @var string[]|null */
    private static ?array $validPermissionSlugs = null;

    /** @var string[]|null */
    private static ?array $validRouteNames = null;

    /**
     * @return array<int, array{0: string}>
     */
    public static function helpJsonFileProvider(): array
    {
        $files = glob(BASE_PATH . '/database/help/*.json') ?: [];
        sort($files);

        return array_map(static fn (string $f): array => [$f], $files);
    }

    /**
     * @dataProvider helpJsonFileProvider
     */
    public function testFileIsWellFormedWithFivePerfectLocaleParity(string $file): void
    {
        $data = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($data, "File JSON non valido: {$file}");

        foreach (['module_key', 'module_name', 'label', 'entries'] as $required) {
            $this->assertArrayHasKey($required, $data, "Campo '{$required}' mancante in {$file}");
        }

        $this->assertSame(
            basename($file, '.json'),
            $data['module_key'],
            "module_key deve combaciare col nome file in {$file}"
        );

        $this->assertIsArray($data['entries']);
        $this->assertNotEmpty($data['entries'], "Il modulo {$data['module_key']} non ha entry");

        foreach ($data['entries'] as $index => $entry) {
            $label = "{$data['module_key']}#{$index}";

            $this->assertArrayHasKey('locales', $entry, "{$label}: manca 'locales'");
            $this->assertSame(
                self::LOCALES,
                array_keys($entry['locales']),
                "{$label}: parità 5 locale attesa nell'ordine it/en/fr/de/es"
            );

            foreach (self::LOCALES as $locale) {
                $block = $entry['locales'][$locale];
                foreach (['question', 'answer_markdown', 'excerpt', 'aliases'] as $field) {
                    $this->assertArrayHasKey($field, $block, "{$label}[{$locale}]: manca '{$field}'");
                }
                $this->assertNotSame('', trim((string) $block['question']), "{$label}[{$locale}]: question vuota");
                $this->assertNotSame('', trim((string) $block['answer_markdown']), "{$label}[{$locale}]: answer_markdown vuota");
                $this->assertIsArray($block['aliases'], "{$label}[{$locale}]: aliases deve essere un array");
            }
        }
    }

    /**
     * @dataProvider helpJsonFileProvider
     */
    public function testPermissionSlugsAndRouteNamesAreValid(string $file): void
    {
        $data = json_decode((string) file_get_contents($file), true);
        $this->assertIsArray($data);

        $validPermissions = $this->loadValidPermissionSlugs();
        $validRoutes = $this->loadValidRouteNames();

        $this->assertPermissionValid($data['permission_slug'] ?? null, "{$data['module_key']} (modulo)", $validPermissions);
        $this->assertRouteValid($data['route_name'] ?? null, "{$data['module_key']} (modulo)", $validRoutes);

        foreach ($data['entries'] as $index => $entry) {
            $label = "{$data['module_key']}#{$index}";
            $this->assertPermissionValid($entry['permission_slug'] ?? null, $label, $validPermissions);
            $this->assertRouteValid($entry['route_name'] ?? null, $label, $validRoutes);
        }
    }

    /**
     * @dataProvider helpJsonFileProvider
     */
    public function testNoStaleItalianModulePrefixesRemain(string $file): void
    {
        $content = (string) file_get_contents($file);

        // Prefissi italiani stantii: i moduli sono stati rinominati in inglese
        // (tasks/calendar/contacts/feedback). Un match qui indica una regressione
        // di contenuto (slug/route/prosa non bonificati).
        $stalePattern = '/\b(attivita|calendario|contatti|segnalazioni)\.(index|view|create|edit|delete|manage|admin|share|import)\b/';

        $this->assertDoesNotMatchRegularExpression(
            $stalePattern,
            $content,
            "Slug/route stantio (prefisso italiano) trovato in {$file}"
        );
    }

    private function assertPermissionValid(?string $slug, string $label, array $validPermissions): void
    {
        if ($slug === null || $slug === '') {
            return;
        }
        $this->assertContains(
            $slug,
            $validPermissions,
            "{$label}: permission_slug '{$slug}' non esiste nel catalogo permessi (project_context.json)"
        );
    }

    private function assertRouteValid(?string $routeName, string $label, array $validRoutes): void
    {
        if ($routeName === null || $routeName === '') {
            return;
        }
        $this->assertContains(
            $routeName,
            $validRoutes,
            "{$label}: route_name '{$routeName}' non esiste nel catalogo route (context/<Modulo>.json)"
        );
    }

    /**
     * @return string[]
     */
    private function loadValidPermissionSlugs(): array
    {
        if (self::$validPermissionSlugs !== null) {
            return self::$validPermissionSlugs;
        }

        $data = json_decode((string) file_get_contents(BASE_PATH . '/project_context.json'), true);
        $permissions = $data['permissions'] ?? [];

        return self::$validPermissionSlugs = array_map(
            static fn (array $p): string => (string) $p['slug'],
            $permissions
        );
    }

    /**
     * @return string[]
     */
    private function loadValidRouteNames(): array
    {
        if (self::$validRouteNames !== null) {
            return self::$validRouteNames;
        }

        $names = [];
        foreach (glob(BASE_PATH . '/context/*.json') ?: [] as $contextFile) {
            if (basename($contextFile) === '_core.json') {
                continue;
            }
            $data = json_decode((string) file_get_contents($contextFile), true);
            foreach ($data['routes'] ?? [] as $route) {
                if (isset($route['name'])) {
                    $names[] = (string) $route['name'];
                }
            }
        }

        return self::$validRouteNames = $names;
    }
}
