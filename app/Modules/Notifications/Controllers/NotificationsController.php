<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\TelegramLinkService;
use App\Traits\ControllerHelpers;

class NotificationsController extends Controller
{
    use ControllerHelpers;

    private NotificationPreferenceService $notificationPreferenceService;
    private TelegramLinkService $telegramLinkService;

    public function __construct()
    {
        $this->notificationPreferenceService = app(NotificationPreferenceService::class);
        $this->telegramLinkService = app(TelegramLinkService::class);
    }

    // ------------------------------------------------------------------
    // HTMX: badge con conteggio non lette (polling ogni 60s)
    // ------------------------------------------------------------------
    public function unreadCount(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $route  = e(route('notifications.unread-count'));

        if (!$userId) {
            echo '<span class="nt-badge badge rounded-pill bg-danger d-none"'
                 . ' id="nt-badge-count"'
                 . ' hx-get="' . $route . '"'
                 . ' hx-trigger="every 60s, notifCountUpdated from:body, notifAllRead from:body"'
                 . ' hx-target="#nt-badge-count"'
                 . ' hx-swap="outerHTML">0</span>';
            return;
        }

        $count = NotificationService::getUnreadCount((int) $userId);

        $hidden = $count === 0 ? ' d-none' : '';
        echo '<span class="nt-badge badge rounded-pill bg-danger' . $hidden . '"'
             . ' id="nt-badge-count"'
             . ' hx-get="' . $route . '"'
             . ' hx-trigger="every 60s, notifCountUpdated from:body, notifAllRead from:body"'
             . ' hx-target="#nt-badge-count"'
             . ' hx-swap="outerHTML">'
             . $count
             . '</span>';
    }

    // ------------------------------------------------------------------
    // HTMX: contenuto dropdown campanella
    // ------------------------------------------------------------------
    public function dropdown(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            echo '<div class="dropdown-item text-muted">' . e(t('notifications.dropdown.unavailable')) . '</div>';
            return;
        }

        $userId      = (int) $userId;
        $items       = NotificationService::getUnread($userId, 8);
        $unreadCount = NotificationService::getUnreadCount($userId);

        $this->renderPartial('Notifications/Views/partials/dropdown_items', [
            'items'       => $items,
            'unreadCount' => $unreadCount,
        ]);
    }

    // ------------------------------------------------------------------
    // Pagina lista completa notifiche
    // ------------------------------------------------------------------
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $filter = $_GET['filter'] ?? null;
        if (!in_array($filter, ['unread', 'read'], true)) {
            $filter = null;
        }

        $result = NotificationService::getPaged($userId, $page, 20, $filter);

        // Hero stats use global counters (not affected by tab filter).
        $unreadTotal = NotificationService::getUnreadCount($userId);
        $allTotal    = NotificationService::getPaged($userId, 1, 1, null)['total'] ?? 0;
        $readTotal   = max(0, $allTotal - $unreadTotal);

        $userProfile = [
            'name'   => $_SESSION['user_name'] ?? '',
            'email'  => $_SESSION['user_email'] ?? '',
            'avatar' => $_SESSION['user_avatar'] ?? null,
        ];

        $result['pages'] = $result['lastPage'] ?? 1;

        $this->htmxOrRender(
            'Notifications/Views/partials/list_rows',
            'Notifications/Views/index',
            array_merge($result, [
                'notificationStats' => [
                    'total'  => (int) $allTotal,
                    'unread' => (int) $unreadTotal,
                    'read'   => (int) $readTotal,
                ],
                'userProfile' => $userProfile,
                'pageTitle'   => t('notifications.title'),
                'filter'      => $filter,
                'breadcrumbs' => [
                    ['label' => t('common.user.profile'), 'route' => 'profile'],
                    ['label' => t('notifications.title')],
                ],
            ])
        );
    }

    public function settings(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $notificationSettings = $this->notificationPreferenceService->getProfileSettings($userId);

        $this->render('Notifications/Views/settings', [
            'pageTitle' => t('notifications.breadcrumb.settings'),
            'notificationSettings' => $notificationSettings,
            'breadcrumbs' => [
                ['label' => t('common.user.profile'), 'route' => 'profile'],
                ['label' => t('notifications.title'), 'route' => 'notifications.index'],
                ['label' => t('notifications.breadcrumb.settings')],
            ],
        ]);
    }

    public function updateSettings(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $submitted = $_POST['notify'] ?? [];
        $submittedEvents = $_POST['notify_events'] ?? [];

        if (!is_array($submitted)) {
            $submitted = [];
        }

        if (!is_array($submittedEvents)) {
            $submittedEvents = [];
        }

        $updated = $this->notificationPreferenceService->updatePreferences($userId, $submitted, $submittedEvents);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('notifications.flash.prefs_updated'));
            header('HX-Refresh: true');
            return;
        }

        $_SESSION['_flash_success'] = t('notifications.flash.prefs_updated_detail', [
            'mod' => $updated['module_updates'],
            'ev'  => $updated['event_updates'],
            'cl'  => $updated['event_cleared'],
        ]);
        $this->redirect(route('notifications.settings'));
    }

    public function regenerateTelegramLink(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->telegramLinkService->regenerateToken($userId);
            flash_success(t('notifications.flash.tg_regenerated'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('notifications.settings'));
    }

    public function disconnectTelegram(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->telegramLinkService->disconnect($userId);
        flash_success(t('notifications.flash.tg_disconnected'));
        $this->redirect(route('notifications.settings'));
    }

    // ------------------------------------------------------------------
    // POST: segna singola come letta
    // ------------------------------------------------------------------
    public function markRead(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        NotificationService::markAsRead((int) $id, $userId);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('notifications.flash.marked_read'), 'info', ['source' => 'notifications']);
            $this->hxSyncNotificationBadge($userId);
            echo '';
            return;
        }

        $this->redirect(route('notifications.index'));
    }

    // ------------------------------------------------------------------
    // POST: segna tutte come lette
    // ------------------------------------------------------------------
    public function markAllRead(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        NotificationService::markAllAsRead($userId);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('notifications.flash.all_marked_read'), 'success', ['source' => 'notifications']);
            $this->hxSyncNotificationBadge($userId);
            $this->hxTrigger(['notifAllRead' => true]);
            $items       = NotificationService::getUnread($userId, 8);
            $unreadCount = 0;
            $this->renderPartial('Notifications/Views/partials/dropdown_items', [
                'items'       => $items,
                'unreadCount' => $unreadCount,
            ]);
            return;
        }

        flash_success(t('notifications.flash.all_marked_read'));
        $this->redirect(route('notifications.index'));
    }

    public function markSelectedRead(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ids = $this->selectedNotificationIds();

        if ($ids === []) {
            flash_error(t('notifications.flash.select_at_least_one'));
            $this->redirect($this->notificationsIndexUrlFromRequest());
        }

        $updated = NotificationService::markManyAsRead($ids, $userId);

        if ($updated > 0) {
            $_SESSION['_flash_success'] = tc('notifications.flash.marked_read_count', $updated);
        } else {
            flash_error(t('notifications.flash.none_updated'));
        }

        $this->redirect($this->notificationsIndexUrlFromRequest());
    }

    // ------------------------------------------------------------------
    // DELETE: elimina singola notifica
    // ------------------------------------------------------------------
    public function destroy(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        NotificationService::deleteForUser((int) $id, $userId);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('notifications.flash.deleted'), 'warning', ['source' => 'notifications']);
            $this->hxSyncNotificationBadge($userId);
            echo '';
            return;
        }

        flash_success(t('notifications.flash.deleted'));
        $this->redirect(route('notifications.index'));
    }

    public function destroySelected(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $ids = $this->selectedNotificationIds();

        if ($ids === []) {
            flash_error(t('notifications.flash.select_at_least_one'));
            $this->redirect($this->notificationsIndexUrlFromRequest());
        }

        $deleted = NotificationService::deleteManyForUser($ids, $userId);

        if ($deleted > 0) {
            $_SESSION['_flash_success'] = tc('notifications.flash.deleted_count', $deleted);
        } else {
            flash_error(t('notifications.flash.none_deleted'));
        }

        $this->redirect($this->notificationsIndexUrlFromRequest());
    }

    private function selectedNotificationIds(): array
    {
        $rawIds = $_POST['notification_ids'] ?? [];

        if (!is_array($rawIds)) {
            return [];
        }

        $ids = array_map(static function ($value) {
            return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        }, $rawIds);

        $ids = array_values(array_filter($ids, static fn ($value) => $value !== false));

        return array_map('intval', array_values(array_unique($ids)));
    }

    private function notificationsIndexUrlFromRequest(): string
    {
        $params = [];

        $page = filter_var($_POST['return_page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($page !== false && $page !== null && (int) $page > 1) {
            $params['page'] = (int) $page;
        }

        $filter = $_POST['return_filter'] ?? null;
        if (is_string($filter) && in_array($filter, ['unread', 'read'], true)) {
            $params['filter'] = $filter;
        }

        if ($params === []) {
            return route('notifications.index');
        }

        return route('notifications.index') . '?' . http_build_query($params);
    }
}
