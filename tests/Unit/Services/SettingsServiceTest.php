<?php

namespace Tests\Unit\Services;

use App\Repositories\SettingsRepository;
use App\Services\SettingsService;
use Tests\ModuleTestCase;

class SettingsServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        // Insert test settings
        $this->pdo->exec("INSERT INTO app_settings (`key`, value, type, `group`, label) VALUES
            ('app_name', 'TestApp', 'string', 'general', 'Nome app'),
            ('maintenance_mode', '0', 'bool', 'general', 'Manutenzione'),
            ('smtp_port', '587', 'int', 'mail', 'Porta SMTP'),
            ('mail_config', '{\"host\":\"smtp.test\"}', 'json', 'mail', 'Config JSON')
        ");

        // Register SettingsRepository in container
        app()->instance(SettingsRepository::class, new SettingsRepository());

        // Clear any cached values
        SettingsService::clearCache();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    public function test_get_returns_default_when_not_found(): void
    {
        $result = SettingsService::get('nonexistent_key', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    public function test_get_returns_default_null_when_not_found(): void
    {
        $result = SettingsService::get('nonexistent_key');
        $this->assertNull($result);
    }

    public function test_set_and_get_roundtrip(): void
    {
        SettingsService::set('app_name', 'UpdatedApp');
        $result = SettingsService::get('app_name');
        $this->assertEquals('UpdatedApp', $result);
    }

    public function test_bool_casting(): void
    {
        $result = SettingsService::get('maintenance_mode');
        $this->assertFalse($result);
        $this->assertIsBool($result);

        SettingsService::set('maintenance_mode', '1');
        $result = SettingsService::get('maintenance_mode');
        $this->assertTrue($result);
        $this->assertIsBool($result);
    }

    public function test_int_casting(): void
    {
        $result = SettingsService::get('smtp_port');
        $this->assertSame(587, $result);
        $this->assertIsInt($result);
    }

    public function test_json_casting(): void
    {
        $result = SettingsService::get('mail_config');
        $this->assertIsArray($result);
        $this->assertEquals('smtp.test', $result['host']);
    }

    public function test_cache_cleared_on_set(): void
    {
        // First call loads cache
        $this->assertEquals('TestApp', SettingsService::get('app_name'));

        // Direct DB update (bypassing service)
        $this->pdo->exec("UPDATE app_settings SET value = 'DirectUpdate' WHERE `key` = 'app_name'");

        // Still cached
        $this->assertEquals('TestApp', SettingsService::get('app_name'));

        // After set(), cache is cleared
        SettingsService::set('smtp_port', '25');

        // Now reads fresh from DB
        $this->assertEquals('DirectUpdate', SettingsService::get('app_name'));
    }

    public function test_get_by_group(): void
    {
        $mailSettings = SettingsService::getByGroup('mail');
        $this->assertNotEmpty($mailSettings);
        $keys = array_column($mailSettings, 'key');
        $this->assertContains('smtp_port', $keys);
        $this->assertContains('mail_config', $keys);
    }
}
