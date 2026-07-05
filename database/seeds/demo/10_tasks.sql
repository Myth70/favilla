-- ============================================================================
-- Demo — Tasks (attività personali/operative, kanban)
-- Cast: utente 1 = admin dell'installazione; 3-12 = utenti test Aurora Studio.
-- ID fissi da 9001 + INSERT IGNORE: ricaricabile, nessuna collisione con dati reali.
-- Date relative a NOW()/CURDATE(): la demo non invecchia.
-- ============================================================================

SET NAMES utf8mb4;

-- Tag (unique per name+user)
INSERT IGNORE INTO `task_tags` (`id`, `name`, `color`, `user_id`) VALUES
    (9001, 'cliente',   '#0d6efd', 1),
    (9002, 'urgente',   '#dc3545', 1),
    (9003, 'interno',   '#198754', 1),
    (9004, 'cliente',   '#0d6efd', 3),
    (9005, 'sviluppo',  '#6f42c1', 6);

INSERT IGNORE INTO `tasks`
    (`id`, `title`, `description`, `status`, `priority`, `due_date`, `due_time`, `position`, `completed_at`, `user_id`, `created_at`)
VALUES
    -- Admin (utente 1): board vivo su tutte le colonne
    (9001, 'Rivedere offerta e-commerce Rossetti', 'Controllare margini e tempi prima dell''invio. Confrontare con il preventivo di marzo.', 'in_progress', 'high',   (CURDATE() + INTERVAL 2 DAY), '15:00:00', 1, NULL, 1, (NOW() - INTERVAL 6 DAY)),
    (9002, 'Approvare ferie di agosto',            'Piano ferie del team: verificare coperture sui progetti attivi.',                    'todo',        'medium', (CURDATE() + INTERVAL 5 DAY), NULL,       2, NULL, 1, (NOW() - INTERVAL 3 DAY)),
    (9003, 'Rinnovo hosting aurora-studio.it',     'Il certificato scade a fine mese. Valutare passaggio al piano superiore.',           'todo',        'urgent', (CURDATE() + INTERVAL 1 DAY), '10:00:00', 3, NULL, 1, (NOW() - INTERVAL 8 DAY)),
    (9004, 'Preparare riunione commerciale',       'Slide con pipeline Q3 e stato trattative.',                                          'review',      'medium', (CURDATE() + INTERVAL 4 DAY), NULL,       1, NULL, 1, (NOW() - INTERVAL 2 DAY)),
    (9005, 'Backup trimestrale documentazione',    NULL,                                                                                 'done',        'low',    (CURDATE() - INTERVAL 3 DAY), NULL,       1, (NOW() - INTERVAL 3 DAY), 1, (NOW() - INTERVAL 12 DAY)),
    (9006, 'Valutare candidature sviluppatore',    'Tre CV in shortlist, fissare i colloqui.',                                           'backlog',     'medium', NULL,                          NULL,       1, NULL, 1, (NOW() - INTERVAL 1 DAY)),

    -- Luca Marinelli (manager, 3)
    (9007, 'Pianificare sprint sito Bottega Verde', 'Aggiornare il backlog con le richieste emerse in call.',                            'in_progress', 'high',   (CURDATE() + INTERVAL 3 DAY), NULL,       1, NULL, 3, (NOW() - INTERVAL 4 DAY)),
    (9008, 'Verifica fatture fornitori giugno',     NULL,                                                                                'todo',        'medium', (CURDATE() + INTERVAL 7 DAY), NULL,       2, NULL, 3, (NOW() - INTERVAL 2 DAY)),

    -- Michele Morrone (6)
    (9009, 'Fix responsive header sito Rossetti',   'Su mobile il menu copre il logo sotto i 380px.',                                    'in_progress', 'high',   (CURDATE() + INTERVAL 1 DAY), NULL,       1, NULL, 6, (NOW() - INTERVAL 1 DAY)),
    (9010, 'Aggiornare librerie progetto gestionale', 'npm audit segnala 2 vulnerabilità moderate.',                                     'todo',        'medium', (CURDATE() + INTERVAL 6 DAY), NULL,       2, NULL, 6, (NOW() - INTERVAL 5 DAY)),

    -- Isabella Ragonese (8)
    (9011, 'Bozza post social lancio campagna',     'Tre varianti per il cliente, tono informale.',                                      'review',      'medium', (CURDATE() + INTERVAL 2 DAY), NULL,       1, NULL, 8, (NOW() - INTERVAL 3 DAY)),
    (9012, 'Calendario editoriale luglio',          NULL,                                                                                'done',        'medium', (CURDATE() - INTERVAL 2 DAY), NULL,       1, (NOW() - INTERVAL 2 DAY), 8, (NOW() - INTERVAL 10 DAY)),

    -- Serena Rossi (11)
    (9013, 'Test accessibilità form contatti',      'WCAG AA: focus, label, contrasto.',                                                 'todo',        'low',    (CURDATE() + INTERVAL 9 DAY), NULL,       1, NULL, 11, (NOW() - INTERVAL 1 DAY)),
    (9014, 'Report ore progetto gestionale',        NULL,                                                                                'done',        'medium', (CURDATE() - INTERVAL 1 DAY), NULL,       1, (NOW() - INTERVAL 1 DAY), 11, (NOW() - INTERVAL 7 DAY)),
    (9015, 'Preparare demo interna HTMX',           'Sessione formazione venerdì: esempi presi dal gestionale.',                         'backlog',     'low',    NULL,                          NULL,       2, NULL, 11, (NOW() - INTERVAL 2 DAY));

-- Checklist sull'offerta e-commerce (task 9001) e sul fix responsive (9009)
INSERT IGNORE INTO `task_checklist` (`id`, `task_id`, `text`, `is_done`, `position`) VALUES
    (9001, 9001, 'Verificare listino aggiornato 2026', 1, 1),
    (9002, 9001, 'Ricalcolare giornate sviluppo',      1, 2),
    (9003, 9001, 'Far validare a Luca',                0, 3),
    (9004, 9009, 'Riprodurre su device reale',         1, 1),
    (9005, 9009, 'Fix CSS + test cross-browser',       0, 2);

INSERT IGNORE INTO `task_tag_map` (`task_id`, `tag_id`) VALUES
    (9001, 9001), (9001, 9002),
    (9003, 9002), (9003, 9003),
    (9004, 9003),
    (9007, 9004),
    (9009, 9005), (9010, 9005);
