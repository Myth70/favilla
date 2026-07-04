<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\AuthController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AuthController via the HTTP harness, plus the pure
 * isSafeRedirectTarget() guard. Only DB-free branches are exercised here; the
 * actual authentication / password flows belong to the Integration suite.
 */
class AuthControllerTest extends ControllerTestCase
{
    /**
     * @dataProvider safeRedirectProvider
     */
    public function testIsSafeRedirectTarget(string $target, bool $expected): void
    {
        $this->assertSame($expected, AuthController::isSafeRedirectTarget($target));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function safeRedirectProvider(): array
    {
        return [
            'empty is safe (no redirect)' => ['', true],
            'local path'                  => ['/dashboard', true],
            'nested path'                 => ['/contacts/5/edit', true],
            'path with query'            => ['/reports?type=csv', true],
            'protocol-relative'          => ['//evil.com', false],
            'absolute url'               => ['https://evil.com', false],
            'does not start with slash'  => ['evil.com', false],
            'backslash escape'           => ['/\\evil.com', false],
            'parent dir traversal'       => ['/a/../../etc', false],
            'encoded double slash'       => ['/path%2f%2fevil', false],
            'encoded backslash'          => ['/%5cevil', false],
        ];
    }

    public function testLoginWithMissingCredentialsRedirectsToLogin(): void
    {
        $result = $this->withPost([])->dispatch(AuthController::class, 'login');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/login', $result->redirectUrl());
        $this->assertNotEmpty($_SESSION['_login_error'] ?? '');
    }

    public function testChangePasswordRejectsMismatchedConfirmation(): void
    {
        $this->actingAs(1);

        $result = $this->withPost([
            'password'              => 'abcdefgh',
            'password_confirmation' => 'different1',
        ])->dispatch(AuthController::class, 'changePassword');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/password.change', $result->redirectUrl());
        $this->assertStringContainsString('non corrispondono', $_SESSION['_change_pw_error'] ?? '');
    }

    public function testUpdateProfileRejectsEmptyName(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['name' => '   '])->dispatch(AuthController::class, 'updateProfile');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/profile', $result->redirectUrl());
        $this->assertArrayHasKey('name', $_SESSION['_errors'] ?? []);
    }
}
