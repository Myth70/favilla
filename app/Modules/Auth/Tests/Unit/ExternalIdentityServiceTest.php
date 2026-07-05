<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Exceptions\ExternalLoginDeniedException;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserRepository;
use App\Services\ExternalIdentityService;
use App\Services\SettingsService;
use App\Services\UserService;
use PDO;
use Tests\ModuleTestCase;

/**
 * Matrice completa di linking/provisioning delle identità esterne:
 * match per sub, aggancio per email verificata, negazioni di policy,
 * JIT on/off, derivazione username, ruolo di default.
 *
 * DDL SQLite speculare a database/schema.sql (tabella oidc_identities).
 */
final class ExternalIdentityServiceTest extends ModuleTestCase
{
    private ExternalIdentityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                avatar_path TEXT DEFAULT NULL,
                created_at TEXT, updated_at TEXT, deleted_at TEXT DEFAULT NULL
            );
            CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE);
            CREATE TABLE user_role (user_id INTEGER, role_id INTEGER, PRIMARY KEY (user_id, role_id));
            CREATE TABLE oidc_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                provider TEXT NOT NULL DEFAULT "oidc",
                issuer TEXT NOT NULL,
                subject TEXT NOT NULL,
                email_at_link TEXT DEFAULT NULL,
                last_login_at TEXT DEFAULT NULL,
                created_at TEXT, updated_at TEXT,
                UNIQUE (provider, issuer, subject),
                UNIQUE (user_id, provider)
            );
            CREATE TABLE app_settings (`key` TEXT PRIMARY KEY, `value` TEXT, `type` TEXT, `group` TEXT, `label` TEXT, updated_at TEXT);
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER, action TEXT, entity TEXT, entity_id INTEGER,
                old_value TEXT, new_value TEXT, ip TEXT, created_at TEXT
            );
        ');

        $this->insertRow('roles', ['name' => 'Administrator', 'slug' => 'admin']);
        $this->insertRow('roles', ['name' => 'Manager', 'slug' => 'manager']);
        $this->insertRow('roles', ['name' => 'User', 'slug' => 'user']);

        $this->setSetting('sso_oidc_jit_enabled', '0', 'bool');
        $this->setSetting('sso_oidc_jit_default_role', 'user', 'string');

        $this->service = new ExternalIdentityService(
            new ExternalIdentityRepository(),
            new UserRepository(),
            new UserService(),
            app(PDO::class)
        );
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    private function setSetting(string $key, string $value, string $type): void
    {
        $this->pdo->prepare('INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`) VALUES (?, ?, ?, "sso", ?)
            ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`')
            ->execute([$key, $value, $type, $key]);
        SettingsService::clearCache();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function identity(array $overrides = []): array
    {
        return array_merge([
            'provider'           => 'oidc',
            'issuer'             => 'https://idp.test',
            'subject'            => 'sub-1',
            'email'              => 'anna@example.test',
            'email_verified'     => true,
            'name'               => 'Anna Verdi',
            'preferred_username' => 'anna.verdi',
        ], $overrides);
    }

    private function createUser(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return $this->insertRow('users', array_merge([
            'name' => 'Utente ' . $n,
            'email' => "utente{$n}@example.test",
            'username' => "utente{$n}",
            'password' => 'hash',
            'is_active' => 1,
        ], $overrides));
    }

    private function auditCount(string $action): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE action = ?');
        $stmt->execute([$action]);

        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------

    public function testSubMatchReturnsLinkedUserAndTouchesLogin(): void
    {
        $userId = $this->createUser(['email' => 'anna@example.test']);
        $this->insertRow('oidc_identities', [
            'user_id' => $userId, 'provider' => 'oidc', 'issuer' => 'https://idp.test', 'subject' => 'sub-1',
        ]);

        $user = $this->service->resolveUser($this->identity());

        $this->assertSame($userId, (int) $user['id']);
        $lastLogin = $this->pdo->query('SELECT last_login_at FROM oidc_identities')->fetchColumn();
        $this->assertNotEmpty($lastLogin);
    }

    public function testVerifiedEmailMatchLinksPermanentlyCaseInsensitive(): void
    {
        $userId = $this->createUser(['email' => 'anna@example.test']);

        $user = $this->service->resolveUser($this->identity(['email' => 'ANNA@Example.TEST']));

        $this->assertSame($userId, (int) $user['id']);
        $link = $this->pdo->query('SELECT * FROM oidc_identities')->fetch();
        $this->assertSame('sub-1', $link['subject']);
        $this->assertSame(1, $this->auditCount('sso_identity_linked'));
    }

    public function testUnverifiedEmailDenied(): void
    {
        $this->createUser(['email' => 'anna@example.test']);

        try {
            $this->service->resolveUser($this->identity(['email_verified' => false]));
            $this->fail('Email non verificata accettata');
        } catch (ExternalLoginDeniedException $e) {
            $this->assertSame(ExternalLoginDeniedException::EMAIL_UNVERIFIED, $e->reason());
        }
    }

    public function testMissingEmailDenied(): void
    {
        try {
            $this->service->resolveUser($this->identity(['email' => null]));
            $this->fail('Identità senza email accettata');
        } catch (ExternalLoginDeniedException $e) {
            $this->assertSame(ExternalLoginDeniedException::EMAIL_MISSING, $e->reason());
        }
    }

    public function testInactiveUserDeniedEvenWithExistingLink(): void
    {
        $userId = $this->createUser(['email' => 'anna@example.test', 'is_active' => 0]);
        $this->insertRow('oidc_identities', [
            'user_id' => $userId, 'provider' => 'oidc', 'issuer' => 'https://idp.test', 'subject' => 'sub-1',
        ]);

        try {
            $this->service->resolveUser($this->identity());
            $this->fail('Utente disattivato accettato');
        } catch (ExternalLoginDeniedException $e) {
            $this->assertSame(ExternalLoginDeniedException::USER_INACTIVE, $e->reason());
        }
    }

    public function testSoftDeletedUserDeniedOnEmailMatch(): void
    {
        $this->createUser(['email' => 'anna@example.test', 'deleted_at' => '2026-01-01 00:00:00']);

        try {
            $this->service->resolveUser($this->identity());
            $this->fail('Utente soft-deleted accettato');
        } catch (ExternalLoginDeniedException $e) {
            // Il soft-deleted non viene trovato dal match email → percorso JIT off.
            $this->assertSame(ExternalLoginDeniedException::NO_LOCAL_ACCOUNT, $e->reason());
        }
    }

    public function testNoLocalAccountDeniedWhenJitDisabled(): void
    {
        try {
            $this->service->resolveUser($this->identity());
            $this->fail('Provisioning avvenuto con JIT disabilitato');
        } catch (ExternalLoginDeniedException $e) {
            $this->assertSame(ExternalLoginDeniedException::NO_LOCAL_ACCOUNT, $e->reason());
        }
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testJitProvisionsActiveUserWithDefaultRole(): void
    {
        $this->setSetting('sso_oidc_jit_enabled', '1', 'bool');

        $user = $this->service->resolveUser($this->identity());

        $this->assertSame('Anna Verdi', $user['name']);
        $this->assertSame('anna.verdi', $user['username']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertSame(0, (int) $user['must_change_password']);

        $roleSlug = $this->pdo->query(
            'SELECT r.slug FROM roles r JOIN user_role ur ON ur.role_id = r.id WHERE ur.user_id = ' . (int) $user['id']
        )->fetchColumn();
        $this->assertSame('user', $roleSlug);

        $this->assertSame(1, $this->auditCount('sso_user_provisioned'));
        $this->assertSame(1, $this->auditCount('sso_identity_linked'));
    }

    public function testJitUsernameCollisionGetsNumericSuffix(): void
    {
        $this->setSetting('sso_oidc_jit_enabled', '1', 'bool');
        $this->createUser(['username' => 'anna.verdi', 'email' => 'altra@example.test']);

        $user = $this->service->resolveUser($this->identity());

        $this->assertSame('anna.verdi2', $user['username']);
    }

    public function testJitInvalidRoleFallsBackToUser(): void
    {
        $this->setSetting('sso_oidc_jit_enabled', '1', 'bool');
        $this->setSetting('sso_oidc_jit_default_role', 'ruolo-inesistente', 'string');

        $user = $this->service->resolveUser($this->identity());

        $roleSlug = $this->pdo->query(
            'SELECT r.slug FROM roles r JOIN user_role ur ON ur.role_id = r.id WHERE ur.user_id = ' . (int) $user['id']
        )->fetchColumn();
        $this->assertSame('user', $roleSlug);
    }

    public function testJitAdminRoleNeverUsedAsDefault(): void
    {
        $this->setSetting('sso_oidc_jit_enabled', '1', 'bool');
        $this->setSetting('sso_oidc_jit_default_role', 'admin', 'string');

        $user = $this->service->resolveUser($this->identity());

        $roleSlug = $this->pdo->query(
            'SELECT r.slug FROM roles r JOIN user_role ur ON ur.role_id = r.id WHERE ur.user_id = ' . (int) $user['id']
        )->fetchColumn();
        $this->assertSame('user', $roleSlug);
    }

    public function testJitDerivesUsernameFromEmailWhenPreferredMissing(): void
    {
        $this->setSetting('sso_oidc_jit_enabled', '1', 'bool');

        $user = $this->service->resolveUser($this->identity([
            'preferred_username' => null,
            'email'              => 'Mario.Rossi+sso@Example.test',
            'name'               => null,
        ]));

        // local-part normalizzato: minuscole, solo [a-z0-9._-]
        $this->assertSame('mario.rossisso', $user['username']);
        $this->assertNotSame('', trim((string) $user['name']));
    }
}
