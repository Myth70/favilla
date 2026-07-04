<?php

/**
 * Scheduler job names — English. Flat, keyed by job command.
 * See resources/lang/it/scheduler_jobs.php for the resolution contract.
 */
return [
    'cleanup'                     => 'Stale data cleanup',
    'notifications:process-queue' => 'Process notification queue',
    'calendar:send-reminders'     => 'Send calendar reminders',
    'contacts:process-reminders'  => 'Process contact recurrences',
    'tasks:send-due-reminders'    => 'Send task due reminders',
    'backup:run'                  => 'Database backup',
    'logs:rotate'                 => 'Log rotation',
    'retention:run'               => 'Apply data retention',
    'reports:cleanup'             => 'Clean up expired reports',
    'session:gc'                  => 'Expired session cleanup',
    'ratelimit:cleanup'           => 'Rate limit cleanup',
];
