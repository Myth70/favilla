<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\EnvWriterService;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Traits\ControllerHelpers;

class SettingsController extends Controller
{
    use ControllerHelpers;

    private EnvWriterService $envWriter;

    public function __construct()
    {
        $this->envWriter = app(EnvWriterService::class);
    }

    public function index(): void
    {
        $allSettings = SettingsService::all();

        // Group by group
        $groups = [];
        foreach ($allSettings as $setting) {
            $groups[$setting['group']][] = $setting;
        }

        // Leggi valori correnti dal .env per il tab Sistema
        $envWriter = $this->envWriter;
        $envValues = [
            'APP_ENV'          => $envWriter->read('APP_ENV') ?? 'production',
            'APP_DEBUG'        => $envWriter->read('APP_DEBUG') ?? 'false',
            'MAINTENANCE_MODE' => $envWriter->read('MAINTENANCE_MODE') ?? 'false',
        ];

        $this->render('Admin/Views/settings/index', [
            'pageTitle'   => t('admin.settings.title'),
            'groups'      => $groups,
            'envValues'   => $envValues,
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.settings.breadcrumb')],
            ],
        ]);
    }

    public function update(): void
    {
        $allSettings = SettingsService::all();

        // Esclude impostazioni di sistema: quelle sono gestite esclusivamente da updateSystem().
        // Senza questo filtro, salvare le impostazioni generali resetta tutti i bool di sistema
        // (es. maintenance_mode) perché i checkbox assenti nel POST vengono forzati a '0'.
        $editableSettings = array_values(array_filter($allSettings, fn ($s) => ($s['group'] ?? '') !== 'system'));
        $allowedKeys = array_column($editableSettings, 'key');

        $updates = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $updates[$key] = trim($_POST[$key]);
            }
        }

        // Handle bool checkboxes — unchecked ones won't appear in POST
        foreach ($editableSettings as $setting) {
            if ($setting['type'] === 'bool') {
                $updates[$setting['key']] = isset($_POST[$setting['key']]) ? '1' : '0';
            }
        }

        // Validazione type-aware: i setting numerici (type=int) devono ricevere
        // un intero valido. Senza questo controllo un valore tipo "abc" verrebbe
        // salvato e poi riletto come 0 (es. smtp_port → 0, SMTP rotto).
        $rules  = [];
        $labels = [];
        foreach ($editableSettings as $setting) {
            if (($setting['type'] ?? '') === 'int' && array_key_exists($setting['key'], $updates)) {
                $rules[$setting['key']]  = 'nullable|integer';
                $labels[$setting['key']] = $setting['label'] ?? $setting['description'] ?? $setting['key'];
            }
        }

        if ($rules !== []) {
            $validator = new \App\Core\Validator();
            if (!$validator->validate($updates, $rules, $labels)) {
                $messages = [];
                foreach ($validator->errors() as $fieldErrors) {
                    foreach ($fieldErrors as $msg) {
                        $messages[] = $msg;
                    }
                }
                $errorText = implode(' ', $messages);

                if ($this->isHtmxRequest()) {
                    $this->hxToast($errorText, 'danger');
                    http_response_code(422);
                    return;
                }
                flash_error($errorText);
                $this->redirect(route('admin.settings.index'));
                return;
            }
        }

        if (!empty($updates)) {
            // Raccogli i valori precedenti per l'audit
            $oldValues = [];
            foreach ($editableSettings as $s) {
                if (array_key_exists($s['key'], $updates) && (string) $s['value'] !== (string) $updates[$s['key']]) {
                    $oldValues[$s['key']] = $s['value'];
                }
            }

            SettingsService::bulkUpdate($updates);

            // Logga solo le impostazioni effettivamente cambiate (esclusa password SMTP dal log)
            if (!empty($oldValues)) {
                $changedNew = array_intersect_key($updates, $oldValues);
                unset($oldValues['smtp_password'], $changedNew['smtp_password']);
                if (!empty($oldValues)) {
                    AuditService::log('settings_updated', 'settings', null, $oldValues, $changedNew);
                }
            }
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('admin.settings.flash_saved'));
            // Reload the page to reflect changes
            header('HX-Refresh: true');
            return;
        }

        flash_success(t('admin.settings.flash_saved'));
        $this->redirect(route('admin.settings.index'));
    }

    /**
     * Toggle immediato di una singola impostazione booleana di sistema (debug / manutenzione).
     * Chiamato via HTMX dal form-switch nella view.
     */
    public function toggleSystemSetting(): void
    {
        $key = $_POST['key'] ?? '';

        $allowed = [
            'app_debug'        => 'APP_DEBUG',
            'maintenance_mode' => 'MAINTENANCE_MODE',
        ];

        if (!isset($allowed[$key])) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('admin.settings.flash_invalid'), 'danger');
                http_response_code(400);
                return;
            }
            flash_error(t('admin.settings.flash_invalid'));
            $this->redirect(route('admin.settings.index'));
            return;
        }

        $envKey = $allowed[$key];

        // Leggi valore corrente e inverti
        $current = (bool) SettingsService::get($key);
        $newValue = $current ? '0' : '1';
        $envVal   = $newValue === '1' ? 'true' : 'false';

        // Aggiorna DB
        SettingsService::set($key, $newValue);

        // Audit
        AuditService::log(
            'system_settings_updated',
            'settings',
            null,
            [$key => $current ? '1' : '0'],
            [$key => $newValue]
        );

        // Sincronizza .env
        $envError = null;
        try {
            $this->envWriter->write($envKey, $envVal);
        } catch (\Throwable $e) {
            $envError = $e->getMessage();
            app_log('error', 'EnvWriter error: ' . $envError);
        }

        // Flag file manutenzione
        if ($key === 'maintenance_mode') {
            $flagPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4))
                      . '/storage/maintenance.enabled';
            if ($newValue === '1') {
                @file_put_contents($flagPath, '');
            } else {
                @unlink($flagPath);
            }
        }

        // Cookie debug
        if ($key === 'app_debug') {
            $basePath = env('APP_BASE_PATH', '') ?: '/';
            if ($newValue === '1') {
                setcookie('favilla_debug', '1', 0, $basePath);
            } else {
                setcookie('favilla_debug', '', time() - 3600, $basePath);
            }
        }

        // Risposta
        if ($this->isHtmxRequest()) {
            $label = $key === 'app_debug' ? t('admin.settings.label_debug') : t('admin.settings.label_maintenance');
            $state = $newValue === '1' ? t('admin.settings.state_enabled') : t('admin.settings.state_disabled');

            if ($envError) {
                $this->hxToast(t('admin.settings.flash_toggle_env_error', ['label' => $label, 'state' => $state]), 'warning');
            } else {
                $this->hxToast(t('admin.settings.flash_toggle_ok', ['label' => $label, 'state' => $state]));
            }

            // Ritorna il checkbox aggiornato per swap in-place
            $checked = $newValue === '1' ? ' checked' : '';
            $route = e(route('admin.settings.system.toggle'));
            $eKey  = e($key);
            $hxVals = json_encode(['key' => $key], JSON_HEX_QUOT | JSON_HEX_APOS);
            echo "<input class=\"form-check-input\" type=\"checkbox\" id=\"setting-{$eKey}\"{$checked}"
               . " hx-post=\"{$route}\""
               . " hx-vals='" . e($hxVals) . "'"
               . " hx-target=\"#toggle-wrap-{$eKey}\""
               . ' hx-swap="innerHTML">';

            // OOB: aggiorna badge manutenzione nel footer in tempo reale
            if ($key === 'maintenance_mode') {
                $badge = '';
                if ($newValue === '1') {
                    $settingsUrl = e(route('admin.settings.index'));
                    $badge = '<a href="' . $settingsUrl . '"'
                           . ' class="app-footer-maint-badge"'
                           . ' data-bs-toggle="tooltip" data-bs-placement="top"'
                           . ' title="' . e(t('admin.settings.maint_badge_title')) . '"'
                           . ' aria-label="' . e(t('admin.settings.maint_badge_aria')) . '">'
                           . '<span class="app-footer-maint-dot" aria-hidden="true"></span>'
                           . e(t('admin.settings.maint_badge_text')) . '</a>';
                }
                echo '<span id="footer-maint-badge" hx-swap-oob="innerHTML">' . $badge . '</span>';
            }
            return;
        }

        flash_success(t('admin.settings.flash_updated'));
        $this->redirect(route('admin.settings.index'));
    }

    /**
     * Aggiorna impostazioni di sistema (ambiente, debug, manutenzione).
     * Sincronizza i valori nel file .env per effetto immediato.
     */
    public function updateSystem(): void
    {
        $envWriter = $this->envWriter;

        // Mappa: setting DB key => env key => valori
        // N.B. app_debug e maintenance_mode sono gestiti da toggleSystemSetting()
        $systemKeys = [
            'app_env' => [
                'env_key'  => 'APP_ENV',
                'allowed'  => ['development', 'production'],
                'default'  => 'production',
            ],
            'impersonation_timeout' => [
                'env_key' => null, // solo DB, non va nel .env
                'type'    => 'int',
            ],
        ];

        $updates = [];
        $envUpdates = [];
        $oldValues = [];

        foreach ($systemKeys as $dbKey => $config) {
            $currentValue = SettingsService::get($dbKey);
            $oldDb = (string) ($currentValue ?? '');

            if (isset($config['type']) && $config['type'] === 'int') {
                $newValue = isset($_POST[$dbKey]) ? (string) max(1, (int) $_POST[$dbKey]) : '30';
            } elseif (isset($config['allowed'])) {
                $posted = $_POST[$dbKey] ?? $config['default'];
                $newValue = in_array($posted, $config['allowed'], true) ? $posted : $config['default'];
            } else {
                $newValue = trim($_POST[$dbKey] ?? '');
            }

            if ($oldDb !== $newValue) {
                $oldValues[$dbKey] = $oldDb;
                $updates[$dbKey] = $newValue;
            }

            // Sincronizza nel .env
            if ($config['env_key'] !== null) {
                $envUpdates[$config['env_key']] = $newValue;
            }
        }

        // Aggiorna DB
        if (!empty($updates)) {
            SettingsService::bulkUpdate($updates);

            if (!empty($oldValues)) {
                $changedNew = array_intersect_key($updates, $oldValues);
                AuditService::log('system_settings_updated', 'settings', null, $oldValues, $changedNew);
            }
        }

        // Sincronizza .env (anche se non cambiato in DB, per allineare)
        if (!empty($envUpdates)) {
            try {
                $envWriter->writeMany($envUpdates);
            } catch (\Throwable $e) {
                app_log('error', 'EnvWriter error: ' . $e->getMessage());
            }
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('admin.settings.flash_system_saved'));
            header('HX-Refresh: true');
            return;
        }

        flash_success(t('admin.settings.flash_system_saved'));
        $this->redirect(route('admin.settings.index'));
    }
}
