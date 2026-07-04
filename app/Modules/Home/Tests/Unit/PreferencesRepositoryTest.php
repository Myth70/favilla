<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Repositories\PreferencesRepository;
use Tests\ModuleTestCase;

/**
 * upsert() usa ON DUPLICATE KEY UPDATE (MySQL-only) → coperto in
 * tests/Integration/PreferencesRepositoryIntegrationTest. Qui si testa la parte
 * portabile (lettura).
 */
class PreferencesRepositoryTest extends ModuleTestCase
{
    private PreferencesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE user_preferences (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL UNIQUE,
                theme         TEXT NOT NULL DEFAULT "light",
                primary_color TEXT NOT NULL DEFAULT "#3b82f6",
                created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at    TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->repo = new PreferencesRepository();
    }

    public function testGetByUserIdReturnsRowOrNull(): void
    {
        $this->insertRow('user_preferences', ['user_id' => 42, 'theme' => 'dark']);

        $found = $this->repo->getByUserId(42);
        $this->assertNotNull($found);
        $this->assertSame('dark', $found['theme']);

        $this->assertNull($this->repo->getByUserId(999));
    }
}
