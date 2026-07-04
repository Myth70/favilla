<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Container;
use App\Core\ModuleLoader;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base per i test di integrazione su MariaDB reale.
 *
 * A differenza degli unit test (SQLite in-memory), questa base carica il
 * `database/schema.sql` canonico in un database MariaDB usa-e-getta e applica
 * le STESSE opzioni PDO di produzione (ATTR_EMULATE_PREPARES=false), così da
 * riprodurre i bug specifici del dialetto che SQLite nasconde: collation,
 * binding di LIMIT/OFFSET, NOW()/DATE_SUB, quoting di parole riservate.
 *
 * OPT-IN: salta a meno che RUN_DB_INTEGRATION=1, quindi la run di default
 * (`vendor/bin/phpunit`) non tocca mai un server MariaDB.
 *
 * Isolamento: ogni test gira dentro una transazione, annullata in tearDown(),
 * così le righe inserite da un test non si vedono nel successivo senza dover
 * ricaricare lo schema ad ogni metodo.
 *
 * Connessione da DB_HOST/DB_PORT/DB_USER/DB_PASS; il nome DB è SEMPRE quello di
 * test dedicato (DB_TEST_NAME, default `favilla_ci_test`), mai DB_NAME.
 */
abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected static ?PDO $pdo = null;
    protected static string $skipReason = '';

    /**
     * Avvolge ogni test in una transazione annullata in tearDown (isolamento).
     * Va messo a false dalle classi che esercitano metodi i quali aprono una
     * PROPRIA transazione (es. upsertBatch/replaceAll): MariaDB non supporta
     * transazioni annidate. Tali classi devono usare dati distinti per test.
     */
    protected bool $useTransaction = true;

    protected static function testDbName(): string
    {
        return (string) (getenv('DB_TEST_NAME') ?: ($_ENV['DB_TEST_NAME'] ?? 'favilla_ci_test'));
    }

    public static function setUpBeforeClass(): void
    {
        $enabled = (getenv('RUN_DB_INTEGRATION') ?: ($_ENV['RUN_DB_INTEGRATION'] ?? '')) === '1';
        if (!$enabled) {
            self::$skipReason = 'DB integration disabled (set RUN_DB_INTEGRATION=1 to enable).';
            return;
        }

        $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'));
        $port = (string) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'));
        $user = (string) (getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
        $pass = getenv('DB_PASS');
        if ($pass === false) {
            $pass = (string) ($_ENV['DB_PASS'] ?? '');
        }
        $db = self::testDbName();

        try {
            $server = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $server->exec("DROP DATABASE IF EXISTS `{$db}`");
            $server->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $options = config('database')['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, $options);

            $schema = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
            $pdo->exec($schema);

            self::$pdo = $pdo;
        } catch (\Throwable $e) {
            self::$skipReason = 'MariaDB unavailable: ' . $e->getMessage();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            try {
                self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::testDbName() . '`');
            } catch (\Throwable) {
                // best effort
            }
        }
        self::$pdo = null;
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped(self::$skipReason ?: 'DB integration unavailable.');
        }

        // I Repository risolvono il PDO via app(PDO::class).
        $container = new Container();
        Container::setInstance($container);
        $container->instance(PDO::class, self::$pdo);

        // Alcuni service risolvono ModuleLoader (es. modules_count); registralo
        // come fa ModuleTestCase per evitare auto-wiring del costruttore.
        $moduleLoader = new ModuleLoader(BASE_PATH);
        $moduleLoader->loadConfig();
        $container->instance(ModuleLoader::class, $moduleLoader);

        if ($this->useTransaction) {
            self::$pdo->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if (self::$pdo !== null && self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        $_SESSION = [];
        $_POST    = [];
    }

    /**
     * Inserisce una riga e restituisce l'ID generato (helper per le fixture).
     *
     * @param array<string,mixed> $data
     */
    protected function insertRow(string $table, array $data): int
    {
        $cols = implode(', ', array_map(static fn ($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        $stmt = self::$pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));

        return (int) self::$pdo->lastInsertId();
    }
}
