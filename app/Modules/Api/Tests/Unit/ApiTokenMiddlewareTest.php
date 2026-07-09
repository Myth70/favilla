<?php

declare(strict_types=1);

namespace App\Modules\Api\Tests\Unit;

use App\Exceptions\HttpException;
use App\Modules\Api\Middleware\ApiTokenMiddleware;
use App\Modules\Api\Repositories\PersonalAccessTokenRepository;
use App\Modules\Api\Support\ApiRequestContext;
use App\Repositories\UserRepository;
use App\Services\SettingsService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Guardie di autenticazione dell'API: kill-switch, token mancante/invalido,
 * account inattivo e successo (contesto popolato + next chiamato). Repository
 * mockati; setting('api_enabled') letto dal default quando app_settings assente.
 */
class ApiTokenMiddlewareTest extends ModuleTestCase
{
    use MakesContainer;

    private PersonalAccessTokenRepository $tokenRepo;
    private UserRepository $userRepo;
    private ApiRequestContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        SettingsService::clearCache();

        $this->tokenRepo = $this->createMock(PersonalAccessTokenRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->context = new ApiRequestContext();

        $this->bindInstance(PersonalAccessTokenRepository::class, $this->tokenRepo);
        $this->bindInstance(UserRepository::class, $this->userRepo);
        $this->bindInstance(ApiRequestContext::class, $this->context);

        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function runMiddleware(): ?HttpException
    {
        $called = false;
        try {
            (new ApiTokenMiddleware())->handle(function () use (&$called) {
                $called = true;
            });
        } catch (HttpException $e) {
            return $e;
        }
        $this->assertTrue($called, 'next() deve essere chiamato quando l\'auth riesce');
        return null;
    }

    public function testMissingTokenReturns401(): void
    {
        $e = $this->runMiddleware();
        $this->assertNotNull($e);
        $this->assertSame(401, $e->getStatusCode());
        $this->assertStringContainsString('unauthenticated', (string) $e->getBody());
        $this->assertSame('Bearer', $e->getHeaders()['WWW-Authenticate'] ?? null);
    }

    public function testInvalidTokenReturns401(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer favilla_pat_bogus';
        $this->tokenRepo->method('findValidByHash')->willReturn(null);

        $e = $this->runMiddleware();
        $this->assertSame(401, $e?->getStatusCode());
        $this->assertStringContainsString('invalid_token', (string) $e?->getBody());
    }

    public function testKillSwitchReturns503(): void
    {
        // app_settings con api_enabled=0 => setting() risolve false.
        $this->migrate('CREATE TABLE app_settings (`key` TEXT PRIMARY KEY, `value` TEXT, `type` TEXT, `group` TEXT, `label` TEXT, updated_at TEXT)');
        $this->pdo->exec("INSERT INTO app_settings (`key`,`value`,`type`,`group`) VALUES ('api_enabled','0','bool','api')");
        SettingsService::clearCache();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer favilla_pat_whatever';

        $e = $this->runMiddleware();
        $this->assertSame(503, $e?->getStatusCode());
        $this->assertStringContainsString('api_disabled', (string) $e?->getBody());
    }

    public function testInactiveAccountReturns401(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer favilla_pat_valid';
        $this->tokenRepo->method('findValidByHash')->willReturn([
            'id' => 5, 'user_id' => 7, 'scopes' => null,
        ]);
        $this->userRepo->method('findWithPermissions')->willReturn([
            'id' => 7, 'is_active' => 0, 'roles' => [], 'permissions' => [],
        ]);

        $e = $this->runMiddleware();
        $this->assertSame(401, $e?->getStatusCode());
        $this->assertStringContainsString('inactive_account', (string) $e?->getBody());
    }

    public function testValidTokenPopulatesContextAndCallsNext(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer favilla_pat_good';
        $this->tokenRepo->method('findValidByHash')->willReturn([
            'id' => 9, 'user_id' => 7, 'scopes' => json_encode(['tasks.view']),
        ]);
        $this->tokenRepo->expects($this->once())->method('touchLastUsed')->with(9);
        $this->userRepo->method('findWithPermissions')->willReturn([
            'id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test', 'is_active' => 1,
            'roles' => [['slug' => 'user']], 'permissions' => ['tasks.view', 'tasks.create'],
        ]);

        $e = $this->runMiddleware();
        $this->assertNull($e);
        $this->assertTrue($this->context->isAuthenticated());
        $this->assertSame(7, $this->context->userId());
        $this->assertSame(['tasks.view'], $this->context->scopes());
        // Gate effettivo: tasks.view è tra permessi e scope; tasks.create no (fuori scope).
        $this->assertTrue($this->context->can('tasks.view'));
        $this->assertFalse($this->context->can('tasks.create'));
    }
}
