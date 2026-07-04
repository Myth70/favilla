<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\AdminLogsRepository;
use App\Modules\Admin\Services\AdminLogsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * AdminLogsService è un delegatore sottile verso AdminLogsRepository: i test
 * verificano l'inoltro corretto di argomenti e risultati usando un repository
 * mockato registrato nel Container.
 */
class AdminLogsServiceTest extends TestCase
{
    use MakesContainer;

    private function serviceWithRepo(AdminLogsRepository $repo): AdminLogsService
    {
        $this->freshContainer();
        $this->bindInstance(AdminLogsRepository::class, $repo);
        return new AdminLogsService();
    }

    public function testListAuditForwardsFiltersAndPageAndReturnsResult(): void
    {
        $repo = $this->createMock(AdminLogsRepository::class);
        $repo->expects($this->once())
            ->method('listAudit')
            ->with(['action' => 'login'], 3)
            ->willReturn(['items' => ['x'], 'total' => 1]);

        $result = $this->serviceWithRepo($repo)->listAudit(['action' => 'login'], 3);
        $this->assertSame(['items' => ['x'], 'total' => 1], $result);
    }

    public function testPurgeAuditForwardsDaysAndReturnsCount(): void
    {
        $repo = $this->createMock(AdminLogsRepository::class);
        $repo->expects($this->once())->method('purgeAudit')->with(30)->willReturn(7);

        $this->assertSame(7, $this->serviceWithRepo($repo)->purgeAudit(30));
    }

    public function testExportAuditForwardsFilters(): void
    {
        $repo = $this->createMock(AdminLogsRepository::class);
        $repo->expects($this->once())->method('exportAudit')->with(['x' => 1])->willReturn([['row']]);

        $this->assertSame([['row']], $this->serviceWithRepo($repo)->exportAudit(['x' => 1]));
    }

    public function testGetExportLimitExposesRepositoryConstant(): void
    {
        $repo = $this->createMock(AdminLogsRepository::class);
        $this->assertSame(AdminLogsRepository::EXPORT_LIMIT, $this->serviceWithRepo($repo)->getExportLimit());
    }
}
