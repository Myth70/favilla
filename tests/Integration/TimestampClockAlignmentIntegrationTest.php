<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;

/**
 * Guards the alignment between the two clocks that write timestamps.
 *
 * Rows reach a timestamp column from PHP (BaseRepository::applyTimestamps, which
 * formats in APP_TIMEZONE) and from SQL NOW(), which runs in the session's zone.
 * When those zones differ the same column collects rows hours apart — the shape
 * this reproduces, since the official MariaDB image runs UTC while the app
 * defaults to Europe/Rome.
 *
 * The zone under test is deliberately one no CI or dev server runs in, so the
 * assertions fail on an unpinned session wherever the suite is executed rather
 * than only on a UTC server.
 */
class TimestampClockAlignmentIntegrationTest extends DatabaseIntegrationTestCase
{
    /** Never the host zone on a dev box or a CI runner. */
    private const PROBE_TIMEZONE = 'Asia/Tokyo';

    private const PROBE_OFFSET = '+09:00';

    protected bool $useTransaction = false;

    private string $originalTimezone = 'UTC';

    /** @var string|null */
    private $originalAppTimezone = null;

    protected function setUp(): void
    {
        parent::setUp();

        // This class moves the process clock; anything running after it must not
        // inherit the probe zone.
        $this->originalTimezone    = date_default_timezone_get();
        $this->originalAppTimezone = $_ENV['APP_TIMEZONE'] ?? null;
    }

    /**
     * Opens a connection under PROBE_TIMEZONE, mirroring what Application::boot()
     * does for the timezone before the container builds the PDO.
     */
    private function connectUnderProbeTimezone(): PDO
    {
        $_ENV['APP_TIMEZONE'] = self::PROBE_TIMEZONE;
        date_default_timezone_set(self::PROBE_TIMEZONE);
        config_flush();

        ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass] = self::connectionParams();
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, self::testDbName());

        return new PDO($dsn, $user, $pass, config('database')['options']);
    }

    protected function tearDown(): void
    {
        if ($this->originalAppTimezone === null) {
            unset($_ENV['APP_TIMEZONE']);
        } else {
            $_ENV['APP_TIMEZONE'] = $this->originalAppTimezone;
        }
        date_default_timezone_set($this->originalTimezone);
        config_flush();

        parent::tearDown();
    }

    public function testSessionTimezoneFollowsAppTimezone(): void
    {
        $pdo = $this->connectUnderProbeTimezone();

        $this->assertSame(
            self::PROBE_OFFSET,
            $pdo->query('SELECT @@session.time_zone')->fetchColumn(),
            'The session zone must be pinned to APP_TIMEZONE, not left at the server default.'
        );
    }

    public function testSqlNowAgreesWithPhpClock(): void
    {
        $pdo = $this->connectUnderProbeTimezone();

        $dbNow  = (string) $pdo->query('SELECT NOW()')->fetchColumn();
        $phpNow = date('Y-m-d H:i:s');

        $this->assertLessThanOrEqual(
            60,
            abs(strtotime($dbNow) - strtotime($phpNow)),
            "SQL NOW() returned {$dbNow} while PHP is at {$phpNow}: the two clocks disagree by more than "
            . 'elapsed time, so rows written by each land at different instants.'
        );
    }

    /**
     * The bug as it reaches a table: one row timestamped by PHP, the next by
     * NOW(), into the same column, moments apart.
     */
    public function testBothClocksWriteTheSameInstantIntoOneColumn(): void
    {
        $pdo = $this->connectUnderProbeTimezone();

        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS clock_probe');
        $pdo->exec('CREATE TEMPORARY TABLE clock_probe (id INT AUTO_INCREMENT PRIMARY KEY, ts TIMESTAMP NULL)');

        // How BaseRepository::applyTimestamps() writes.
        $pdo->prepare('INSERT INTO clock_probe (ts) VALUES (?)')->execute([date('Y-m-d H:i:s')]);
        // How the raw "SET updated_at = NOW()" statements write.
        $pdo->exec('INSERT INTO clock_probe (ts) VALUES (NOW())');

        $rows = $pdo->query('SELECT ts FROM clock_probe ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        $this->assertLessThanOrEqual(
            60,
            abs(strtotime((string) $rows[1]) - strtotime((string) $rows[0])),
            "PHP wrote {$rows[0]} and NOW() wrote {$rows[1]} into the same column."
        );
    }
}
