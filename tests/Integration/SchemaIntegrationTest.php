<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Container;
use App\Repositories\MailLogRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Real-MariaDB integration tests.
 *
 * The rest of the suite runs on in-memory SQLite, which hides DB-specific bugs
 * (collation, LIMIT binding, NOW(), reserved-word quoting, …). This suite loads
 * the canonical `database/schema.sql` into a throwaway MariaDB database and runs
 * representative queries against it.
 *
 * It is OPT-IN: it skips unless RUN_DB_INTEGRATION=1, so the default
 * `vendor/bin/phpunit` (local + the SQLite CI job) is never affected nor does it
 * touch any MariaDB server. The dedicated CI MariaDB job enables it.
 *
 * Connection comes from DB_HOST/DB_PORT/DB_USER/DB_PASS; the database name is a
 * DEDICATED test DB (DB_TEST_NAME, default `favilla_ci_test`) — never DB_NAME —
 * so the dev database is never created, loaded or dropped.
 */
class SchemaIntegrationTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static string $skipReason = '';

    private static function testDbName(): string
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

            // Use the application's REAL PDO options so this is a faithful
            // reproduction of production — notably ATTR_EMULATE_PREPARES=false,
            // without which bound LIMIT/OFFSET integers are quoted as strings and
            // MariaDB rejects `LIMIT '20'`. (That divergence is exactly what an
            // integration suite must reproduce, not paper over.)
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

        // Repositories resolve PDO via app(PDO::class).
        $container = new Container();
        Container::setInstance($container);
        $container->instance(PDO::class, self::$pdo);
    }

    public function testCanonicalSchemaLoadsAllTables(): void
    {
        $tables = self::$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('users', $tables);
        $this->assertContains('sessions', $tables);
        $this->assertContains('mail_log', $tables);
        $this->assertContains('permissions', $tables);
        $this->assertGreaterThanOrEqual(
            50,
            count($tables),
            'the consolidated schema declares ~63 tables; it must load on MariaDB'
        );
    }

    public function testMailLogPaginationQueryRunsOnMariaDb(): void
    {
        // A representative non-trivial repository query (LEFT JOIN + whitelisted
        // ORDER BY + bound LIMIT/OFFSET). Running it on the real MariaDB schema
        // proves the SQL is portable (SQLite would not catch a MariaDB-only break).
        $repo = new MailLogRepository();

        $res = $repo->listPaginated(1, 20, ['sort' => 'subject', 'dir' => 'asc']);
        $this->assertSame(0, $res['total']);
        $this->assertSame([], $res['data']);

        // An out-of-whitelist sort must not be interpolated → still a valid query.
        $res2 = $repo->listPaginated(1, 20, ['sort' => 'id; DROP TABLE mail_log;--', 'dir' => 'nope']);
        $this->assertSame(0, $res2['total']);

        // The table must still exist — the injection was never executed.
        $still = self::$pdo->query('SHOW TABLES LIKE "mail_log"')->fetchAll();
        $this->assertCount(1, $still);
    }
}
