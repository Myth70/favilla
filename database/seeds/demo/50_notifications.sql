-- ============================================================================
-- Demo — Notifications (campanella in-app viva per admin e utenti)
-- Nessun link hardcoded (evita problemi di base path); icon/color ai default.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `notifications`
    (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_by`, `created_at`)
VALUES
    -- Admin (utente 1): 2 non lette + 2 lette
    (9001, 1, 'Attività in scadenza domani', 'Il rinnovo hosting aurora-studio.it scade domani alle 10:00.', 'warning', NULL, NULL, (NOW() - INTERVAL 2 HOUR)),
    (9002, 1, 'Nuovo commento sul blog', 'Isabella Ragonese ha commentato "Nuova procedura ferie".', 'info', NULL, NULL, (NOW() - INTERVAL 5 HOUR)),
    (9003, 1, 'Backup completato', 'Il backup notturno del database è stato completato correttamente (164 KB).', 'success', (NOW() - INTERVAL 20 HOUR), NULL, (NOW() - INTERVAL 22 HOUR)),
    (9004, 1, 'Documento in approvazione', 'Il documento "Procedura gestione ferie" attende la tua approvazione.', 'info', (NOW() - INTERVAL 1 DAY), NULL, (NOW() - INTERVAL 2 DAY)),

    -- Qualche utente del cast
    (9005, 3, 'Evento tra 30 minuti', 'Riunione settimanale Aurora alle 09:00 in Sala grande.', 'info', NULL, NULL, (NOW() - INTERVAL 1 HOUR)),
    (9006, 6, 'Ti è stata assegnata un''attività', 'Fix responsive header sito Rossetti — priorità alta.', 'warning', NULL, 3, (NOW() - INTERVAL 1 DAY)),
    (9007, 8, 'Commento approvato', 'Il tuo commento su "Risultati del trimestre" è stato pubblicato.', 'success', (NOW() - INTERVAL 3 DAY), NULL, (NOW() - INTERVAL 3 DAY));
