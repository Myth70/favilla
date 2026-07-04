<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\AdminLogsService;
use App\Services\CsvExportService;
use App\Traits\ControllerHelpers;

class AdminLogsController extends Controller
{
    use ControllerHelpers;

    private AdminLogsService $service;

    private const ALLOWED_DAYS    = [7, 30, 90, 180, 365];
    private const ALLOWED_TARGETS = ['audit', 'attempts', 'sessions', 'password_resets'];
    private const EXPORT_TYPES    = ['audit', 'attempts', 'sessions'];

    public function __construct()
    {
        $this->service = app(AdminLogsService::class);
    }

    // ---------------------------------------------------------------
    // INDEX — full page with stats + tabs
    // ---------------------------------------------------------------

    public function index(): void
    {
        $auditStats    = $this->service->getAuditStats();
        $attemptsStats = $this->service->getAttemptsStats();
        $sessionsStats = $this->service->getSessionsStats();

        $users = $this->service->getUsersForFilter();

        $auditActions  = $this->service->getDistinctAuditActions();
        $auditEntities = $this->service->getDistinctAuditEntities();

        $cleanTab  = $this->cleanGet(['tab']);
        $activeTab = in_array($cleanTab['tab'] ?? '', ['audit', 'attempts', 'sessions', 'errors'], true)
                     ? $cleanTab['tab'] : 'audit';

        $this->render('Admin/Views/logs/index', [
            'auditStats'    => $auditStats,
            'attemptsStats' => $attemptsStats,
            'sessionsStats' => $sessionsStats,
            'users'         => $users,
            'auditActions'  => $auditActions,
            'auditEntities' => $auditEntities,
            'activeTab'     => $activeTab,
            'pageTitle'     => t('admin.logs.page_title'),
            'breadcrumbs'   => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.logs.breadcrumb')],
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // HTMX PARTIALS — one per tab
    // ---------------------------------------------------------------

    public function auditTable(): void
    {
        $clean = $this->cleanGet(['action', 'user_id', 'entity', 'ip', 'search', 'date_from', 'date_to', 'sort', 'dir', 'page']);
        $filters = [
            'action'    => $clean['action']    ?? '',
            'user_id'   => $clean['user_id']   ?? '',
            'entity'    => $clean['entity']    ?? '',
            'ip'        => $clean['ip']        ?? '',
            'search'    => $clean['search']    ?? '',
            'date_from' => $clean['date_from'] ?? '',
            'date_to'   => $clean['date_to']   ?? '',
            'sort'      => $clean['sort']      ?? 'created_at',
            'dir'       => $clean['dir']       ?? 'DESC',
        ];
        $page   = max(1, (int) ($clean['page'] ?? 1));
        $result = $this->service->listAudit($filters, $page);

        $this->renderPartial(
            'Admin/Views/logs/partials/audit-table',
            array_merge($result, ['total_pages' => $result['lastPage']], compact('filters'))
        );
    }

    public function attemptsTable(): void
    {
        $clean = $this->cleanGet(['email', 'ip', 'success', 'date_from', 'date_to', 'sort', 'dir', 'page']);
        $filters = [
            'email'     => $clean['email']     ?? '',
            'ip'        => $clean['ip']        ?? '',
            'success'   => $clean['success']   ?? '',
            'date_from' => $clean['date_from'] ?? '',
            'date_to'   => $clean['date_to']   ?? '',
            'sort'      => $clean['sort']      ?? 'created_at',
            'dir'       => $clean['dir']       ?? 'DESC',
        ];
        $page   = max(1, (int) ($clean['page'] ?? 1));
        $result = $this->service->listAttempts($filters, $page);

        $this->renderPartial(
            'Admin/Views/logs/partials/attempts-table',
            array_merge($result, ['total_pages' => $result['lastPage']], compact('filters'))
        );
    }

    public function sessionsTable(): void
    {
        $clean = $this->cleanGet(['user_id', 'active_only', 'date_from', 'date_to', 'sort', 'dir', 'page']);
        $filters = [
            'user_id'     => $clean['user_id']     ?? '',
            'active_only' => $clean['active_only'] ?? '',
            'date_from'   => $clean['date_from']   ?? '',
            'date_to'     => $clean['date_to']     ?? '',
            'sort'        => $clean['sort']         ?? 'last_activity',
            'dir'         => $clean['dir']          ?? 'DESC',
        ];
        $page   = max(1, (int) ($clean['page'] ?? 1));
        $result = $this->service->listSessions($filters, $page);

        $users = $this->service->getUsersForFilter();

        $this->renderPartial(
            'Admin/Views/logs/partials/sessions-table',
            array_merge($result, ['total_pages' => $result['lastPage']], compact('filters', 'users'))
        );
    }

    public function errorsTable(): void
    {
        $clean  = $this->cleanGet(['level', 'search', 'date_from', 'date_to', 'page']);
        $level  = in_array($clean['level'] ?? '', ['ERROR', 'CRITICAL', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'], true)
                  ? ($clean['level'] ?? '') : '';
        $search    = $clean['search']    ?? '';
        $dateFrom  = $clean['date_from'] ?? '';
        $dateTo    = $clean['date_to']   ?? '';
        $page      = max(1, (int) ($clean['page'] ?? 1));
        $perPage   = 50;

        $logFile = BASE_PATH . '/storage/logs/app.log';
        $entries = [];

        if (is_readable($logFile)) {
            // Read file lines in reverse (most recent first), limit to prevent memory issues
            $lines = $this->readLastLines($logFile, 5000);
            $lines = array_reverse($lines);

            $pattern = '/^\[(.+?)\] \w+\.([A-Z]+): (.+?) (\{.*\})(.*)$/s';

            foreach ($lines as $line) {
                $line = trim($line);
                if (!preg_match($pattern, $line, $m)) {
                    continue;
                }

                [$full, $timestamp, $logLevel, $message, $contextJson] = $m;

                // Level filter (default: ERROR and CRITICAL)
                $filterLevel = $level !== '' ? $level : null;
                if ($filterLevel !== null && $logLevel !== $filterLevel) {
                    continue;
                }
                if ($filterLevel === null && !in_array($logLevel, ['ERROR', 'CRITICAL'], true)) {
                    continue;
                }

                // Date filters
                try {
                    $dt = new \DateTimeImmutable($timestamp);
                    if ($dateFrom !== '' && $dt < new \DateTimeImmutable($dateFrom . ' 00:00:00')) {
                        continue;
                    }
                    if ($dateTo !== '' && $dt > new \DateTimeImmutable($dateTo . ' 23:59:59')) {
                        continue;
                    }
                    $formattedDate = $dt->format('d/m/Y H:i:s');
                } catch (\Throwable) {
                    $formattedDate = $timestamp;
                }

                // Search filter
                if ($search !== '' && stripos($message, $search) === false && stripos($contextJson, $search) === false) {
                    continue;
                }

                // Extract file/line from context JSON
                $file    = '';
                $fileLine = '';
                $ctx = @json_decode($contextJson, true);
                if (is_array($ctx)) {
                    $rawFile = $ctx['file'] ?? '';
                    // Strip base path for cleaner display
                    $basePath = str_replace('\\', '/', BASE_PATH);
                    $file     = str_replace([$basePath . '/', str_replace('/', '\\', $basePath) . '\\'], '', str_replace('\\', '/', $rawFile));
                    $fileLine = $ctx['line'] ?? '';
                }

                $entries[] = [
                    'timestamp' => $formattedDate,
                    'level'     => $logLevel,
                    'message'   => mb_strimwidth($message, 0, 300, '…'),
                    'file'      => $file,
                    'line'      => $fileLine,
                ];
            }
        }

        $total   = count($entries);
        $offset  = ($page - 1) * $perPage;
        $items   = array_slice($entries, $offset, $perPage);
        $pages   = (int) ceil($total / $perPage);

        $filters = compact('level', 'search', 'dateFrom', 'dateTo');

        $this->renderPartial('Admin/Views/logs/partials/errors-table', compact(
            'items',
            'total',
            'page',
            'pages',
            'perPage',
            'filters',
            'logFile'
        ));
    }

    // ---------------------------------------------------------------
    // STATS WIDGET — HTMX auto-refresh partial
    // ---------------------------------------------------------------

    public function statsWidget(): void
    {
        $auditStats    = $this->service->getAuditStats();
        $attemptsStats = $this->service->getAttemptsStats();
        $sessionsStats = $this->service->getSessionsStats();

        $this->renderPartial(
            'Admin/Views/logs/partials/stats-widget',
            compact('auditStats', 'attemptsStats', 'sessionsStats')
        );
    }

    // ---------------------------------------------------------------
    // EXPORT CSV
    // ---------------------------------------------------------------

    public function export(): void
    {
        $clean = $this->cleanGet(['type', 'action', 'user_id', 'entity', 'ip', 'search', 'date_from', 'date_to', 'email', 'success', 'active_only']);
        $type  = $clean['type'] ?? '';
        if (!in_array($type, self::EXPORT_TYPES, true)) {
            http_response_code(400);
            return;
        }

        $filters = match ($type) {
            'audit'    => [
                'action'    => $clean['action']    ?? '',
                'user_id'   => $clean['user_id']   ?? '',
                'entity'    => $clean['entity']    ?? '',
                'ip'        => $clean['ip']        ?? '',
                'search'    => $clean['search']    ?? '',
                'date_from' => $clean['date_from'] ?? '',
                'date_to'   => $clean['date_to']   ?? '',
            ],
            'attempts' => [
                'email'     => $clean['email']     ?? '',
                'ip'        => $clean['ip']        ?? '',
                'success'   => $clean['success']   ?? '',
                'date_from' => $clean['date_from'] ?? '',
                'date_to'   => $clean['date_to']   ?? '',
            ],
            'sessions' => [
                'user_id'     => $clean['user_id']     ?? '',
                'active_only' => $clean['active_only'] ?? '',
                'date_from'   => $clean['date_from']   ?? '',
                'date_to'     => $clean['date_to']     ?? '',
            ],
        };

        $rows = match ($type) {
            'audit'    => $this->service->exportAudit($filters),
            'attempts' => $this->service->exportAttempts($filters),
            'sessions' => $this->service->exportSessions($filters),
        };

        // Avviso se l'export è stato troncato al limite massimo
        $limit = $this->service->getExportLimit();
        if (count($rows) >= $limit) {
            $rows[] = [t('admin.logs.export_truncated', ['limit' => number_format($limit)])];
        }

        $filename = 'log_' . $type . '_' . date('Ymd_His') . '.csv';

        CsvExportService::stream($rows, $filename);
    }

    // ---------------------------------------------------------------
    // CLEANUP — POST only, destructive
    // ---------------------------------------------------------------

    public function cleanup(): void
    {
        $clean  = $this->cleanPost(['target', 'days']);
        $target = $clean['target'] ?? '';
        $days   = (int) ($clean['days'] ?? 0);

        if (!in_array($target, self::ALLOWED_TARGETS, true)) {
            flash_error(t('admin.logs.flash_invalid_target'));
            $this->redirect(route('admin.logs.index'));
            return;
        }

        if (!in_array($days, self::ALLOWED_DAYS, true) && $target !== 'sessions') {
            flash_error(t('admin.logs.flash_invalid_days'));
            $this->redirect(route('admin.logs.index'));
            return;
        }

        $deleted = match ($target) {
            'audit'           => $this->service->purgeAudit($days),
            'attempts'        => $this->service->purgeAttempts($days),
            'sessions'        => $this->service->purgeExpiredSessions(),
            'password_resets' => $this->service->purgePasswordResets($days),
        };

        $label = match ($target) {
            'audit'           => t('admin.logs.label_audit'),
            'attempts'        => t('admin.logs.label_attempts'),
            'sessions'        => t('admin.logs.label_sessions'),
            'password_resets' => t('admin.logs.label_password_resets'),
        };

        flash_success(t('admin.logs.flash_cleanup_done', ['count' => $deleted, 'label' => $label]));
        $this->redirect(route('admin.logs.index'));
    }

    // ---------------------------------------------------------------
    // PRIVATE HELPERS
    // ---------------------------------------------------------------

    /**
     * Read the last $n lines of a file efficiently without loading it all.
     *
     * @return string[]
     */
    private function readLastLines(string $filePath, int $n): array
    {
        $lines = [];
        $fp    = @fopen($filePath, 'rb');
        if (!$fp) {
            return $lines;
        }

        fseek($fp, 0, SEEK_END);
        $size   = ftell($fp);
        $buffer = '';
        $pos    = $size;
        $chunk  = 8192;

        while ($pos > 0 && count($lines) < $n) {
            $readSize = min($chunk, $pos);
            $pos -= $readSize;
            fseek($fp, $pos);
            $buffer  = fread($fp, $readSize) . $buffer;
            $parts   = explode("\n", $buffer);
            // Keep the first partial line to prepend to the next chunk
            $buffer  = array_shift($parts);
            // Prepend to lines (we're going backwards)
            $lines   = array_merge(array_reverse($parts), $lines);
        }

        if ($buffer !== '') {
            array_unshift($lines, $buffer);
        }

        fclose($fp);

        // Return only the last $n lines
        return array_slice($lines, -$n);
    }
}
