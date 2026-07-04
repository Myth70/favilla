<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Services;

use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use App\Modules\HelpOnline\Support\HelpTextNormalizer;
use Throwable;

/**
 * Lato amministrativo dell'Help Online: CRUD moduli/entries/alias,
 * reindicizzazione del motore e analytics del query log.
 * Il percorso utente (ask/feedback/panel) vive in HelpOnlineService.
 */
class HelpAdminService
{
    public function __construct(
        private ?HelpOnlineRepository $repository = null,
    ) {
        $this->repository = $this->repository ?? app(HelpOnlineRepository::class);
    }

    public function isSchemaReady(): bool
    {
        return $this->repository->isSchemaReady();
    }

    /**
     * Reindicizza i search term di tutte le entry attive.
     */
    public function sync(): array
    {
        if (!$this->repository->isQaSchemaReady()) {
            return ['ok' => false, 'message' => 'Schema QA non disponibile. Esegui la migrazione del modulo.'];
        }

        return $this->repository->rebuildAllSearchTerms();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Data builders (overview, moduli, entries, query log)
    // ─────────────────────────────────────────────────────────────────────

    public function getAdminOverviewData(): array
    {
        $schemaReady = $this->repository->isSchemaReady();

        $summary = $schemaReady ? $this->repository->getQaSummary() : [
            'modules' => 0, 'entries' => 0, 'aliases' => 0, 'queries' => 0,
        ];

        $stats = $schemaReady ? $this->repository->getQueryStats() : [
            'total' => 0, 'matched' => 0, 'unmatched' => 0,
            'helpful' => 0, 'unhelpful' => 0, 'pending' => 0,
        ];
        $stats['match_rate'] = $stats['total'] > 0
            ? (int) round(($stats['matched'] / $stats['total']) * 100)
            : 0;
        $rated = $stats['helpful'] + $stats['unhelpful'];
        $stats['helpful_rate'] = $rated > 0
            ? (int) round(($stats['helpful'] / $rated) * 100)
            : 0;

        return [
            'schemaReady' => $schemaReady,
            'summary' => $summary,
            'stats' => $stats,
            'topUnmatched' => $schemaReady ? $this->repository->getTopUnmatchedQueries(5) : [],
            'modules' => $schemaReady ? array_slice($this->repository->listQaModulesWithStats(), 0, 8) : [],
            'entries' => $schemaReady ? array_slice($this->repository->listQaEntries([], 8), 0, 8) : [],
            'queries' => $schemaReady ? array_slice($this->repository->listQueries([], 8), 0, 8) : [],
            'topAliases' => $schemaReady ? array_slice($this->repository->listQaAliases([], 20), 0, 20) : [],
        ];
    }

    public function getAdminModulesData(): array
    {
        $schemaReady = $this->repository->isSchemaReady();

        return [
            'schemaReady' => $schemaReady,
            'modules' => $schemaReady ? $this->repository->listQaModulesWithStats() : [],
        ];
    }

    public function getAdminModuleEditData(int $id): ?array
    {
        return $this->repository->getQaModuleById($id);
    }

    public function createQaModule(array $payload): array
    {
        if (!$this->repository->isQaSchemaReady()) {
            return ['ok' => false, 'message' => 'Schema QA non disponibile.'];
        }

        $moduleKey = mb_strtolower(trim((string) ($payload['module_key'] ?? '')), 'UTF-8');
        $moduleName = trim((string) ($payload['module_name'] ?? ''));
        $label = trim((string) ($payload['label'] ?? $moduleName));
        if ($moduleName === '' || $moduleKey === '' || $label === '') {
            return ['ok' => false, 'message' => 'Compila module key, nome e label del modulo.'];
        }

        try {
            $this->repository->createQaModule([
                'module_key' => $moduleKey,
                'module_name' => $moduleName,
                'label' => $label,
                'description' => trim((string) ($payload['description'] ?? '')),
                'audience_default' => trim((string) ($payload['audience_default'] ?? 'user')) ?: 'user',
                'locale_default' => trim((string) ($payload['locale_default'] ?? 'it')) ?: 'it',
                'route_name' => trim((string) ($payload['route_name'] ?? '')) ?: null,
                'permission_slug' => trim((string) ($payload['permission_slug'] ?? '')) ?: null,
                'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
                'is_active' => (($payload['is_active'] ?? '1') === '1'),
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Creazione modulo non riuscita: ' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Modulo help creato con successo.'];
    }

    public function updateQaModule(int $id, array $payload): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Modulo non valido.'];
        }

        $moduleKey = mb_strtolower(trim((string) ($payload['module_key'] ?? '')), 'UTF-8');
        $moduleName = trim((string) ($payload['module_name'] ?? ''));
        $label = trim((string) ($payload['label'] ?? $moduleName));
        if ($moduleName === '' || $moduleKey === '' || $label === '') {
            return ['ok' => false, 'message' => 'Compila module key, nome e label del modulo.'];
        }

        try {
            $updated = $this->repository->updateQaModule($id, [
                'module_key' => $moduleKey,
                'module_name' => $moduleName,
                'label' => $label,
                'description' => trim((string) ($payload['description'] ?? '')),
                'audience_default' => trim((string) ($payload['audience_default'] ?? 'user')) ?: 'user',
                'locale_default' => trim((string) ($payload['locale_default'] ?? 'it')) ?: 'it',
                'route_name' => trim((string) ($payload['route_name'] ?? '')) ?: null,
                'permission_slug' => trim((string) ($payload['permission_slug'] ?? '')) ?: null,
                'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
                'is_active' => (($payload['is_active'] ?? '1') === '1'),
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Aggiornamento modulo non riuscito: ' . $e->getMessage()];
        }

        if (!$updated) {
            return ['ok' => false, 'message' => 'Modulo non aggiornato.'];
        }

        return ['ok' => true, 'message' => 'Modulo help aggiornato.'];
    }

    public function deleteQaModule(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Modulo non valido.'];
        }

        $deleted = $this->repository->deleteQaModule($id);
        if (!$deleted) {
            return ['ok' => false, 'message' => 'Modulo non eliminato.'];
        }

        return ['ok' => true, 'message' => 'Modulo disattivato e record associati disattivati.'];
    }

    public function getAdminEntriesData(array $filters = []): array
    {
        $schemaReady = $this->repository->isSchemaReady();

        return [
            'schemaReady' => $schemaReady,
            'filters' => $filters,
            'entries' => $schemaReady ? $this->repository->listQaEntries($filters, 150) : [],
            'modules' => $schemaReady ? array_map(
                static fn (array $row): string => (string) ($row['module_name'] ?? ''),
                $this->repository->listQaModules()
            ) : [],
            'moduleOptions' => $schemaReady ? $this->repository->listQaModules() : [],
            'topAliases' => $schemaReady ? $this->repository->listQaAliases([], 20) : [],
        ];
    }

    public function getAdminEntryEditData(int $id): ?array
    {
        $entry = $this->repository->getQaEntryForEdit($id);
        if ($entry === null) {
            return null;
        }

        return array_merge($entry, [
            'moduleOptions'    => $this->repository->listQaModules(),
            'canonicalEntries' => $this->repository->listCanonicalEntries($id),
        ]);
    }

    public function createQaEntry(array $payload): array
    {
        $question = trim((string) ($payload['question'] ?? ''));
        $answer = str_replace(["\r\n", "\r"], "\n", (string) ($payload['answer_markdown'] ?? ''));
        $moduleId = max(0, (int) ($payload['module_id'] ?? 0));

        if ($moduleId <= 0 || $question === '' || trim($answer) === '') {
            return ['ok' => false, 'message' => 'Compila modulo, domanda e risposta.'];
        }

        try {
            $entryId = $this->repository->createQaEntry([
                'module_id' => $moduleId,
                'source_entry_id' => $payload['source_entry_id'] ?? null,
                'question' => $question,
                'normalized_question' => HelpTextNormalizer::normalize($question),
                'answer_markdown' => $answer,
                'answer_plain' => HelpTextNormalizer::normalize(strip_tags($answer)),
                'excerpt' => trim((string) ($payload['excerpt'] ?? '')),
                'audience' => trim((string) ($payload['audience'] ?? 'user')) ?: 'user',
                'locale' => trim((string) ($payload['locale'] ?? 'it')) ?: 'it',
                'route_name' => trim((string) ($payload['route_name'] ?? '')) ?: null,
                'permission_slug' => trim((string) ($payload['permission_slug'] ?? '')) ?: null,
                'ranking_weight' => (int) ($payload['ranking_weight'] ?? 0),
                'is_active' => (($payload['is_active'] ?? '1') === '1'),
                'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
            ]);

            $aliases = $this->parseAliases((string) ($payload['aliases'] ?? ''));
            $this->repository->replaceQaEntryAliases($entryId, $aliases);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Creazione record non riuscita: ' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Record domanda/risposta creato.', 'id' => $entryId];
    }

    public function updateQaEntry(int $id, array $payload): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Record non valido.'];
        }

        $question = trim((string) ($payload['question'] ?? ''));
        $answer = str_replace(["\r\n", "\r"], "\n", (string) ($payload['answer_markdown'] ?? ''));
        $moduleId = max(0, (int) ($payload['module_id'] ?? 0));

        if ($moduleId <= 0 || $question === '' || trim($answer) === '') {
            return ['ok' => false, 'message' => 'Compila modulo, domanda e risposta.'];
        }

        try {
            $updated = $this->repository->updateQaEntry($id, [
                'module_id' => $moduleId,
                'source_entry_id' => $payload['source_entry_id'] ?? null,
                'question' => $question,
                'normalized_question' => HelpTextNormalizer::normalize($question),
                'answer_markdown' => $answer,
                'answer_plain' => HelpTextNormalizer::normalize(strip_tags($answer)),
                'excerpt' => trim((string) ($payload['excerpt'] ?? '')),
                'audience' => trim((string) ($payload['audience'] ?? 'user')) ?: 'user',
                'locale' => trim((string) ($payload['locale'] ?? 'it')) ?: 'it',
                'route_name' => trim((string) ($payload['route_name'] ?? '')) ?: null,
                'permission_slug' => trim((string) ($payload['permission_slug'] ?? '')) ?: null,
                'ranking_weight' => (int) ($payload['ranking_weight'] ?? 0),
                'is_active' => (($payload['is_active'] ?? '1') === '1'),
                'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
            ]);

            $aliases = $this->parseAliases((string) ($payload['aliases'] ?? ''));
            $this->repository->replaceQaEntryAliases($id, $aliases);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Aggiornamento record non riuscito: ' . $e->getMessage()];
        }

        if (!$updated) {
            return ['ok' => false, 'message' => 'Record non aggiornato.'];
        }

        return ['ok' => true, 'message' => 'Record domanda/risposta aggiornato.'];
    }

    public function deleteQaEntry(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Record non valido.'];
        }

        $deleted = $this->repository->deleteQaEntry($id);
        if (!$deleted) {
            return ['ok' => false, 'message' => 'Record non eliminato.'];
        }

        return ['ok' => true, 'message' => 'Record domanda/risposta disattivato.'];
    }

    public function saveQaEntryAliases(int $id, string $rawAliases): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Record non valido.'];
        }

        try {
            $this->repository->replaceQaEntryAliases($id, $this->parseAliases($rawAliases));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Salvataggio alias non riuscito: ' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Alias aggiornati.'];
    }

    public function getAdminQueriesData(array $filters = []): array
    {
        $schemaReady = $this->repository->isSchemaReady();

        return [
            'schemaReady' => $schemaReady,
            'filters' => $filters,
            'queries' => $schemaReady ? $this->repository->listQueries($filters, 120) : [],
            'modules' => $schemaReady
                ? array_map(
                    static fn (array $row): string => (string) ($row['module_name'] ?? ''),
                    $this->repository->listQaModules()
                )
                : [],
        ];
    }

    /**
     * Alias separati da virgola, punto e virgola o newline (deduplicati).
     *
     * @return string[]
     */
    private function parseAliases(string $rawAliases): array
    {
        $chunks = preg_split('/[\n,;]+/u', $rawAliases) ?: [];
        $clean = [];
        foreach ($chunks as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '') {
                $clean[$alias] = true;
            }
        }

        return array_keys($clean);
    }
}
