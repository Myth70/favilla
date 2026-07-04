<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\DataRetentionController;
use Tests\ControllerTestCase;

/**
 * Controller-level test for DataRetentionController via the HTTP harness.
 *
 * Only index() is exercised: the mutating actions (update/toggle/execute)
 * terminate with raw header()+exit instead of the redirect() seam, so they
 * cannot be driven from a unit test without killing the runner — those belong
 * to the Integration suite.
 */
class DataRetentionControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE data_retention_policies (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                entity         TEXT NOT NULL,
                table_name     TEXT,
                date_column    TEXT,
                retention_days INTEGER NOT NULL DEFAULT 30,
                action         TEXT NOT NULL DEFAULT "delete",
                enabled        INTEGER NOT NULL DEFAULT 1,
                last_run_at    TEXT DEFAULT NULL,
                updated_at     TEXT DEFAULT NULL
            )
        ');
        $this->insertRow('data_retention_policies', [
            'entity'         => 'audit_log',
            'table_name'     => 'audit_log',
            'date_column'    => 'created_at',
            'retention_days' => 90,
            'action'         => 'delete',
            'enabled'        => 1,
        ]);
    }

    public function testIndexRendersDashboardWithPoliciesAndStats(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch(DataRetentionController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/data-retention', $result->renderedTemplate());

        $data = $result->renderedData();
        $this->assertCount(1, $data['policies']);
        $this->assertSame('audit_log', $data['policies'][0]['entity']);
        $this->assertSame(1, $data['stats']['total']);
        $this->assertSame(1, $data['stats']['enabled']);
    }
}
