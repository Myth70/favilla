<?php

declare(strict_types=1);

namespace App\Modules\Api\Tests\Unit;

use App\Modules\Api\Services\ApiTokenService;
use App\Repositories\UserRepository;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Creazione/lista/revoca dei PAT su SQLite. UserRepository e AuditService sono
 * mockati; il token repo è reale (verifica hash a riposo e intersezione scope).
 */
class ApiTokenServiceTest extends ModuleTestCase
{
    use MakesContainer;

    private ApiTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE personal_access_tokens (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                name         TEXT NOT NULL,
                token_hash   TEXT NOT NULL UNIQUE,
                scopes       TEXT NULL,
                last_used_at TEXT NULL,
                expires_at   TEXT NULL,
                revoked_at   TEXT NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        // AuditService::log è statico e inghiotte gli errori (nessuna tabella
        // audit_logs qui): non serve mockarlo.
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findWithPermissions')->willReturnCallback(function (int $id): ?array {
            if ($id === 1) {
                return ['id' => 1, 'roles' => [['slug' => 'user']], 'permissions' => ['tasks.view', 'tasks.create', 'contacts.view']];
            }
            return null;
        });
        $this->bindInstance(UserRepository::class, $userRepo);

        $this->service = new ApiTokenService();
    }

    public function testCreateReturnsPlainTokenOnceWithPrefixAndStoresHash(): void
    {
        $result = $this->service->create(1, 'Mobile app', ['tasks.view']);

        $this->assertStringStartsWith(ApiTokenService::TOKEN_PREFIX, $result['plain_token']);
        $this->assertSame(['tasks.view'], $result['scopes']);

        // A riposo solo l'hash: la lista non espone il token in chiaro.
        $tokens = $this->service->listForUser(1);
        $this->assertCount(1, $tokens);
        $this->assertArrayNotHasKey('plain_token', $tokens[0]);
        $this->assertSame(hash('sha256', $result['plain_token']), $tokens[0]['token_hash']);
    }

    public function testCreateIntersectsRequestedScopesWithUserPermissions(): void
    {
        // 'admin.users.view' non è tra i permessi dell'utente => filtrato via.
        $result = $this->service->create(1, 'Limited', ['tasks.view', 'admin.users.view']);

        $this->assertSame(['tasks.view'], $result['scopes']);
    }

    public function testCreateWithNoScopesMeansFullUserPermissions(): void
    {
        $result = $this->service->create(1, 'Full', []);

        $this->assertNull($result['scopes'], 'scopes null = permessi pieni utente');
    }

    public function testCreateRejectsWhenNoRequestedScopeIsGranted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->create(1, 'Bogus', ['admin.users.view']);
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->create(1, '   ', null);
    }

    public function testRevokeHidesTokenFromList(): void
    {
        $result = $this->service->create(1, 'ToRevoke', null);
        $this->assertCount(1, $this->service->listForUser(1));

        $this->assertTrue($this->service->revoke($result['id'], 1));
        $this->assertCount(0, $this->service->listForUser(1));
    }

    public function testRevokeOtherUsersTokenFails(): void
    {
        $result = $this->service->create(1, 'Owned', null);

        $this->assertFalse($this->service->revoke($result['id'], 999));
        $this->assertCount(1, $this->service->listForUser(1));
    }
}
