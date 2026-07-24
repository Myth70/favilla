-- ============================================================================
-- Demo — Sistema (HealthCheck, Backup, Scheduler) con dati minimi.
-- Rende vivi i widget/pannelli di sistema (backup.last, healthcheck.status,
-- scheduler.jobs). I job scheduler demo sono DISABILITATI (enabled=0) per non
-- essere eseguiti dal loop. Sezione core. ID fissi da 9001 + INSERT IGNORE.
-- ============================================================================

SET NAMES utf8mb4;

-- Health check: uno storico verde + uno con un warning
INSERT IGNORE INTO `healthcheck_runs` (`id`, `total_ok`, `total_warn`, `total_fail`, `data`, `created_by`, `created_at`) VALUES
    (9001, 8, 0, 0, '{"checks":[{"name":"Database","status":"ok"},{"name":"Storage","status":"ok"},{"name":"Mail","status":"ok"},{"name":"Scheduler","status":"ok"},{"name":"PHP extensions","status":"ok"},{"name":"Permissions","status":"ok"},{"name":"Disk space","status":"ok"},{"name":"Sessions","status":"ok"}]}', 1, (NOW() - INTERVAL 1 DAY)),
    (9002, 7, 1, 0, '{"checks":[{"name":"Database","status":"ok"},{"name":"Storage","status":"ok"},{"name":"Mail","status":"warn","message":"SMTP non configurato: driver in modalità log"},{"name":"Scheduler","status":"ok"},{"name":"PHP extensions","status":"ok"},{"name":"Permissions","status":"ok"},{"name":"Disk space","status":"ok"},{"name":"Sessions","status":"ok"}]}', 1, (NOW() - INTERVAL 8 DAY));

-- Storico backup (coerente con la notifica "Backup completato")
INSERT IGNORE INTO `backup_history` (`id`, `filename`, `format`, `size_bytes`, `table_count`, `databases_json`, `files_json`, `created_by`, `created_at`) VALUES
    (9001, 'favilla-backup-auto.sql.gz', 'sqlgz', 262144, 100, NULL, NULL, 1, (NOW() - INTERVAL 22 HOUR)),
    (9002, 'favilla-backup-auto.sql.gz', 'sqlgz', 258048, 100, NULL, NULL, 1, (NOW() - INTERVAL 2 DAY)),
    (9003, 'favilla-backup-manual.sql.gz', 'sqlgz', 270336, 100, NULL, NULL, 1, (NOW() - INTERVAL 12 DAY));

-- Job scheduler demo (DISABILITATI: enabled=0). slug demo-* per non collidere.
INSERT IGNORE INTO `scheduler_jobs`
    (`id`, `slug`, `name`, `command`, `args_json`, `interval_minutes`, `enabled`, `last_run_at`, `last_status`, `last_output`, `last_duration_ms`)
VALUES
    (9001, 'demo-digest-notturno', 'Digest notturno notifiche', 'notifications:digest', NULL, 1440, 0, (NOW() - INTERVAL 20 HOUR), 'success', 'Digest inviato a 10 utenti.', 1840),
    (9002, 'demo-pulizia-log',     'Pulizia log applicativi',   'logs:cleanup',          NULL, 10080, 0, (NOW() - INTERVAL 3 DAY),  'success', 'Rimossi 0 file oltre la retention.', 420);

-- Esecuzioni recenti (storico)
INSERT IGNORE INTO `scheduler_log` (`id`, `job_slug`, `started_at`, `finished_at`, `status`, `output`, `duration_ms`) VALUES
    (9001, 'demo-digest-notturno', (NOW() - INTERVAL 20 HOUR), (NOW() - INTERVAL 20 HOUR) + INTERVAL 2 SECOND, 'success', 'Digest inviato a 10 utenti.', 1840),
    (9002, 'demo-digest-notturno', (NOW() - INTERVAL 44 HOUR), (NOW() - INTERVAL 44 HOUR) + INTERVAL 2 SECOND, 'success', 'Digest inviato a 10 utenti.', 1760),
    (9003, 'demo-pulizia-log',     (NOW() - INTERVAL 3 DAY),   (NOW() - INTERVAL 3 DAY) + INTERVAL 1 SECOND,   'success', 'Rimossi 0 file oltre la retention.', 420);
