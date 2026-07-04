<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Repositories;

use PDO;
use Throwable;

class HelpOnlineRepository
{
    private PDO $pdo;

    private ?bool $localeScopeReady = null;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * La colonna help_entries.source_entry_id è presente? (Migrazione i18n.)
     * Cache per istanza; su DB non ancora migrati le letture restano sul
     * comportamento base (tutte le entry, già di fatto solo italiane).
     */
    private function localeScopeReady(): bool
    {
        if ($this->localeScopeReady !== null) {
            return $this->localeScopeReady;
        }
        try {
            $this->pdo->query('SELECT source_entry_id FROM help_entries LIMIT 1');
            return $this->localeScopeReady = true;
        } catch (Throwable) {
            return $this->localeScopeReady = false;
        }
    }

    /**
     * Frammento WHERE che restringe le help_entries (alias `e`) alla locale
     * attiva, con fallback per-entry alla riga canonica italiana quando la
     * traduzione manca. Il canonico è 'it' (invariante dell'app). Stringa vuota
     * se lo scope locale non è disponibile (colonna assente): in tal caso non
     * esistono traduzioni e il comportamento base coincide con il solo italiano.
     *
     * La locale NON è input utente: è un enum app-controllato (it/en/fr/de/es).
     * Viene comunque validata a whitelist `^[a-z]{2}$` — come per gli ORDER BY
     * user-driven — così può essere interpolata in sicurezza, evitando di
     * rimaneggiare i binding posizionali delle query host.
     */
    private function localeScopeClause(?string $locale): string
    {
        if (!$this->localeScopeReady()) {
            return '';
        }

        $locale = preg_match('/^[a-z]{2}$/', (string) $locale) === 1 ? (string) $locale : 'it';

        return " AND (
            e.locale = '{$locale}'
            OR (
                e.source_entry_id IS NULL
                AND e.locale = 'it'
                AND NOT EXISTS (
                    SELECT 1 FROM help_entries hl_t
                    WHERE hl_t.source_entry_id = e.id
                      AND hl_t.locale = '{$locale}'
                      AND hl_t.is_active = 1
                )
            )
        )";
    }

    /**
     * Alias pubblico di isQaSchemaReady(): controlla disponibilità dello schema HelpOnline.
     */
    public function isSchemaReady(): bool
    {
        return $this->isQaSchemaReady();
    }

    public function isQaSchemaReady(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM help_modules LIMIT 1');
            $this->pdo->query('SELECT 1 FROM help_entries LIMIT 1');
            $this->pdo->query('SELECT 1 FROM help_entry_aliases LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function countQaEntries(): int
    {
        if (!$this->isQaSchemaReady()) {
            return 0;
        }

        return (int) $this->pdo->query('SELECT COUNT(*) FROM help_entries WHERE is_active = 1')->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Moduli QA
    // ─────────────────────────────────────────────────────────────────────

    public function listQaModules(): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT id, module_key, module_name, label, route_name, permission_slug
			 FROM help_modules
			 WHERE is_active = 1
			 ORDER BY sort_order ASC, module_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listQaModulesWithStats(): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT m.id,
					m.module_key,
					m.module_name,
					m.label,
					m.description,
					m.audience_default,
					m.locale_default,
					m.route_name,
					m.permission_slug,
					m.sort_order,
					m.is_active,
					COUNT(e.id) AS entries,
					COALESCE(SUM(alias_counts.aliases), 0) AS aliases
			 FROM help_modules m
			 LEFT JOIN help_entries e ON e.module_id = m.id AND e.is_active = 1
			 LEFT JOIN (
				 SELECT entry_id, COUNT(*) AS aliases
				 FROM help_entry_aliases
				 GROUP BY entry_id
			 ) alias_counts ON alias_counts.entry_id = e.id
			 GROUP BY m.id
			 ORDER BY m.is_active DESC, m.sort_order ASC, m.module_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createQaModule(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO help_modules (
				module_key, module_name, label, description,
				audience_default, locale_default, route_name, permission_slug,
				sort_order, is_active
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['module_key'],
            $data['module_name'],
            $data['label'],
            $data['description'] ?? null,
            $data['audience_default'] ?? 'user',
            $data['locale_default'] ?? 'it',
            $data['route_name'] ?? null,
            $data['permission_slug'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateQaModule(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE help_modules
			 SET module_key = ?, module_name = ?, label = ?, description = ?,
				 audience_default = ?, locale_default = ?, route_name = ?, permission_slug = ?,
				 sort_order = ?, is_active = ?
			 WHERE id = ?'
        );
        $stmt->execute([
            $data['module_key'],
            $data['module_name'],
            $data['label'],
            $data['description'] ?? null,
            $data['audience_default'] ?? 'user',
            $data['locale_default'] ?? 'it',
            $data['route_name'] ?? null,
            $data['permission_slug'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getQaModuleById(int $id): ?array
    {
        if (!$this->isQaSchemaReady() || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM help_modules WHERE id = ? LIMIT 1');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function deleteQaModule(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE help_modules SET is_active = 0 WHERE id = ?');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->pdo->prepare('UPDATE help_entries SET is_active = 0 WHERE module_id = ?')->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Tutti i moduli (anche inattivi), ordinamento deterministico per export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllModulesForExport(): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT * FROM help_modules ORDER BY module_key ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getQaModuleByKey(string $moduleKey): ?array
    {
        if (!$this->isQaSchemaReady() || $moduleKey === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM help_modules WHERE module_key = ? LIMIT 1');
        $stmt->execute([$moduleKey]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function countEntriesForModule(int $moduleId): int
    {
        if (!$this->isQaSchemaReady()) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM help_entries WHERE module_id = ?');
        $stmt->bindValue(1, $moduleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Rimozione hard (non soft-delete) di tutte le entry di un modulo — usata
     * dall'import con --force per rimpiazzare il contenuto. Il DELETE si
     * propaga via FK CASCADE ad alias e search term.
     */
    public function deleteAllEntriesForModule(int $moduleId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM help_entries WHERE module_id = ?');
        $stmt->bindValue(1, $moduleId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Tutte le entry attive di un modulo (tutte le locale, canoniche e
     * traduzioni), ordinamento deterministico per export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllEntriesForExport(int $moduleId): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM help_entries
             WHERE module_id = ? AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->bindValue(1, $moduleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Alias grezzi (solo testo) di una entry, ordinamento stabile per export.
     *
     * @return string[]
     */
    public function listAliasesForEntry(int $entryId): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT alias FROM help_entry_aliases WHERE entry_id = ? ORDER BY id ASC'
        );
        $stmt->bindValue(1, $entryId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function listQaQuestionsByModule(string $moduleName, int $limit = 3, ?string $locale = null): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $scope = $this->localeScopeClause($locale ?? locale());

        $stmt = $this->pdo->prepare(
            'SELECT e.question
			 FROM help_entries e
			 INNER JOIN help_modules m ON m.id = e.module_id
			 WHERE e.is_active = 1
			   AND m.module_name = ?' . $scope . '
			 ORDER BY e.sort_order ASC, e.question ASC
			 LIMIT ?'
        );
        $stmt->bindValue(1, $moduleName, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function listQuickPromptEntries(?string $moduleName = null, int $limit = 12, ?string $locale = null): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $scope = $this->localeScopeClause($locale ?? locale());

        $sql = 'SELECT e.id,
					e.question,
					e.permission_slug,
					m.module_name,
					e.ranking_weight,
					e.sort_order
				 FROM help_entries e
				 INNER JOIN help_modules m ON m.id = e.module_id
				 WHERE e.is_active = 1';

        $params = [];
        if ($moduleName !== null && $moduleName !== '') {
            $sql .= ' AND m.module_name = ?';
            $params[] = $moduleName;
        }

        $sql .= $scope . ' ORDER BY e.ranking_weight DESC, e.sort_order ASC, e.question ASC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listQaTopics(int $limit = 12, ?string $locale = null): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $scope = $this->localeScopeClause($locale ?? locale());

        $stmt = $this->pdo->prepare(
            'SELECT e.id,
					e.question AS title,
					e.excerpt,
					e.route_name,
					e.permission_slug,
					m.module_name,
					m.label AS module_label
			 FROM help_entries e
			 INNER JOIN help_modules m ON m.id = e.module_id
			 WHERE e.is_active = 1' . $scope . '
			 ORDER BY m.module_name ASC, e.sort_order ASC, e.question ASC
			 LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Entries QA
    // ─────────────────────────────────────────────────────────────────────

    public function searchQaCandidates(array $tokens, string $normalizedQuery, int $limit = 24, string $mode = 'AND', ?string $locale = null): array
    {
        if (!$this->isQaSchemaReady() || $tokens === [] || $normalizedQuery === '') {
            return [];
        }

        $mode    = strtoupper($mode) === 'OR' ? 'OR' : 'AND';
        $scope   = $this->localeScopeClause($locale ?? locale());
        $clauses = [];
        $params = [];

        foreach ($tokens as $token) {
            $like = '%' . $token . '%';
            $clauses[] = '(
				e.normalized_question LIKE ?
				OR e.answer_plain LIKE ?
				OR m.module_name LIKE ?
				OR EXISTS (
					SELECT 1 FROM help_entry_aliases alias_lookup
					WHERE alias_lookup.entry_id = e.id
					  AND alias_lookup.normalized_alias LIKE ?
				)
				OR EXISTS (
					SELECT 1 FROM help_search_terms term_lookup
					WHERE term_lookup.entry_id = e.id
					  AND term_lookup.normalized_term LIKE ?
				)
			)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = 'SELECT e.id,
				   e.module_id,
				   e.question,
				   e.normalized_question,
				   e.answer_markdown,
				   e.answer_plain,
				   e.excerpt,
				   e.route_name,
				   e.permission_slug,
				   e.ranking_weight,
				   e.sort_order,
				   m.module_name,
				   m.label AS module_label,
				   m.module_key,
				   COALESCE(aliases.aliases, "") AS aliases
			FROM help_entries e
			INNER JOIN help_modules m ON m.id = e.module_id
			LEFT JOIN (
				SELECT entry_id, GROUP_CONCAT(normalized_alias SEPARATOR "||") AS aliases
				FROM help_entry_aliases
				GROUP BY entry_id
			) aliases ON aliases.entry_id = e.id
			WHERE e.is_active = 1
			  AND (' . implode(' ' . $mode . ' ', $clauses) . ')' . $scope . '
			ORDER BY m.module_name ASC, e.sort_order ASC, e.question ASC
			LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getQaEntryById(int $entryId): ?array
    {
        if (!$this->isQaSchemaReady() || $entryId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT e.id,
					e.module_id,
					e.question,
					e.normalized_question,
					e.answer_markdown,
					e.answer_plain,
					e.excerpt,
					e.route_name,
					e.permission_slug,
					e.ranking_weight,
					e.sort_order,
					m.module_name,
					m.label AS module_label,
					m.module_key,
					COALESCE(aliases.aliases, "") AS aliases
			 FROM help_entries e
			 INNER JOIN help_modules m ON m.id = e.module_id
			 LEFT JOIN (
				 SELECT entry_id, GROUP_CONCAT(normalized_alias SEPARATOR "||") AS aliases
				 FROM help_entry_aliases
				 GROUP BY entry_id
			 ) aliases ON aliases.entry_id = e.id
			 WHERE e.id = ?
			   AND e.is_active = 1
			 LIMIT 1'
        );
        $stmt->bindValue(1, $entryId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Entry completa (tutte le colonne, inclusi locale e source_entry_id) per il
     * form admin di modifica. Non filtrata per locale: l'id è esplicito.
     */
    public function getQaEntryForEdit(int $entryId): ?array
    {
        if (!$this->isQaSchemaReady() || $entryId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT e.*, m.module_name, m.label AS module_label,
                    COALESCE(a.aliases, "") AS aliases
             FROM help_entries e
             INNER JOIN help_modules m ON m.id = e.module_id
             LEFT JOIN (
                 SELECT entry_id, GROUP_CONCAT(normalized_alias SEPARATOR "||") AS aliases
                 FROM help_entry_aliases
                 GROUP BY entry_id
             ) a ON a.entry_id = e.id
             WHERE e.id = ?
             LIMIT 1'
        );
        $stmt->bindValue(1, $entryId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Entry canoniche (source_entry_id IS NULL) selezionabili come sorgente di
     * traduzione. Esclude $excludeId (una entry non traduce sé stessa). Vuoto se
     * lo scope locale non è disponibile (colonna assente su DB non migrato).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCanonicalEntries(int $excludeId = 0): array
    {
        if (!$this->isQaSchemaReady() || !$this->localeScopeReady()) {
            return [];
        }

        $sql = 'SELECT e.id, e.question, e.locale, m.module_name
                FROM help_entries e
                INNER JOIN help_modules m ON m.id = e.module_id
                WHERE e.source_entry_id IS NULL';
        $params = [];
        if ($excludeId > 0) {
            $sql .= ' AND e.id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY m.module_name ASC, e.question ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $i => $v) {
            $stmt->bindValue($i + 1, $v, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRelatedQaEntries(int $moduleId, int $excludeEntryId, int $limit = 3, ?string $locale = null): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $scope = $this->localeScopeClause($locale ?? locale());

        $stmt = $this->pdo->prepare(
            'SELECT e.id,
					e.question AS title,
					e.question,
					e.excerpt,
					e.route_name,
					e.permission_slug
			 FROM help_entries e
			 WHERE e.module_id = ?
			   AND e.id <> ?
			   AND e.is_active = 1' . $scope . '
			 ORDER BY e.sort_order ASC, e.question ASC
			 LIMIT ?'
        );
        $stmt->bindValue(1, $moduleId, PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeEntryId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listQaEntries(array $filters = [], int $limit = 120): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(e.question) LIKE ? OR LOWER(e.excerpt) LIKE ? OR LOWER(m.module_name) LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'm.module_name = ?';
            $params[] = $module;
        }

        $audience = trim((string) ($filters['audience'] ?? ''));
        if ($audience !== '') {
            $where[] = 'e.audience = ?';
            $params[] = $audience;
        }

        $sql = 'SELECT e.id,
					e.module_id,
					e.question,
					e.excerpt,
					e.audience,
					e.locale,
					e.route_name,
					e.permission_slug,
					e.ranking_weight,
					e.sort_order,
					e.is_active,
					e.updated_at,
					m.module_name,
					COALESCE(alias_counts.aliases, 0) AS aliases
			FROM help_entries e
			INNER JOIN help_modules m ON m.id = e.module_id
			LEFT JOIN (
				SELECT entry_id, COUNT(*) AS aliases
				FROM help_entry_aliases
				GROUP BY entry_id
			) alias_counts ON alias_counts.entry_id = e.id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY e.is_active DESC, m.module_name ASC, e.sort_order ASC, e.question ASC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createQaEntry(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO help_entries (
				module_id, source_entry_id, question, normalized_question,
				answer_markdown, answer_plain, excerpt,
				audience, locale, route_name, permission_slug,
				ranking_weight, is_active, sort_order, indexed_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            (int) $data['module_id'],
            isset($data['source_entry_id']) && $data['source_entry_id'] !== '' ? (int) $data['source_entry_id'] : null,
            $data['question'],
            $data['normalized_question'],
            $data['answer_markdown'],
            $data['answer_plain'],
            $data['excerpt'] ?? null,
            $data['audience'] ?? 'user',
            $data['locale'] ?? 'it',
            $data['route_name'] ?? null,
            $data['permission_slug'] ?? null,
            (int) ($data['ranking_weight'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateQaEntry(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE help_entries
			 SET module_id = ?, source_entry_id = ?, question = ?, normalized_question = ?,
				 answer_markdown = ?, answer_plain = ?, excerpt = ?,
				 audience = ?, locale = ?, route_name = ?, permission_slug = ?,
				 ranking_weight = ?, is_active = ?, sort_order = ?,
				 indexed_at = CURRENT_TIMESTAMP
			 WHERE id = ?'
        );
        $stmt->execute([
            (int) $data['module_id'],
            isset($data['source_entry_id']) && $data['source_entry_id'] !== '' ? (int) $data['source_entry_id'] : null,
            $data['question'],
            $data['normalized_question'],
            $data['answer_markdown'],
            $data['answer_plain'],
            $data['excerpt'] ?? null,
            $data['audience'] ?? 'user',
            $data['locale'] ?? 'it',
            $data['route_name'] ?? null,
            $data['permission_slug'] ?? null,
            (int) ($data['ranking_weight'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteQaEntry(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE help_entries SET is_active = 0 WHERE id = ?');
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Alias QA
    // ─────────────────────────────────────────────────────────────────────

    public function listQaAliases(array $filters = [], int $limit = 200): array
    {
        if (!$this->isQaSchemaReady()) {
            return [];
        }

        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(a.alias) LIKE ? OR LOWER(e.question) LIKE ? OR LOWER(m.module_name) LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'm.module_name = ?';
            $params[] = $module;
        }

        $sql = 'SELECT a.id,
					a.entry_id,
					a.alias,
					a.normalized_alias,
					a.weight_bonus,
					e.question,
					m.module_name
			FROM help_entry_aliases a
			INNER JOIN help_entries e ON e.id = a.entry_id
			INNER JOIN help_modules m ON m.id = e.module_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.weight_bonus DESC, m.module_name ASC, e.question ASC, a.alias ASC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replaceQaEntryAliases(int $entryId, array $aliases): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmtDelete = $this->pdo->prepare('DELETE FROM help_entry_aliases WHERE entry_id = ?');
            $stmtDelete->execute([$entryId]);

            $stmtInsert = $this->pdo->prepare(
                'INSERT INTO help_entry_aliases (entry_id, alias, normalized_alias, weight_bonus)
				 VALUES (?, ?, ?, ?)'
            );

            foreach ($aliases as $alias) {
                $normalized = mb_strtolower(trim((string) $alias), 'UTF-8');
                if ($normalized === '') {
                    continue;
                }
                $stmtInsert->execute([$entryId, trim((string) $alias), $normalized, 8]);
            }

            $this->rebuildQaSearchTermsForEntry($entryId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function rebuildQaSearchTermsForEntry(int $entryId): void
    {
        $delete = $this->pdo->prepare('DELETE FROM help_search_terms WHERE entry_id = ?');
        $delete->execute([$entryId]);

        $entryStmt = $this->pdo->prepare(
            'SELECT e.question, e.normalized_question, m.module_name, m.label
			 FROM help_entries e
			 INNER JOIN help_modules m ON m.id = e.module_id
			 WHERE e.id = ?'
        );
        $entryStmt->execute([$entryId]);
        $row = $entryStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO help_search_terms (entry_id, term, normalized_term, term_type, term_weight)
			 VALUES (?, ?, ?, ?, ?)'
        );

        $insert->execute([$entryId, (string) $row['question'], (string) $row['normalized_question'], 'question', 30]);

        $moduleNorm = mb_strtolower(trim((string) ($row['module_name'] ?? '')), 'UTF-8');
        if ($moduleNorm !== '') {
            $insert->execute([$entryId, (string) $row['module_name'], $moduleNorm, 'module', 20]);
        }

        $moduleLabel = trim((string) ($row['label'] ?? ''));
        $moduleLabelNorm = mb_strtolower($moduleLabel, 'UTF-8');
        if ($moduleLabelNorm !== '' && $moduleLabelNorm !== $moduleNorm) {
            $insert->execute([$entryId, $moduleLabel, $moduleLabelNorm, 'module', 20]);
        }

        $aliasStmt = $this->pdo->prepare('SELECT alias, normalized_alias FROM help_entry_aliases WHERE entry_id = ?');
        $aliasStmt->execute([$entryId]);
        foreach ($aliasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $aliasRow) {
            $insert->execute([
                $entryId,
                (string) ($aliasRow['alias'] ?? ''),
                (string) ($aliasRow['normalized_alias'] ?? ''),
                'alias',
                25,
            ]);
        }
    }

    /**
     * Rigenera l'intera tabella help_search_terms dai dati QA correnti.
     * Usato dall'azione admin "Reindicizza".
     */
    public function rebuildAllSearchTerms(): array
    {
        if (!$this->isQaSchemaReady()) {
            return ['ok' => false, 'message' => 'Schema QA non disponibile.'];
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM help_search_terms');

            $this->pdo->exec(
                'INSERT INTO help_search_terms (entry_id, term, normalized_term, term_type, term_weight)
				 SELECT id, question, normalized_question, "question", 30
				 FROM help_entries
				 WHERE is_active = 1 AND normalized_question <> ""'
            );

            $this->pdo->exec(
                'INSERT INTO help_search_terms (entry_id, term, normalized_term, term_type, term_weight)
				 SELECT e.id, m.module_name, LOWER(TRIM(m.module_name)), "module", 20
				 FROM help_entries e
				 INNER JOIN help_modules m ON m.id = e.module_id
				 WHERE e.is_active = 1
				   AND COALESCE(NULLIF(m.module_name, ""), "") <> ""'
            );

            $this->pdo->exec(
                'INSERT INTO help_search_terms (entry_id, term, normalized_term, term_type, term_weight)
				 SELECT e.id, m.label, LOWER(TRIM(m.label)), "module", 20
				 FROM help_entries e
				 INNER JOIN help_modules m ON m.id = e.module_id
				 WHERE e.is_active = 1
				   AND COALESCE(NULLIF(m.label, ""), "") <> ""
				   AND LOWER(TRIM(m.label)) <> LOWER(TRIM(m.module_name))'
            );

            $this->pdo->exec(
                'INSERT INTO help_search_terms (entry_id, term, normalized_term, term_type, term_weight)
				 SELECT a.entry_id, a.alias, a.normalized_alias, "alias", 25
				 FROM help_entry_aliases a
				 WHERE COALESCE(NULLIF(a.normalized_alias, ""), "") <> ""'
            );

            $entries = $this->countQaEntries();
            $terms = (int) $this->pdo->query('SELECT COUNT(*) FROM help_search_terms')->fetchColumn();

            $this->pdo->commit();
            return ['ok' => true, 'entries' => $entries, 'terms' => $terms];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function getQaSummary(): array
    {
        if (!$this->isQaSchemaReady()) {
            return [
                'modules' => 0,
                'entries' => 0,
                'aliases' => 0,
                'queries' => (int) $this->pdo->query('SELECT COUNT(*) FROM help_queries')->fetchColumn(),
            ];
        }

        return [
            'modules' => (int) $this->pdo->query('SELECT COUNT(*) FROM help_modules WHERE is_active = 1')->fetchColumn(),
            'entries' => (int) $this->pdo->query('SELECT COUNT(*) FROM help_entries WHERE is_active = 1')->fetchColumn(),
            'aliases' => (int) $this->pdo->query('SELECT COUNT(*) FROM help_entry_aliases')->fetchColumn(),
            'queries' => (int) $this->pdo->query('SELECT COUNT(*) FROM help_queries')->fetchColumn(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Query log / analytics
    // ─────────────────────────────────────────────────────────────────────

    public function getQueryStats(): array
    {
        $row = $this->pdo->query(
            'SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN matched_entry_id IS NOT NULL THEN 1 ELSE 0 END) AS matched,
				SUM(CASE WHEN matched_entry_id IS NULL THEN 1 ELSE 0 END) AS unmatched,
				SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) AS helpful,
				SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) AS unhelpful,
				SUM(CASE WHEN helpful IS NULL THEN 1 ELSE 0 END) AS pending
			 FROM help_queries'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'matched' => (int) ($row['matched'] ?? 0),
            'unmatched' => (int) ($row['unmatched'] ?? 0),
            'helpful' => (int) ($row['helpful'] ?? 0),
            'unhelpful' => (int) ($row['unhelpful'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    public function getTopUnmatchedQueries(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT normalized_query,
					MAX(query_text) AS query_text,
					MAX(context_module) AS context_module,
					COUNT(*) AS occurrences,
					MAX(created_at) AS last_seen
			 FROM help_queries
			 WHERE matched_entry_id IS NULL
			   AND normalized_query <> ""
			 GROUP BY normalized_query
			 ORDER BY occurrences DESC, last_seen DESC
			 LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getQueryOwner(int $queryId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM help_queries WHERE id = ? LIMIT 1');
        $stmt->bindValue(1, $queryId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $row['user_id'] !== null ? (int) $row['user_id'] : 0;
    }

    public function recordQuery(array $data): int
    {
        $matchedEntryId = isset($data['matched_entry_id']) ? (int) $data['matched_entry_id'] : null;
        $matchedEntryId = $this->resolveExistingHelpEntryId($matchedEntryId);

        $stmt = $this->pdo->prepare(
            'INSERT INTO help_queries (
				user_id, query_text, normalized_query,
				context_path, context_module,
				matched_entry_id, confidence, response_title
			 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $params = [
            $data['user_id'] ?? null,
            $data['query_text'] ?? '',
            $data['normalized_query'] ?? '',
            $data['context_path'] ?? null,
            $data['context_module'] ?? null,
            $matchedEntryId,
            $data['confidence'] ?? null,
            $data['response_title'] ?? null,
        ];

        try {
            $stmt->execute($params);
        } catch (Throwable $e) {
            if ($this->isHelpQueriesReferenceViolation($e) && $matchedEntryId !== null) {
                $params[5] = null;
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function resolveExistingHelpEntryId(?int $entryId): ?int
    {
        if ($entryId === null || $entryId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM help_entries WHERE id = ? LIMIT 1');
        $stmt->bindValue(1, $entryId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false ? $entryId : null;
    }

    private function isHelpQueriesReferenceViolation(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (!str_contains($message, '1452')) {
            return false;
        }

        return str_contains($message, 'help_queries_ibfk_3');
    }

    public function updateQueryFeedback(int $queryId, ?bool $helpful): void
    {
        $stmt = $this->pdo->prepare('UPDATE help_queries SET helpful = ? WHERE id = ?');
        if ($helpful === null) {
            $stmt->bindValue(1, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(1, $helpful ? 1 : 0, PDO::PARAM_INT);
        }
        $stmt->bindValue(2, $queryId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listQueries(array $filters = [], int $limit = 100): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(q.query_text LIKE ? OR q.response_title LIKE ? OR e.question LIKE ? OR u.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'q.context_module = ?';
            $params[] = $module;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'unmatched') {
            $where[] = 'q.matched_entry_id IS NULL';
        } elseif ($status === 'positive') {
            $where[] = 'q.helpful = 1';
        } elseif ($status === 'negative') {
            $where[] = 'q.helpful = 0';
        } elseif ($status === 'pending') {
            $where[] = 'q.helpful IS NULL';
        }

        $sql = 'SELECT q.id,
					   q.query_text,
					   q.context_module,
					   q.confidence,
					   q.helpful,
					   q.response_title,
					   q.created_at,
					   u.name AS user_name,
					   e.question AS entry_title,
					   m.module_name AS module_title
				FROM help_queries q
				LEFT JOIN users u ON u.id = q.user_id
				LEFT JOIN help_entries e ON e.id = q.matched_entry_id
				LEFT JOIN help_modules m ON m.id = e.module_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY q.created_at DESC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
