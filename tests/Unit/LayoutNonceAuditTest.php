<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LayoutNonceAuditTest extends TestCase
{
    /**
     * @dataProvider executableScriptFilesProvider
     */
    public function test_executable_scripts_have_nonce(string $relativePath): void
    {
        $fullPath = BASE_PATH . '/' . $relativePath;
        $this->assertFileExists($fullPath, 'File non trovato: ' . $relativePath);

        $content = (string) file_get_contents($fullPath);
        $normalized = (string) preg_replace('/<\\?=.+?\\?>/s', 'PHP_EXPR', $content);
        preg_match_all('/<script\\b([^>]*)>/i', $normalized, $matches);

        foreach ($matches[1] as $attrs) {
            $isJsonDataIsland = (bool) preg_match('/\btype\s*=\s*["\']application\\/json["\']/i', $attrs);
            if ($isJsonDataIsland) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/\bnonce\s*=\s*["\'][^"\']+["\']/i',
                $attrs,
                'Tag <script' . $attrs . '> privo di nonce in ' . $relativePath
            );
        }
    }

    public static function executableScriptFilesProvider(): array
    {
        return [
            ['app/Views/layouts/main.php'],
            ['app/Views/layouts/auth.php'],
            ['app/Modules/Auth/Views/totp-setup.php'],
            ['app/Modules/Notifications/Views/partials/bell.php'],
            ['app/Modules/Scheduler/Views/form.php'],
            ['app/Modules/Scheduler/Views/index.php'],
        ];
    }
}
