<?php

namespace Tests\Unit;

use App\Repositories\MailLogRepository;
use Tests\ModuleTestCase;

/**
 * ORDER BY whitelist invariant (SECURITY.md): user-driven sort columns/direction
 * must never be interpolated raw into SQL. MailLogRepository is the representative
 * surface (it exposes ?sort/?dir). An out-of-whitelist value must be silently
 * normalised, never executed.
 */
class OrderByWhitelistTest extends ModuleTestCase
{
    private MailLogRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate(
            'CREATE TABLE mail_log (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                to_email    TEXT,
                subject     TEXT,
                status      TEXT,
                created_by  INTEGER,
                created_at  TEXT
            )'
        );
        $this->createUsersTable();

        $this->insertRow('mail_log', [
            'to_email' => 'a@example.test', 'subject' => 'Alpha',
            'status' => 'sent', 'created_by' => null, 'created_at' => '2026-01-01 00:00:00',
        ]);
        $this->insertRow('mail_log', [
            'to_email' => 'b@example.test', 'subject' => 'Bravo',
            'status' => 'failed', 'created_by' => null, 'created_at' => '2026-01-02 00:00:00',
        ]);

        $this->repo = new MailLogRepository();
    }

    public function testWhitelistedSortIsApplied(): void
    {
        $res = $this->repo->listPaginated(1, 20, ['sort' => 'subject', 'dir' => 'asc']);

        $this->assertCount(2, $res['data']);
        $this->assertSame('Alpha', $res['data'][0]['subject']);
        $this->assertSame('Bravo', $res['data'][1]['subject']);
    }

    public function testMaliciousSortColumnIsIgnored(): void
    {
        // If the sort were interpolated, this would be a SQL injection. It must be
        // rejected by the whitelist → query runs on the default column, table intact.
        $res = $this->repo->listPaginated(1, 20, [
            'sort' => 'id; DROP TABLE mail_log;--',
            'dir'  => 'asc',
        ]);

        $this->assertCount(2, $res['data']);
        $survived = (int) $this->pdo->query('SELECT COUNT(*) FROM mail_log')->fetchColumn();
        $this->assertSame(2, $survived, 'table must still exist — the injection was not executed');
    }

    public function testMaliciousDirectionIsIgnored(): void
    {
        $res = $this->repo->listPaginated(1, 20, [
            'sort' => 'created_at',
            'dir'  => 'ASC; DROP TABLE mail_log',
        ]);

        $this->assertCount(2, $res['data']);
    }
}
