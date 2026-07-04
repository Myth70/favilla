<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\NotificationAdminService;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;
use App\Traits\ControllerHelpers;

class AdminNotificationsController extends Controller
{
    use ControllerHelpers;

    private NotificationAdminService $adminService;

    public function __construct()
    {
        $this->adminService = app(NotificationAdminService::class);
    }

    public function settings(): void
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        $this->adminService->syncEventRegistry();
        $data = $this->adminService->getDashboardData();

        $this->render('Notifications/Views/admin_settings', array_merge($data, [
            'pageTitle'   => t('notifications.breadcrumb.dispatcher'),
            'errors'      => $errors,
            'breadcrumbs' => [
                ['label' => t('notifications.breadcrumb.admin'), 'route' => 'admin.dashboard'],
                ['label' => t('notifications.breadcrumb.dispatcher')],
            ],
        ]));
    }

    public function simulateEvent(string $slug): void
    {
        $this->adminService->syncEventRegistry();
        $event = $this->adminService->getEventCardData($slug);
        if ($event === null) {
            flash_error(t('notifications.flash.event_not_in_catalog'));
            $this->redirect(route('admin.notifications.settings') . '#pane-events');
            return;
        }

        $admins = $this->getActiveAdminRecipients();
        if (empty($admins)) {
            flash_error(t('notifications.flash.no_admin_for_sim'));
            $this->redirect(route('admin.notifications.settings') . '#pane-events');
            return;
        }

        $targetUserId = $this->resolveSimulationRecipientId($admins);
        $eventSlug = (string) ($event['slug'] ?? $slug);
        $sourceModule = (string) ($event['module_slug'] ?? 'Notifications');
        $defaultType = (string) ($event['default_level'] ?? 'info');
        $allowedTypes = ['info', 'success', 'warning', 'danger'];
        $type = in_array($defaultType, $allowedTypes, true) ? $defaultType : 'info';
        $icon = trim((string) ($event['icon'] ?? ''));
        $context = $this->buildSimulationContext($event);

        $fromUserId = (int) ($_SESSION['user_id'] ?? 0) ?: null;
        NotificationService::dispatchEventToUser(
            $eventSlug,
            $sourceModule,
            $targetUserId,
            $context,
            route('admin.notifications.settings'),
            $fromUserId,
            $type,
            $icon !== '' ? $icon : null
        );

        AuditService::log('notification_event_simulated', 'notification', $targetUserId, null, [
            'event_slug' => $eventSlug,
            'source_module' => $sourceModule,
            'type' => $type,
        ]);

        $recipientName = 'Admin #' . $targetUserId;
        foreach ($admins as $adminUser) {
            if ((int) ($adminUser['id'] ?? 0) === $targetUserId) {
                $recipientName = (string) ($adminUser['name'] ?? $recipientName);
                break;
            }
        }

        $_SESSION['_flash_success'] = t('notifications.flash.event_simulated', [
            'name'      => (string) ($event['name'] ?? $eventSlug),
            'recipient' => $recipientName,
        ]);
        $this->redirect(route('admin.notifications.settings') . '#pane-events');
    }

    private function getActiveAdminRecipients(): array
    {
        return $this->adminService->getActiveAdminRecipients();
    }

    private function resolveSimulationRecipientId(array $admins): int
    {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        foreach ($admins as $adminUser) {
            if ((int) ($adminUser['id'] ?? 0) === $currentUserId) {
                return $currentUserId;
            }
        }

        return (int) ($admins[0]['id'] ?? 0);
    }

    private function buildSimulationContext(array $event): array
    {
        $eventName = (string) ($event['name'] ?? 'Evento notifiche');
        $eventSlug = (string) ($event['slug'] ?? 'event.simulated');
        $moduleSlug = (string) ($event['module_slug'] ?? 'notifications');
        $now = date('Y-m-d H:i:s');

        $context = [
            'title' => $eventName,
            'body' => 'Simulazione evento ' . $eventSlug . ' eseguita da admin.',
            'message' => 'Questo è un trigger di test del dispatcher per verificare il template.',
            'user' => auth()['name'] ?? 'Admin',
            'user_name' => auth()['name'] ?? 'Admin',
            'module_slug' => $moduleSlug,
            'event_slug' => $eventSlug,
            'datetime' => $now,
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'date_it' => date('d/m/Y'),
            'time_it' => date('H:i'),
        ];

        $variables = is_array($event['context_variables'] ?? null) ? $event['context_variables'] : [];
        foreach ($variables as $key => $label) {
            $k = (string) $key;
            if (isset($context[$k])) {
                continue;
            }
            $context[$k] = $this->sampleValueForContextKey($k, (string) $label);
        }

        return $context;
    }

    private function sampleValueForContextKey(string $key, string $label): string
    {
        $k = strtolower($key);
        return match (true) {
            str_contains($k, 'article') || str_contains($k, 'post') => 'Articolo demo: Template in test',
            str_contains($k, 'contact') || str_contains($k, 'cliente') => 'Contatto Demo Srl',
            str_contains($k, 'team') => 'Team Operativo',
            str_contains($k, 'task') || str_contains($k, 'tasks') => 'Attività demo #42',
            str_contains($k, 'id') => '123',
            str_contains($k, 'url') || str_contains($k, 'link') => route('admin.notifications.settings'),
            str_contains($k, 'date') => date('Y-m-d'),
            str_contains($k, 'time') => date('H:i'),
            default => 'Esempio ' . ($label !== '' ? $label : $key),
        };
    }

    public function updateSettings(): void
    {
        $updated = $this->adminService->updateBindings($_POST);
        flash_success(t('notifications.flash.bindings_updated', ['count' => $updated]));
        $this->redirect(route('admin.notifications.settings'));
    }

    public function saveEventSettings(string $slug): void
    {
        $updated = $this->adminService->updateEventBindings($slug, $_POST);

        if ($this->isHtmxRequest()) {
            header('HX-Trigger: ' . json_encode([
                'notify'                => ['message' => t('notifications.flash.event_updated', ['count' => $updated]), 'type' => 'success'],
                'ntasCloseModal'        => true,
                'ntasRefreshEventsTable' => true,
            ]));
            return;
        }

        flash_success(t('notifications.flash.event_updated', ['count' => $updated]));
        $this->redirect(route('admin.notifications.settings'));
    }

    public function editEvent(string $slug): void
    {
        $this->adminService->syncEventRegistry();
        $event = $this->adminService->getEventCardData($slug);
        if ($event === null) {
            if ($this->isHtmxRequest()) {
                http_response_code(404);
                echo '<div class="alert alert-danger m-3">' . e(t('notifications.flash.event_not_found')) . '</div>';
                return;
            }
            flash_error(t('notifications.flash.event_not_found'));
            $this->redirect(route('admin.notifications.settings'));
            return;
        }

        $this->renderPartial('Notifications/Views/partials/admin_event_modal', [
            'event' => $event,
        ]);
    }

    public function saveBot(): void
    {
        $errors = $this->adminService->validateBot($_POST);
        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $this->redirect(route('admin.notifications.settings') . '#telegram-bot');
            return;
        }

        $this->adminService->saveBot($_POST, (int) (auth()['id'] ?? 0));
        flash_success(t('notifications.flash.bot_saved'));
        $this->redirect(route('admin.notifications.settings') . '#telegram-bot');
    }

    // ------------------------------------------------------------------
    // GET /admin/notifications/send — form di invio
    // ------------------------------------------------------------------
    public function showSend(): void
    {
        $users = NotificationService::getActiveUsers();
        $roles = NotificationService::getAvailableRoles();

        $errors    = $_SESSION['_errors'] ?? [];
        $old       = $_SESSION['_old']    ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        // Preselect user_id se passato via GET (dal bottone in show utente)
        $preselect = (int) ($_GET['user_id'] ?? 0);

        // Return URL: usa il parametro back se presente, altrimenti il referer, infine la lista utenti
        $returnUrl = $_GET['back'] ?? ($_SERVER['HTTP_REFERER'] ?? route('admin.users.index'));
        // Sicurezza: accetta solo URL relativi (stesso host)
        if (!str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, route('admin.dashboard'))) {
            $returnUrl = route('admin.users.index');
        }

        // Canali attivi per system.direct_send
        $directSendEvent = $this->adminService->getEventCardData('system.direct_send');
        $activeChannels = [];
        if ($directSendEvent && !empty($directSendEvent['channels'])) {
            foreach ($directSendEvent['channels'] as $ch) {
                $activeChannels[(string) $ch['slug']] = !empty($ch['enabled']);
            }
        }

        $this->render('Notifications/Views/admin_send', [
            'pageTitle'   => t('notifications.breadcrumb.send'),
            'breadcrumbs' => [
                ['label' => t('notifications.breadcrumb.admin'), 'route' => 'admin.dashboard'],
                ['label' => t('notifications.breadcrumb.send')],
            ],
            'users'          => $users,
            'roles'          => $roles,
            'errors'         => $errors,
            'old'            => $old,
            'preselect'      => $preselect,
            'returnUrl'      => $returnUrl,
            'activeChannels' => $activeChannels,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /admin/notifications/send — elaborazione invio
    // ------------------------------------------------------------------
    public function store(): void
    {
        $sendMode  = $_POST['send_mode'] ?? 'user';
        $toUserId  = (int) ($_POST['user_id'] ?? 0);
        $roleSlug  = trim($_POST['role_slug'] ?? '');
        $clean     = $this->cleanPost(['title', 'body', 'type', 'link', 'icon']);
        $title     = $clean['title'];
        $body      = $clean['body'];
        $type      = $clean['type'] ?: 'info';
        $link      = $clean['link'];
        $icon      = $clean['icon'] ?: null;

        $errors = [];

        if ($sendMode === 'role') {
            if ($roleSlug === '') {
                $errors['role_slug'] = t('notifications.flash.err_role');
            }
        } else {
            if ($toUserId <= 0) {
                $errors['user_id'] = t('notifications.flash.err_recipient');
            } elseif (!NotificationService::isValidUser($toUserId)) {
                $errors['user_id'] = t('notifications.flash.err_user_invalid');
            }
        }

        if ($title === '') {
            $errors['title'] = t('notifications.flash.err_title_required');
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = t('notifications.flash.err_title_too_long');
        }

        $allowedTypes = ['info', 'success', 'warning', 'danger'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        if ($link !== '') {
            $scheme = strtolower(parse_url($link, PHP_URL_SCHEME) ?? '');
            $allowedSchemes = ['http', 'https', ''];
            if (!in_array($scheme, $allowedSchemes, true)) {
                $errors['link'] = t('notifications.flash.err_link_scheme');
            } elseif ($scheme === '' && !str_starts_with($link, '/')) {
                $errors['link'] = t('notifications.flash.err_link_relative');
            }
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = [
                'send_mode' => $sendMode,
                'user_id'   => $toUserId,
                'role_slug' => $roleSlug,
                'title'     => $title,
                'body'      => $body,
                'type'      => $type,
                'link'      => $link,
                'icon'      => $icon,
            ];
            $this->redirect(route('admin.notifications.send'));
            return;
        }

        $fromUserId = (int) ($_SESSION['user_id'] ?? 0);
        if ($sendMode === 'role') {
            $ids = NotificationService::sendToRole(
                $roleSlug,
                $title,
                $body,
                $type,
                $link !== '' ? $link : null,
                $fromUserId,
                $icon
            );

            AuditService::log('notification_sent_to_role', 'notification', null, null, [
                'role'  => $roleSlug,
                'title' => $title,
                'type'  => $type,
                'count' => count($ids),
            ]);

            flash_success(t('notifications.flash.sent_to_role', ['count' => count($ids), 'role' => $roleSlug]));
        } else {
            NotificationService::send(
                $toUserId,
                $title,
                $body,
                $type,
                $link !== '' ? $link : null,
                $fromUserId,
                $icon
            );

            AuditService::log('notification_sent', 'notification', $toUserId, null, [
                'title' => $title, 'type' => $type,
            ]);

            // Recupera nome utente per flash message
            $users = NotificationService::getActiveUsers();
            $recipientName = "ID {$toUserId}";
            foreach ($users as $u) {
                if ((int) $u['id'] === $toUserId) {
                    $recipientName = $u['name'];
                    break;
                }
            }

            flash_success(t('notifications.flash.sent_to_user', ['recipient' => $recipientName]));
        }

        $this->redirect(route('admin.notifications.send'));
    }

    // ------------------------------------------------------------------
    // Queue retry
    // ------------------------------------------------------------------

    public function retryQueueItem(string $id): void
    {
        $queueId = (int) $id;
        $success = $this->adminService->retryQueueItem($queueId);
        $queueLink = route('admin.notifications.settings') . '#pane-queue';

        if ($this->isHtmxRequest()) {
            if ($success) {
                $this->notifyCurrentUser(
                    t('notifications.flash.queue_item_requeued_title'),
                    t('notifications.flash.queue_item_requeued_body', ['id' => $queueId]),
                    'info',
                    $queueLink,
                    'fa-solid fa-clock-rotate-left'
                );
                $this->hxToast(t('notifications.flash.queue_item_requeued', ['id' => $queueId]));
            } else {
                $this->hxToast(t('notifications.flash.queue_item_not_failed'));
            }
            if ($success) {
                $this->hxSyncNotificationBadge();
            }
            header('HX-Refresh: true');
            return;
        }

        $_SESSION[$success ? '_flash_success' : '_flash_error'] = $success
            ? t('notifications.flash.queue_item_requeued', ['id' => $queueId])
            : t('notifications.flash.queue_item_not_failed');
        if ($success) {
            $this->notifyCurrentUser(
                t('notifications.flash.queue_item_requeued_title'),
                t('notifications.flash.queue_item_requeued_body', ['id' => $queueId]),
                'info',
                $queueLink,
                'fa-solid fa-clock-rotate-left'
            );
        }
        $this->redirect($queueLink);
    }

    public function retryAllFailed(): void
    {
        $count = $this->adminService->retryAllFailed();
        $queueLink = route('admin.notifications.settings') . '#pane-queue';

        if ($this->isHtmxRequest()) {
            $this->hxToast($count > 0 ? t('notifications.flash.queue_requeued_count', ['count' => $count]) : t('notifications.flash.queue_no_failed'));
            if ($count > 0) {
                $this->notifyCurrentUser(
                    t('notifications.flash.queue_retry_done_title'),
                    t('notifications.flash.queue_retry_done_body', ['count' => $count]),
                    'info',
                    $queueLink,
                    'fa-solid fa-layer-group'
                );
                $this->hxSyncNotificationBadge();
            }
            header('HX-Refresh: true');
            return;
        }

        $_SESSION[$count > 0 ? '_flash_success' : '_flash_error'] = $count > 0
            ? t('notifications.flash.queue_requeued_count', ['count' => $count])
            : t('notifications.flash.queue_no_failed');
        if ($count > 0) {
            $this->notifyCurrentUser(
                t('notifications.flash.queue_retry_done_title'),
                t('notifications.flash.queue_retry_done_body', ['count' => $count]),
                'info',
                $queueLink,
                'fa-solid fa-layer-group'
            );
        }
        $this->redirect($queueLink);
    }
}
