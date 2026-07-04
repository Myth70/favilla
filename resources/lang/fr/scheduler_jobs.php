<?php

/**
 * Scheduler job names — French. Flat, keyed by job command.
 * See resources/lang/it/scheduler_jobs.php for the resolution contract.
 */
return [
    'cleanup'                     => 'Nettoyage des données obsolètes',
    'notifications:process-queue' => 'Traitement de la file de notifications',
    'calendar:send-reminders'     => 'Envoi des rappels du calendrier',
    'contacts:process-reminders'  => 'Traitement des récurrences de contacts',
    'tasks:send-due-reminders'    => 'Envoi des rappels d’échéance des tâches',
    'backup:run'                  => 'Sauvegarde de la base de données',
    'logs:rotate'                 => 'Rotation des journaux',
    'retention:run'               => 'Application de la rétention des données',
    'reports:cleanup'             => 'Nettoyage des rapports expirés',
    'session:gc'                  => 'Nettoyage des sessions expirées',
    'ratelimit:cleanup'           => 'Nettoyage de la limitation de débit',
];
