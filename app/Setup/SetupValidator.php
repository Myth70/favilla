<?php

namespace App\Setup;

/**
 * SetupValidator — validazione dati Setup Wizard.
 * Standalone: nessuna dipendenza dal framework.
 */
class SetupValidator
{
    // ------------------------------------------------------------------
    // Requisiti di sistema
    // ------------------------------------------------------------------

    /**
     * Esegue tutti i check di sistema e restituisce un array di risultati.
     * @return array<array{name: string, ok: bool, detail: string}>
     */
    public static function checkRequirements(): array
    {
        $checks = [];

        // PHP version
        $checks[] = [
            'name'   => 'PHP >= 8.2',
            'ok'     => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => PHP_VERSION,
        ];

        // Estensioni obbligatorie
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo'] as $ext) {
            $checks[] = [
                'name'   => "Estensione $ext",
                'ok'     => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'attiva' : 'MANCANTE',
            ];
        }

        // Directory scrivibili
        $dirs = ['storage/logs', 'storage/sessions', 'public/uploads'];
        foreach ($dirs as $dir) {
            $path = BASE_PATH . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $checks[] = [
                'name'   => "Dir scrivibile: $dir",
                'ok'     => $ok,
                'detail' => $ok ? 'scrivibile' : 'NON scrivibile',
            ];
        }

        // .env.example presente
        $hasExample = self::safeFileExists(BASE_PATH . '/.env.example');
        $checks[] = [
            'name'   => '.env.example presente',
            'ok'     => $hasExample,
            'detail' => $hasExample ? 'presente' : 'MANCANTE',
        ];

        return $checks;
    }

    // ------------------------------------------------------------------
    // Database
    // ------------------------------------------------------------------

    /**
     * Testa la connessione PDO a MariaDB/MySQL.
     * Ritorna true se OK, oppure la stringa dell'errore.
     */
    public static function testDbConnection(
        string $host,
        string $port,
        string $name,
        string $user,
        string $pass
    ): bool|string {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            return true;
        } catch (\PDOException $e) {
            return $e->getMessage();
        }
    }

    // ------------------------------------------------------------------
    // Password
    // ------------------------------------------------------------------

    /**
     * Valida la password amministratore.
     * Regole: min 8 char, almeno 1 maiuscola, almeno 1 numero.
     * @return string[] Lista di errori (vuota = OK)
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'La password deve essere di almeno 8 caratteri.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La password deve contenere almeno una lettera maiuscola.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La password deve contenere almeno un numero.';
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // APP_KEY
    // ------------------------------------------------------------------

    /**
     * Verifica che l'APP_KEY abbia almeno 32 caratteri.
     */
    public static function validateAppKey(string $key): bool
    {
        return strlen($key) >= 32;
    }

    // ------------------------------------------------------------------
    // .env
    // ------------------------------------------------------------------

    /**
     * Sostituisce i valori delle chiavi nel file .env.example
     * e scrive il risultato come .env nella stessa directory.
     *
     * @param string          $envExamplePath Path assoluto a .env.example
     * @param array<string, string> $vars     Mappa KEY => valore (già formattato per .env)
     */
    public static function writeEnvFile(string $envExamplePath, array $vars): bool
    {
        if (!self::safeFileExists($envExamplePath)) {
            return false;
        }

        $content = @file_get_contents($envExamplePath);
        if ($content === false) {
            return false;
        }

        foreach ($vars as $key => $value) {
            $content = preg_replace(
                '/^' . preg_quote($key, '/') . '=.*/m',
                $key . '=' . $value,
                $content
            );
        }

        $envPath = dirname($envExamplePath) . '/.env';
        return @file_put_contents($envPath, $content) !== false;
    }

    private static function safeFileExists(string $path): bool
    {
        return @is_file($path);
    }
}
