<?php

namespace App\Modules\Auth\Tests\Unit;

use App\Core\Container;
use App\Core\Router;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use App\Services\UserService;
use Tests\ModuleTestCase;

class AuthPasswordResetServiceTest extends ModuleTestCase
{
    private AuthService $service;
    private object $mailSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                username TEXT NOT NULL,
                password TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                remember_token TEXT NULL,
                deleted_at TEXT NULL,
                updated_at TEXT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL
            );
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity TEXT NOT NULL,
                entity_id INTEGER NULL,
                ip TEXT NULL,
                new_value TEXT NULL
            );
            CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                success INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE user_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL
            );
            CREATE TABLE user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL
            );
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL
            );
            CREATE TABLE role_permission (
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL
            );
        ');

        $container = Container::getInstance();

        $container->instance(Router::class, new class () {
            public function url(string $name, array $params = []): string
            {
                if ($name === 'password.reset.form') {
                    return '/password/reset/' . ($params['token'] ?? '');
                }
                return '/';
            }
        });

        $this->mailSpy = new class () {
            public array $calls = [];

            public function sendFromTemplate(string $to, string $slug, array $vars): bool
            {
                $this->calls[] = ['to' => $to, 'slug' => $slug, 'vars' => $vars];
                return true;
            }
        };
        $container->instance(MailService::class, $this->mailSpy);

        $container->instance(PasswordPolicyService::class, new class () {
            public function validate(string $password, int $userId): array
            {
                return [];
            }
        });

        $container->instance(UserService::class, new class ($this->pdo) {
            public function __construct(private \PDO $pdo)
            {
            }

            public function changePassword(int $userId, string $newPassword): bool
            {
                $stmt = $this->pdo->prepare('UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?');
                return $stmt->execute([password_hash($newPassword, PASSWORD_ARGON2ID), $userId]);
            }
        });

        $this->service = app(AuthService::class);
    }

    public function testProcessForgotPasswordIgnoresUnknownEmailWithoutSideEffects(): void
    {
        $this->service->processForgotPassword('unknown@example.com');

        $resetCount = (int) $this->pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn();
        $auditCount = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

        $this->assertSame(0, $resetCount);
        $this->assertSame(0, $auditCount);
        $this->assertCount(0, $this->mailSpy->calls);
    }

    public function testValidatePasswordResetTokenRejectsInvalidFormat(): void
    {
        $result = $this->service->validatePasswordResetToken('not-a-valid-token');
        $this->assertNull($result);
    }

    public function testConsumePasswordResetTokenMarksUsedAndUpdatesPassword(): void
    {
        $userId = $this->insertRow('users', [
            'name' => 'Giulia Verdi',
            'email' => 'giulia@example.com',
            'username' => 'giulia',
            'password' => password_hash('OldPass!123', PASSWORD_ARGON2ID),
            'is_active' => 1,
        ]);

        $token = str_repeat('a', 64);
        $this->insertRow('password_resets', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'used_at' => null,
        ]);

        $ok = $this->service->consumePasswordResetToken($token, 'NewPass!456');
        $this->assertTrue($ok);

        $usedAt = $this->pdo->query('SELECT used_at FROM password_resets ORDER BY id DESC LIMIT 1')->fetchColumn();
        $this->assertNotEmpty($usedAt);

        $newHash = $this->pdo->query('SELECT password FROM users WHERE id = ' . (int) $userId)->fetchColumn();
        $this->assertTrue(password_verify('NewPass!456', (string) $newHash));
    }
}
