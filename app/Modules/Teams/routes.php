<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Teams\Controllers\AdminTeamsController;
use App\Modules\Teams\Controllers\GroupPanelController;
use App\Modules\Teams\Controllers\MemberController;
use App\Modules\Teams\Controllers\MessageController;
use App\Modules\Teams\Controllers\ReactionController;
use App\Modules\Teams\Controllers\TeamsController;

// ── Admin Teams (teams.admin) — PRIMA delle route parametriche ─
$router->group([
    'prefix'     => 'admin/teams',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.admin')]], function ($r) {
        // Route statiche prima di quelle parametriche
        $r->get('/', [AdminTeamsController::class, 'index'])->name('teams.admin.index');
        $r->get('/conversations', [AdminTeamsController::class, 'conversationTable'])->name('teams.admin.conversations');
        $r->get('/cleanup-preview', [AdminTeamsController::class, 'cleanupPreview'])->name('teams.admin.cleanup_preview');
        $r->post('/cleanup', [AdminTeamsController::class, 'triggerCleanup'])->name('teams.admin.cleanup');
        // Route parametriche dopo
        $r->post('/{id}/archive', [AdminTeamsController::class, 'archiveConversation'])->name('teams.admin.archive');
        $r->delete('/{id}', [AdminTeamsController::class, 'destroy'])->name('teams.admin.destroy');
    });
});

$router->group([
    'prefix'     => 'teams',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {

    // ── Static GET routes (BEFORE parametric) ──────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.view')]], function ($r) {
        $r->get('/', [TeamsController::class, 'index'])->name('teams.index');
        $r->get('/search', [TeamsController::class, 'search'])->name('teams.search');
        $r->get('/conversations', [TeamsController::class, 'conversationList'])->name('teams.conversations');
        $r->get('/unread-count', [TeamsController::class, 'unreadCount'])->name('teams.unread-count');
        $r->get('/users/search', [TeamsController::class, 'userSearch'])->name('teams.users.search');
        // Streaming allegati (uploads/teams non servita da Apache); l'ACL
        // membership è verificata nel service.
        $r->get('/attachments/{attachmentId}', [MessageController::class, 'attachment'])->name('teams.attachments.show');
    });

    // ── Static POST routes ─────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->post('/conversations', [TeamsController::class, 'store'])->name('teams.conversations.store');
        $r->post('/conversations/direct', [TeamsController::class, 'storeDirect'])->name('teams.conversations.store-direct');
    });

    // ── Presence / Typing ──────────────────────────────────────────
    // Rate-limit a 120/min: il client invia heartbeat ogni ~10s (6/min) e typing
    // ogni keystroke (throttled). 120/min copre comodamente l'uso legittimo e
    // blocca client malevoli che potrebbero abusare di updatePresence +
    // cleanupStaleTyping (entrambi pesanti su DB).
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.view'), RateLimitMiddleware::perMinute(120)]], function ($r) {
        $r->post('/presence/heartbeat', [TeamsController::class, 'heartbeat'])->name('teams.presence.heartbeat');
        $r->post('/typing', [TeamsController::class, 'typing'])->name('teams.typing');
    });

    // ── Parametric GET routes ──────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.view')]], function ($r) {
        $r->get('/{id}', [TeamsController::class, 'show'])->name('teams.show');
        $r->get('/{id}/state', [TeamsController::class, 'pollState'])->name('teams.state');
        $r->get('/{id}/messages', [MessageController::class, 'index'])->name('teams.messages.index');
        $r->get('/{id}/messages/search', [TeamsController::class, 'searchMessagesInConversation'])->name('teams.messages.search');
        $r->get('/{id}/members', [MemberController::class, 'index'])->name('teams.members.index');
        $r->get('/{id}/mentions/autocomplete', [TeamsController::class, 'mentionAutocomplete'])->name('teams.mentions.autocomplete');
        $r->get('/{id}/messages/{messageId}/history', [MessageController::class, 'history'])->name('teams.messages.history');
        $r->get('/{id}/messages/{messageId}/readers', [MessageController::class, 'readers'])->name('teams.messages.readers');
        $r->get('/{id}/pinned', [MessageController::class, 'pinnedList'])->name('teams.pinned.list');

        // ── Group panel (offcanvas): header + media/files/links ────
        $r->get('/{id}/panel/header', [GroupPanelController::class, 'header'])->name('teams.panel.header');
        $r->get('/{id}/media', [GroupPanelController::class, 'media'])->name('teams.panel.media');
        $r->get('/{id}/files', [GroupPanelController::class, 'files'])->name('teams.panel.files');
        $r->get('/{id}/links', [GroupPanelController::class, 'links'])->name('teams.panel.links');
    });

    // ── Messages: store ────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create'), RateLimitMiddleware::perMinute(60)]], function ($r) {
        $r->post('/{id}/messages', [MessageController::class, 'store'])->name('teams.messages.store');
    });

    // ── Messages: edit/delete ──────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.delete')]], function ($r) {
        $r->put('/{id}/messages/{messageId}', [MessageController::class, 'update'])->name('teams.messages.update');
        $r->delete('/{id}/messages/{messageId}', [MessageController::class, 'destroy'])->name('teams.messages.destroy');
    });

    // ── Reactions ──────────────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->post('/{id}/messages/{messageId}/reactions', [ReactionController::class, 'toggle'])->name('teams.messages.reactions.toggle');
    });

    // ── Pin / unpin messaggio (solo admin di conversazione o teams.admin) ──
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->post('/{id}/messages/{messageId}/pin', [MessageController::class, 'togglePin'])->name('teams.messages.pin');
    });

    // ── Conversation: edit/archive/leave ───────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->put('/{id}', [TeamsController::class, 'update'])->name('teams.conversations.update');
        $r->post('/{id}/archive', [TeamsController::class, 'archive'])->name('teams.conversations.archive');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.delete')]], function ($r) {
        $r->post('/{id}/leave', [TeamsController::class, 'leave'])->name('teams.conversations.leave');
    });

    // ── Conversation: toggle mute ──────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.view')]], function ($r) {
        $r->post('/{id}/mute', [TeamsController::class, 'toggleMute'])->name('teams.conversations.mute');
        $r->post('/{id}/hide', [TeamsController::class, 'hide'])->name('teams.conversations.hide');
        $r->post('/{id}/unhide', [TeamsController::class, 'unhide'])->name('teams.conversations.unhide');
    });

    // ── Members: add/remove ────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->post('/{id}/members', [MemberController::class, 'store'])->name('teams.members.store');
        $r->delete('/{id}/members/{userId}', [MemberController::class, 'destroy'])->name('teams.members.destroy');
    });

    // ── Group avatar upload ────────────────────────────────────────
    $r->group(['middleware' => [RoleMiddleware::withPermission('teams.create')]], function ($r) {
        $r->post('/{id}/avatar', [TeamsController::class, 'uploadAvatar'])->name('teams.conversations.avatar');
    });
});
