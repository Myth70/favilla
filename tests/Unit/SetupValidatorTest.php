<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Setup\SetupValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test per SetupValidator — standalone, nessun DB richiesto tranne
 * il test di connessione con credenziali errate.
 */
class SetupValidatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // testDbConnection
    // ------------------------------------------------------------------

    public function testDbConnectionWithInvalidCredentialsReturnsErrorString(): void
    {
        // Connessione su host non raggiungibile → deve ritornare stringa di errore
        $result = SetupValidator::testDbConnection(
            '127.0.0.2',  // host inesistente
            '3306',
            'nonexistent',
            'bad_user',
            'bad_pass'
        );

        $this->assertIsString($result, 'Deve ritornare una stringa di errore');
        $this->assertNotSame(true, $result);
    }

    // ------------------------------------------------------------------
    // validatePassword
    // ------------------------------------------------------------------

    public function testPasswordTooShortReturnsError(): void
    {
        $errors = SetupValidator::validatePassword('Ab1x');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsStringIgnoringCase('8', implode(' ', $errors));
    }

    public function testPasswordWithoutUppercaseReturnsError(): void
    {
        $errors = SetupValidator::validatePassword('abcdefg1');
        $found  = array_filter($errors, fn ($e) => stripos($e, 'maiuscola') !== false);
        $this->assertNotEmpty($found, 'Deve segnalare la mancanza di maiuscola');
    }

    public function testPasswordWithoutNumberReturnsError(): void
    {
        $errors = SetupValidator::validatePassword('Abcdefgh');
        $found  = array_filter($errors, fn ($e) => stripos($e, 'numero') !== false);
        $this->assertNotEmpty($found, 'Deve segnalare la mancanza di un numero');
    }

    public function testValidPasswordReturnsNoErrors(): void
    {
        $errors = SetupValidator::validatePassword('Favilla1');
        $this->assertEmpty($errors, 'Password valida non deve produrre errori');
    }

    public function testPasswordWithMultipleIssuesReturnsMultipleErrors(): void
    {
        $errors = SetupValidator::validatePassword('abc');
        $this->assertCount(3, $errors, 'Deve segnalare 3 problemi (lunghezza, maiuscola, numero)');
    }

    // ------------------------------------------------------------------
    // validateAppKey
    // ------------------------------------------------------------------

    public function testAppKeyTooShortReturnsFalse(): void
    {
        $this->assertFalse(SetupValidator::validateAppKey('tooshort'));
    }

    public function testAppKeyExactly32CharsReturnsTrue(): void
    {
        $key = str_repeat('a', 32);
        $this->assertTrue(SetupValidator::validateAppKey($key));
    }

    public function testAppKeyLongerThan32CharsReturnsTrue(): void
    {
        $key = bin2hex(random_bytes(32)); // 64 hex chars
        $this->assertTrue(SetupValidator::validateAppKey($key));
    }

    public function testEmptyAppKeyReturnsFalse(): void
    {
        $this->assertFalse(SetupValidator::validateAppKey(''));
    }

    // ------------------------------------------------------------------
    // writeEnvFile
    // ------------------------------------------------------------------

    public function testWriteEnvFileFalseIfExampleMissing(): void
    {
        $result = SetupValidator::writeEnvFile('/non/existent/.env.example', []);
        $this->assertFalse($result);
    }

    public function testWriteEnvFileSubstitutesPlaceholders(): void
    {
        $baseTmp = defined('BASE_PATH') ? BASE_PATH . '/storage/tmp' : dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($baseTmp)) {
            mkdir($baseTmp, 0755, true);
        }
        $tmpDir = $baseTmp . '/setup_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $example = $tmpDir . '/.env.example';
        file_put_contents($example, implode("\n", [
            'APP_NAME="Favilla"',
            'APP_ENV=production',
            'APP_KEY=',
            'DB_HOST=localhost',
            'DB_PASS=old_pass',
        ]));

        $result = SetupValidator::writeEnvFile($example, [
            'APP_NAME' => '"Mia Intranet"',
            'APP_ENV'  => 'development',
            'APP_KEY'  => 'abc123def456ghi789jkl012mno345pqr',
            'DB_PASS'  => 'nuova_password',
        ]);

        $this->assertTrue($result, 'writeEnvFile deve ritornare true');

        $envPath = $tmpDir . '/.env';
        $this->assertFileExists($envPath);

        $content = file_get_contents($envPath);
        $this->assertStringContainsString('APP_NAME="Mia Intranet"', $content);
        $this->assertStringContainsString('APP_ENV=development', $content);
        $this->assertStringContainsString('APP_KEY=abc123def456ghi789jkl012mno345pqr', $content);
        $this->assertStringContainsString('DB_PASS=nuova_password', $content);
        // Le righe non toccate rimangono invariate
        $this->assertStringContainsString('DB_HOST=localhost', $content);

        // Cleanup
        unlink($envPath);
        unlink($example);
        rmdir($tmpDir);
    }

    public function testWriteEnvFileDoesNotTouchUnspecifiedKeys(): void
    {
        $baseTmp = defined('BASE_PATH') ? BASE_PATH . '/storage/tmp' : dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($baseTmp)) {
            mkdir($baseTmp, 0755, true);
        }
        $tmpDir = $baseTmp . '/setup_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $example = $tmpDir . '/.env.example';
        file_put_contents($example, "APP_NAME=\"Favilla\"\nSESSION_LIFETIME=480\n");

        SetupValidator::writeEnvFile($example, ['APP_NAME' => '"Nuovo"']);

        $content = file_get_contents($tmpDir . '/.env');
        // SESSION_LIFETIME deve restare invariato
        $this->assertStringContainsString('SESSION_LIFETIME=480', $content);

        unlink($tmpDir . '/.env');
        unlink($example);
        rmdir($tmpDir);
    }
}
