<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Modules\Notifications\Controllers\NotificationsController;
use App\Modules\Notifications\Controllers\TelegramWebhookController;

$router->post('/notifications/telegram/webhook/{secret}', [TelegramWebhookController::class, 'webhook'])
  ->name('notifications.telegram.webhook');

$router->group([
    'prefix'     => 'notifications',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ------------------------------------------------------------------
    // HTMX endpoints — GET (CSRF ignorato su GET dal middleware)
    // ------------------------------------------------------------------

    // Badge conteggio (polling)
    $r->get('/unread-count', [NotificationsController::class, 'unreadCount'])
      ->name('notifications.unread-count');

    // Contenuto dropdown campanella
    $r->get('/dropdown', [NotificationsController::class, 'dropdown'])
      ->name('notifications.dropdown');

    // Lista completa (pagina)
    $r->get('/', [NotificationsController::class, 'index'])
      ->name('notifications.index');

    // Impostazioni personali notifiche
    $r->get('/settings', [NotificationsController::class, 'settings'])
      ->name('notifications.settings');

    // ------------------------------------------------------------------
    // Azioni di scrittura — CSRF validato dal middleware esterno
    // ------------------------------------------------------------------

    // Static routes BEFORE parametric
    $r->post('/read-all', [NotificationsController::class, 'markAllRead'])
      ->name('notifications.read-all');

    $r->post('/bulk/read', [NotificationsController::class, 'markSelectedRead'])
      ->name('notifications.bulk-read');

    $r->post('/bulk/delete', [NotificationsController::class, 'destroySelected'])
      ->name('notifications.bulk-destroy');

    $r->post('/settings', [NotificationsController::class, 'updateSettings'])
      ->name('notifications.settings.update');

    $r->post('/settings/telegram/regenerate', [NotificationsController::class, 'regenerateTelegramLink'])
      ->name('notifications.settings.telegram.regenerate');

    $r->post('/settings/telegram/disconnect', [NotificationsController::class, 'disconnectTelegram'])
      ->name('notifications.settings.telegram.disconnect');

    $r->post('/{id}/read', [NotificationsController::class, 'markRead'])
      ->name('notifications.read');

    $r->delete('/{id}', [NotificationsController::class, 'destroy'])
      ->name('notifications.destroy');
});
