<?php

/**
 * Scheduler job names — Spanish. Flat, keyed by job command.
 * See resources/lang/it/scheduler_jobs.php for the resolution contract.
 */
return [
    'cleanup'                     => 'Limpieza de datos obsoletos',
    'notifications:process-queue' => 'Procesar cola de notificaciones',
    'calendar:send-reminders'     => 'Enviar recordatorios de calendario',
    'contacts:process-reminders'  => 'Procesar recurrencias de contactos',
    'tasks:send-due-reminders'    => 'Enviar recordatorios de vencimiento de tareas',
    'backup:run'                  => 'Copia de seguridad de la base de datos',
    'logs:rotate'                 => 'Rotación de registros',
    'retention:run'               => 'Aplicar retención de datos',
    'reports:cleanup'             => 'Limpiar informes caducados',
    'session:gc'                  => 'Limpieza de sesiones caducadas',
    'ratelimit:cleanup'           => 'Limpieza de límite de tasa',
];
