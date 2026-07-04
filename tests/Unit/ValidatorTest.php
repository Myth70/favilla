<?php

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequiredPassesWithValue(): void
    {
        $result = $this->validator->validate(
            ['name' => 'Mario'],
            ['name' => 'required']
        );

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->errors());
    }

    public function testRequiredFailsWhenEmpty(): void
    {
        $result = $this->validator->validate(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->errors());
    }

    public function testRequiredFailsWhenMissing(): void
    {
        $result = $this->validator->validate(
            [],
            ['name' => 'required']
        );

        $this->assertFalse($result);
        $this->assertNotNull($this->validator->first('name'));
    }

    public function testEmailPassesWithValidEmail(): void
    {
        $result = $this->validator->validate(
            ['email' => 'user@example.com'],
            ['email' => 'required|email']
        );

        $this->assertTrue($result);
    }

    public function testEmailFailsWithInvalidEmail(): void
    {
        $result = $this->validator->validate(
            ['email' => 'not-an-email'],
            ['email' => 'required|email']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->errors());
    }

    public function testEmailSkipsValidationWhenEmpty(): void
    {
        $result = $this->validator->validate(
            ['email' => ''],
            ['email' => 'email']
        );

        // email rule skips empty values (only required catches empty)
        $this->assertTrue($result);
    }

    public function testMinPassesWhenLongEnough(): void
    {
        $result = $this->validator->validate(
            ['password' => 'abcdef'],
            ['password' => 'min:6']
        );

        $this->assertTrue($result);
    }

    public function testMinFailsWhenTooShort(): void
    {
        $result = $this->validator->validate(
            ['password' => 'abc'],
            ['password' => 'min:6']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('password', $this->validator->errors());
    }

    public function testInPassesWithAllowedValue(): void
    {
        $result = $this->validator->validate(
            ['role' => 'admin'],
            ['role' => 'required|in:admin,editor,viewer']
        );

        $this->assertTrue($result);
    }

    public function testInFailsWithDisallowedValue(): void
    {
        $result = $this->validator->validate(
            ['role' => 'superuser'],
            ['role' => 'required|in:admin,editor,viewer']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('role', $this->validator->errors());
    }

    public function testInSkipsEmptyValue(): void
    {
        $result = $this->validator->validate(
            ['role' => ''],
            ['role' => 'in:admin,editor,viewer']
        );

        $this->assertTrue($result);
    }

    public function testMultipleRulesCombined(): void
    {
        $result = $this->validator->validate(
            ['name' => 'ab', 'email' => 'bad'],
            ['name' => 'required|min:3', 'email' => 'required|email']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->errors());
        $this->assertArrayHasKey('email', $this->validator->errors());
    }

    public function testMaxPassesWithinLimit(): void
    {
        $result = $this->validator->validate(
            ['title' => 'Hello'],
            ['title' => 'max:10']
        );

        $this->assertTrue($result);
    }

    public function testMaxFailsOverLimit(): void
    {
        $result = $this->validator->validate(
            ['title' => 'This is a very long string that exceeds the limit'],
            ['title' => 'max:10']
        );

        $this->assertFalse($result);
    }

    public function testFirstReturnsNullForCleanField(): void
    {
        $this->validator->validate(
            ['name' => 'Mario'],
            ['name' => 'required']
        );

        $this->assertNull($this->validator->first('name'));
    }

    public function testFirstReturnsFirstErrorMessage(): void
    {
        $this->validator->validate(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertIsString($this->validator->first('name'));
    }

    // =====================================================================
    //  Nuove regole
    // =====================================================================

    public function testNullableSkipsAllRulesWhenEmpty(): void
    {
        $result = $this->validator->validate(
            ['website' => ''],
            ['website' => 'nullable|url']
        );

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->errors());
    }

    public function testNullableSkipsWhenNull(): void
    {
        $result = $this->validator->validate(
            [],
            ['note' => 'nullable|min:5']
        );

        $this->assertTrue($result);
    }

    public function testNullableStillValidatesWhenPresent(): void
    {
        $result = $this->validator->validate(
            ['website' => 'not-a-url'],
            ['website' => 'nullable|url']
        );

        $this->assertFalse($result);
        $this->assertArrayHasKey('website', $this->validator->errors());
    }

    public function testNumericPassesWithNumber(): void
    {
        $result = $this->validator->validate(
            ['price' => '19.99'],
            ['price' => 'required|numeric']
        );

        $this->assertTrue($result);
    }

    public function testNumericFailsWithText(): void
    {
        $result = $this->validator->validate(
            ['price' => 'abc'],
            ['price' => 'required|numeric']
        );

        $this->assertFalse($result);
    }

    public function testIntegerPassesWithInteger(): void
    {
        $result = $this->validator->validate(
            ['quantity' => '42'],
            ['quantity' => 'required|integer']
        );

        $this->assertTrue($result);
    }

    public function testIntegerFailsWithFloat(): void
    {
        $result = $this->validator->validate(
            ['quantity' => '3.14'],
            ['quantity' => 'required|integer']
        );

        $this->assertFalse($result);
    }

    public function testUrlPassesWithValidUrl(): void
    {
        $result = $this->validator->validate(
            ['site' => 'https://example.com'],
            ['site' => 'required|url']
        );

        $this->assertTrue($result);
    }

    public function testUrlFailsWithInvalidUrl(): void
    {
        $result = $this->validator->validate(
            ['site' => 'not a url'],
            ['site' => 'required|url']
        );

        $this->assertFalse($result);
    }

    public function testDatePassesWithValidDate(): void
    {
        $result = $this->validator->validate(
            ['birthday' => '2000-01-15'],
            ['birthday' => 'required|date']
        );

        $this->assertTrue($result);
    }

    public function testDateFailsWithInvalidDate(): void
    {
        $result = $this->validator->validate(
            ['birthday' => 'not-a-date'],
            ['birthday' => 'required|date']
        );

        $this->assertFalse($result);
    }
}
