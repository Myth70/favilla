<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SessionSecurityMiddleware;
use App\Modules\HelpOnline\Controllers\HelpOnlineController;

$router->group([
    'prefix' => 'admin/help-online',
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        SessionSecurityMiddleware::class,
        RoleMiddleware::withPermission('helponline.admin'),
    ],
], function ($r) {
    $r->get('/', [HelpOnlineController::class, 'adminIndex'])->name('helponline.admin.index');

    // Moduli
    $r->get('/modules', [HelpOnlineController::class, 'adminModules'])->name('helponline.admin.modules');
    $r->post('/modules/create', [HelpOnlineController::class, 'adminModuleCreate'])->name('helponline.admin.modules.create');
    $r->get('/modules/{id}/edit', [HelpOnlineController::class, 'adminModuleEdit'])->name('helponline.admin.modules.edit');
    $r->post('/modules/{id}/update', [HelpOnlineController::class, 'adminModuleUpdate'])->name('helponline.admin.modules.update');
    $r->post('/modules/{id}/delete', [HelpOnlineController::class, 'adminModuleDelete'])->name('helponline.admin.modules.delete');

    // Domande/Risposte (entries)
    $r->get('/entries', [HelpOnlineController::class, 'adminEntries'])->name('helponline.admin.entries');
    $r->post('/entries/create', [HelpOnlineController::class, 'adminEntryCreate'])->name('helponline.admin.entries.create');
    $r->get('/entries/{id}/edit', [HelpOnlineController::class, 'adminEntryEdit'])->name('helponline.admin.entries.edit');
    $r->post('/entries/{id}/update', [HelpOnlineController::class, 'adminEntryUpdate'])->name('helponline.admin.entries.update');
    $r->post('/entries/{id}/delete', [HelpOnlineController::class, 'adminEntryDelete'])->name('helponline.admin.entries.delete');
    $r->post('/entries/{id}/aliases', [HelpOnlineController::class, 'adminEntryAliasesSave'])->name('helponline.admin.entries.aliases.save');

    // Query log / analytics
    $r->get('/queries', [HelpOnlineController::class, 'adminQueries'])->name('helponline.admin.queries');

    // Reindicizzazione motore
    $r->post('/sync', [HelpOnlineController::class, 'sync'])->name('helponline.admin.sync');
});

$router->group([
    'middleware' => [
        AuthMiddleware::class,
        CsrfMiddleware::class,
        SessionSecurityMiddleware::class,
    ],
], function ($r) {
    $r->get('/help', [HelpOnlineController::class, 'index'])->name('helponline.index');
    $r->get('/help/panel', [HelpOnlineController::class, 'panel'])->name('helponline.panel');

    // Endpoint di scrittura (help_queries): rate-limit anti-spam.
    $r->group(['middleware' => [\App\Middleware\RateLimitMiddleware::perMinute(30)]], function ($r) {
        $r->post('/help/ask', [HelpOnlineController::class, 'ask'])->name('helponline.ask');
        $r->post('/help/feedback', [HelpOnlineController::class, 'feedback'])->name('helponline.feedback');
    });
});
