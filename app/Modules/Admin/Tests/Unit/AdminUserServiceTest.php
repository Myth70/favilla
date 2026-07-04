<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\AdminUserRepository;
use App\Modules\Admin\Services\AdminUserService;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

class AdminUserServiceTest extends TestCase
{
    use MakesContainer;

    private AdminUserRepository $repo;

    private function service(): AdminUserService
    {
        $this->freshContainer();
        $this->bindInstance(AdminUserRepository::class, $this->repo);
        // UserService usato da invalidateSessions/resetPassword: mock no-op.
        $this->bindInstance(UserService::class, $this->createMock(UserService::class));
        return new AdminUserService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->createMock(AdminUserRepository::class);
    }

    public function testBulkActionExcludesSelfAndRejectsEmptySelection(): void
    {
        // L'unico id è quello dell'admin corrente → escluso → selezione vuota.
        $result = $this->service()->bulkAction('activate', [5], 5);

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['status']);
    }

    public function testBulkActivateReturnsCountAndPluralMessage(): void
    {
        $this->repo->method('bulkSetActive')->with([1, 2], 1)->willReturn(2);

        $result = $this->service()->bulkAction('activate', [1, 2, 5], 5);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['count']);
        $this->assertStringContainsString('utenti attivati', $result['message']);
    }

    public function testBulkActivateSingularMessage(): void
    {
        $this->repo->method('bulkSetActive')->willReturn(1);

        $result = $this->service()->bulkAction('activate', [1], 99);
        $this->assertStringContainsString('utente attivato', $result['message']);
    }

    public function testBulkAssignRoleWithoutRoleIsRejected(): void
    {
        $result = $this->service()->bulkAction('assign_role', [1, 2], 99, null);
        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['status']);
    }

    public function testBulkActionUnknownActionIsRejected(): void
    {
        $result = $this->service()->bulkAction('boh', [1, 2], 99);
        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['status']);
    }

    public function testEmailExistsDelegates(): void
    {
        $this->repo->method('emailExists')->with('a@x.test', 0)->willReturn(true);
        $this->assertTrue($this->service()->emailExists('a@x.test'));
    }
}
