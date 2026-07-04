<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit;

use App\Modules\HealthCheck\Checks\AbstractHealthCheck;
use App\Modules\HealthCheck\Checks\HealthCheck;
use App\Modules\HealthCheck\Services\HealthCheckService;
use PHPUnit\Framework\TestCase;

/**
 * Logica di orchestrazione del servizio: filtro fast/deep, aggregazione,
 * prioritizzazione ed export. I check sono iniettati come fake, quindi nessun DB.
 */
class HealthCheckServiceTest extends TestCase
{
    // ------------------------------------------------------------------
    // runFast() / runAll() — filtro per profondità
    // ------------------------------------------------------------------

    public function testRunFastExcludesDeepChecks(): void
    {
        $service = new HealthCheckService([
            new FakeCheck('php', 'PHP', AbstractHealthCheck::DEPTH_FAST),
            new FakeCheck('email', 'Email', AbstractHealthCheck::DEPTH_DEEP),
        ]);

        $results = $service->runFast();

        $this->assertArrayHasKey('php', $results);
        $this->assertArrayNotHasKey('email', $results, 'I check deep non devono girare in modalità fast');
    }

    public function testRunAllIncludesDeepChecks(): void
    {
        $service = new HealthCheckService([
            new FakeCheck('php', 'PHP', AbstractHealthCheck::DEPTH_FAST),
            new FakeCheck('email', 'Email', AbstractHealthCheck::DEPTH_DEEP),
        ]);

        $results = $service->runAll();

        $this->assertArrayHasKey('php', $results);
        $this->assertArrayHasKey('email', $results);
    }

    public function testRunIndexesResultsByCheckKey(): void
    {
        $service = new HealthCheckService([
            new FakeCheck('database', 'Database', AbstractHealthCheck::DEPTH_FAST),
        ]);

        $results = $service->runAll();

        $this->assertSame('Database', $results['database']['label']);
        $this->assertArrayHasKey('checks', $results['database']);
    }

    public function testDefaultRegistryIsNotEmpty(): void
    {
        $this->assertNotEmpty(HealthCheckService::defaultChecks());
    }

    // ------------------------------------------------------------------
    // checkPhpHardening() — gruppo singolo per la dashboard Admin
    // ------------------------------------------------------------------

    public function testCheckPhpHardeningReturnsHardeningGroup(): void
    {
        // Indipendente dal registro iniettato: gira sempre il check di hardening PHP.
        $group = (new HealthCheckService([]))->checkPhpHardening();

        $this->assertSame('Hardening PHP', $group['label']);
        $this->assertArrayHasKey('checks', $group);
        $this->assertNotEmpty($group['checks']);

        foreach ($group['checks'] as $check) {
            $this->assertArrayHasKey('name', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertContains($check['status'], ['ok', 'warn', 'fail']);
        }
    }

    // ------------------------------------------------------------------
    // summary()
    // ------------------------------------------------------------------

    public function testSummaryCountsByStatus(): void
    {
        $summary = (new HealthCheckService([]))->summary([
            'php' => ['label' => 'PHP', 'description' => '', 'checks' => [
                ['name' => 'A', 'status' => 'ok', 'detail' => ''],
                ['name' => 'B', 'status' => 'warn', 'detail' => ''],
                ['name' => 'C', 'status' => 'fail', 'detail' => ''],
            ]],
            'db' => ['label' => 'Database', 'description' => '', 'checks' => [
                ['name' => 'D', 'status' => 'ok', 'detail' => ''],
                ['name' => 'E', 'status' => 'ok', 'detail' => ''],
            ]],
        ]);

        $this->assertSame(3, $summary['ok']);
        $this->assertSame(1, $summary['warn']);
        $this->assertSame(1, $summary['fail']);
    }

    public function testSummaryEmpty(): void
    {
        $summary = (new HealthCheckService([]))->summary([]);

        $this->assertSame(['ok' => 0, 'warn' => 0, 'fail' => 0], $summary);
    }

    // ------------------------------------------------------------------
    // prioritizeResults()
    // ------------------------------------------------------------------

    public function testPrioritizeSortsFailFirstThenWarnThenOk(): void
    {
        $results = [
            'all_ok'   => ['label' => 'Ok',   'description' => '', 'checks' => [['name' => 'x', 'status' => 'ok',   'detail' => '']]],
            'has_fail' => ['label' => 'Fail', 'description' => '', 'checks' => [['name' => 'y', 'status' => 'fail', 'detail' => '']]],
            'has_warn' => ['label' => 'Warn', 'description' => '', 'checks' => [['name' => 'z', 'status' => 'warn', 'detail' => '']]],
        ];

        $groups = (new HealthCheckService([]))->prioritizeResults($results);

        $this->assertSame('has_fail', $groups[0]['key']);
        $this->assertSame('has_warn', $groups[1]['key']);
        $this->assertSame('all_ok', $groups[2]['key']);
    }

    public function testPrioritizeExtractsActionableHighlights(): void
    {
        $results = [
            'mix' => ['label' => 'Mix', 'description' => '', 'checks' => [
                ['name' => 'ok1',   'status' => 'ok',   'detail' => ''],
                ['name' => 'warn1', 'status' => 'warn', 'detail' => ''],
                ['name' => 'fail1', 'status' => 'fail', 'detail' => ''],
            ]],
        ];

        $groups = (new HealthCheckService([]))->prioritizeResults($results);

        $this->assertSame('fail', $groups[0]['status']);
        $this->assertSame(1, $groups[0]['counts']['warn']);
        $this->assertSame(1, $groups[0]['counts']['fail']);
        // highlights = solo i non-ok
        $this->assertCount(2, $groups[0]['highlights']);
    }

    // ------------------------------------------------------------------
    // toExportRows()
    // ------------------------------------------------------------------

    public function testToExportRowsFlattensResults(): void
    {
        $rows = (new HealthCheckService([]))->toExportRows([
            'php' => ['label' => 'PHP', 'description' => '', 'checks' => [
                ['name' => 'Versione', 'status' => 'ok',   'detail' => '8.2.0'],
                ['name' => 'Memory',   'status' => 'warn', 'detail' => '64M'],
            ]],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('PHP', $rows[0]['Categoria']);
        $this->assertSame('Versione', $rows[0]['Check']);
        $this->assertSame('OK', $rows[0]['Stato']);
        $this->assertSame('8.2.0', $rows[0]['Dettaglio']);
        $this->assertSame('WARN', $rows[1]['Stato']);
    }

    public function testToExportRowsEmpty(): void
    {
        $this->assertSame([], (new HealthCheckService([]))->toExportRows([]));
    }
}

/**
 * Check fittizio per testare l'orchestrazione senza dipendenze reali.
 */
class FakeCheck implements HealthCheck
{
    public function __construct(
        private string $key,
        private string $label,
        private string $depth = 'fast',
        private array $rows = []
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return 'fake';
    }

    public function depth(): string
    {
        return $this->depth;
    }

    public function run(): array
    {
        return ['label' => $this->label, 'description' => 'fake', 'checks' => $this->rows];
    }
}
