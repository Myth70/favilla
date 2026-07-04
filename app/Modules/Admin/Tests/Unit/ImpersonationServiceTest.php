<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ImpersonationService;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

class ImpersonationServiceTest extends TestCase
{
    use MakesContainer;

    /**
     * @param array<string,mixed>|null $target valore restituito da findWithPermissions()
     */
    private function serviceWithTarget(?array $target): ImpersonationService
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findWithPermissions')->willReturn($target);

        $this->freshContainer();
        $this->bindInstance(UserRepository::class, $repo);
        return new ImpersonationService();
    }

    public function testCanImpersonateRejectsSelf(): void
    {
        $res = $this->serviceWithTarget(null)->canImpersonate(5, 5);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('te stesso', $res['error']);
    }

    public function testCanImpersonateRejectsWhenAlreadyImpersonating(): void
    {
        $res = $this->serviceWithTarget(null)->canImpersonate(1, 2, ['_impersonator_id' => 9]);
        $this->assertFalse($res['ok']);
    }

    public function testCanImpersonateRejectsUnknownTarget(): void
    {
        $res = $this->serviceWithTarget(null)->canImpersonate(1, 2);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('non trovato', $res['error']);
    }

    public function testCanImpersonateRejectsInactiveTarget(): void
    {
        $res = $this->serviceWithTarget(['id' => 2, 'is_active' => 0, 'roles' => []])->canImpersonate(1, 2);
        $this->assertFalse($res['ok']);
    }

    public function testCanImpersonateRejectsAdminTarget(): void
    {
        $target = ['id' => 2, 'is_active' => 1, 'roles' => [['slug' => 'admin']]];
        $res = $this->serviceWithTarget($target)->canImpersonate(1, 2);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('amministratore', $res['error']);
    }

    public function testCanImpersonateAllowsValidTarget(): void
    {
        $target = ['id' => 2, 'is_active' => 1, 'roles' => [['slug' => 'editor']]];
        $res = $this->serviceWithTarget($target)->canImpersonate(1, 2);
        $this->assertTrue($res['ok']);
        $this->assertNull($res['error']);
    }

    public function testSessionIsImpersonatingDetectsImpersonatorId(): void
    {
        $this->assertFalse(ImpersonationService::sessionIsImpersonating([]));
        $this->assertFalse(ImpersonationService::sessionIsImpersonating(['user_id' => 5]));
        $this->assertTrue(ImpersonationService::sessionIsImpersonating(['_impersonator_id' => 1]));
    }

    public function testSessionIsExpiredFalseWhenNotImpersonating(): void
    {
        // Anche con un expires_at passato: senza impersonazione attiva non scade nulla.
        $this->assertFalse(ImpersonationService::sessionIsExpired([
            '_impersonation_expires_at' => time() - 100,
        ]));
    }

    public function testSessionIsExpiredFalseBeforeDeadline(): void
    {
        $this->assertFalse(ImpersonationService::sessionIsExpired([
            '_impersonator_id'          => 1,
            '_impersonation_expires_at' => time() + 60,
        ]));
    }

    public function testSessionIsExpiredTrueAfterDeadline(): void
    {
        $this->assertTrue(ImpersonationService::sessionIsExpired([
            '_impersonator_id'          => 1,
            '_impersonation_expires_at' => time() - 1,
        ]));
    }

    public function testSessionIsExpiredTrueWhenImpersonatingWithoutDeadline(): void
    {
        // Sessione impersonata senza expires_at (stato anomalo): trattata come
        // scaduta, così la guardia in AuthMiddleware fa il revert e non lascia
        // un'impersonazione senza limite di durata.
        $this->assertTrue(ImpersonationService::sessionIsExpired([
            '_impersonator_id' => 1,
        ]));
    }
}
