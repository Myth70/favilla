<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServicePasswordResetTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new class () extends AuthService {
            public function __construct()
            {
                // Bypass container dependencies for guard-clause tests.
            }
        };
    }

    public function testValidatePasswordResetTokenRejectsInvalidFormat(): void
    {
        $this->assertNull($this->service->validatePasswordResetToken('invalid-token'));
        $this->assertNull($this->service->validatePasswordResetToken(str_repeat('a', 63)));
        $this->assertNull($this->service->validatePasswordResetToken(str_repeat('g', 64)));
    }

    public function testConsumePasswordResetTokenReturnsFalseForInvalidFormat(): void
    {
        $this->assertFalse($this->service->consumePasswordResetToken('bad-token', 'TestPassword123!'));
    }
}
