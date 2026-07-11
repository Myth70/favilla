<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Progetti\Repositories\ProgettiRepository;

/**
 * Copre getTimesheetTrend(), che usa costrutti data MySQL-only assenti in SQLite:
 *   - `WEEKDAY(...)` e `DATE_FORMAT(...)` (non esistono in SQLite → errore);
 *   - `DATE_SUB(CURDATE(), INTERVAL ? WEEK)` per la finestra temporale.
 *
 * Valida sia che la query giri sul motore reale sia che l'aggregazione per
 * settimana ISO (lunedì) sommi correttamente le ore.
 */
final class ProgettiTimesheetTrendIntegrationTest extends DatabaseIntegrationTestCase
{
    private function repo(): ProgettiRepository
    {
        return new ProgettiRepository();
    }

    private function seedProjectWithTask(): array
    {
        $userId = $this->insertRow('users', [
            'name'     => 'PM',
            'email'    => 'pm@example.test',
            'username' => 'pm',
            'password' => 'x',
        ]);
        $projectId = $this->insertRow('projects', [
            'name'          => 'Trend project',
            'owner_user_id' => $userId,
        ]);
        $taskId = $this->insertRow('project_tasks', [
            'project_id' => $projectId,
            'title'      => 'Trend task',
        ]);

        return [$userId, $projectId, $taskId];
    }

    private function logHours(int $projectId, int $taskId, int $userId, string $date, string $hours): void
    {
        $this->insertRow('project_timesheets', [
            'project_id' => $projectId,
            'task_id'    => $taskId,
            'user_id'    => $userId,
            'work_date'  => $date,
            'hours'      => $hours,
        ]);
    }

    public function testEmptyProjectReturnsNoTrend(): void
    {
        [, $projectId] = $this->seedProjectWithTask();

        // Nessun timesheet: la query (DATE_FORMAT/WEEKDAY/CURDATE) deve comunque
        // eseguire senza errori sul motore reale.
        $this->assertSame([], $this->repo()->getTimesheetTrend($projectId));
    }

    public function testHoursAreBucketedByIsoWeek(): void
    {
        [$userId, $projectId, $taskId] = $this->seedProjectWithTask();

        $lastMon = date('Y-m-d', strtotime('monday last week'));
        $twoMon  = date('Y-m-d', strtotime('monday last week -1 week'));

        // Stessa settimana (lunedì $lastMon): martedì + giovedì → sommate.
        $this->logHours($projectId, $taskId, $userId, date('Y-m-d', strtotime($lastMon . ' +1 day')), '2.00');
        $this->logHours($projectId, $taskId, $userId, date('Y-m-d', strtotime($lastMon . ' +3 day')), '3.00');
        // Settimana precedente: mercoledì → bucket separato.
        $this->logHours($projectId, $taskId, $userId, date('Y-m-d', strtotime($twoMon . ' +2 day')), '4.00');

        $trend = $this->repo()->getTimesheetTrend($projectId);

        $this->assertCount(2, $trend, 'due settimane ISO distinte');

        // Ordinamento ASC per week_start.
        $this->assertSame($twoMon, $trend[0]['week_start']);
        $this->assertSame(4.0, (float) $trend[0]['hours']);

        $this->assertSame($lastMon, $trend[1]['week_start']);
        $this->assertSame(5.0, (float) $trend[1]['hours'], 'le ore della stessa settimana vanno sommate');
    }
}
