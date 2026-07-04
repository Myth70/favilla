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
}
