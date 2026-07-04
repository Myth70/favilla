<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Contacts\Controllers\CategoriesController;
use App\Modules\Contacts\Controllers\ContactsController;
use App\Modules\Contacts\Controllers\FileImportController;
use App\Modules\Contacts\Controllers\ImportController;
use App\Modules\Contacts\Controllers\RecurrencesController;
use App\Modules\Contacts\Controllers\ReminderController;
use App\Modules\Contacts\Controllers\SharingController;

$router->group([
    'prefix'     => 'contacts',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── STATICHE — devono venire PRIMA di /{id} ─────────────────────────────

    // Lista + ricerca HTMX
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.view')]], function ($r) {
        $r->get('/', [ContactsController::class, 'index'])->name('contacts.index');
        $r->get('/search', [ContactsController::class, 'search'])->name('contacts.search');
    });

    // Creazione
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.create')]], function ($r) {
        $r->get('/create', [ContactsController::class, 'create'])->name('contacts.create');
        $r->post('/', [ContactsController::class, 'store'])->name('contacts.store');
    });

    // Gestione categorie (tutto statico /categories/*)
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.view')]], function ($r) {
        $r->get('/categories', [CategoriesController::class, 'index'])->name('contacts.categories.index');
        $r->post('/categories', [CategoriesController::class, 'store'])->name('contacts.categories.store');
        $r->put('/categories/{cid}', [CategoriesController::class, 'update'])->name('contacts.categories.update');
        $r->delete('/categories/{cid}', [CategoriesController::class, 'destroy'])->name('contacts.categories.destroy');
    });

    // Reminder (fire-and-forget, chiamato via HTMX load)
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.view')]], function ($r) {
        $r->post('/reminders/process', [ReminderController::class, 'process'])->name('contacts.reminders.process');
    });

    // Import da altri moduli (tutto statico /import/*)
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.import')]], function ($r) {
        $r->get('/import', [ImportController::class, 'index'])->name('contacts.import.index');

        // Import da file (CSV / vCard) — rotte statiche, devono venire prima
        // di /import/{module}/{source} per il match in ordine del router.
        $r->get('/import/file', [FileImportController::class, 'upload'])->name('contacts.import.file.upload');
        $r->post('/import/file/upload', [FileImportController::class, 'store'])->name('contacts.import.file.store');
        $r->get('/import/file/preview', [FileImportController::class, 'preview'])->name('contacts.import.file.preview');
        $r->post('/import/file/commit', [FileImportController::class, 'commit'])->name('contacts.import.file.commit');
        $r->get('/import/file/result', [FileImportController::class, 'result'])->name('contacts.import.file.result');
        $r->get('/import/file/template.csv', [FileImportController::class, 'template'])->name('contacts.import.file.template');

        $r->get('/import/{module}/{source}', [ImportController::class, 'browse'])->name('contacts.import.browse');
        $r->get('/import/{module}/{source}/list', [ImportController::class, 'listPartial'])->name('contacts.import.list');
        $r->get('/import/{module}/{source}/{sourceId}', [ImportController::class, 'preview'])->name('contacts.import.preview');
        $r->post('/import/{module}/{source}/{sourceId}', [ImportController::class, 'store'])->name('contacts.import.store');
    });

    // ── PARAMETRICHE /{id} — DOPO le statiche ───────────────────────────────

    // Dettaglio
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.view')]], function ($r) {
        $r->get('/{id}', [ContactsController::class, 'show'])->name('contacts.show');
        // Streaming foto (uploads/contacts non servita da Apache); stessa
        // visibilità di show (owner o share per ruolo).
        $r->get('/{id}/foto', [ContactsController::class, 'foto'])->name('contacts.foto');
    });

    // Modifica
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.edit')]], function ($r) {
        $r->get('/{id}/edit', [ContactsController::class, 'edit'])->name('contacts.edit');
        $r->put('/{id}', [ContactsController::class, 'update'])->name('contacts.update');
    });

    // Eliminazione
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.delete')]], function ($r) {
        $r->delete('/{id}', [ContactsController::class, 'destroy'])->name('contacts.destroy');
    });

    // Toggle preferito (edit permission)
    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.edit')]], function ($r) {
        $r->post('/{id}/toggle-preferito', [ContactsController::class, 'togglePreferito'])->name('contacts.toggle-preferito');
    });

    // ── SHARING per ruolo (nested sotto /{id}) ──────────────────────────────

    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.share')]], function ($r) {
        $r->get('/{id}/sharing', [SharingController::class, 'edit'])->name('contacts.sharing.edit');
        $r->put('/{id}/sharing', [SharingController::class, 'update'])->name('contacts.sharing.update');
        $r->delete('/{id}/sharing/{rid}', [SharingController::class, 'destroy'])->name('contacts.sharing.destroy');
    });

    // ── RICORRENZE (nested sotto /{id}) ─────────────────────────────────────

    $r->group(['middleware' => [RoleMiddleware::withPermission('contacts.edit')]], function ($r) {
        $r->post('/{id}/recurrences', [RecurrencesController::class, 'store'])->name('contacts.recurrences.store');
        $r->get('/{id}/recurrences/list', [RecurrencesController::class, 'listPartial'])->name('contacts.recurrences.list');
        $r->get('/{id}/recurrences/{rid}/edit', [RecurrencesController::class, 'editForm'])->name('contacts.recurrences.edit');
        $r->put('/{id}/recurrences/{rid}', [RecurrencesController::class, 'update'])->name('contacts.recurrences.update');
        $r->delete('/{id}/recurrences/{rid}', [RecurrencesController::class, 'destroy'])->name('contacts.recurrences.destroy');
    });
});
