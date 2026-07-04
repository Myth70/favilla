<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Home\Repositories\WidgetPreferencesRepository;

/**
 * upsertBatch() usa ON DUPLICATE KEY UPDATE (MySQL-only): va verificato sul
 * dialetto reale. Testa insert iniziale e ri-upsert idempotente.
 */
class WidgetPreferencesRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    // upsertBatch() apre una propria transazione → niente wrapping (MariaDB non
    // annida le transazioni). I test usano utenti distinti per restare isolati.
    protected bool $useTransaction = false;

    public function testUpsertBatchInsertsThenUpdatesWithoutDuplicates(): void
    {
        $userId = $this->insertRow('users', [
            'name' => 'U', 'email' => 'w@example.test', 'username' => 'u_widget', 'password' => 'x',
        ]);
        $repo = new WidgetPreferencesRepository();

        $repo->upsertBatch($userId, [
            ['widget_id' => 'tasks.open', 'sort_order' => 1, 'visible' => 1],
            ['widget_id' => 'calendar.next', 'sort_order' => 2, 'visible' => 1],
        ]);
        $this->assertCount(2, $repo->getByUserId($userId));

        // Re-upsert sugli stessi widget_id: aggiorna, non duplica (UNIQUE user+widget).
        $repo->upsertBatch($userId, [
            ['widget_id' => 'tasks.open', 'sort_order' => 5, 'visible' => 0],
        ]);

        $rows = $repo->getByUserId($userId);
        $this->assertCount(2, $rows, 'Nessun duplicato dopo il re-upsert');
        $byId = array_column($rows, null, 'widget_id');
        $this->assertSame(0, (int) $byId['tasks.open']['visible']);
        $this->assertSame(5, (int) $byId['tasks.open']['sort_order']);
    }

    public function testUpsertBatchWithEmptyItemsIsNoop(): void
    {
        $userId = $this->insertRow('users', [
            'name' => 'U', 'email' => 'w2@example.test', 'username' => 'u_widget2', 'password' => 'x',
        ]);
        $repo = new WidgetPreferencesRepository();

        $repo->upsertBatch($userId, []);
        $this->assertSame([], $repo->getByUserId($userId));
    }
}
