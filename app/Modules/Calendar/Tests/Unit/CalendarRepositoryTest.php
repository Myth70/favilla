<?php

namespace App\Modules\Calendar\Tests\Unit;

use App\Modules\Calendar\Repositories\CalendarRepository;
use Tests\ModuleTestCase;

class CalendarRepositoryTest extends ModuleTestCase
{
    private CalendarRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );
            CREATE TABLE calendar_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NULL,
                start_datetime TEXT NOT NULL,
                end_datetime TEXT NULL,
                all_day INTEGER NOT NULL DEFAULT 0,
                color TEXT NULL,
                category TEXT NULL,
                location TEXT NULL,
                visibility TEXT NOT NULL,
                visible_to_role INTEGER NULL,
                reminder_minutes INTEGER NULL,
                recurrence_rule TEXT NULL,
                recurrence_end TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
        ');

        $this->repo = new CalendarRepository();
    }

    public function testFindByDateRangeRespectsVisibilityAndRole(): void
    {
        $alice = $this->insertRow('users', ['name' => 'Alice']);
        $bob = $this->insertRow('users', ['name' => 'Bob']);

        $this->insertRow('calendar_events', [
            'title' => 'Mio evento',
            'start_datetime' => '2026-04-25 10:00:00',
            'end_datetime' => '2026-04-25 11:00:00',
            'visibility' => 'personal',
            'visible_to_role' => null,
            'created_by' => $alice,
            'deleted_at' => null,
        ]);

        $this->insertRow('calendar_events', [
            'title' => 'Pubblico',
            'start_datetime' => '2026-04-25 12:00:00',
            'end_datetime' => '2026-04-25 13:00:00',
            'visibility' => 'public',
            'visible_to_role' => null,
            'created_by' => $bob,
            'deleted_at' => null,
        ]);

        $this->insertRow('calendar_events', [
            'title' => 'Ruolo 3',
            'start_datetime' => '2026-04-25 14:00:00',
            'end_datetime' => '2026-04-25 15:00:00',
            'visibility' => 'role',
            'visible_to_role' => 3,
            'created_by' => $bob,
            'deleted_at' => null,
        ]);

        $this->insertRow('calendar_events', [
            'title' => 'Ricorrente master fuori range',
            'start_datetime' => '2026-01-01 09:00:00',
            'end_datetime' => '2026-01-01 10:00:00',
            'visibility' => 'public',
            'visible_to_role' => null,
            'recurrence_rule' => 'FREQ=WEEKLY;COUNT=10',
            'created_by' => $bob,
            'deleted_at' => null,
        ]);

        $rows = $this->repo->findByDateRange('2026-04-25 00:00:00', '2026-04-26 00:00:00', $alice, [3]);
        $titles = array_column($rows, 'title');

        $this->assertContains('Mio evento', $titles);
        $this->assertContains('Pubblico', $titles);
        $this->assertContains('Ruolo 3', $titles);
        $this->assertContains('Ricorrente master fuori range', $titles);
    }

    public function testFindUpcomingAndHeroStats(): void
    {
        $owner = $this->insertRow('users', ['name' => 'Owner']);
        $other = $this->insertRow('users', ['name' => 'Other']);

        $this->insertRow('calendar_events', [
            'title' => 'Owned all day',
            'start_datetime' => '2099-01-10 00:00:00',
            'end_datetime' => '2099-01-10 23:59:59',
            'all_day' => 1,
            'visibility' => 'personal',
            'visible_to_role' => null,
            'created_by' => $owner,
            'deleted_at' => null,
        ]);

        $this->insertRow('calendar_events', [
            'title' => 'Shared public',
            'start_datetime' => '2099-01-11 10:00:00',
            'end_datetime' => '2099-01-11 11:00:00',
            'all_day' => 0,
            'visibility' => 'public',
            'visible_to_role' => null,
            'created_by' => $other,
            'deleted_at' => null,
        ]);

        $upcoming = $this->repo->findUpcoming($owner, [], 5);
        $this->assertCount(2, $upcoming);

        $stats = $this->repo->getHeroStats($owner, []);
        $this->assertSame(2, $stats['visible_total']);
        $this->assertSame(1, $stats['owned']);
        $this->assertSame(1, $stats['shared']);
        $this->assertSame(1, $stats['all_day']);
    }

    public function testFindWithCreatorReturnsNullForDeletedRows(): void
    {
        $creator = $this->insertRow('users', ['name' => 'Mario']);
        $eventId = $this->insertRow('calendar_events', [
            'title' => 'Evento',
            'start_datetime' => '2026-05-01 09:00:00',
            'end_datetime' => '2026-05-01 10:00:00',
            'visibility' => 'public',
            'created_by' => $creator,
            'deleted_at' => null,
        ]);

        $row = $this->repo->findWithCreator($eventId);
        $this->assertNotNull($row);
        $this->assertSame('Mario', $row['creator_name']);

        $this->pdo->prepare('UPDATE calendar_events SET deleted_at = ? WHERE id = ?')
            ->execute(['2026-05-02 00:00:00', $eventId]);

        $this->assertNull($this->repo->findWithCreator($eventId));
    }
}
