<?php

namespace App\Modules\Feedback\Tests\Unit;

use App\Modules\Feedback\Repositories\FeedbackRepository;
use App\Modules\Feedback\Services\FeedbackService;
use Tests\ModuleTestCase;

/**
 * Test del Service su SQLite in-memory (ModuleTestCase).
 * Copre creazione + enrichment server-side, validazione, triage e lista/conteggi.
 */
class FeedbackServiceTest extends ModuleTestCase
{
    private FeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Schema SQLite (tipi semplificati: TEXT per VARCHAR/ENUM, INTEGER per UNSIGNED).
        $this->migrate("
            CREATE TABLE feedback (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                ref_code            TEXT,
                tipo                TEXT DEFAULT 'bug',
                severita            TEXT DEFAULT 'media',
                stato               TEXT DEFAULT 'nuova',
                titolo              TEXT,
                descrizione         TEXT,
                passi               TEXT,
                pagina_url          TEXT,
                route_name          TEXT,
                modulo              TEXT,
                contesto_json       TEXT,
                errori_console_json TEXT,
                dom_snapshot        TEXT,
                user_agent          TEXT,
                viewport            TEXT,
                app_version         TEXT,
                created_by          INTEGER,
                assegnata_a         INTEGER,
                note_admin          TEXT,
                created_at          TEXT,
                updated_at          TEXT,
                deleted_at          TEXT
            )
        ");

        $this->migrate('
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT,
                email      TEXT,
                is_active  INTEGER DEFAULT 1,
                deleted_at TEXT
            )
        ');
        $this->insertRow('users', ['id' => 7, 'name' => 'Mario Rossi', 'email' => 'mario@example.it', 'is_active' => 1]);

        $this->service = new FeedbackService(new FeedbackRepository());

        $_SESSION = [
            'user_id'          => 7,
            'user_name'        => 'Mario Rossi',
            'user_email'       => 'mario@example.it',
            'user_roles'       => ['admin'],
            'user_permissions' => ['feedback.view', 'feedback.manage'],
        ];
    }

    public function testCreatePersistsAndEnriches(): void
    {
        $result = $this->service->create(
            ['tipo' => 'bug', 'severita' => 'alta', 'titolo' => '', 'descrizione' => 'Il salvataggio va in errore'],
            [
                'url'          => 'http://localhost/favilla/public/contacts/5/edit',
                'path'         => '/favilla/public/contacts/5/edit',
                'user_agent'   => 'Mozilla/5.0 Test',
                'viewport_str' => '1920x1080@2',
                'errors'       => [['type' => 'js', 'message' => 'boom']],
                'breadcrumb'   => [['kind' => 'nav', 'path' => '/contacts']],
            ]
        );

        $this->assertArrayHasKey('ref_code', $result);
        $this->assertMatchesRegularExpression('/^FB-[A-F0-9]{6}$/', $result['ref_code']);

        $row = (new FeedbackRepository())->find($result['id']);
        $this->assertNotNull($row);
        $this->assertSame('Il salvataggio va in errore', $row['descrizione']);
        $this->assertSame('alta', $row['severita']);
        $this->assertSame('nuova', $row['stato']);
        $this->assertSame(7, (int) $row['created_by']);
        // Titolo auto-derivato dalla descrizione quando vuoto.
        $this->assertSame('Il salvataggio va in errore', $row['titolo']);
        // Modulo dedotto dal path.
        $this->assertSame('Contacts', $row['modulo']);
        // Enrichment server-side nel contesto.
        $this->assertStringContainsString('"server"', $row['contesto_json']);
        $this->assertStringContainsString('8.', $row['contesto_json']); // php_version
        $this->assertNotEmpty($row['errori_console_json']);
        $this->assertSame('1920x1080@2', $row['viewport']);
    }

    public function testCreateRejectsEmptyDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->create(['descrizione' => '   '], []);
    }

    public function testTriageUpdatesFields(): void
    {
        $id = $this->insertRow('feedback', [
            'ref_code'    => 'SG-TEST01',
            'tipo'        => 'bug',
            'severita'    => 'media',
            'stato'       => 'nuova',
            'descrizione' => 'demo',
            'created_at'  => '2026-05-31 09:00:00',
        ]);

        $ok = $this->service->triage($id, [
            'stato'       => 'risolta',
            'severita'    => 'critica',
            'assegnata_a' => 7,
            'note_admin'  => 'Sistemato nel commit X',
        ]);
        $this->assertTrue($ok);

        $row = (new FeedbackRepository())->find($id);
        $this->assertSame('risolta', $row['stato']);
        $this->assertSame('critica', $row['severita']);
        $this->assertSame(7, (int) $row['assegnata_a']);
        $this->assertSame('Sistemato nel commit X', $row['note_admin']);
    }

    public function testTriageIgnoresInvalidState(): void
    {
        $id = $this->insertRow('feedback', [
            'ref_code'    => 'SG-TEST02',
            'stato'       => 'nuova',
            'descrizione' => 'demo',
            'created_at'  => '2026-05-31 09:00:00',
        ]);

        // Stato non valido + nessun altro campo → nessun update.
        $changed = $this->service->triage($id, ['stato' => 'inventato']);
        $this->assertFalse($changed);
        $this->assertSame('nuova', (new FeedbackRepository())->find($id)['stato']);
    }

    public function testListFiltersByStateAndCountsOpen(): void
    {
        foreach ([
            ['ref_code' => 'SG-A00001', 'stato' => 'nuova', 'tipo' => 'bug', 'modulo' => 'Contatti'],
            ['ref_code' => 'SG-A00002', 'stato' => 'in_lavorazione', 'tipo' => 'bug', 'modulo' => 'Attivita'],
            ['ref_code' => 'SG-A00003', 'stato' => 'chiusa', 'tipo' => 'funzionalita', 'modulo' => 'Contatti'],
        ] as $r) {
            $this->insertRow('feedback', $r + ['descrizione' => 'x', 'created_at' => '2026-05-31 09:00:00']);
        }

        $onlyNew = $this->service->list(['stato' => 'nuova']);
        $this->assertSame(1, $onlyNew['total']);
        $this->assertSame('SG-A00001', $onlyNew['items'][0]['ref_code']);

        // Aperte = nuova + in_lavorazione.
        $this->assertSame(2, $this->service->countOpen());
        $this->assertSame(1, $this->service->countNew());

        // Moduli distinti per il filtro.
        $moduli = $onlyNew['moduli'];
        $this->assertContains('Contatti', $moduli);
        $this->assertContains('Attivita', $moduli);
    }

    public function testCreateDedupesIdenticalSubmissions(): void
    {
        $payload = ['descrizione' => 'Stesso identico problema'];

        $first  = $this->service->create($payload, []);
        $second = $this->service->create($payload, []);

        // Il secondo invio identico non crea un doppione: ritorna lo stesso ref.
        $this->assertSame($first['ref_code'], $second['ref_code']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn());
    }

    public function testCreateStoresDomSnapshot(): void
    {
        $result = $this->service->create(
            ['descrizione' => 'Con DOM'],
            ['url' => 'http://localhost/x'],
            '<!doctype html><html><body>ciao</body></html>'
        );

        $row = (new FeedbackRepository())->find($result['id']);
        $this->assertStringContainsString('ciao', (string) $row['dom_snapshot']);
    }

    public function testListIgnoresMaliciousSort(): void
    {
        $this->insertRow('feedback', [
            'ref_code'    => 'SG-SORT01',
            'stato'       => 'nuova',
            'descrizione' => 'x',
            'created_at'  => '2026-05-31 09:00:00',
        ]);

        $res = $this->service->list(['sort' => 'descrizione); DROP TABLE feedback;--', 'dir' => 'evil']);

        $this->assertSame('created_at', $res['sortBy']);
        $this->assertSame('DESC', $res['sortDir']);
        $this->assertSame(1, $res['total']);
    }

    public function testResolvingPurgesDomSnapshot(): void
    {
        $res = $this->service->create(
            ['descrizione' => 'Con DOM da eliminare alla chiusura'],
            ['url' => 'http://localhost/x'],
            '<!doctype html><html><body>contenuto sensibile</body></html>'
        );
        $id = $res['id'];
        $this->assertNotEmpty((new FeedbackRepository())->find($id)['dom_snapshot']);

        $this->service->triage($id, ['stato' => 'risolta']);

        $row = (new FeedbackRepository())->find($id);
        $this->assertNull($row['dom_snapshot']);
        $this->assertSame('risolta', $row['stato']);
    }

    public function testInProgressKeepsDomSnapshot(): void
    {
        $res = $this->service->create(
            ['descrizione' => 'Il DOM resta finché è aperta'],
            ['url' => 'http://localhost/x'],
            '<html><body>x</body></html>'
        );

        $this->service->triage($res['id'], ['stato' => 'in_lavorazione']);

        $this->assertNotEmpty((new FeedbackRepository())->find($res['id'])['dom_snapshot']);
    }
}
