<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Admin\Controllers\AdminIndexController;
use App\Modules\Admin\Controllers\AdminLogsController;
use App\Modules\Admin\Controllers\ChangelogController;
use App\Modules\Admin\Controllers\DataRetentionController;
use App\Modules\Admin\Controllers\DevSimulatorController;
use App\Modules\Admin\Controllers\ImpersonationController;
use App\Modules\Admin\Controllers\MailController;
use App\Modules\Admin\Controllers\ModuleController;
use App\Modules\Admin\Controllers\PaletteApiController;
use App\Modules\Admin\Controllers\RoleConstraintController;
use App\Modules\Admin\Controllers\RoleController;
use App\Modules\Admin\Controllers\SecurityDashboardController;
use App\Modules\Admin\Controllers\SecurityIncidentController;
use App\Modules\Admin\Controllers\SettingsController;
use App\Modules\Admin\Controllers\UserController;
use App\Modules\Notifications\Controllers\AdminNotificationsController;

$router->group([
    'prefix'     => 'admin',
    'middleware' => [AuthMiddleware::class, \App\Middleware\CsrfMiddleware::class, \App\Middleware\SessionSecurityMiddleware::class],
], function ($r) {

    // ---------------------------------------------------------------
    // COMMAND PALETTE API
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/api/palette', [PaletteApiController::class, 'index'])->name('admin.api.palette');
    });

    // ---------------------------------------------------------------
    // ADMIN INDEX
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/', [AdminIndexController::class, 'index'])->name('admin.index');
    });

    // ---------------------------------------------------------------
    // ADMIN DASHBOARD
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        // HTMX partials
        $r->get('/dashboard/stats', [AdminDashboardController::class, 'statsWidget'])->name('admin.dashboard.stats');
        $r->get('/dashboard/recent-logs', [AdminDashboardController::class, 'recentLogs'])->name('admin.dashboard.recent-logs');
        $r->get('/dashboard/modules', [AdminDashboardController::class, 'modulesWidget'])->name('admin.dashboard.modules');
        $r->get('/dashboard/online', [AdminDashboardController::class, 'onlineWidget'])->name('admin.dashboard.online');
    });

    // ---------------------------------------------------------------
    // LOGS — analisi e pulizia
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.logs.view')]], function ($r) {
        $r->get('/logs', [AdminLogsController::class, 'index'])->name('admin.logs.index');
        $r->get('/logs/stats', [AdminLogsController::class, 'statsWidget'])->name('admin.logs.stats');
        $r->get('/logs/export', [AdminLogsController::class, 'export'])->name('admin.logs.export');
        $r->get('/logs/audit', [AdminLogsController::class, 'auditTable'])->name('admin.logs.audit');
        $r->get('/logs/attempts', [AdminLogsController::class, 'attemptsTable'])->name('admin.logs.attempts');
        $r->get('/logs/sessions', [AdminLogsController::class, 'sessionsTable'])->name('admin.logs.sessions');
        $r->get('/logs/errors', [AdminLogsController::class, 'errorsTable'])->name('admin.logs.errors');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.logs.purge')]], function ($r) {
        $r->post('/logs/cleanup', [AdminLogsController::class, 'cleanup'])->name('admin.logs.cleanup');
    });

    // ---------------------------------------------------------------
    // SECURITY INCIDENTS — ISO 27001 A.16
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.security.view')]], function ($r) {
        $r->get('/security/incidents', [SecurityIncidentController::class, 'index'])->name('admin.security.incidents');
        $r->get('/security/incidents/summary', [SecurityIncidentController::class, 'summaryWidget'])->name('admin.security.incidents.summary');
        $r->get('/security/assets', [SecurityDashboardController::class, 'assets'])->name('admin.security.assets');
        $r->get('/security/hardening', [SecurityDashboardController::class, 'hardening'])->name('admin.security.hardening');
        $r->get('/security/logs', [SecurityDashboardController::class, 'logsStatus'])->name('admin.security.logs');
        $r->get('/security/keys', [SecurityDashboardController::class, 'keys'])->name('admin.security.keys');
        $r->get('/security/sod', [RoleConstraintController::class, 'index'])->name('admin.security.sod');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.security.manage')]], function ($r) {
        $r->post('/security/logs/rotate', [SecurityDashboardController::class, 'rotateNow'])->name('admin.security.logs.rotate');
        $r->post('/security/logs/purge', [SecurityDashboardController::class, 'purgeOld'])->name('admin.security.logs.purge');
        $r->post('/security/keys/{name}/rotated', [SecurityDashboardController::class, 'recordKeyRotation'])->name('admin.security.keys.record');
        $r->post('/security/sod/create', [RoleConstraintController::class, 'store'])->name('admin.security.sod.store');
        $r->post('/security/sod/{id}/toggle', [RoleConstraintController::class, 'toggle'])->name('admin.security.sod.toggle');
        $r->post('/security/sod/{id}/delete', [RoleConstraintController::class, 'delete'])->name('admin.security.sod.delete');
    });

    // ---------------------------------------------------------------
    // DATA RETENTION — ISO 27001 A.18.1.3
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.security.view')]], function ($r) {
        $r->get('/retention', [DataRetentionController::class, 'index'])->name('admin.retention.index');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.security.manage')]], function ($r) {
        $r->post('/retention/{id}/update', [DataRetentionController::class, 'update'])->name('admin.retention.update');
        $r->post('/retention/{id}/toggle', [DataRetentionController::class, 'toggle'])->name('admin.retention.toggle');
        $r->post('/retention/execute', [DataRetentionController::class, 'execute'])->name('admin.retention.execute');
    });

    // ---------------------------------------------------------------
    // USERS — static routes first, then parametric
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/users', [UserController::class, 'index'])->name('admin.users.index');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.create')]], function ($r) {
        $r->get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        $r->post('/users', [UserController::class, 'store'])->name('admin.users.store');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.edit')]], function ($r) {
        $r->post('/users/bulk', [UserController::class, 'bulk'])->name('admin.users.bulk');
    });

    // Parametric user routes
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/users/{id}', [UserController::class, 'show'])->name('admin.users.show');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.edit')]], function ($r) {
        $r->get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        $r->put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        $r->post('/users/{id}/roles', [UserController::class, 'updateRoles'])->name('admin.users.roles.update');
        $r->post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        $r->post('/users/{id}/reset-2fa', [UserController::class, 'resetTotp'])->name('admin.users.reset-2fa');
        $r->post('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
        $r->post('/users/{id}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('admin.users.revoke-sessions');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.delete')]], function ($r) {
        $r->delete('/users/{id}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    // Impersonate — revert FUORI dal gruppo permessi (l'utente impersonato non ha il permesso)
    $r->post('/impersonate/revert', [ImpersonationController::class, 'revert'])->name('admin.impersonate.revert');

    // Impersonate — avvio (richiede permesso specifico)
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.impersonate')]], function ($r) {
        $r->post('/users/{id}/impersonate', [ImpersonationController::class, 'start'])->name('admin.users.impersonate');
    });

    // ---------------------------------------------------------------
    // ROLES — static first (/roles, /roles/create), then parametric
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.roles.manage')]], function ($r) {
        $r->get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        $r->get('/roles/table', [RoleController::class, 'rolesTable'])->name('admin.roles.table');
        $r->get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
        $r->post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');

        // Parametric role routes (no conflict: /roles/{id} is GET-only for show,
        // but RoleController has no show() — edit/permissions have /edit and /permissions suffixes)
        $r->get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
        $r->put('/roles/{id}', [RoleController::class, 'update'])->name('admin.roles.update');
        $r->post('/roles/{id}/clone', [RoleController::class, 'cloneRole'])->name('admin.roles.clone');
        $r->delete('/roles/{id}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
        $r->get('/roles/{id}/permissions', [RoleController::class, 'permissions'])->name('admin.roles.permissions');
        $r->post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'])->name('admin.roles.permissions.update');
    });

    // ---------------------------------------------------------------
    // NOTIFICATIONS — invio notifiche agli utenti (admin)
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('notifications.admin.send')]], function ($r) {
        $r->get('/notifications/send', [AdminNotificationsController::class, 'showSend'])->name('admin.notifications.send');
        $r->post('/notifications/send', [AdminNotificationsController::class, 'store'])->name('admin.notifications.store');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('notifications.admin.manage')]], function ($r) {
        $r->get('/notifications/settings', [AdminNotificationsController::class, 'settings'])->name('admin.notifications.settings');
        $r->post('/notifications/settings', [AdminNotificationsController::class, 'updateSettings'])->name('admin.notifications.settings.update');
        $r->get('/notifications/settings/events/{slug}/edit', [AdminNotificationsController::class, 'editEvent'])->name('admin.notifications.settings.events.edit');
        $r->post('/notifications/settings/events/{slug}', [AdminNotificationsController::class, 'saveEventSettings'])->name('admin.notifications.settings.events.update');
        $r->post('/notifications/settings/events/{slug}/simulate', [AdminNotificationsController::class, 'simulateEvent'])->name('admin.notifications.settings.events.simulate');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('notifications.admin.bots')]], function ($r) {
        $r->post('/notifications/settings/bot', [AdminNotificationsController::class, 'saveBot'])->name('admin.notifications.bot.save');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('notifications.admin.manage')]], function ($r) {
        $r->post('/notifications/queue/retry-all', [AdminNotificationsController::class, 'retryAllFailed'])->name('admin.notifications.queue.retry-all');
        $r->post('/notifications/queue/{id}/retry', [AdminNotificationsController::class, 'retryQueueItem'])->name('admin.notifications.queue.retry');
    });

    // ---------------------------------------------------------------
    // MODULES — static routes BEFORE parametric
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.modules.manage')]], function ($r) {
        $r->get('/modules', [ModuleController::class, 'index'])->name('admin.modules.index');
        $r->get('/modules/import', [ModuleController::class, 'importForm'])->name('admin.modules.import');
        $r->post('/modules/import', [ModuleController::class, 'import'])->name('admin.modules.import.store');
        $r->get('/modules/{name}/export', [ModuleController::class, 'export'])->name('admin.modules.export');
        $r->get('/modules/{name}/uninstall', [ModuleController::class, 'uninstallConfirm'])->name('admin.modules.uninstall');
        $r->post('/modules/{name}/uninstall', [ModuleController::class, 'uninstallDo'])->name('admin.modules.uninstall.do');
        $r->post('/modules/{name}/toggle', [ModuleController::class, 'toggle'])->name('admin.modules.toggle');
        $r->post('/modules/{name}/import-permissions', [ModuleController::class, 'importPermissions'])->name('admin.modules.import-permissions');
    });

    // ---------------------------------------------------------------
    // SETTINGS — impostazioni applicazione
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.settings.manage')]], function ($r) {
        $r->get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        $r->post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
        $r->post('/settings/system', [SettingsController::class, 'updateSystem'])->name('admin.settings.system.update');
        $r->post('/settings/system/toggle', [SettingsController::class, 'toggleSystemSetting'])->name('admin.settings.system.toggle');
        $r->post('/settings/sso/test', [SettingsController::class, 'testOidc'])->name('admin.settings.sso.test');
    });

    // ---------------------------------------------------------------
    // MAIL — templates, log, test
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.mail.manage')]], function ($r) {
        $r->get('/mail/panel', [MailController::class, 'panel'])->name('admin.mail.panel');
        $r->get('/mail', [MailController::class, 'index'])->name('admin.mail.index');
        $r->get('/mail/templates/create', [MailController::class, 'create'])->name('admin.mail.templates.create');
        $r->post('/mail/templates', [MailController::class, 'store'])->name('admin.mail.templates.store');
        $r->get('/mail/templates/table', [MailController::class, 'templatesTable'])->name('admin.mail.templates.table');
        $r->get('/mail/templates/{id}/edit', [MailController::class, 'edit'])->name('admin.mail.templates.edit');
        $r->put('/mail/templates/{id}', [MailController::class, 'update'])->name('admin.mail.templates.update');
        $r->delete('/mail/templates/{id}', [MailController::class, 'destroy'])->name('admin.mail.templates.destroy');
        $r->post('/mail/test', [MailController::class, 'sendTest'])->name('admin.mail.test');
    });

    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.mail.log')]], function ($r) {
        $r->get('/mail/log', [MailController::class, 'log'])->name('admin.mail.log');
        $r->get('/mail/log/table', [MailController::class, 'logTable'])->name('admin.mail.log.table');
    });

    // ---------------------------------------------------------------
    // CHANGELOG — versioning interno
    // ---------------------------------------------------------------

    // Endpoint versione (accessibile a tutti gli admin autenticati)
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.users.view')]], function ($r) {
        $r->get('/changelog/version', [ChangelogController::class, 'version'])->name('admin.changelog.version');
    });

    // CRUD (richiede permesso manage) — statiche PRIMA delle parametriche
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.changelog.manage')]], function ($r) {
        $r->get('/changelog', [ChangelogController::class, 'index'])->name('admin.changelog.index');
        $r->get('/changelog/create', [ChangelogController::class, 'create'])->name('admin.changelog.create');
        $r->post('/changelog', [ChangelogController::class, 'store'])->name('admin.changelog.store');
        $r->get('/changelog/{id}', [ChangelogController::class, 'show'])->name('admin.changelog.show');
        $r->get('/changelog/{id}/edit', [ChangelogController::class, 'edit'])->name('admin.changelog.edit');
        $r->put('/changelog/{id}', [ChangelogController::class, 'update'])->name('admin.changelog.update');
        $r->delete('/changelog/{id}', [ChangelogController::class, 'destroy'])->name('admin.changelog.destroy');
        $r->post('/changelog/{id}/publish', [ChangelogController::class, 'publish'])->name('admin.changelog.publish');
    });

    // ---------------------------------------------------------------
    // DEV SIMULATOR
    // ---------------------------------------------------------------
    $r->group(['middleware' => [RoleMiddleware::withPermission('admin.dev.simulator')]], function ($r) {
        $r->get('/dev/simulator', [DevSimulatorController::class, 'index'], 'admin.dev.simulator');
        $r->get('/dev/error/{code}', [DevSimulatorController::class, 'errorPreview'], 'admin.dev.error-preview');
    });
});
