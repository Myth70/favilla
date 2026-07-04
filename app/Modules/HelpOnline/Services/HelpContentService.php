<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Services;

use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use App\Modules\HelpOnline\Support\HelpTextNormalizer;

/**
 * Round-trip tra la KB Help Online (DB) e i file `database/help/<module_key>.json`
 * versionati nel repo. L'export dumpa il contenuto corrente; l'import lo
 * ricarica (installazione fresca o rigenerazione) passando sempre dal
 * repository/normalizzatore — mai INSERT manuali dei campi derivati.
 */
class HelpContentService
{
    /** Ordine deterministico delle locale nei file esportati. */
    private const LOCALES = ['it', 'en', 'fr', 'de', 'es'];

    public function __construct(
        private ?HelpOnlineRepository $repository = null,
    ) {
        $this->repository = $this->repository ?? app(HelpOnlineRepository::class);
    }

    public function isSchemaReady(): bool
    {
        return $this->repository->isQaSchemaReady();
    }

    /**
     * Esporta ogni modulo help in `<targetDir>/<module_key>.json`.
     *
     * @return array<string, int> module_key => numero di entry canoniche esportate
     */
    public function exportAll(string $targetDir): array
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $summary = [];
        foreach ($this->repository->listAllModulesForExport() as $module) {
            $data = $this->buildModuleExport($module);
            $path = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $module['module_key'] . '.json';

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            file_put_contents($path, $json . "\n");

            $summary[$module['module_key']] = count($data['entries']);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, mixed>
     */
    private function buildModuleExport(array $module): array
    {
        $entries = $this->repository->listAllEntriesForExport((int) $module['id']);

        $canonicalById = [];
        $translationsByCanonical = [];
        foreach ($entries as $row) {
            if ($row['source_entry_id'] === null) {
                $canonicalById[(int) $row['id']] = $row;
            } else {
                $translationsByCanonical[(int) $row['source_entry_id']][] = $row;
            }
        }

        uasort($canonicalById, static fn (array $a, array $b): int =>
            [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

        $entriesOut = [];
        foreach ($canonicalById as $canonicalId => $canonical) {
            $locales = ['it' => $this->buildLocaleBlock($canonical)];

            $translations = $translationsByCanonical[$canonicalId] ?? [];
            foreach ($translations as $translation) {
                $locales[(string) $translation['locale']] = $this->buildLocaleBlock($translation);
            }

            $ordered = [];
            foreach (self::LOCALES as $locale) {
                if (isset($locales[$locale])) {
                    $ordered[$locale] = $locales[$locale];
                }
            }

            $entriesOut[] = [
                'audience' => $canonical['audience'],
                'route_name' => $canonical['route_name'],
                'permission_slug' => $canonical['permission_slug'],
                'ranking_weight' => (int) $canonical['ranking_weight'],
                'sort_order' => (int) $canonical['sort_order'],
                'locales' => $ordered,
            ];
        }

        return [
            'module_key' => $module['module_key'],
            'module_name' => $module['module_name'],
            'label' => $module['label'],
            'description' => $module['description'],
            'audience_default' => $module['audience_default'],
            'locale_default' => $module['locale_default'],
            'route_name' => $module['route_name'],
            'permission_slug' => $module['permission_slug'],
            'sort_order' => (int) $module['sort_order'],
            'entries' => $entriesOut,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildLocaleBlock(array $row): array
    {
        return [
            'question' => $row['question'],
            'answer_markdown' => $row['answer_markdown'],
            'excerpt' => $row['excerpt'],
            'aliases' => $this->repository->listAliasesForEntry((int) $row['id']),
        ];
    }

    /**
     * Importa i file `<sourceDir>/*.json`. Di default salta i moduli che
     * hanno già entry (installazione fresca sicura); con $force sostituisce
     * il contenuto esistente. Ricostruisce i search term alla fine.
     *
     * @return array{imported: array<string,int>, skipped: string[]}
     */
    public function importAll(string $sourceDir, ?string $onlyModuleKey = null, bool $force = false): array
    {
        $files = glob(rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files);

        $imported = [];
        $skipped = [];

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || !isset($data['module_key'])) {
                continue;
            }

            $moduleKey = (string) $data['module_key'];
            if ($onlyModuleKey !== null && $onlyModuleKey !== $moduleKey) {
                continue;
            }

            $moduleId = $this->resolveModuleId($data);
            $existing = $this->repository->countEntriesForModule($moduleId);

            if ($existing > 0 && !$force) {
                $skipped[] = $moduleKey;
                continue;
            }

            if ($existing > 0) {
                $this->repository->deleteAllEntriesForModule($moduleId);
            }

            foreach ((array) ($data['entries'] ?? []) as $entry) {
                $this->importEntry($moduleId, (array) $entry);
            }

            $imported[$moduleKey] = count((array) ($data['entries'] ?? []));
        }

        $this->repository->rebuildAllSearchTerms();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveModuleId(array $data): int
    {
        $moduleKey = (string) $data['module_key'];
        $existingModule = $this->repository->getQaModuleByKey($moduleKey);
        if ($existingModule !== null) {
            return (int) $existingModule['id'];
        }

        return $this->repository->createQaModule([
            'module_key' => $moduleKey,
            'module_name' => $data['module_name'] ?? $moduleKey,
            'label' => $data['label'] ?? ($data['module_name'] ?? $moduleKey),
            'description' => $data['description'] ?? null,
            'audience_default' => $data['audience_default'] ?? 'user',
            'locale_default' => $data['locale_default'] ?? 'it',
            'route_name' => $data['route_name'] ?? null,
            'permission_slug' => $data['permission_slug'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function importEntry(int $moduleId, array $entry): void
    {
        $locales = (array) ($entry['locales'] ?? []);
        if (!isset($locales['it'])) {
            throw new \RuntimeException('Entry help senza locale "it" canonica (module_id=' . $moduleId . ').');
        }

        $canonicalId = $this->createEntryRow($moduleId, null, 'it', $entry, (array) $locales['it']);

        foreach (self::LOCALES as $locale) {
            if ($locale === 'it' || !isset($locales[$locale])) {
                continue;
            }
            $this->createEntryRow($moduleId, $canonicalId, $locale, $entry, (array) $locales[$locale]);
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $localeData
     */
    private function createEntryRow(int $moduleId, ?int $sourceEntryId, string $locale, array $entry, array $localeData): int
    {
        $question = (string) ($localeData['question'] ?? '');
        $answer = (string) ($localeData['answer_markdown'] ?? '');

        $entryId = $this->repository->createQaEntry([
            'module_id' => $moduleId,
            'source_entry_id' => $sourceEntryId,
            'question' => $question,
            'normalized_question' => HelpTextNormalizer::normalize($question),
            'answer_markdown' => $answer,
            'answer_plain' => HelpTextNormalizer::normalize(strip_tags($answer)),
            'excerpt' => $localeData['excerpt'] ?? null,
            'audience' => $entry['audience'] ?? 'user',
            'locale' => $locale,
            'route_name' => $entry['route_name'] ?? null,
            'permission_slug' => $entry['permission_slug'] ?? null,
            'ranking_weight' => (int) ($entry['ranking_weight'] ?? 0),
            'is_active' => true,
            'sort_order' => (int) ($entry['sort_order'] ?? 0),
        ]);

        $aliases = array_values(array_filter(array_map('strval', (array) ($localeData['aliases'] ?? []))));
        if ($aliases !== []) {
            $this->repository->replaceQaEntryAliases($entryId, $aliases);
        }

        return $entryId;
    }
}
