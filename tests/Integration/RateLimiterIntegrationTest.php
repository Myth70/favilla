<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Security\RateLimiter;

/**
 * RateLimiter è interamente SQL-bound: usa DATE_SUB(NOW(), INTERVAL ? MINUTE),
 * sintassi MySQL che SQLite non sa nemmeno parsare. Va quindi verificato sul
 * dialetto reale (MariaDB), non a unit con SQLite.
 *
 * Default applicati (config non caricata nei test): max 5 tentativi / 15 minuti.
 */
class RateLimiterIntegrationTest extends DatabaseIntegrationTestCase
{
    private function recordFailure(string $email, string $ip, string $whenSql = 'NOW()'): void
    {
        // created_at esplicito per testare il confine della finestra temporale.
        self::$pdo->prepare(
            "INSERT INTO login_attempts (email, ip_address, success, created_at) VALUES (?, ?, 0, {$whenSql})"
        )->execute([$email, $ip]);
    }

    public function testIpBucketLocksAfterMaxAttempts(): void
    {
        $rl = new RateLimiter();
        for ($i = 0; $i < 5; $i++) {
            $this->recordFailure('a@example.test', '10.0.0.1');
        }

        $this->assertTrue($rl->isLimited('10.0.0.1'), 'Il bucket IP deve bloccare dopo 5 fallimenti');
        $this->assertFalse($rl->isLimited('10.0.0.2'), 'Un IP diverso non deve essere bloccato dal bucket IP');
    }

    public function testAccountBucketBlocksIpRotationEvasion(): void
    {
        $rl = new RateLimiter();
        // Stesso account, 5 IP diversi: il bucket per-account deve scattare.
        for ($i = 1; $i <= 5; $i++) {
            $this->recordFailure('victim@example.test', '10.0.1.' . $i);
        }

        $this->assertTrue(
            $rl->isLimited('10.0.99.99', 'victim@example.test'),
            'Il bucket per-account deve chiudere la rotazione di IP'
        );
    }

    public function testRemainingAttemptsIsMostRestrictiveBucket(): void
    {
        $rl = new RateLimiter();
        // IP: 1 fallimento (restano 4). Account: 3 fallimenti (restano 2).
        $this->recordFailure('user@example.test', '10.0.2.1');
        $this->recordFailure('user@example.test', '10.0.2.2');
        $this->recordFailure('user@example.test', '10.0.2.3');

        $remaining = $rl->remainingAttempts('10.0.2.1', 'user@example.test');
        $this->assertSame(2, $remaining, 'remainingAttempts deve essere il minimo tra bucket IP e account');
    }

    public function testAttemptsOutsideWindowAreNotCounted(): void
    {
        $rl = new RateLimiter();
        // 5 fallimenti ma vecchi (16 min fa, finestra = 15 min) → non contano.
        for ($i = 0; $i < 5; $i++) {
            $this->recordFailure('old@example.test', '10.0.3.1', 'DATE_SUB(NOW(), INTERVAL 16 MINUTE)');
        }

        $this->assertFalse($rl->isLimited('10.0.3.1'), 'Tentativi fuori finestra non devono bloccare');
    }

    public function testRecentAttemptsInsideWindowAreCounted(): void
    {
        $rl = new RateLimiter();
        for ($i = 0; $i < 5; $i++) {
            $this->recordFailure('recent@example.test', '10.0.4.1', 'DATE_SUB(NOW(), INTERVAL 14 MINUTE)');
        }

        $this->assertTrue($rl->isLimited('10.0.4.1'), 'Tentativi dentro finestra devono bloccare');
    }

    public function testSuccessfulAttemptsDoNotCount(): void
    {
        $rl = new RateLimiter();
        $rl->record('clean@example.test', '10.0.5.1', true);
        $rl->record('clean@example.test', '10.0.5.1', true);

        $this->assertSame(5, $rl->remainingAttempts('10.0.5.1', 'clean@example.test'));
        $this->assertFalse($rl->isLimited('10.0.5.1', 'clean@example.test'));
    }
}
