<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\DashboardService;
use App\Modules\Home\Services\OggiService;
use App\Modules\Home\Services\WidgetPreferencesService;
use App\Modules\Tasks\Services\TasksService;
use App\Traits\ControllerHelpers;

class HomeController extends Controller
{
    use ControllerHelpers;

    private DashboardService $dashboard;
    private OggiService $oggiService;
    private WidgetPreferencesService $widgetPrefs;

    public function __construct()
    {
        $this->dashboard = app(DashboardService::class);
        $this->oggiService = app(OggiService::class);
        $this->widgetPrefs = app(WidgetPreferencesService::class);
    }

    public function oggi(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->render('Home/Views/oggi', [
            'pageTitle'      => t('home.today'),
            'breadcrumbs'    => [['label' => t('home.breadcrumb.today')]],
            'todayFeed'      => $this->oggiService->buildFeed($userId),
            'completedToday' => $this->oggiService->getCompletedTodayList($userId),
        ]);
    }

    /**
     * HTMX endpoint for refreshing the unified "Oggi" feed.
     */
    public function oggiFeed(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->renderPartial('Home/Views/partials/oggi_feed', [
            'todayFeed'      => $this->oggiService->buildFeed($userId),
            'completedToday' => $this->oggiService->getCompletedTodayList($userId),
        ]);
    }

    /**
     * HTMX action: force task completion in an idempotent way.
     */
    public function oggiCompleteTask(string $id): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        $taskId = (int) $id;

        if ($taskId <= 0 || !isModuleEnabled('Tasks') || !has_permission('tasks.edit')) {
            http_response_code(204);
            return;
        }

        try {
            $tasksService = app(TasksService::class);
            $task = $tasksService->find($taskId, $userId);

            if (!$task) {
                $this->hxToast(t('home.oggi.task_not_found'), 'warning', ['source' => 'oggi']);
                http_response_code(204);
                return;
            }

            if (($task['status'] ?? '') !== 'done') {
                $tasksService->toggleComplete($taskId, $userId);
            }

            $this->hxToast(t('home.oggi.task_completed'), 'success', ['source' => 'oggi']);
        } catch (\Throwable $e) {
            app_log('error', '[Home] oggi task complete failed: ' . $e->getMessage());
            $this->hxToast(t('home.oggi.task_complete_failed'), 'danger', ['source' => 'oggi']);
        }

        http_response_code(204);
    }

    /**
     * HTMX action: create a new task scheduled for today from the Oggi quick-add bar.
     */
    public function oggiQuickAddTask(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        if (!isModuleEnabled('Tasks') || !has_permission('tasks.create')) {
            $this->hxToast(t('home.oggi.no_create_perm'), 'warning', ['source' => 'oggi']);
            http_response_code(204);
            return;
        }

        $clean = $this->cleanPost(['title']);
        $title = trim((string) ($clean['title'] ?? ''));

        if ($title === '') {
            $this->hxToast(t('home.oggi.title_required'), 'warning', ['source' => 'oggi']);
            http_response_code(204);
            return;
        }

        if (mb_strlen($title) > 255) {
            $this->hxToast(t('home.oggi.title_too_long'), 'warning', ['source' => 'oggi']);
            http_response_code(204);
            return;
        }

        try {
            app(TasksService::class)->create([
                'title'    => $title,
                'status'   => 'todo',
                'priority' => 'medium',
                'due_date' => date('Y-m-d'),
                'due_time' => null,
                'color'    => null,
                'tag_ids'  => [],
            ], $userId);

            $this->hxToast(t('home.oggi.task_added'), 'success', ['source' => 'oggi']);
            $this->hxTrigger(['refreshTodayFeed' => true]);
        } catch (\Throwable $e) {
            app_log('error', '[Home] oggi quick-add task failed: ' . $e->getMessage());
            $this->hxToast(t('home.oggi.task_add_failed'), 'danger', ['source' => 'oggi']);
        }

        http_response_code(204);
    }

    public function index(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->render('Home/Views/index', [
            'pageTitle'      => t('home.title'),
            'breadcrumbs'    => [['label' => t('home.breadcrumb.home')]],
            'dashboard'      => $this->dashboard->buildDashboard($userId),
            'unreadCount'    => $this->dashboard->getUnreadCount($userId),
        ]);
    }

    /**
     * HTMX endpoint: the whole widget grid in one request. Fast widgets are
     * rendered inline; slow ones (e.g. weather) stay as lazy placeholders that
     * load separately via the home.widget endpoint.
     */
    public function widgets(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->renderPartial('Home/Views/partials/dashboard_widgets', [
            'dashboard' => $this->dashboard->buildDashboard($userId),
        ]);
    }

    /**
     * HTMX endpoint: render a single (lazy) widget body on demand.
     * Returns an empty 200 body when the widget is forbidden or has nothing to
     * show, so the client-side cleanup removes the empty placeholder.
     */
    public function widget(string $id): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $widget = $this->dashboard->renderWidget($userId, $id);
        if ($widget === null) {
            return;
        }

        $allowedTypes = ['stat', 'chart', 'list', 'html'];
        $type = in_array($widget['type'] ?? 'stat', $allowedTypes, true) ? $widget['type'] : 'stat';

        $this->renderPartial('Home/Views/partials/widgets/widget_' . $type, [
            'widget' => $widget,
        ]);
    }

    /**
     * HTMX endpoint: widget settings offcanvas content.
     */
    public function widgetSettings(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->renderPartial('Home/Views/partials/widgets/widget_settings', [
            'allWidgets' => $this->dashboard->getAllAvailableWidgets($userId),
        ]);
    }

    /**
     * POST: save widget layout (order + visibility).
     */
    public function saveWidgetLayout(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $input = json_decode(file_get_contents('php://input'), true);
        $widgets = $input['widgets'] ?? [];

        if (!is_array($widgets)) {
            $this->json(['error' => 'Invalid input'], 400);
            return;
        }

        // Validate and sanitize
        $clean = [];
        foreach ($widgets as $w) {
            if (!empty($w['id']) && is_string($w['id'])) {
                $clean[] = [
                    'id'      => substr($w['id'], 0, 100),
                    'visible' => !empty($w['visible']),
                ];
            }
        }

        $this->widgetPrefs->saveLayout($userId, $clean);

        $this->hxToast(t('home.dashboard.updated'));
        http_response_code(204);
    }

    /**
     * POST: reset widget layout to defaults.
     */
    public function resetWidgetLayout(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->widgetPrefs->resetToDefaults($userId);

        $this->hxToast(t('home.dashboard.reset_done'));
        // Trigger a widget refresh on the client
        header('HX-Trigger: {"refreshWidgets": true}');
        http_response_code(204);
    }
}
