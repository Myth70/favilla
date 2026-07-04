<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Services\BundledTemplateService;
use Tests\ModuleTestCase;

/**
 * Tests for BundledTemplateService — the filesystem-driven import paths that are
 * deterministic without a populated report_templates schema (a module with no
 * bundled templates yields an all-zero result; importAll never crashes even
 * when the DB tables are absent because per-file errors are captured).
 */
class BundledTemplateServiceTest extends ModuleTestCase
{
    public function testImportFromModuleWithoutTemplatesReturnsZeroedResult(): void
    {
        $service = new BundledTemplateService();

        $result = $service->importFromModule('Zzzphantommodule');

        $this->assertSame(
            ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []],
            $result
        );
    }

    public function testImportAllReturnsAggregateShapeWithoutImporting(): void
    {
        $service = new BundledTemplateService();

        // No report_templates schema in the in-memory DB, so nothing is actually
        // imported/updated/skipped; the method must still return cleanly.
        $result = $service->importAll();

        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
    }
}
