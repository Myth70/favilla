<?php

namespace App\Modules\Calendar\Tests\Unit;

use App\Core\Container;
use App\Modules\Calendar\Services\CalendarReminderService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * sendDueReminders() usa DATE_SUB(...INTERVAL...) (MySQL-only) → la verifica sul
 * dialetto reale è in tests/Integration/CalendarReminderServiceIntegrationTest.
 * Qui si verifica il flusso PHP con un PDO mockato: nessun evento dovuto → 0 invii.
 */
class CalendarReminderServiceTest extends TestCase
{
    public function testReturnsZeroWhenNoEventsAreDue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $container = new Container();
        Container::setInstance($container);
        $container->instance(PDO::class, $pdo);

        $this->assertSame(0, (new CalendarReminderService())->sendDueReminders());
    }
}
