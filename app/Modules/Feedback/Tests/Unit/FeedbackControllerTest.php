<?php

namespace App\Modules\Feedback\Tests\Unit;

use App\Modules\Feedback\Controllers\FeedbackController;
use Tests\ModuleTestCase;

/**
 * Controller-level tests for FeedbackController::store().
 *
 * The base Controller's json()/redirect()/render() terminate the request with
 * exit(), so we drive the action through a test subclass that overrides those
 * protected helpers to capture the outcome instead. This exercises the real
 * controller logic — input parsing, the AJAX-vs-form branch, and validation
 * error handling — without touching framework code.
 */
class FeedbackControllerTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ref_code TEXT, tipo TEXT, severita TEXT, stato TEXT,
                titolo TEXT, descrizione TEXT, passi TEXT,
                pagina_url TEXT, route_name TEXT, modulo TEXT,
                contesto_json TEXT, errori_console_json TEXT, dom_snapshot TEXT,
                user_agent TEXT, viewport TEXT, app_version TEXT,
                created_by INTEGER, assegnata_a INTEGER, note_admin TEXT,
                created_at TEXT, updated_at TEXT, deleted_at TEXT
            )
        ');
        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT, email TEXT, is_active INTEGER DEFAULT 1, deleted_at TEXT
            )
        ');
        $this->insertRow('users', ['id' => 7, 'name' => 'Mario Rossi', 'email' => 'mario@example.it', 'is_active' => 1]);

        $_SESSION = [
            'user_id'    => 7,
            'user_name'  => 'Mario Rossi',
            'user_email' => 'mario@example.it',
            'user_roles' => ['admin'],
        ];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testAjaxStorePersistsAndReturnsJson(): void
    {
        $_SERVER['HTTP_HX_REQUEST'] = 'true'; // → isPartialRequest() == true → JSON branch
        $_POST = [
            'tipo'        => 'bug',
            'severita'    => 'alta',
            'titolo'      => 'Errore salvataggio',
            'descrizione' => 'Il salvataggio della pratica va in errore.',
            'passi'       => '1. apro 2. salvo',
        ];

        $controller = new CapturingFeedbackController();
        $controller->store();

        $this->assertSame(200, $controller->jsonStatus);
        $this->assertTrue($controller->jsonData['ok'] ?? null);
        $this->assertNotEmpty($controller->jsonData['ref_code'] ?? null);
        $this->assertNull($controller->redirectUrl, 'AJAX mode must not redirect');

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
        $this->assertSame(1, $count, 'the feedback row must be persisted');
    }

    public function testAjaxStoreRejectsMissingDescriptionWith422(): void
    {
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        $_POST = [
            'tipo'        => 'bug',
            'severita'    => 'media',
            'titolo'      => 'Senza descrizione',
            'descrizione' => '',   // → InvalidArgumentException in the service
        ];

        $controller = new CapturingFeedbackController();
        $controller->store();

        $this->assertSame(422, $controller->jsonStatus);
        $this->assertFalse($controller->jsonData['ok'] ?? null);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
        $this->assertSame(0, $count, 'nothing must be persisted on a validation failure');
    }

    public function testFormStoreRedirectsAndFlashesOnSuccess(): void
    {
        // No HX/AJAX header → form branch → flash + redirect.
        $_POST = [
            'tipo'        => 'idea',
            'severita'    => 'bassa',
            'titolo'      => 'Suggerimento',
            'descrizione' => 'Aggiungere un filtro per data.',
        ];

        $controller = new CapturingFeedbackController();
        $controller->store();

        $this->assertNull($controller->jsonData, 'form mode must not emit JSON');
        $this->assertNotNull($controller->redirectUrl, 'form mode must redirect after success');
        $this->assertArrayHasKey('_flash_success', $_SESSION);
        $this->assertStringContainsString('Riferimento', $_SESSION['_flash_success']);
    }
}

/**
 * Captures the terminal response helpers instead of exiting the process.
 */
class CapturingFeedbackController extends FeedbackController
{
    public ?array $jsonData = null;
    public ?int $jsonStatus = null;
    public ?string $redirectUrl = null;
    public ?string $renderedView = null;
    /** @var array<string,mixed> */
    public array $renderedData = [];

    protected function json(array $data, int $status = 200): void
    {
        $this->jsonData = $data;
        $this->jsonStatus = $status;
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;
    }

    protected function render(string $template, array $data = []): void
    {
        $this->renderedView = $template;
        $this->renderedData = $data;
    }
}
