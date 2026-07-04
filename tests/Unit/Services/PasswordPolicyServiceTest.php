<?php

namespace Tests\Unit\Services;

use App\Repositories\SettingsRepository;
use App\Services\PasswordPolicyService;
use App\Services\SettingsService;
use Tests\ModuleTestCase;

class PasswordPolicyServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("\n            CREATE TABLE users (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                password TEXT DEFAULT NULL,\n                password_changed_at TEXT DEFAULT NULL,\n                deleted_at TEXT DEFAULT NULL\n            )\n        ");

        $this->migrate("\n            CREATE TABLE password_history (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                user_id INTEGER NOT NULL,\n                password_hash TEXT NOT NULL,\n                created_at TEXT DEFAULT (datetime('now'))\n            )\n        ");

        $this->migrate("\n            CREATE TABLE app_settings (\n                key TEXT NOT NULL PRIMARY KEY,\n                value TEXT DEFAULT NULL,\n                type TEXT NOT NULL DEFAULT 'string',\n                'group' TEXT NOT NULL DEFAULT 'general',\n                label TEXT DEFAULT NULL,\n                updated_at TEXT DEFAULT NULL\n            )\n        ");

        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES
            ('password_policy_enabled', '1', 'bool', 'security'),
            ('password_min_length', '12', 'int', 'security'),
            ('password_require_upper', '1', 'bool', 'security'),
            ('password_require_lower', '1', 'bool', 'security'),
            ('password_require_digit', '1', 'bool', 'security'),
            ('password_require_special', '1', 'bool', 'security'),
            ('password_history_count', '5', 'int', 'security')
        ");

        app()->instance(SettingsRepository::class, new SettingsRepository());
        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    public function test_valid_password_passes(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('Abcdef1!xyz9');

        $this->assertEmpty($errors);
    }

    public function test_too_short_password_fails(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('Ab1!');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('almeno', $errors[0]);
    }

    public function test_missing_uppercase_fails(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('abcdef1!xyz9');

        $this->assertNotEmpty($errors);
    }

    public function test_missing_digit_fails(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('Abcdefgh!xyz');

        $this->assertNotEmpty($errors);
    }

    public function test_missing_special_fails(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('Abcdefgh1xyz');

        $this->assertNotEmpty($errors);
    }

    public function test_policy_disabled_always_passes(): void
    {
        $this->pdo->exec("UPDATE app_settings SET value = '0' WHERE `key` = 'password_policy_enabled'");
        SettingsService::clearCache();

        $service = new PasswordPolicyService();
        $errors = $service->validate('weak');

        $this->assertSame([], $errors);
    }

    public function test_missing_lowercase_fails(): void
    {
        $service = new PasswordPolicyService();
        $errors = $service->validate('ABCDEF1!XYZ9');
        $this->assertNotEmpty($errors);
    }

    public function test_is_enabled_reads_setting(): void
    {
        $svc = new PasswordPolicyService();
        $this->assertTrue($svc->isEnabled());

        $this->pdo->exec("UPDATE app_settings SET value = '0' WHERE `key` = 'password_policy_enabled'");
        SettingsService::clearCache();
        $this->assertFalse($svc->isEnabled());
    }

    public function test_rules_description_when_enabled(): void
    {
        $svc = new PasswordPolicyService();
        $rules = $svc->getRulesDescription();
        $this->assertNotEmpty($rules);
        $this->assertStringContainsString('12 caratteri', implode(' ', $rules));
        $joined = implode(' ', $rules);
        $this->assertStringContainsString('maiuscola', $joined);
        $this->assertStringContainsString('minuscola', $joined);
        $this->assertStringContainsString('numero', $joined);
        $this->assertStringContainsString('speciale', $joined);
    }

    public function test_rules_description_when_disabled(): void
    {
        $this->pdo->exec("UPDATE app_settings SET value = '0' WHERE `key` = 'password_policy_enabled'");
        SettingsService::clearCache();

        $svc = new PasswordPolicyService();
        $this->assertSame(['Almeno 8 caratteri.'], $svc->getRulesDescription());
    }

    public function test_validate_returns_multiple_errors(): void
    {
        $svc = new PasswordPolicyService();
        $errors = $svc->validate('abc'); // too short, no upper, no digit, no special
        $this->assertGreaterThanOrEqual(4, count($errors));
    }

    public function test_history_rejects_recently_used_password(): void
    {
        $userId = $this->insertRow('users', ['password' => password_hash('current!A1pass', PASSWORD_DEFAULT)]);
        $oldHash = password_hash('OldSecret!A1pw', PASSWORD_DEFAULT);
        $this->insertRow('password_history', ['user_id' => $userId, 'password_hash' => $oldHash]);

        $svc = new PasswordPolicyService();
        $errors = $svc->validate('OldSecret!A1pw', $userId);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('ultime', end($errors));
    }

    public function test_history_rejects_current_password(): void
    {
        $userId = $this->insertRow('users', ['password' => password_hash('current!A1pass', PASSWORD_DEFAULT)]);
        $svc = new PasswordPolicyService();
        $errors = $svc->validate('current!A1pass', $userId);
        $this->assertNotEmpty($errors);
    }

    public function test_record_in_history_and_prune(): void
    {
        $this->pdo->exec("UPDATE app_settings SET value = '2' WHERE `key` = 'password_history_count'");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', ['password' => 'x']);
        $svc = new PasswordPolicyService();
        $svc->recordInHistory($userId, 'hash1');
        $svc->recordInHistory($userId, 'hash2');
        $svc->recordInHistory($userId, 'hash3');

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM password_history WHERE user_id = {$userId}")->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function test_touch_password_changed_at_updates_column(): void
    {
        $userId = $this->insertRow('users', ['password' => 'x']);
        $svc = new PasswordPolicyService();
        $svc->touchPasswordChangedAt($userId);

        $changed = $this->pdo->query("SELECT password_changed_at FROM users WHERE id = {$userId}")->fetchColumn();
        $this->assertNotNull($changed);
        $this->assertNotSame('', $changed);
    }

    public function test_password_expired_when_no_changed_at(): void
    {
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('password_max_age_days', '90', 'int', 'security')");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', ['password' => 'x']);
        $svc = new PasswordPolicyService();
        $this->assertTrue($svc->isPasswordExpired($userId));
    }

    public function test_password_not_expired_when_recent(): void
    {
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('password_max_age_days', '90', 'int', 'security')");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', [
            'password' => 'x',
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
        $svc = new PasswordPolicyService();
        $this->assertFalse($svc->isPasswordExpired($userId));
    }

    public function test_password_expired_when_old(): void
    {
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('password_max_age_days', '30', 'int', 'security')");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', [
            'password' => 'x',
            'password_changed_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
        ]);
        $svc = new PasswordPolicyService();
        $this->assertTrue($svc->isPasswordExpired($userId));
    }

    public function test_days_until_expiry_returns_null_when_disabled(): void
    {
        $this->pdo->exec("UPDATE app_settings SET value = '0' WHERE `key` = 'password_policy_enabled'");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', ['password' => 'x']);
        $svc = new PasswordPolicyService();
        $this->assertNull($svc->daysUntilExpiry($userId));
    }

    public function test_days_until_expiry_zero_when_never_set(): void
    {
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('password_max_age_days', '90', 'int', 'security')");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', ['password' => 'x']);
        $svc = new PasswordPolicyService();
        $this->assertSame(0, $svc->daysUntilExpiry($userId));
    }

    public function test_days_until_expiry_returns_positive_for_recent_change(): void
    {
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`) VALUES ('password_max_age_days', '90', 'int', 'security')");
        SettingsService::clearCache();

        $userId = $this->insertRow('users', [
            'password' => 'x',
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
        $svc = new PasswordPolicyService();
        $days = $svc->daysUntilExpiry($userId);
        $this->assertGreaterThan(80, $days);
    }
}
