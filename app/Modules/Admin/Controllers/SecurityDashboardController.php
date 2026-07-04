<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\HealthCheck\Services\HealthCheckService;
use App\Services\KeyRotationService;
use App\Services\LogRotationService;
use App\Traits\ControllerHelpers;

/**
 * ISO 27001 A.8.1, A.12.6 — Asset inventory & vulnerability management.
 * ISO 27001 A.12.4 — Log management dashboard.
 */
class SecurityDashboardController extends Controller
{
    use ControllerHelpers;

    /**
     * GET /admin/security/assets — Software asset inventory.
     */
    public function assets(): void
    {
        $composerLock = $this->getComposerPackages();
        $auditResults = $this->runComposerAudit();

        $data = [
            'packages'    => $composerLock['packages'],
            'devPackages' => $composerLock['devPackages'],
            'phpVersion'  => PHP_VERSION,
            'phpExtensions' => get_loaded_extensions(),
            'audit'       => $auditResults,
            'pageTitle'   => t('admin.security.assets.title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.breadcrumb'), 'route' => 'admin.security.incidents'],
                ['label' => t('admin.security.assets.title')],
            ],
        ];

        $this->render('Admin/Views/security-assets', $data);
    }

    /**
     * GET /admin/security/logs-status — Log management status.
     */
    public function logsStatus(): void
    {
        $logService = app(LogRotationService::class);
        $status = $logService->getStatus();
        $verification = $logService->verifyAll();

        $data = [
            'logStatus'    => $status,
            'verification' => $verification,
            'pageTitle'    => t('admin.security.logs.title'),
            'breadcrumbs'  => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.breadcrumb'), 'route' => 'admin.security.incidents'],
                ['label' => t('admin.security.logs.title')],
            ],
        ];

        $this->render('Admin/Views/security-logs-status', $data);
    }

    /**
     * POST /admin/security/logs-rotate — Trigger manual log rotation.
     */
    public function rotateNow(): void
    {
        $logService = app(LogRotationService::class);
        $result = $logService->rotate();

        if ($result['rotated']) {
            $_SESSION['_flash_success'] = t('admin.security.logs.flash_rotated', [
                'file' => $result['file'],
                'size' => LogRotationService::humanSize($result['size']),
            ]);
        } else {
            flash_error($result['error'] ?? t('admin.security.logs.flash_no_rotation'));
        }

        $this->redirect(route('admin.security.logs'));
    }

    /**
     * POST /admin/security/logs-purge — Trigger manual purge of old log files.
     */
    public function purgeOld(): void
    {
        $logService = app(LogRotationService::class);
        $result = $logService->purge();

        if ($result['deleted'] > 0) {
            flash_success(t('admin.security.logs.flash_purged', [
                'count' => $result['deleted'],
                'freed' => LogRotationService::humanSize($result['freed']),
            ]));
        } else {
            flash_error(t('admin.security.logs.flash_nothing_to_purge'));
        }

        foreach ($result['errors'] as $err) {
            flash_error(($result['errors'][0] ?? '') . '. ' . $err);
        }

        $this->redirect(route('admin.security.logs'));
    }

    /**
     * GET /admin/security/hardening — PHP hardening check dashboard.
     */
    public function hardening(): void
    {
        $healthService = app(HealthCheckService::class);
        $results = $healthService->checkPhpHardening();

        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($results['checks'] as $check) {
            $summary[$check['status']] = ($summary[$check['status']] ?? 0) + 1;
        }

        $data = [
            'checks'     => $results['checks'],
            'summary'    => $summary,
            'pageTitle'  => t('admin.security.hardening.title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.breadcrumb'), 'route' => 'admin.security.incidents'],
                ['label' => t('admin.security.hardening.title')],
            ],
        ];

        $this->render('Admin/Views/security-hardening', $data);
    }

    /**
     * GET /admin/security/keys — Key rotation status dashboard.
     */
    public function keys(): void
    {
        $keyService = app(KeyRotationService::class);
        $keys = $keyService->getStatus();

        $data = [
            'keys'       => $keys,
            'pageTitle'  => t('admin.security.keys.title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.security.breadcrumb'), 'route' => 'admin.security.incidents'],
                ['label' => t('admin.security.keys.title')],
            ],
        ];

        $this->render('Admin/Views/security-keys', $data);
    }

    /**
     * POST /admin/security/keys/{name}/rotated — Record that a key has been rotated.
     */
    public function recordKeyRotation(string $name): void
    {
        $allowed = ['APP_KEY', 'BACKUP_ENCRYPTION_KEY'];
        if (!in_array($name, $allowed, true)) {
            flash_error(t('admin.security.keys.flash_unknown'));
            header('Location: ' . route('admin.security.keys'));
            exit;
        }

        $keyService = app(KeyRotationService::class);
        $keyService->recordRotation($name);

        flash_success(t('admin.security.keys.flash_recorded', ['name' => $name]));
        header('Location: ' . route('admin.security.keys'));
        exit;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function getComposerPackages(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $lockFile = $basePath . '/composer.lock';

        $packages = [];
        $devPackages = [];

        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true);
            if (is_array($lock)) {
                foreach ($lock['packages'] ?? [] as $pkg) {
                    $packages[] = [
                        'name'    => $pkg['name'] ?? '',
                        'version' => $pkg['version'] ?? '',
                        'description' => $pkg['description'] ?? '',
                        'license' => implode(', ', $pkg['license'] ?? []),
                    ];
                }
                foreach ($lock['packages-dev'] ?? [] as $pkg) {
                    $devPackages[] = [
                        'name'    => $pkg['name'] ?? '',
                        'version' => $pkg['version'] ?? '',
                        'description' => $pkg['description'] ?? '',
                        'license' => implode(', ', $pkg['license'] ?? []),
                    ];
                }
            }
        }

        usort($packages, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($devPackages, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['packages' => $packages, 'devPackages' => $devPackages];
    }

    private function runComposerAudit(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $phpBin = PHP_BINARY ?: 'php';
        $composerPath = $basePath . '/vendor/bin/composer';

        // Try composer in PATH first
        $cmd = 'composer audit --format=json --no-interaction 2>&1';
        $output = @shell_exec($cmd);

        if ($output === null) {
            return ['available' => false, 'advisories' => [], 'error' => t('admin.security.assets.composer_unavailable')];
        }

        $data = @json_decode($output, true);
        if (!is_array($data)) {
            return ['available' => true, 'advisories' => [], 'error' => t('admin.security.assets.composer_invalid_output')];
        }

        $advisories = [];
        foreach ($data['advisories'] ?? [] as $package => $items) {
            foreach ($items as $advisory) {
                $advisories[] = [
                    'package'  => $package,
                    'title'    => $advisory['title'] ?? '',
                    'cve'      => $advisory['cve'] ?? '',
                    'link'     => $advisory['link'] ?? '',
                    'severity' => $advisory['severity'] ?? 'unknown',
                    'affected' => $advisory['affectedVersions'] ?? '',
                ];
            }
        }

        return ['available' => true, 'advisories' => $advisories, 'error' => null];
    }
}
