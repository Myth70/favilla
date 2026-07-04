<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Repositories\SettingsRepository;
use App\Services\SettingsService;
use Tests\ModuleTestCase;

/**
 * Verifica la risoluzione di edition()/edition_profile()/is_single_user():
 * priorità env > setting > default, fallback a 'developer' su valori ignoti.
 */
class EditionHelpersTest extends ModuleTestCase
{
    private array $savedEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedEnv = $_ENV;
        unset($_ENV['APP_EDITION']);

        $this->migrate("
            CREATE TABLE app_settings (
                key        TEXT NOT NULL PRIMARY KEY,
                value      TEXT DEFAULT NULL,
                type       TEXT NOT NULL DEFAULT 'string',
                'group'    TEXT NOT NULL DEFAULT 'general',
                label      TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            )
        ");

        app()->instance(SettingsRepository::class, new SettingsRepository());
        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        SettingsService::clearCache();
        parent::tearDown();
    }

    private function seedSetting(string $value): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO app_settings (`key`, value, type, `group`, label)
             VALUES ('app_edition', ?, 'string', 'internal', 'Edizione installazione')"
        );
        $stmt->execute([$value]);
        SettingsService::clearCache();
    }

    public function test_default_edition_is_developer_when_nothing_set(): void
    {
        $this->assertSame('developer', edition());
        $this->assertFalse(is_single_user());
    }

    public function test_setting_is_used_when_env_is_absent(): void
    {
        $this->seedSetting('personal');

        $this->assertSame('personal', edition());
        $this->assertTrue(is_single_user());
    }

    public function test_env_takes_priority_over_setting(): void
    {
        $this->seedSetting('personal');
        $_ENV['APP_EDITION'] = 'team';

        $this->assertSame('team', edition());
        $this->assertFalse(is_single_user());
    }

    public function test_invalid_env_value_falls_back_to_default(): void
    {
        $_ENV['APP_EDITION'] = 'not-a-real-edition';

        $this->assertSame('developer', edition());
    }

    public function test_invalid_setting_value_falls_back_to_default(): void
    {
        $this->seedSetting('bogus');

        $this->assertSame('developer', edition());
    }

    public function test_is_single_user_true_only_for_personal(): void
    {
        $_ENV['APP_EDITION'] = 'personal';
        $this->assertTrue(is_single_user());

        $_ENV['APP_EDITION'] = 'team';
        $this->assertFalse(is_single_user());

        $_ENV['APP_EDITION'] = 'developer';
        $this->assertFalse(is_single_user());
    }

    public function test_edition_profile_returns_matching_config(): void
    {
        $_ENV['APP_EDITION'] = 'personal';

        $profile = edition_profile();

        $this->assertSame('Personal', $profile['label']);
        $this->assertContains('Admin', $profile['sidebar_hidden_modules']);
    }
}
