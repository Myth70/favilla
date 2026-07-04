<?php

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  ROUTE DI MODULO — Copia e adatta per il tuo nuovo modulo      ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * ISTRUZIONI:
 * 1. Sostituisci il prefix 'example' con il tuo (es. 'clienti')
 * 2. Sostituisci ExampleController con il tuo controller
 * 3. Aggiorna i nomi route (es. 'example.index' → 'clienti.index')
 * 4. Aggiorna i permessi (es. 'example.view' → 'clienti.view')
 *
 * REGOLE FONDAMENTALI:
 * - Route statiche SEMPRE prima di quelle con {parametro}
 *   (altrimenti /create viene catturato da /{id})
 * - Permessi separati per azione: view, create, edit, delete
 * - AuthMiddleware + CsrfMiddleware sul gruppo esterno
 * - RoleMiddleware::withPermission() sui sotto-gruppi per azione
 *
 * $router è iniettato da ModuleLoader.
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\_Template\Controllers\ExampleController;

$router->group([
    'prefix'     => 'example',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── VIEW: lista (route statiche prima) ─────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('example.view')]], function ($r) {
        $r->get('/', [ExampleController::class, 'index'])->name('example.index');
    });

    // ── CREATE: form + salvataggio (statiche, prima di /{id}) ─────
    $r->group(['middleware' => [RoleMiddleware::withPermission('example.create')]], function ($r) {
        $r->get('/create', [ExampleController::class, 'create'])->name('example.create');
        $r->post('/', [ExampleController::class, 'store'])->name('example.store');
    });

    // ── VIEW: dettaglio (parametrica, DOPO le statiche) ───────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('example.view')]], function ($r) {
        $r->get('/{id}', [ExampleController::class, 'show'])->name('example.show');
    });

    // ── EDIT: form + aggiornamento (parametriche) ─────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('example.edit')]], function ($r) {
        $r->get('/{id}/edit', [ExampleController::class, 'edit'])->name('example.edit');
        $r->put('/{id}', [ExampleController::class, 'update'])->name('example.update');
    });

    // ── DELETE: eliminazione (parametrica) ─────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('example.delete')]], function ($r) {
        $r->delete('/{id}', [ExampleController::class, 'destroy'])->name('example.destroy');
    });

});
