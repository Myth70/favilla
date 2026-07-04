<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Services\HistoryService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

class HistoryServiceTest extends TestCase
{
    use MakesContainer;

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function serviceWith(HistoryRepository $repo): HistoryService
    {
        $this->freshContainer();
        $this->bindInstance(HistoryRepository::class, $repo);
        return new HistoryService();
    }

    public function testRecordNormalizesPayload(): void
    {
        $_SESSION['user_id'] = 5;

        $repo = $this->createMock(HistoryRepository::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $d): bool {
                return $d['template_id'] === 1
                    && $d['output_format'] === 'pdf'
                    && $d['generated_by'] === 5
                    && $d['filters_used'] === json_encode(['q' => 'x'], JSON_UNESCAPED_UNICODE)
                    && !empty($d['expires_at'])
                    && !empty($d['generated_at']);
            }))
            ->willReturn(99);

        $id = $this->serviceWith($repo)->record(
            1,
            'Vendite',
            'Reports',
            'sales',
            'pdf',
            'file.pdf',
            1024,
            10,
            ['q' => 'x']
        );
        $this->assertSame(99, $id);
    }

    public function testRecordWithoutFiltersStoresNull(): void
    {
        $repo = $this->createMock(HistoryRepository::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(fn (array $d): bool => $d['filters_used'] === null))
            ->willReturn(1);

        $this->serviceWith($repo)->record(null, 'T', 'M', 's', 'xlsx', 'f.xlsx', 1, 1);
    }

    public function testCleanupExpiredCountsDeletedEntries(): void
    {
        $repo = $this->createMock(HistoryRepository::class);
        // File inesistenti → freed_bytes 0 ma deleted_count = righe rimosse.
        $repo->method('deleteExpired')->willReturn([
            ['stored_filename' => 'a.pdf', 'file_size' => 100],
            ['stored_filename' => 'b.pdf', 'file_size' => 200],
        ]);

        $result = $this->serviceWith($repo)->cleanupExpired();
        $this->assertSame(2, $result['deleted_count']);
        $this->assertSame(0, $result['freed_bytes']);
    }

    public function testGetStatsDelegates(): void
    {
        $repo = $this->createMock(HistoryRepository::class);
        $repo->method('getStats')->willReturn(['total' => 7]);

        $this->assertSame(['total' => 7], $this->serviceWith($repo)->getStats());
    }
}
