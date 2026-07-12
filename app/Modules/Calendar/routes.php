<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Api\Middleware\ApiRateLimitMiddleware;
use App\Modules\Api\Middleware\ApiTokenMiddleware;
use App\Modules\Calendar\Controllers\Api\CalendarApiController;
use App\Modules\Calendar\Controllers\CalendarController;

// ── API v1 — token-based, stateless (riusa CalendarService). Static prima di {id}.
$router->group([
    'prefix'     => 'api/v1/calendar',
    'middleware' => [ApiTokenMiddleware::class, ApiRateLimitMiddleware::class],
], function ($r) {
    $r->get('/events', [CalendarApiController::class, 'index'])->name('api.calendar.events.index');
    $r->get('/events/{id}', [CalendarApiController::class, 'show'])->name('api.calendar.events.show');
});

$router->group([
    'prefix'     => 'calendar',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── View (static routes first) ───────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('calendar.view')]], function ($r) {
        $r->get('/', [CalendarController::class, 'index'])->name('calendar.index');
        $r->get('/agenda', [CalendarController::class, 'agenda'])->name('calendar.agenda');
        $r->get('/events', [CalendarController::class, 'events'])->name('calendar.events');
        $r->get('/export-ics', [CalendarController::class, 'exportIcs'])->name('calendar.export_ics');
    });

    // ── Create ───────────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('calendar.create')]], function ($r) {
        $r->get('/create', [CalendarController::class, 'create'])->name('calendar.create');
        $r->post('/', [CalendarController::class, 'store'])->name('calendar.store');
        $r->post('/import-ics', [CalendarController::class, 'importIcs'])->name('calendar.import_ics');
    });

    // ── Show (parametric, after static) ──────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('calendar.view')]], function ($r) {
        $r->get('/{id}', [CalendarController::class, 'show'])->name('calendar.show');
    });

    // ── Edit ─────────────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('calendar.edit')]], function ($r) {
        $r->get('/{id}/edit', [CalendarController::class, 'edit'])->name('calendar.edit');
        $r->put('/{id}', [CalendarController::class, 'update'])->name('calendar.update');
        $r->put('/{id}/move', [CalendarController::class, 'move'])->name('calendar.move');
    });

    // ── Delete ───────────────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('calendar.delete')]], function ($r) {
        $r->delete('/{id}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    });
});
