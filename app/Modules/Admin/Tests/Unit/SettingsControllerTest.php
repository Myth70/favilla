<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\SettingsController;
use App\Services\SettingsService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for SettingsController via the HTTP harness.
 * Covers the DB-free invalid-key guard plus the type-aware validation /
 * happy-path of update() against a minimal app_settings table.
 */
class SettingsControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE app_settings (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                `key`       TEXT NOT NULL,
                `value`     TEXT,
                `type`      TEXT NOT NULL DEFAULT "string",
                `group`     TEXT NOT NULL DEFAULT "general",
                label       TEXT,
                description TEXT
            )
        ');
        // insertRow() does not quote identifiers; app_settings uses reserved words
        // (key/value/group), so insert directly with backticks.
        $this->pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, `type`, `group`, label)
             VALUES (?, ?, ?, ?, ?)'
        )->execute(['items_per_page', '20', 'int', 'general', 'Items per page']);

        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    public function testToggleSystemSettingRejectsUnknownKey(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['key' => 'bogus_key'])
            ->dispatch(SettingsController::class, 'toggleSystemSetting');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.settings.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testUpdateRejectsNonIntegerForIntSetting(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['items_per_page' => 'abc'])
            ->dispatch(SettingsController::class, 'update');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.settings.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'), 'Un int non valido deve produrre un flash di errore');
    }

    public function testUpdatePersistsValidValueAndFlashesSuccess(): void
    {
        $this->actingAsAdmin();

        $result = $this->withPost(['items_per_page' => '50'])
            ->dispatch(SettingsController::class, 'update');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/admin.settings.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('success'));

        $stored = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "items_per_page"')
            ->fetchColumn();
        $this->assertSame('50', $stored);
    }

    // ------------------------------------------------------------------
    // SSO (OIDC)
    // ------------------------------------------------------------------

    private function seedSsoSettings(string $storedSecret = ''): void
    {
        $this->migrate('
            CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE);
            CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER,
                action TEXT, entity TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT,
                ip TEXT, created_at TEXT);
        ');
        $this->insertRow('roles', ['name' => 'User', 'slug' => 'user']);

        $insert = $this->pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, `type`, `group`, label) VALUES (?, ?, ?, \'sso\', ?)'
        );
        foreach ([
            ['sso_oidc_enabled', '0', 'bool'],
            ['sso_oidc_issuer', '', 'string'],
            ['sso_oidc_client_id', '', 'string'],
            ['sso_oidc_client_secret', $storedSecret, 'string'],
            ['sso_oidc_jit_default_role', 'user', 'string'],
            ['sso_only', '0', 'bool'],
        ] as [$key, $value, $type]) {
            $insert->execute([$key, $value, $type, $key]);
        }
        SettingsService::clearCache();
    }

    public function testUpdateKeepsStoredSecretWhenPostedBlank(): void
    {
        $this->actingAsAdmin();
        $this->seedSsoSettings('valore-cifrato-esistente');

        $this->withPost(['sso_oidc_client_secret' => ''])
            ->dispatch(SettingsController::class, 'update');

        $stored = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_oidc_client_secret"')
            ->fetchColumn();
        $this->assertSame('valore-cifrato-esistente', $stored);
    }

    public function testUpdateEncryptsNewSecretAndExcludesItFromAudit(): void
    {
        $this->actingAsAdmin();
        $this->seedSsoSettings();

        $this->withPost(['sso_oidc_client_secret' => 'super-secret-value'])
            ->dispatch(SettingsController::class, 'update');

        $stored = (string) $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_oidc_client_secret"')
            ->fetchColumn();
        $encryption = new \App\Services\EncryptionService();
        $this->assertNotSame('super-secret-value', $stored);
        $this->assertTrue($encryption->isEncrypted($stored), 'Il secret deve essere cifrato a riposo');
        $this->assertSame('super-secret-value', $encryption->decrypt($stored));

        $auditRows = $this->pdo->query('SELECT old_value, new_value FROM audit_logs')->fetchAll();
        foreach ($auditRows as $row) {
            $this->assertStringNotContainsString('super-secret-value', (string) $row['new_value']);
            $this->assertStringNotContainsString('sso_oidc_client_secret', (string) $row['new_value']);
        }
    }

    public function testUpdateRejectsSsoOnlyWithoutSsoEnabled(): void
    {
        $this->actingAsAdmin();
        $this->seedSsoSettings();

        $result = $this->withPost(['sso_only' => '1'])
            ->dispatch(SettingsController::class, 'update');

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->flashOf('error'));
        $stored = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_only"')->fetchColumn();
        $this->assertSame('0', $stored, 'sso_only non deve essere salvato se la validazione fallisce');
    }

    public function testUpdateRejectsEnabledSsoWithoutIssuer(): void
    {
        $this->actingAsAdmin();
        $this->seedSsoSettings();

        $result = $this->withPost(['sso_oidc_enabled' => '1', 'sso_oidc_issuer' => ''])
            ->dispatch(SettingsController::class, 'update');

        $this->assertNotNull($this->flashOf('error'));
        $stored = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_oidc_enabled"')->fetchColumn();
        $this->assertSame('0', $stored);
    }

    public function testUpdateIgnoresSsoKeysInPersonalEdition(): void
    {
        $this->actingAsAdmin();
        $this->seedSsoSettings('segreto-originale');
        $_ENV['APP_EDITION'] = 'personal';

        try {
            $this->withPost(['sso_oidc_client_secret' => 'nuovo-segreto', 'sso_oidc_issuer' => 'https://idp.test'])
                ->dispatch(SettingsController::class, 'update');
        } finally {
            unset($_ENV['APP_EDITION']);
        }

        $secret = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_oidc_client_secret"')->fetchColumn();
        $issuer = $this->pdo->query('SELECT `value` FROM app_settings WHERE `key` = "sso_oidc_issuer"')->fetchColumn();
        $this->assertSame('segreto-originale', $secret, 'In Personal il gruppo sso non deve essere modificabile');
        $this->assertSame('', $issuer);
    }
}
