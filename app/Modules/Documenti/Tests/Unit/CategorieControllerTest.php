<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\CategorieController;
use Tests\ControllerTestCase;

class CategorieControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE IF NOT EXISTS documenti_categorie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NULL,
                nome TEXT NOT NULL,
                slug TEXT NOT NULL,
                codice TEXT NOT NULL,
                descrizione TEXT NULL,
                path TEXT NULL,
                depth INTEGER NOT NULL DEFAULT 0,
                approvazione_richiesta INTEGER NOT NULL DEFAULT 1,
                reminder_giorni_default TEXT NULL,
                ordine INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                updated_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS documenti (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                categoria_id INTEGER NOT NULL,
                titolo TEXT NOT NULL,
                owner_user_id INTEGER NOT NULL,
                stato TEXT NOT NULL DEFAULT 'bozza',
                deleted_at TEXT NULL
            );
        ");

        $this->actingAs(1, ['documenti.access', 'documenti.manage_categorie']);
    }

    public function testIndexRendersCategoryTree(): void
    {
        $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'slug' => 'generale', 'codice' => 'GEN']);

        $result = $this->dispatch(CategorieController::class, 'index', []);

        $this->assertTrue($result->didRender());
        $this->assertCount(1, $result->renderedData()['categorie']);
    }

    /**
     * Regressione: albero_categorie.php legge $cat['n_documenti'] per decidere
     * se il pulsante elimina è attivo. Se il repository non lo popola, il
     * conteggio resta sempre 0 e il pulsante appare attivo anche per categorie
     * con documenti (il server rifiuta comunque, ma la UI è fuorviante).
     */
    public function testIndexTreeIncludesDocumentCountPerCategory(): void
    {
        $catId = $this->insertRow('documenti_categorie', ['nome' => 'Con doc', 'slug' => 'con-doc', 'codice' => 'CDO']);
        $this->insertRow('documenti', ['categoria_id' => $catId, 'titolo' => 'Doc', 'owner_user_id' => 1]);

        $result = $this->dispatch(CategorieController::class, 'index', []);

        $categorie = $result->renderedData()['categorie'];
        $this->assertSame(1, (int) $categorie[0]['n_documenti']);
    }

    public function testStoreCreatesCategory(): void
    {
        $result = $this->withPost(['nome' => 'Legale', 'codice' => 'LEG'])
            ->dispatch(CategorieController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_categorie')->fetchColumn());
    }

    public function testStoreRejectsMissingCodice(): void
    {
        $result = $this->withPost(['nome' => 'Legale'])
            ->dispatch(CategorieController::class, 'store', []);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_categorie')->fetchColumn());
    }

    public function testUpdateUpdatesCategory(): void
    {
        $catId = $this->insertRow('documenti_categorie', ['nome' => 'Generale', 'slug' => 'generale', 'codice' => 'GEN']);

        $result = $this->withPost(['nome' => 'Generale aggiornato', 'codice' => 'GEN'])
            ->dispatch(CategorieController::class, 'update', [(string) $catId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('Generale aggiornato', $this->pdo->query("SELECT nome FROM documenti_categorie WHERE id = {$catId}")->fetchColumn());
    }

    public function testSpostaMovesCategoryUnderNewParent(): void
    {
        $parentId = $this->insertRow('documenti_categorie', ['nome' => 'Parent', 'slug' => 'parent', 'codice' => 'PAR', 'path' => '/1/']);
        $childId = $this->insertRow('documenti_categorie', ['nome' => 'Child', 'slug' => 'child', 'codice' => 'CHI', 'path' => '/2/']);

        $result = $this->withPost(['new_parent_id' => (string) $parentId])
            ->dispatch(CategorieController::class, 'sposta', [(string) $childId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame($parentId, (int) $this->pdo->query("SELECT parent_id FROM documenti_categorie WHERE id = {$childId}")->fetchColumn());
    }

    public function testDestroyRemovesEmptyCategory(): void
    {
        $catId = $this->insertRow('documenti_categorie', ['nome' => 'Vuota', 'slug' => 'vuota', 'codice' => 'VUO']);

        $result = $this->dispatch(CategorieController::class, 'destroy', [(string) $catId]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM documenti_categorie WHERE id = {$catId}")->fetchColumn());
    }

    public function testDestroyRefusesCategoryWithDocuments(): void
    {
        $catId = $this->insertRow('documenti_categorie', ['nome' => 'Con doc', 'slug' => 'con-doc', 'codice' => 'CDO']);
        $this->insertRow('documenti', ['categoria_id' => $catId, 'titolo' => 'Doc', 'owner_user_id' => 1]);

        $result = $this->dispatch(CategorieController::class, 'destroy', [(string) $catId]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documenti_categorie')->fetchColumn());
    }

    public function testQuickStoreReturnsJsonSuccess(): void
    {
        $result = $this->withPost(['nome' => 'Rapida', 'codice' => 'RAP'])
            ->dispatch(CategorieController::class, 'quickStore', []);

        $payload = json_decode($result->echoed, true);
        $this->assertTrue($payload['success']);
    }

    public function testQuickStoreReturns422OnValidationFailure(): void
    {
        $result = $this->withPost(['nome' => 'Rapida'])
            ->dispatch(CategorieController::class, 'quickStore', []);

        $this->assertSame(422, http_response_code());
    }
}
