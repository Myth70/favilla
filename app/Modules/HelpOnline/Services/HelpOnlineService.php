<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Services;

use App\Core\ModuleLoader;
use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use App\Modules\HelpOnline\Support\HelpTextNormalizer;
use Throwable;

/**
 * Percorso utente dell'Help Online: ricerca knowledge base, ask/feedback,
 * pannello contestuale, quick prompt e rendering markdown delle risposte.
 * Il lato amministrativo (CRUD, sync, analytics) vive in HelpAdminService.
 */
class HelpOnlineService
{
    private const MIN_SCORE_THRESHOLD = 6;
    private const MAX_QUERY_LENGTH = 500;
    private const QUICK_PROMPT_LIMIT = 6;

    private ?array $moduleContextMap = null;
    private ?array $moduleDisplayNameMap = null;

    public function __construct(
        private ?HelpOnlineRepository $repository = null,
        private ?ModuleLoader $moduleLoader = null,
    ) {
        $this->repository = $this->repository ?? app(HelpOnlineRepository::class);
    }

    public function isSchemaReady(): bool
    {
        return $this->repository->isSchemaReady();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Endpoint pubblici (used by HelpOnlineController + HelpOnlineSearchProvider)
    // ─────────────────────────────────────────────────────────────────────

    public function getPageData(string $query, ?int $selectedChunkId, string $contextPath): array
    {
        $query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH, 'UTF-8');
        $schemaReady = $this->repository->isSchemaReady();

        $contextModule = $this->inferContextFromPath($contextPath);
        $contextModuleTitle = $this->getModuleDisplayName($contextModule) ?? $contextModule;

        $results = $schemaReady && $query !== ''
            ? $this->searchKnowledgeBase($query, $contextPath, 12)
            : [];

        $selectedEntry = null;
        if ($schemaReady && $selectedChunkId !== null && $selectedChunkId > 0) {
            $selectedEntry = $this->decorateQaEntry(
                $this->repository->getQaEntryById($selectedChunkId),
                $contextPath
            );
        }
        if ($selectedEntry === null && $results !== []) {
            $selectedEntry = $results[0];
        }

        $topics = $schemaReady ? $this->getTopicCards($contextModule, 100) : [];
        $topicsByModule = [];
        foreach ($topics as $topic) {
            $module = (string) ($topic['module_name'] ?? 'Generale');
            $topicsByModule[$module][] = $topic;
        }

        $summary = $schemaReady ? $this->repository->getQaSummary() : [
            'modules' => 0, 'entries' => 0, 'aliases' => 0, 'queries' => 0,
        ];

        $stats = [
            'entries' => (int) ($summary['entries'] ?? 0),
            'aliases' => (int) ($summary['aliases'] ?? 0),
            'modules' => (int) ($summary['modules'] ?? 0),
            'topics' => count($topics),
        ];

        return [
            'schemaReady' => $schemaReady,
            'query' => $query,
            'selectedChunk' => $selectedEntry,
            'results' => $results,
            'topics' => $topics,
            'topicsByModule' => $topicsByModule,
            'contextModule' => $contextModule,
            'contextModuleTitle' => $contextModuleTitle,
            'quickPrompts' => $this->getQuickPrompts($contextModule),
            'stats' => $stats,
        ];
    }

    public function getPanelData(string $contextPath, string $pageTitle = ''): array
    {
        $schemaReady = $this->repository->isSchemaReady();
        $contextModule = $this->inferContextFromPath($contextPath);
        $contextModuleTitle = $this->getModuleDisplayName($contextModule) ?? $contextModule;

        return [
            'schemaReady' => $schemaReady,
            'contextModule' => $contextModule,
            'contextModuleTitle' => $contextModuleTitle,
            'contextLabel' => $this->buildContextLabel($contextModule, $pageTitle),
            'quickPrompts' => $this->getQuickPrompts($contextModule),
            'topics' => $schemaReady ? $this->getTopicCards($contextModule, 4, true) : [],
            'fullGuideUrl' => route('helponline.index'),
        ];
    }

    public function answerQuestion(string $query, int $userId, string $contextPath, string $pageTitle = '', ?int $selectedChunkId = null): array
    {
        if (!$this->repository->isSchemaReady()) {
            return [
                'ok' => false,
                'message' => t('helponline.answer.schema_unavailable'),
            ];
        }

        $query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH, 'UTF-8');

        $normalizedQuery = $this->normalizeText($query);
        if ($normalizedQuery === '') {
            return [
                'ok' => false,
                'message' => t('helponline.answer.empty_query'),
            ];
        }

        $contextModule = $this->inferContextFromPath($contextPath);
        $tokens = $this->tokenize($normalizedQuery);
        $results = [];

        if ($selectedChunkId !== null && $selectedChunkId > 0) {
            $selectedEntry = $this->decorateQaEntry(
                $this->repository->getQaEntryById($selectedChunkId),
                $contextPath,
                $normalizedQuery,
                $tokens,
                $contextModule
            );

            if ($selectedEntry !== null) {
                $results[] = $selectedEntry;
            }
        }

        if ($results === []) {
            $results = $this->searchKnowledgeBase($query, $contextPath, 6);
        }

        if ($results === []) {
            $queryId = $this->repository->recordQuery([
                'user_id' => $userId > 0 ? $userId : null,
                'query_text' => $query,
                'normalized_query' => $normalizedQuery,
                'context_path' => $contextPath,
                'context_module' => $contextModule,
                'matched_entry_id' => null,
                'confidence' => null,
                'response_title' => t('helponline.answer.no_match_title'),
            ]);

            return [
                'ok' => true,
                'queryId' => $queryId,
                'matched' => false,
                'answer' => [
                    'title' => t('helponline.answer.no_match_title'),
                    'html' => t('helponline.answer.no_match_html'),
                    'confidence' => 0,
                    'openUrl' => $this->buildHelpUrl($query, 0),
                ],
                'suggestions' => $this->getQuickPrompts($contextModule),
            ];
        }

        $top = $results[0];
        $queryId = $this->repository->recordQuery([
            'user_id' => $userId > 0 ? $userId : null,
            'query_text' => $query,
            'normalized_query' => $normalizedQuery,
            'context_path' => $contextPath,
            'context_module' => $contextModule,
            'matched_entry_id' => $top['id'],
            'confidence' => $top['confidence'],
            'response_title' => $top['title'],
        ]);

        return [
            'ok' => true,
            'queryId' => $queryId,
            'matched' => true,
            'answer' => [
                'id' => $top['id'],
                'title' => $top['title'],
                'moduleTitle' => $top['module_title'] ?? $top['module_name'],
                'moduleName' => $top['module_name'],
                'html' => $top['body_html'],
                'excerpt' => $top['excerpt'],
                'confidence' => $top['confidence'],
                'openUrl' => $top['help_url'],
                'targetUrl' => $top['target_url'],
            ],
            'related' => $top['related'],
        ];
    }

    public function recordFeedback(int $queryId, ?bool $helpful, int $userId = 0): bool
    {
        if ($queryId <= 0 || !$this->repository->isSchemaReady()) {
            return false;
        }

        $owner = $this->repository->getQueryOwner($queryId);
        if ($owner === null) {
            return false;
        }

        if ($owner !== $userId) {
            return false;
        }

        $this->repository->updateQueryFeedback($queryId, $helpful);
        return true;
    }

    /**
     * Reindicizza l'engine: ricostruisce help_search_terms dai dati QA correnti.
     */
    // ─────────────────────────────────────────────────────────────────────
    // Search engine
    // ─────────────────────────────────────────────────────────────────────

    public function searchKnowledgeBase(string $query, string $contextPath, int $limit = 8): array
    {
        if (!$this->repository->isSchemaReady()) {
            return [];
        }

        $normalizedQuery = $this->normalizeText($query);
        if ($normalizedQuery === '') {
            return [];
        }

        $tokens = $this->tokenize($normalizedQuery);
        if ($tokens === []) {
            return [];
        }

        $contextModule = $this->inferContextFromPath($contextPath);

        $fanout = max($limit * 4, 20);
        $candidates = $this->repository->searchQaCandidates($tokens, $normalizedQuery, $fanout, 'AND');

        // OR fallback: a multi-word natural-language question often won't match
        // every token in the same entry. Re-query with OR so partial matches
        // (e.g. "creo evento" matching only "evento") still surface.
        if ($candidates === [] && count($tokens) > 1) {
            $candidates = $this->repository->searchQaCandidates($tokens, $normalizedQuery, $fanout, 'OR');
        }

        $decorated = [];
        foreach ($candidates as $candidate) {
            $entry = $this->decorateQaEntry($candidate, $contextPath, $normalizedQuery, $tokens, $contextModule);
            if ($entry !== null && $entry['confidence'] >= self::MIN_SCORE_THRESHOLD) {
                $decorated[] = $entry;
            }
        }

        usort($decorated, static function (array $left, array $right): int {
            return [$right['score'], $left['title']] <=> [$left['score'], $right['title']];
        });

        return array_slice($decorated, 0, $limit);
    }

    /**
     * Topic cards per panel inline / pagina /help.
     *
     * Ordinamento:
     *   1. Entries del modulo contestuale (boost) per copertura "profonda" della pagina corrente
     *   2. Altre entries alfabetiche per titolo
     *
     * $dedupByModule (usato dal panel a 4 slot): conserva TUTTE le entries del
     * modulo contestuale ma mostra max 1 entry per ciascun altro modulo —
     * evita le sfilze di voci omonime ("Accesso", "Accesso", "Accesso"...) tipiche
     * delle KB strutturate uniformemente per modulo.
     */
    public function getTopicCards(?string $contextModule, int $limit = 6, bool $dedupByModule = false): array
    {
        if (!$this->repository->isSchemaReady()) {
            return [];
        }

        $topics = array_filter(
            $this->repository->listQaTopics(120),
            fn (array $entry): bool => $this->canAccessPermission($entry['permission_slug'] ?? null)
        );

        usort($topics, function (array $left, array $right) use ($contextModule): int {
            $leftBoost = $contextModule !== null && ($left['module_name'] ?? '') === $contextModule ? 1 : 0;
            $rightBoost = $contextModule !== null && ($right['module_name'] ?? '') === $contextModule ? 1 : 0;
            return [$rightBoost, $left['title']] <=> [$leftBoost, $right['title']];
        });

        if ($dedupByModule) {
            $seenModules = [];
            $deduped = [];
            foreach ($topics as $topic) {
                $module = (string) ($topic['module_name'] ?? 'Generale');
                // Modulo contestuale: nessun limite — mostra copertura profonda
                if ($contextModule !== null && $module === $contextModule) {
                    $deduped[] = $topic;
                    continue;
                }
                if (isset($seenModules[$module])) {
                    continue;
                }
                $seenModules[$module] = true;
                $deduped[] = $topic;
            }
            $topics = $deduped;
        }

        $cards = [];
        foreach (array_slice($topics, 0, $limit) as $entry) {
            $moduleName = (string) ($entry['module_name'] ?? '');
            $moduleTitle = $this->getModuleDisplayName($moduleName, (string) ($entry['module_label'] ?? '')) ?? $moduleName;
            $cards[] = [
                'title' => $entry['title'],
                'subtitle' => $entry['excerpt'] ?: t('helponline.answer.module_guide', ['module' => $moduleTitle !== '' ? $moduleTitle : 'Favilla']),
                'module_name' => $moduleName,
                'module_title' => $moduleTitle,
                'url' => $this->buildHelpUrl((string) ($entry['title'] ?? ''), (int) ($entry['id'] ?? 0)),
            ];
        }

        return $cards;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Decorator + scoring
    // ─────────────────────────────────────────────────────────────────────

    private function decorateQaEntry(?array $candidate, string $contextPath, ?string $normalizedQuery = null, ?array $tokens = null, ?string $contextModule = null): ?array
    {
        if ($candidate === null) {
            return null;
        }

        if (!$this->canAccessPermission($candidate['permission_slug'] ?? null)) {
            return null;
        }

        $normalizedQuery = $normalizedQuery ?? '';
        $tokens = $tokens ?? $this->tokenize($normalizedQuery);
        $contextModule = $contextModule ?? $this->inferContextFromPath($contextPath);

        $question = (string) ($candidate['question'] ?? '');
        $questionNorm = $this->normalizeText($question);
        $answerNorm = $this->normalizeText((string) ($candidate['answer_plain'] ?? ''));
        $aliases = (string) ($candidate['aliases'] ?? '');
        $aliasList = array_filter(explode('||', $aliases));
        $moduleName = (string) ($candidate['module_name'] ?? 'HelpOnline');
        $moduleTitle = $this->getModuleDisplayName($moduleName, (string) ($candidate['module_label'] ?? '')) ?? $moduleName;
        $moduleNorm = $this->normalizeText(trim($moduleName . ' ' . $moduleTitle));

        $score = (int) ($candidate['ranking_weight'] ?? 0);

        if ($normalizedQuery !== '') {
            if ($questionNorm === $normalizedQuery) {
                $score += 120;
            }
            if (str_contains($questionNorm, $normalizedQuery)) {
                $score += 80;
            }
            foreach ($aliasList as $alias) {
                if ($alias === $normalizedQuery) {
                    $score += 100;
                    break;
                }
            }
        }

        foreach ($tokens as $token) {
            if (str_contains($questionNorm, $token)) {
                $score += 20;
            }
            if (str_contains($answerNorm, $token)) {
                $score += 8;
            }
            if ($moduleNorm !== '' && str_contains($moduleNorm, $token)) {
                $score += 18;
            }
            foreach ($aliasList as $alias) {
                if (str_contains($alias, $token)) {
                    $score += 16;
                    break;
                }
            }
        }

        if ($contextModule !== null && ($candidate['module_name'] ?? null) === $contextModule) {
            $score += 35;
        }

        return [
            'id' => (int) ($candidate['id'] ?? 0),
            'document_id' => (int) ($candidate['module_id'] ?? 0),
            'title' => $question,
            'heading_path' => $question,
            'module_title' => $moduleTitle !== '' ? $moduleTitle : t('helponline.answer.guide_fallback'),
            'module_name' => $moduleName,
            'owner_module' => $moduleName,
            'excerpt' => (string) ($candidate['excerpt'] ?? ''),
            'body_html' => $this->formatAnswerHtml((string) ($candidate['answer_markdown'] ?? ''), (string) ($candidate['answer_plain'] ?? '')),
            'target_url' => $this->safeRoute((string) ($candidate['route_name'] ?? '')),
            'help_url' => $this->buildHelpUrl(
                $normalizedQuery !== '' ? $normalizedQuery : $question,
                (int) ($candidate['id'] ?? 0)
            ),
            'score' => $score,
            'confidence' => min(100, max(0, $score)),
            'related' => $this->decorateRelatedEntries(
                $this->repository->getRelatedQaEntries((int) ($candidate['module_id'] ?? 0), (int) ($candidate['id'] ?? 0), 3),
                $normalizedQuery
            ),
        ];
    }

    private function decorateRelatedEntries(array $related, string $query): array
    {
        $items = [];
        foreach ($related as $row) {
            if (!$this->canAccessPermission($row['permission_slug'] ?? null)) {
                continue;
            }

            $relatedTitle = (string) ($row['title'] ?? $row['question'] ?? '');
            $relatedExcerpt = (string) ($row['excerpt'] ?? '');

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $relatedTitle,
                'excerpt' => $relatedExcerpt,
                'url' => $this->buildHelpUrl(
                    $query !== '' ? $query : $relatedTitle,
                    (int) ($row['id'] ?? 0)
                ),
            ];
        }

        return $items;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Routing helpers + context inference + prompts
    // ─────────────────────────────────────────────────────────────────────

    private function buildHelpUrl(string $query, int $entryId): string
    {
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($entryId > 0) {
            $params['chunk'] = $entryId;
        }

        $base = route('helponline.index');
        return $params === [] ? $base : $base . '?' . http_build_query($params);
    }

    public function buildContextLabel(?string $contextModule, string $pageTitle): string
    {
        $contextTitle = $this->getModuleDisplayName($contextModule);

        if ($pageTitle !== '' && $contextTitle !== null) {
            return $pageTitle . ' · ' . $contextTitle;
        }

        if ($pageTitle !== '') {
            return $pageTitle;
        }

        return $contextTitle !== null ? t('helponline.answer.context_prefix', ['title' => $contextTitle]) : t('helponline.answer.context_general');
    }

    public function inferContextFromPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $routeBase = rtrim(parse_url(route('home.index'), PHP_URL_PATH) ?? '', '/');
        $normalizedPath = rtrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        if ($routeBase !== '' && str_starts_with($normalizedPath, $routeBase)) {
            $normalizedPath = substr($normalizedPath, strlen($routeBase));
            $normalizedPath = $normalizedPath === '' ? '/' : $normalizedPath;
        }

        $staticMap = [
            '/profile' => 'Auth',
            '/login' => 'Auth',
            '/password' => 'Auth',
            '/mfa' => 'Auth',
            '/search' => 'Home',
            '/home' => 'Home',
        ];
        foreach ($staticMap as $prefix => $module) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return $module;
            }
        }

        if ($normalizedPath === '/') {
            return 'Home';
        }

        $map = $this->getModuleContextMap();
        foreach ($map as $prefix => $module) {
            if (str_starts_with($normalizedPath, $prefix)) {
                return $module;
            }
        }

        return null;
    }

    private function getModuleContextMap(): array
    {
        if ($this->moduleContextMap !== null) {
            return $this->moduleContextMap;
        }

        $loader = $this->moduleLoader ?? $this->resolveModuleLoader();
        $entries = [];

        if ($loader !== null) {
            foreach ($loader->getModules() as $module) {
                $name = (string) ($module['name'] ?? '');
                if ($name === '' || $name === '_Template' || $name === 'HelpOnline') {
                    continue;
                }

                $meta = $loader->readModuleJson($name) ?: [];
                $candidates = [];

                foreach (((array) ($meta['navigation'] ?? [])) as $nav) {
                    if (!is_array($nav) || empty($nav['route'])) {
                        continue;
                    }
                    $url = $this->routeToPath((string) $nav['route']);
                    if ($url !== null) {
                        $candidates[] = $url;
                    }
                }

                $candidates[] = '/' . strtolower($name);

                foreach (array_unique($candidates) as $prefix) {
                    if ($prefix === '' || $prefix === '/') {
                        continue;
                    }
                    if (!isset($entries[$prefix])) {
                        $entries[$prefix] = $name;
                    }
                }
            }
        }

        uksort($entries, static fn (string $a, string $b): int => strlen($b) - strlen($a));

        return $this->moduleContextMap = $entries;
    }

    private function routeToPath(string $routeName): ?string
    {
        try {
            $url = route($routeName);
        } catch (Throwable) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $routeBase = rtrim(parse_url(route('home.index'), PHP_URL_PATH) ?? '', '/');
        if ($routeBase !== '' && str_starts_with($path, $routeBase)) {
            $path = substr($path, strlen($routeBase));
        }

        $path = rtrim($path, '/');
        return $path === '' ? null : $path;
    }

    private function resolveModuleLoader(): ?ModuleLoader
    {
        try {
            return app(ModuleLoader::class);
        } catch (Throwable) {
            return null;
        }
    }

    private function getModuleDisplayName(?string $moduleName, ?string $moduleLabel = null): ?string
    {
        $moduleLabel = trim((string) $moduleLabel);
        if ($moduleLabel !== '') {
            return $moduleLabel;
        }

        $moduleName = trim((string) $moduleName);
        if ($moduleName === '') {
            return null;
        }

        $map = $this->getModuleDisplayNameMap();
        return $map[$moduleName] ?? $moduleName;
    }

    private function getModuleDisplayNameMap(): array
    {
        if ($this->moduleDisplayNameMap !== null) {
            return $this->moduleDisplayNameMap;
        }

        $loader = $this->moduleLoader ?? $this->resolveModuleLoader();
        $map = [];

        if ($loader !== null) {
            foreach ($loader->getModules() as $module) {
                $name = trim((string) ($module['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $meta = $loader->readModuleJson($name) ?: [];
                $label = $this->extractModuleDisplayName($name, $meta);
                if ($label !== '') {
                    $map[$name] = $label;
                }
            }
        }

        return $this->moduleDisplayNameMap = $map;
    }

    private function extractModuleDisplayName(string $fallback, array $meta): string
    {
        foreach ((array) ($meta['navigation'] ?? []) as $item) {
            if (is_array($item) && trim((string) ($item['label'] ?? '')) !== '') {
                return trim((string) $item['label']);
            }
        }

        foreach ((array) ($meta['menu'] ?? []) as $item) {
            if (is_array($item) && trim((string) ($item['label'] ?? '')) !== '') {
                return trim((string) $item['label']);
            }
        }

        $name = trim((string) ($meta['name'] ?? ''));
        return $name !== '' ? $name : $fallback;
    }

    public function getQuickPrompts(?string $contextModule): array
    {
        if (!$this->repository->isSchemaReady()) {
            return [];
        }

        $prompts = [];

        if ($contextModule !== null) {
            try {
                $prompts = $this->buildQuickPromptsFromEntries(
                    $this->repository->listQuickPromptEntries($contextModule, 12)
                );
            } catch (Throwable) {
                $prompts = [];
            }
        }

        if (count($prompts) < self::QUICK_PROMPT_LIMIT) {
            try {
                foreach ($this->buildQuickPromptsFromEntries($this->repository->listQuickPromptEntries(null, 24)) as $prompt) {
                    $promptId = (int) ($prompt['chunk'] ?? 0);
                    $alreadyPresent = array_filter(
                        $prompts,
                        static fn (array $candidate): bool => (int) ($candidate['chunk'] ?? 0) === $promptId
                    ) !== [];

                    if ($alreadyPresent) {
                        continue;
                    }

                    $prompts[] = $prompt;
                    if (count($prompts) >= self::QUICK_PROMPT_LIMIT) {
                        break;
                    }
                }
            } catch (Throwable) {
                // Keep current prompts only.
            }
        }

        return array_slice($prompts, 0, self::QUICK_PROMPT_LIMIT);
    }

    private function buildQuickPromptsFromEntries(array $entries): array
    {
        $candidates = [];

        foreach ($entries as $index => $entry) {
            $question = trim((string) ($entry['question'] ?? ''));
            $entryId = (int) ($entry['id'] ?? 0);

            if ($question === '' || $entryId <= 0) {
                continue;
            }

            if (!$this->canAccessPermission($entry['permission_slug'] ?? null)) {
                continue;
            }

            $candidates[] = [
                'position' => (int) $index,
                'priority' => $this->getQuickPromptPriority($question),
                'label' => $question,
                'message' => $question,
                'chunk' => $entryId,
                'url' => $this->buildHelpUrl($question, $entryId),
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            return [$left['priority'], $left['position']] <=> [$right['priority'], $right['position']];
        });

        return array_map(static function (array $prompt): array {
            unset($prompt['priority'], $prompt['position']);
            return $prompt;
        }, $candidates);
    }

    private function getQuickPromptPriority(string $question): int
    {
        $question = trim($question);
        if ($question === '') {
            return 3;
        }

        if (str_ends_with($question, '?') || preg_match('/^(come|dove|perche|quando|quale|quali|chi|cosa|a cosa)\b/ui', $question) === 1) {
            return 0;
        }

        if (str_contains($question, ' ')) {
            return 1;
        }

        return 2;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Text normalization + helpers
    // ─────────────────────────────────────────────────────────────────────

    public function normalizeText(string $text): string
    {
        return HelpTextNormalizer::normalize($text);
    }

    private function tokenize(string $normalizedQuery): array
    {
        return HelpTextNormalizer::tokenize($normalizedQuery);
    }

    private function canAccessPermission(?string $permissionSlug): bool
    {
        if ($permissionSlug === null || $permissionSlug === '') {
            return true;
        }

        return has_permission($permissionSlug);
    }

    private function safeRoute(string $routeName): ?string
    {
        if ($routeName === '') {
            return null;
        }

        try {
            return route($routeName);
        } catch (Throwable) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Markdown → HTML renderer
    // ─────────────────────────────────────────────────────────────────────

    public function renderMarkdownDocument(string $markdown): string
    {
        return $this->formatAnswerHtml($markdown, '');
    }

    private function applyInlineMarkdown(string $escapedHtml): string
    {
        $result = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $escapedHtml) ?? $escapedHtml;
        $result = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $result) ?? $result;
        $result = preg_replace('/`([^`\n]+)`/u', '<code>$1</code>', $result) ?? $result;
        $result = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/u',
            function (array $m): string {
                $href = $this->safeMarkdownUrl(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($href === null) {
                    return $m[1];
                }
                $hrefAttr = e($href);
                $external = (bool) preg_match('/^https?:/i', $href);
                $rel = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . $hrefAttr . '"' . $rel . '>' . $m[1] . '</a>';
            },
            $result
        ) ?? $result;

        return $result;
    }

    private function safeMarkdownUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^(/|\?|\#)#', $url) === 1) {
            return $url;
        }

        if (preg_match('#^(https?:|mailto:|tel:)#i', $url) === 1) {
            return $url;
        }

        return null;
    }

    private function formatAnswerHtml(string $markdown, string $plain): string
    {
        $source = trim($markdown) !== '' ? str_replace(["\r\n", "\r"], "\n", $markdown) : $plain;
        if (trim($source) === '') {
            return '<p>' . e(t('helponline.answer.no_content')) . '</p>';
        }

        $html = [];
        $paragraph = [];
        $listItems = [];
        $listType = null;
        $blockquote = [];
        $tableRows = [];
        $codeBuffer = [];
        $inCode = false;
        $inline = fn (string $text): string => $this->applyInlineMarkdown(e($text));

        $flushParagraph = static function () use (&$paragraph, &$html, $inline): void {
            if ($paragraph === []) {
                return;
            }
            $text = trim(implode(' ', $paragraph));
            if ($text !== '') {
                $html[] = '<p>' . $inline($text) . '</p>';
            }
            $paragraph = [];
        };

        $flushList = static function () use (&$listItems, &$listType, &$html, $inline): void {
            if ($listItems === [] || $listType === null) {
                $listItems = [];
                $listType = null;
                return;
            }
            $tag = $listType === 'ol' ? 'ol' : 'ul';
            $html[] = '<' . $tag . '>' . implode('', array_map(
                static fn (string $item): string => '<li>' . $inline($item) . '</li>',
                $listItems
            )) . '</' . $tag . '>';
            $listItems = [];
            $listType = null;
        };

        $flushBlockquote = static function () use (&$blockquote, &$html, $inline): void {
            if ($blockquote === []) {
                return;
            }
            $html[] = '<blockquote class="ho-quote">' . $inline(implode(' ', $blockquote)) . '</blockquote>';
            $blockquote = [];
        };

        $flushTable = static function () use (&$tableRows, &$html, $inline): void {
            if ($tableRows === []) {
                return;
            }
            $rendered = '<div class="table-responsive"><table class="table table-sm align-middle ho-md-table">';
            $headerDone = false;
            foreach ($tableRows as $row) {
                if (preg_match('/^[\s\|:\-]+$/', $row) === 1) {
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($row, "| \t")));
                $tag = $headerDone ? 'td' : 'th';
                $rendered .= '<tr>';
                foreach ($cells as $cell) {
                    $rendered .= '<' . $tag . '>' . $inline($cell) . '</' . $tag . '>';
                }
                $rendered .= '</tr>';
                if (!$headerDone) {
                    $headerDone = true;
                }
            }
            $rendered .= '</table></div>';
            $html[] = $rendered;
            $tableRows = [];
        };

        foreach (explode("\n", $source) as $line) {
            $trimmed = trim($line);

            if (preg_match('/^```/', $trimmed) === 1) {
                if ($inCode) {
                    $html[] = '<pre class="ho-code"><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
                    $codeBuffer = [];
                    $inCode = false;
                } else {
                    $flushParagraph();
                    $flushList();
                    $flushBlockquote();
                    $flushTable();
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $codeBuffer[] = $line;
                continue;
            }

            if ($trimmed === '' || $trimmed === '---') {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $flushTable();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/u', $trimmed, $matches)) {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $flushTable();
                $level = max(4, min(6, strlen($matches[1]) + 2));
                $html[] = '<h' . $level . ' class="ho-md-heading">' . $inline($matches[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/u', $trimmed, $matches)) {
                $flushParagraph();
                $flushList();
                $flushTable();
                $blockquote[] = $matches[1];
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $flushTable();
                if ($listType !== 'ul') {
                    $flushList();
                    $listType = 'ul';
                }
                $listItems[] = $matches[1];
                continue;
            }

            if (preg_match('/^\d+\.\s+(.*)$/u', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $flushTable();
                if ($listType !== 'ol') {
                    $flushList();
                    $listType = 'ol';
                }
                $listItems[] = $matches[1];
                continue;
            }

            if (str_starts_with($trimmed, '|')) {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $tableRows[] = $trimmed;
                continue;
            }

            $flushList();
            $flushBlockquote();
            $flushTable();
            $paragraph[] = $trimmed;
        }

        if ($inCode && $codeBuffer !== []) {
            $html[] = '<pre class="ho-code"><code>' . e(implode("\n", $codeBuffer)) . '</code></pre>';
        }
        $flushParagraph();
        $flushList();
        $flushBlockquote();
        $flushTable();

        return implode('', $html);
    }
}
