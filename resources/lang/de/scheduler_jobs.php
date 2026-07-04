<?php

/**
 * Scheduler job names — German. Flat, keyed by job command.
 * See resources/lang/it/scheduler_jobs.php for the resolution contract.
 */
return [
    'cleanup'                     => 'Bereinigung veralteter Daten',
    'notifications:process-queue' => 'Benachrichtigungswarteschlange verarbeiten',
    'calendar:send-reminders'     => 'Kalender-Erinnerungen senden',
    'contacts:process-reminders'  => 'Kontakt-Wiederholungen verarbeiten',
    'tasks:send-due-reminders'    => 'Fälligkeits-Erinnerungen für Aufgaben senden',
    'backup:run'                  => 'Datenbank-Backup',
    'logs:rotate'                 => 'Log-Rotation',
    'retention:run'               => 'Datenaufbewahrung anwenden',
    'reports:cleanup'             => 'Abgelaufene Berichte bereinigen',
    'session:gc'                  => 'Bereinigung abgelaufener Sitzungen',
    'ratelimit:cleanup'           => 'Bereinigung der Ratenbegrenzung',
];
