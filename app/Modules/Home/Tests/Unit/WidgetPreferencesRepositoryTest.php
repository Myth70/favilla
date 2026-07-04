<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Repositories\WidgetPreferencesRepository;
use Tests\ModuleTestCase;

/**
 * upsertBatch() usa ON DUPLICATE KEY UPDATE (MySQL-only) → coperto in
 * tests/Integration/WidgetPreferencesRepositoryIntegrationTest. Qui si testano
 * i metodi portabili: lettura ordinata, delete e replaceAll atomico.
 */
class WidgetPreferencesRepositoryTest extends ModuleTestCase
{
    private WidgetPreferencesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE user_widget_preferences (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                widget_id  TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                visible    INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, widget_id)
            );
        ');
        $this->repo = new WidgetPreferencesRepository();
    }

    public function testGetByUserIdReturnsRowsOrderedBySortOrder(): void
    {
        $this->insertRow('user_widget_preferences', ['user_id' => 1, 'widget_id' => 'b', 'sort_order' => 2, 'visible' => 1]);
        $this->insertRow('user_widget_preferences', ['user_id' => 1, 'widget_id' => 'a', 'sort_order' => 1, 'visible' => 1]);
        $this->insertRow('user_widget_preferences', ['user_id' => 2, 'widget_id' => 'x', 'sort_order' => 1, 'visible' => 1]);

        $rows = $this->repo->getByUserId(1);

        $this->assertCount(2, $rows);
        $this->assertSame(['a', 'b'], array_column($rows, 'widget_id'));
    }

    public function testDeleteByUserIdRemovesOnlyThatUser(): void
    {
        $this->insertRow('user_widget_preferences', ['user_id' => 1, 'widget_id' => 'a', 'sort_order' => 1, 'visible' => 1]);
        $this->insertRow('user_widget_preferences', ['user_id' => 2, 'widget_id' => 'a', 'sort_order' => 1, 'visible' => 1]);

        $this->repo->deleteByUserId(1);

        $this->assertSame([], $this->repo->getByUserId(1));
        $this->assertCount(1, $this->repo->getByUserId(2));
    }

    public function testReplaceAllRemovesOrphansAndInsertsNewSet(): void
    {
        $this->insertRow('user_widget_preferences', ['user_id' => 1, 'widget_id' => 'old', 'sort_order' => 1, 'visible' => 1]);

        $this->repo->replaceAll(1, [
            ['widget_id' => 'new1', 'sort_order' => 1, 'visible' => 1],
            ['widget_id' => 'new2', 'sort_order' => 2, 'visible' => 0],
        ]);

        $rows = $this->repo->getByUserId(1);
        $this->assertSame(['new1', 'new2'], array_column($rows, 'widget_id'));
        $this->assertSame(0, (int) $rows[1]['visible']);
    }

    public function testReplaceAllWithEmptySetClearsEverything(): void
    {
        $this->insertRow('user_widget_preferences', ['user_id' => 1, 'widget_id' => 'a', 'sort_order' => 1, 'visible' => 1]);

        $this->repo->replaceAll(1, []);

        $this->assertSame([], $this->repo->getByUserId(1));
    }
}
