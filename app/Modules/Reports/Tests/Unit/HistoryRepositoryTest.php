<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\HistoryRepository;
use Tests\ModuleTestCase;

class HistoryRepositoryTest extends ModuleTestCase
{
    private HistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE report_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER DEFAULT NULL,
                template_name TEXT NOT NULL,
                module TEXT NOT NULL,
                source_key TEXT NOT NULL,
                output_format TEXT NOT NULL,
                stored_filename TEXT DEFAULT NULL,
                file_size INTEGER DEFAULT 0,
                row_count INTEGER DEFAULT 0,
                filters_used TEXT DEFAULT NULL,
                generated_by INTEGER DEFAULT NULL,
                generated_at TEXT DEFAULT NULL,
                expires_at TEXT DEFAULT NULL
            );
        ');

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('users', ['name' => 'Luigi']);

        $this->repo = new HistoryRepository($this->pdo);
    }

    public function testListPaginatedFiltersByUserWhenNotAdmin(): void
    {
        $this->insertRow('report_history', [
            'template_name' => 'T1',
            'module' => 'Sales',
            'source_key' => 'orders',
            'output_format' => 'pdf',
            'generated_by' => 1,
            'generated_at' => '2026-03-20 10:00:00',
        ]);
        $this->insertRow('report_history', [
            'template_name' => 'T2',
            'module' => 'HR',
            'source_key' => 'employees',
            'output_format' => 'csv',
            'generated_by' => 2,
            'generated_at' => '2026-03-20 11:00:00',
        ]);

        $result = $this->repo->listPaginated(['sort' => 'generated_at', 'dir' => 'DESC'], 1, 20, 1, false);

        $this->assertCount(1, $result['items']);
        $this->assertSame('T1', $result['items'][0]['template_name']);
    }

    public function testGetStatsAggregatesCounts(): void
    {
        $this->insertRow('report_history', [
            'template_name' => 'PDF Report',
            'module' => 'Sales',
            'source_key' => 'orders',
            'output_format' => 'pdf',
            'file_size' => 100,
            'row_count' => 10,
            'generated_by' => 1,
            'generated_at' => '2026-03-20 10:00:00',
        ]);
        $this->insertRow('report_history', [
            'template_name' => 'CSV Report',
            'module' => 'HR',
            'source_key' => 'employees',
            'output_format' => 'csv',
            'file_size' => 200,
            'row_count' => 15,
            'generated_by' => 2,
            'generated_at' => '2026-03-21 10:00:00',
        ]);

        $stats = $this->repo->getStats();

        $this->assertSame(2, $stats['total_reports']);
        $this->assertSame(300, $stats['total_size']);
        $this->assertSame(25, $stats['total_rows']);
        $this->assertSame(1, $stats['pdf_count']);
        $this->assertSame(1, $stats['csv_count']);
    }
}
