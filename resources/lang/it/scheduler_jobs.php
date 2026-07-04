<?php

/**
 * Scheduler job names — Italian (canonical baseline for lang:check).
 *
 * Flat map keyed by the job COMMAND (the stable, known catalog — job slugs are
 * admin-chosen and not enumerable here). Resolved at render via
 * t_line('scheduler_jobs', $command, $storedName); see
 * App\Modules\Scheduler\Services\SchedulerService::localizeJobName().
 *
 * NOTE: in Italian the job label is rendered from the DB-stored name
 * (admin-canonical), so at runtime this `it` file is intentionally bypassed —
 * it exists only as the lang:check baseline and as the fallback source for the
 * other locales. To force a per-job override in a specific locale, add a key
 * by job SLUG (slugs contain dots; commands contain colons — no collision).
 */
return [
    'cleanup'                     => 'Pulizia dati obsoleti',
    'notifications:process-queue' => 'Elaborazione coda notifiche',
    'calendar:send-reminders'     => 'Invio promemoria calendario',
    'contacts:process-reminders'  => 'Elaborazione ricorrenze contatti',
    'tasks:send-due-reminders'    => 'Invio promemoria scadenze attività',
    'backup:run'                  => 'Backup database',
    'logs:rotate'                 => 'Rotazione log',
    'retention:run'               => 'Applicazione data retention',
    'reports:cleanup'             => 'Pulizia report scaduti',
    'session:gc'                  => 'Pulizia sessioni scadute',
    'ratelimit:cleanup'           => 'Pulizia rate limit',
];
