<?php

namespace App\Modules\HelpOnline\Tests\Unit;

use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use App\Modules\HelpOnline\Services\HelpContentService;
use Tests\ModuleTestCase;

/**
 * Round-trip export -> import della KB Help Online via file JSON. Verifica
 * che entry, traduzioni (source_entry_id ricostruito) e alias sopravvivano
 * identiche e che i search term vengano rigenerati.
 */
class HelpContentServiceTest extends ModuleTestCase
{
    private HelpOnlineRepository $repo;
    private HelpContentService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE help_modules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_key TEXT NOT NULL,
                module_name TEXT NOT NULL,
                label TEXT NOT NULL,
                description TEXT NULL,
                audience_default TEXT NOT NULL DEFAULT "user",
                locale_default TEXT NOT NULL DEFAULT "it",
                route_name TEXT NULL,
                permission_slug TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE help_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_id INTEGER NOT NULL,
                source_entry_id INTEGER NULL,
                question TEXT NOT NULL,
                normalized_question TEXT NOT NULL,
                answer_markdown TEXT NOT NULL,
                answer_plain TEXT NOT NULL,
                excerpt TEXT NULL,
                audience TEXT NOT NULL DEFAULT "user",
                locale TEXT NOT NULL DEFAULT "it",
                route_name TEXT NULL,
                permission_slug TEXT NULL,
                ranking_weight INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                indexed_at TEXT NULL
            );
            CREATE TABLE help_entry_aliases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_id INTEGER NOT NULL,
                alias TEXT NOT NULL,
                normalized_alias TEXT NOT NULL,
                weight_bonus INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE help_search_terms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entry_id INTEGER NOT NULL,
                term TEXT NOT NULL,
                normalized_term TEXT NOT NULL,
                term_type TEXT NOT NULL DEFAULT "answer",
                term_weight INTEGER NOT NULL DEFAULT 10
            );
        ');

        $this->repo = new HelpOnlineRepository();
        $this->service = new HelpContentService($this->repo);

        $this->tmpDir = __DIR__ . '/tmp_help_content_' . getmypid();
        $this->cleanTmpDir();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanTmpDir();
        parent::tearDown();
    }

    private function cleanTmpDir(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        foreach (glob($this->tmpDir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    private function seedTasksModuleWithOneEntry(): void
    {
        $moduleId = $this->repo->createQaModule([
            'module_key' => 'tasks',
            'module_name' => 'Tasks',
            'label' => 'Attività',
            'description' => 'Gestione attività personali',
            'audience_default' => 'user',
            'locale_default' => 'it',
            'route_name' => 'tasks.index',
            'permission_slug' => 'tasks.view',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $canonicalId = $this->repo->createQaEntry([
            'module_id' => $moduleId,
            'source_entry_id' => null,
            'question' => 'Come creo una attività?',
            'normalized_question' => 'come creo una attivita',
            'answer_markdown' => "## Creazione\n\nVai su **Nuova attività**.",
            'answer_plain' => 'creazione vai su nuova attivita',
            'excerpt' => 'Crea una nuova attività dal pulsante dedicato.',
            'audience' => 'user',
            'locale' => 'it',
            'route_name' => 'tasks.create',
            'permission_slug' => 'tasks.create',
            'ranking_weight' => 18,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->repo->replaceQaEntryAliases($canonicalId, ['nuova attivita', 'aggiungi task']);

        $translationId = $this->repo->createQaEntry([
            'module_id' => $moduleId,
            'source_entry_id' => $canonicalId,
            'question' => 'How do I create a task?',
            'normalized_question' => 'how do i create a task',
            'answer_markdown' => "## Creation\n\nGo to **New task**.",
            'answer_plain' => 'creation go to new task',
            'excerpt' => 'Create a new task from the dedicated button.',
            'audience' => 'user',
            'locale' => 'en',
            'route_name' => 'tasks.create',
            'permission_slug' => 'tasks.create',
            'ranking_weight' => 18,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->repo->replaceQaEntryAliases($translationId, ['new task', 'add task']);
    }

    public function testExportWritesDeterministicJsonWithNestedLocales(): void
    {
        $this->seedTasksModuleWithOneEntry();

        $summary = $this->service->exportAll($this->tmpDir);

        $this->assertSame(['tasks' => 1], $summary);
        $this->assertFileExists($this->tmpDir . '/tasks.json');

        $data = json_decode((string) file_get_contents($this->tmpDir . '/tasks.json'), true);

        $this->assertSame('tasks', $data['module_key']);
        $this->assertSame('tasks.index', $data['route_name']);
        $this->assertCount(1, $data['entries']);

        $entry = $data['entries'][0];
        $this->assertSame('tasks.create', $entry['permission_slug']);
        $this->assertSame(18, $entry['ranking_weight']);
        $this->assertSame(['it', 'en'], array_keys($entry['locales']));
        $this->assertSame('Come creo una attività?', $entry['locales']['it']['question']);
        $this->assertSame(['nuova attivita', 'aggiungi task'], $entry['locales']['it']['aliases']);
        $this->assertSame('How do I create a task?', $entry['locales']['en']['question']);
        $this->assertSame(['new task', 'add task'], $entry['locales']['en']['aliases']);
    }

    public function testImportRebuildsEntriesTranslationsAliasesAndSearchTerms(): void
    {
        $this->seedTasksModuleWithOneEntry();
        $this->service->exportAll($this->tmpDir);

        // Simula un'installazione fresca: nessun modulo/entry preesistente.
        $this->pdo->exec('DELETE FROM help_entry_aliases');
        $this->pdo->exec('DELETE FROM help_search_terms');
        $this->pdo->exec('DELETE FROM help_entries');
        $this->pdo->exec('DELETE FROM help_modules');

        $result = $this->service->importAll($this->tmpDir);

        $this->assertSame(['tasks' => 1], $result['imported']);
        $this->assertSame([], $result['skipped']);

        $module = $this->repo->getQaModuleByKey('tasks');
        $this->assertNotNull($module);
        $this->assertSame('Attività', $module['label']);

        $entries = $this->pdo->query('SELECT * FROM help_entries ORDER BY locale ASC')->fetchAll();
        $this->assertCount(2, $entries);

        $en = $entries[0]['locale'] === 'en' ? $entries[0] : $entries[1];
        $it = $entries[0]['locale'] === 'it' ? $entries[0] : $entries[1];

        $this->assertNull($it['source_entry_id']);
        $this->assertSame((int) $it['id'], (int) $en['source_entry_id']);
        $this->assertSame('How do I create a task?', $en['question']);
        $this->assertSame('how do i create a task', $en['normalized_question']);

        $itAliases = $this->pdo->prepare('SELECT alias FROM help_entry_aliases WHERE entry_id = ? ORDER BY alias');
        $itAliases->execute([$it['id']]);
        $this->assertSame(['aggiungi task', 'nuova attivita'], $itAliases->fetchAll(\PDO::FETCH_COLUMN));

        $termCount = (int) $this->pdo->query('SELECT COUNT(*) FROM help_search_terms')->fetchColumn();
        $this->assertGreaterThan(0, $termCount, 'rebuildAllSearchTerms deve popolare help_search_terms');
    }

    public function testExportAfterImportRoundTripsIdentically(): void
    {
        $this->seedTasksModuleWithOneEntry();
        $original = (string) file_get_contents($this->pathAfterExport());

        $this->pdo->exec('DELETE FROM help_entry_aliases');
        $this->pdo->exec('DELETE FROM help_search_terms');
        $this->pdo->exec('DELETE FROM help_entries');
        $this->pdo->exec('DELETE FROM help_modules');

        $this->service->importAll($this->tmpDir);

        $reExportDir = $this->tmpDir . '_reexport';
        mkdir($reExportDir, 0777, true);
        $this->service->exportAll($reExportDir);
        $roundTripped = (string) file_get_contents($reExportDir . '/tasks.json');

        $this->assertJsonStringEqualsJsonString($original, $roundTripped);

        unlink($reExportDir . '/tasks.json');
        rmdir($reExportDir);
    }

    public function testImportSkipsModuleWithExistingEntriesUnlessForced(): void
    {
        $this->seedTasksModuleWithOneEntry();
        $this->service->exportAll($this->tmpDir);

        // Il modulo ha già entry (quelle appena seedate): senza --force viene saltato.
        $result = $this->service->importAll($this->tmpDir, null, false);
        $this->assertSame([], $result['imported']);
        $this->assertSame(['tasks'], $result['skipped']);
        $this->assertCount(2, $this->pdo->query('SELECT id FROM help_entries')->fetchAll());

        // Con --force il contenuto viene sostituito (stesse 2 righe, id nuovi).
        $result = $this->service->importAll($this->tmpDir, null, true);
        $this->assertSame(['tasks' => 1], $result['imported']);
        $this->assertSame([], $result['skipped']);
        $this->assertCount(2, $this->pdo->query('SELECT id FROM help_entries')->fetchAll());
    }

    private function pathAfterExport(): string
    {
        $this->service->exportAll($this->tmpDir);
        return $this->tmpDir . '/tasks.json';
    }
}
