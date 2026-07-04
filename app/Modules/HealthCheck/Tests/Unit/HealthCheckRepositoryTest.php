<?php

namespace App\Modules\HealthCheck\Tests\Unit;

use App\Modules\HealthCheck\Repositories\HealthCheckRepository;
use Tests\ModuleTestCase;

class HealthCheckRepositoryTest extends ModuleTestCase
{
    private HealthCheckRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("
            CREATE TABLE users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                name     TEXT NOT NULL,
                email    TEXT NOT NULL,
                password TEXT NOT NULL DEFAULT ''
            )
        ");

        $this->migrate("
            CREATE TABLE healthcheck_runs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                total_ok    INTEGER NOT NULL DEFAULT 0,
                total_warn  INTEGER NOT NULL DEFAULT 0,
                total_fail  INTEGER NOT NULL DEFAULT 0,
                data        TEXT NOT NULL,
                created_by  INTEGER NULL,
                created_at  TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->repo = new HealthCheckRepository();
    }

    public function testCreateAndFind(): void
    {
        $id = $this->repo->create([
            'total_ok'   => 10,
            'total_warn' => 2,
            'total_fail' => 1,
            'data'       => '{"php":{"label":"PHP","checks":[]}}',
            'created_by' => null,
        ]);

        $this->assertGreaterThan(0, $id);

        $run = $this->repo->find($id);
        $this->assertNotNull($run);
        $this->assertEquals(10, $run['total_ok']);
        $this->assertEquals(2, $run['total_warn']);
        $this->assertEquals(1, $run['total_fail']);
    }

    public function testGetHistoryOrdering(): void
    {
        // Inserisci 3 run — ID crescente, ORDER BY created_at DESC (= ID più alto primo)
        $this->repo->create([
            'total_ok' => 5, 'total_warn' => 0, 'total_fail' => 0,
            'data' => '{}', 'created_by' => null,
        ]);
        $this->repo->create([
            'total_ok' => 8, 'total_warn' => 1, 'total_fail' => 0,
            'data' => '{}', 'created_by' => null,
        ]);
        $id3 = $this->repo->create([
            'total_ok' => 3, 'total_warn' => 0, 'total_fail' => 2,
            'data' => '{}', 'created_by' => null,
        ]);

        $result = $this->repo->getHistory(20, 1);

        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['items']);
        // L'ultimo inserito (ID più alto) deve essere il primo
        $this->assertEquals($id3, $result['items'][0]['id']);
    }

    public function testGetHistoryPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repo->create([
                'total_ok' => $i, 'total_warn' => 0, 'total_fail' => 0,
                'data' => '{}', 'created_by' => null,
            ]);
        }

        $page1 = $this->repo->getHistory(2, 1);
        $page2 = $this->repo->getHistory(2, 2);

        $this->assertSame(5, $page1['total']);
        $this->assertCount(2, $page1['items']);
        $this->assertSame(3, $page1['lastPage']);
        $this->assertCount(2, $page2['items']);
    }

    public function testGetLastRun(): void
    {
        $this->assertNull($this->repo->getLastRun());

        $this->repo->create([
            'total_ok' => 5, 'total_warn' => 1, 'total_fail' => 0,
            'data' => '{"test":true}', 'created_by' => null,
        ]);

        $last = $this->repo->getLastRun();
        $this->assertNotNull($last);
        $this->assertEquals(5, $last['total_ok']);
    }

    public function testGetHistoryEmpty(): void
    {
        $result = $this->repo->getHistory(20, 1);

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['items']);
        $this->assertSame(0, $result['lastPage']);
    }
}
