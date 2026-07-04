<?php

namespace App\Modules\HelpOnline\Tests\Unit;

use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use Tests\ModuleTestCase;

/**
 * Verifica lo scope per-locale delle letture KB: traduzione attiva quando
 * presente, fallback per-entry alla riga canonica italiana quando manca.
 */
class HelpOnlineRepositoryLocaleTest extends ModuleTestCase
{
    private HelpOnlineRepository $repo;

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

        $this->insertRow('help_modules', [
            'id' => 1, 'module_key' => 'calendar', 'module_name' => 'Calendar', 'label' => 'Calendario',
        ]);

        // E1 canonica IT con traduzione EN (E2); E3 canonica IT senza traduzione.
        $this->insertRow('help_entries', [
            'id' => 1, 'module_id' => 1, 'source_entry_id' => null, 'locale' => 'it',
            'question' => 'Come creo un evento', 'normalized_question' => 'come creo un evento',
            'answer_markdown' => 'Apri il calendario.', 'answer_plain' => 'apri il calendario',
            'excerpt' => 'IT excerpt', 'is_active' => 1, 'sort_order' => 1,
        ]);
        $this->insertRow('help_entries', [
            'id' => 2, 'module_id' => 1, 'source_entry_id' => 1, 'locale' => 'en',
            'question' => 'How do I create an event', 'normalized_question' => 'how do i create an event',
            'answer_markdown' => 'Open the calendar.', 'answer_plain' => 'open the calendar',
            'excerpt' => 'EN excerpt', 'is_active' => 1, 'sort_order' => 1,
        ]);
        $this->insertRow('help_entries', [
            'id' => 3, 'module_id' => 1, 'source_entry_id' => null, 'locale' => 'it',
            'question' => 'Solo italiano', 'normalized_question' => 'solo italiano',
            'answer_markdown' => 'Contenuto solo IT.', 'answer_plain' => 'contenuto solo it',
            'excerpt' => 'IT only', 'is_active' => 1, 'sort_order' => 2,
        ]);

        $this->repo = new HelpOnlineRepository();
    }

    public function testListQaTopicsScopesToActiveLocaleWithItalianFallback(): void
    {
        // EN: la traduzione EN di E1 + il fallback IT di E3 (non la canonica E1).
        $en = array_column($this->repo->listQaTopics(50, 'en'), 'title', 'id');
        $this->assertSame('How do I create an event', $en[2] ?? null);
        $this->assertSame('Solo italiano', $en[3] ?? null);
        $this->assertArrayNotHasKey(1, $en, 'La canonica IT con traduzione EN non deve comparire in EN');

        // IT: solo le canoniche (E1, E3), mai la riga di traduzione E2.
        $it = array_column($this->repo->listQaTopics(50, 'it'), 'title', 'id');
        $this->assertSame('Come creo un evento', $it[1] ?? null);
        $this->assertSame('Solo italiano', $it[3] ?? null);
        $this->assertArrayNotHasKey(2, $it, 'La riga di traduzione EN non deve comparire in IT');
    }

    public function testListQuickPromptEntriesScopesToActiveLocaleWithItalianFallback(): void
    {
        // Copre lo stile di iniezione `$sql .= $scope` (diverso da listQaTopics).
        // EN: traduzione EN (E2) + fallback IT (E3); mai la canonica E1.
        $en = array_column($this->repo->listQuickPromptEntries(null, 50, 'en'), 'question', 'id');
        $this->assertArrayHasKey(2, $en);
        $this->assertArrayHasKey(3, $en);
        $this->assertArrayNotHasKey(1, $en);

        // IT: solo canoniche (E1, E3), mai la traduzione E2.
        $it = array_column($this->repo->listQuickPromptEntries(null, 50, 'it'), 'question', 'id');
        $this->assertArrayHasKey(1, $it);
        $this->assertArrayHasKey(3, $it);
        $this->assertArrayNotHasKey(2, $it);
    }
}
