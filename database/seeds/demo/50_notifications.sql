-- ============================================================================
-- Demo — Notifications (campanella in-app viva per admin e per il cast)
-- Nessun link hardcoded (evita problemi di base path); icon/color ai default.
-- type ∈ info/success/warning/danger. Mix lette/non lette.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `notifications`
    (`id`, `user_id`, `title`, `body`, `type`, `read_at`, `created_by`, `created_at`)
VALUES
    -- Admin (1): 2 non lette + 2 lette
    (9001, 1, 'Attività in scadenza domani', 'Il rinnovo hosting aurora-studio.it scade domani alle 10:00.', 'warning', NULL, NULL, (NOW() - INTERVAL 2 HOUR)),
    (9002, 1, 'Documento in approvazione', 'Il documento "Procedura gestione ferie" attende la tua approvazione.', 'info', NULL, NULL, (NOW() - INTERVAL 5 HOUR)),
    (9003, 1, 'Backup completato', 'Il backup notturno del database è stato completato correttamente.', 'success', (NOW() - INTERVAL 20 HOUR), NULL, (NOW() - INTERVAL 22 HOUR)),
    (9004, 1, 'Nuovo commento sul blog', 'Marco Bianchi ha commentato "New leave & time-off policy".', 'info', (NOW() - INTERVAL 1 DAY), NULL, (NOW() - INTERVAL 2 DAY)),

    -- Giulia (3) — project lead
    (9005, 3, 'Evento tra 30 minuti', 'Weekly team sync alle 09:00 su Google Meet.', 'info', NULL, NULL, (NOW() - INTERVAL 1 HOUR)),
    (9006, 3, 'Task assegnata a un membro', 'Import catalogo da CSV assegnato a Marco Bianchi.', 'info', (NOW() - INTERVAL 2 DAY), 1, (NOW() - INTERVAL 3 DAY)),

    -- James (4) — account
    (9007, 4, 'Nuovo cliente confermato', 'Nordlys Interiors ha firmato: kickoff a novembre.', 'success', NULL, 1, (NOW() - INTERVAL 6 HOUR)),

    -- Anna (5) — ops
    (9008, 5, 'Documento in controllo', 'L''offerta e-commerce Rossetti è passata a te per il controllo.', 'info', NULL, 3, (NOW() - INTERVAL 4 HOUR)),

    -- Marco (6) — backend
    (9009, 6, 'Ti è stata assegnata un''attività', 'Fix responsive header sito Rossetti — priorità alta.', 'warning', NULL, 8, (NOW() - INTERVAL 1 DAY)),
    (9010, 6, 'Menzione in chat', 'Sei stato menzionato nel gruppo "Aurora — General".', 'info', (NOW() - INTERVAL 3 HOUR), NULL, (NOW() - INTERVAL 5 HOUR)),

    -- Emily (7) — content
    (9011, 7, 'Commento approvato', 'Il tuo commento su "Aurora Studio intranet is live" è stato pubblicato.', 'success', (NOW() - INTERVAL 3 DAY), NULL, (NOW() - INTERVAL 3 DAY)),

    -- Julien (8) — frontend
    (9012, 8, 'Review richiesta', 'Camille ti ha chiesto una review sul prototipo checkout.', 'info', NULL, 9, (NOW() - INTERVAL 8 HOUR)),

    -- Camille (9) — design
    (9013, 9, 'Nuovo file condiviso', 'Lucía ha condiviso "Moodboard campagna lancio.png".', 'info', (NOW() - INTERVAL 1 DAY), 12, (NOW() - INTERVAL 1 DAY)),

    -- Lukas (10) — devops
    (9014, 10, 'Manutenzione pianificata', 'Finestra di manutenzione server prevista in serata.', 'warning', NULL, NULL, (NOW() - INTERVAL 10 HOUR)),

    -- Diego (11) — fullstack
    (9015, 11, 'Task completata', 'Report risultati campagna Bottega Verde segnato come fatto.', 'success', (NOW() - INTERVAL 6 DAY), NULL, (NOW() - INTERVAL 6 DAY)),

    -- Lucía (12) — social
    (9016, 12, 'Menzione in chat', 'Anna ti ha menzionata a proposito dei risultati della campagna.', 'info', NULL, 5, (NOW() - INTERVAL 2 HOUR));
