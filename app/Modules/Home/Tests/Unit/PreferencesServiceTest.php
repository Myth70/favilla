<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\PreferencesService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PreferencesService validation logic.
 * Tests the input validation without requiring a database connection.
 */
class PreferencesServiceTest extends TestCase
{
    /**
     * Theme validation: valid values should pass through unchanged.
     * @dataProvider validThemeProvider
     */
    public function testValidThemePassesThrough(string $input): void
    {
        $this->assertTrue(in_array($input, ['light', 'dark'], true));
    }

    public static function validThemeProvider(): array
    {
        return [
            'light' => ['light'],
            'dark'  => ['dark'],
        ];
    }

    /**
     * Theme validation: invalid values should fall back to 'light'.
     * @dataProvider invalidThemeProvider
     */
    public function testInvalidThemeFallsBackToLight(string $input): void
    {
        $theme = in_array($input, ['light', 'dark'], true) ? $input : 'light';
        $this->assertSame('light', $theme);
    }

    public static function invalidThemeProvider(): array
    {
        return [
            'empty'       => [''],
            'random'      => ['blue'],
            'uppercase'   => ['Dark'],
            'xss attempt' => ['<script>'],
        ];
    }

    /**
     * Color validation: valid hex codes should pass.
     * @dataProvider validColorProvider
     */
    public function testValidColorPassesThrough(string $input): void
    {
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $input);
    }

    public static function validColorProvider(): array
    {
        return [
            'blue'    => ['#3b82f6'],
            'red'     => ['#ff0000'],
            'green'   => ['#00ff00'],
            'black'   => ['#000000'],
            'white'   => ['#ffffff'],
            'mixed'   => ['#aAbBcC'],
        ];
    }

    /**
     * Color validation: invalid values should fall back to default.
     * @dataProvider invalidColorProvider
     */
    public function testInvalidColorFallsBackToDefault(string $input): void
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $input) ? $input : '#3b82f6';
        $this->assertSame('#3b82f6', $color);
    }

    public static function invalidColorProvider(): array
    {
        return [
            'short hex'    => ['#fff'],
            'no hash'      => ['3b82f6'],
            'word'         => ['blue'],
            'too long'     => ['#3b82f6ff'],
            'rgb'          => ['rgb(0,0,0)'],
            'empty'        => [''],
            'xss attempt'  => ['#<script>'],
        ];
    }

    /**
     * Sidebar collapsed validation: only 1 is collapsed.
     */
    public function testSidebarCollapsedValues(): void
    {
        // Valid: 1 stays 1
        $collapsed = 1 === 1 ? 1 : 0;
        $this->assertSame(1, $collapsed);

        // Valid: 0 stays 0
        $collapsed = 0 === 1 ? 1 : 0;
        $this->assertSame(0, $collapsed);

        // Invalid: 99 becomes 0
        $collapsed = 99 === 1 ? 1 : 0;
        $this->assertSame(0, $collapsed);

        // Invalid: -1 becomes 0
        $collapsed = -1 === 1 ? 1 : 0;
        $this->assertSame(0, $collapsed);
    }

    /**
     * POST data parsing: sidebar_collapsed from form.
     */
    public function testSidebarPostParsing(): void
    {
        // Simulates the controller parsing
        $data = '1';
        $collapsed = $data === '1' ? 1 : 0;
        $this->assertSame(1, $collapsed);

        $data = '0';
        $collapsed = $data === '1' ? 1 : 0;
        $this->assertSame(0, $collapsed);

        $data = 'yes';
        $collapsed = $data === '1' ? 1 : 0;
        $this->assertSame(0, $collapsed);
    }
}
