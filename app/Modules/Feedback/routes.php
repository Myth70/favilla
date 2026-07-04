<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Feedback\Controllers\FeedbackController;

$router->group([
    'prefix'     => 'feedback',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── Pagina di fallback (usata dalle pagine d'errore) ──
    $r->get('/new', [FeedbackController::class, 'reportPage'])->name('feedback.new');

    // ── Invio universale: ogni utente loggato può segnalare (nessun permesso) ──
    // Rate-limit anti-abuso: max 8 invii / 10 minuti per utente.
    $r->group(['middleware' => [RateLimitMiddleware::make(8, 600)]], function ($r) {
        $r->post('/', [FeedbackController::class, 'store'])->name('feedback.store');
    });

    // ── Console admin — lettura (rotte statiche prima delle parametriche) ──
    $r->group(['middleware' => [RoleMiddleware::withPermission('feedback.view')]], function ($r) {
        $r->get('/admin', [FeedbackController::class, 'index'])->name('feedback.admin.index');
        $r->get('/admin/{id}/export', [FeedbackController::class, 'export'])->name('feedback.admin.export');
        $r->get('/admin/{id}/dom', [FeedbackController::class, 'dom'])->name('feedback.admin.dom');
        $r->get('/admin/{id}', [FeedbackController::class, 'show'])->name('feedback.admin.show');
    });

    // ── Console admin — triage / eliminazione (POST + hidden _method) ──
    $r->group(['middleware' => [RoleMiddleware::withPermission('feedback.manage')]], function ($r) {
        $r->put('/admin/{id}/triage', [FeedbackController::class, 'triage'])->name('feedback.admin.triage');
        $r->delete('/admin/{id}', [FeedbackController::class, 'destroy'])->name('feedback.admin.destroy');
    });
});
