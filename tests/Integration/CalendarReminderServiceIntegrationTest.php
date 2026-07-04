<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Calendar\Services\CalendarReminderService;

/**
 * sendDueReminders() filtra gli eventi con DATE_SUB(start, INTERVAL minutes MINUTE)
 * <= NOW() — sintassi MySQL non portabile su SQLite: verificata su MariaDB reale.
 */
class CalendarReminderServiceIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testReturnsZeroWhenNoEventsDue(): void
    {
        // DB vuoto → la query (MySQL-only) deve eseguire senza errori e dare 0.
        $this->assertSame(0, (new CalendarReminderService())->sendDueReminders());
    }

    public function testEventOutsideReminderWindowIsNotSent(): void
    {
        $userId = $this->insertRow('users', [
            'name' => 'U', 'email' => 'cal@x.test', 'username' => 'cal_u', 'password' => 'x',
        ]);
        // Evento tra 10 giorni con reminder di 30 minuti → finestra non ancora raggiunta.
        $this->insertRow('calendar_events', [
            'title' => 'Lontano',
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'end_datetime' => date('Y-m-d H:i:s', strtotime('+10 days +1 hour')),
            'visibility' => 'personal',
            'reminder_minutes' => 30,
            'created_by' => $userId,
        ]);

        $this->assertSame(0, (new CalendarReminderService())->sendDueReminders());
    }
}
