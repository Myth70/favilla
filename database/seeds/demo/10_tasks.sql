-- ============================================================================
-- Demo — Tasks (attività personali/operative, kanban)
-- Cast: utente 1 = admin/founder; 3-12 = team Aurora Studio (vedi NARRATIVE.md).
-- ID fissi da 9001 + INSERT IGNORE. Date: mix CURDATE()±INTERVAL (attività recente)
-- e CONCAT(YEAR(CURDATE()),'-MM-DD') (copertura sull'anno).
-- ============================================================================

SET NAMES utf8mb4;

-- Tag (unique per name+user)
INSERT IGNORE INTO `task_tags` (`id`, `name`, `color`, `user_id`) VALUES
    (9001, 'cliente',   '#0d6efd', 1),
    (9002, 'urgente',   '#dc3545', 1),
    (9003, 'interno',   '#198754', 1),
    (9004, 'rossetti',  '#0d6efd', 3),
    (9005, 'sviluppo',  '#6f42c1', 6),
    (9006, 'design',    '#e83e8c', 9),
    (9007, 'infra',     '#0dcaf0', 10),
    (9008, 'social',    '#ffc107', 12);

INSERT IGNORE INTO `tasks`
    (`id`, `title`, `description`, `status`, `priority`, `due_date`, `due_time`, `position`, `completed_at`, `user_id`, `created_at`)
VALUES
    -- Admin / founder (1): board vivo su tutte le colonne
    (9001, 'Rivedere offerta e-commerce Rossetti', 'Controllare margini e tempi prima dell''invio. Confronto col preventivo iniziale.', 'in_progress', 'high',   (CURDATE() + INTERVAL 2 DAY), '15:00:00', 1, NULL, 1, (NOW() - INTERVAL 6 DAY)),
    (9002, 'Approvare piano ferie estive',         'Verificare coperture sui progetti attivi prima di firmare.',                        'todo',        'medium', (CURDATE() + INTERVAL 5 DAY), NULL,       2, NULL, 1, (NOW() - INTERVAL 3 DAY)),
    (9003, 'Rinnovo hosting aurora-studio.it',     'Il certificato scade a breve. Valutare il passaggio al piano superiore.',           'todo',        'urgent', (CURDATE() + INTERVAL 1 DAY), '10:00:00', 3, NULL, 1, (NOW() - INTERVAL 8 DAY)),
    (9004, 'Preparare kickoff Nordlys',            'Agenda, obiettivi e team per il nuovo cliente tedesco.',                            'review',      'medium', (CURDATE() + INTERVAL 4 DAY), NULL,       1, NULL, 1, (NOW() - INTERVAL 2 DAY)),
    (9005, 'Backup trimestrale documentazione',    NULL,                                                                                'done',        'low',    (CURDATE() - INTERVAL 3 DAY), NULL,       1, (NOW() - INTERVAL 3 DAY), 1, (NOW() - INTERVAL 12 DAY)),
    (9006, 'Valutare candidature sviluppatore',    'Tre CV in shortlist, fissare i colloqui.',                                          'backlog',     'medium', NULL,                          NULL,       1, NULL, 1, (NOW() - INTERVAL 1 DAY)),

    -- Giulia Ferrari (3) — project lead
    (9007, 'Pianificare sprint e-commerce Rossetti', 'Aggiornare il backlog con le richieste emerse in call col cliente.',              'in_progress', 'high',   (CURDATE() + INTERVAL 3 DAY), NULL,       1, NULL, 3, (NOW() - INTERVAL 4 DAY)),
    (9008, 'Verifica avanzamento milestone catalogo', 'Allineare Marco e Julien sui tempi delle schede prodotto.',                     'todo',        'medium', (CURDATE() + INTERVAL 7 DAY), NULL,       2, NULL, 3, (NOW() - INTERVAL 2 DAY)),
    (9009, 'Report stato progetti per la direzione', NULL,                                                                             'done',        'medium', (CURDATE() - INTERVAL 5 DAY), NULL,       1, (NOW() - INTERVAL 5 DAY), 3, (NOW() - INTERVAL 15 DAY)),

    -- James Whitaker (4) — account lead
    (9010, 'Preparare proposta commerciale Nordlys', 'Scope, tempi e budget per il rebrand + sito.',                                   'in_progress', 'high',   (CURDATE() + INTERVAL 6 DAY), NULL,       1, NULL, 4, (NOW() - INTERVAL 3 DAY)),
    (9011, 'Follow-up rinnovo contratto Bottega Verde', NULL,                                                                          'todo',        'medium', (CURDATE() + INTERVAL 10 DAY), NULL,      2, NULL, 4, (NOW() - INTERVAL 1 DAY)),

    -- Anna Weber (5) — ops & quality
    (9012, 'Revisione procedura gestione ferie',   'Ultimo giro prima dell''approvazione formale.',                                     'review',      'medium', (CURDATE() + INTERVAL 2 DAY), NULL,       1, NULL, 5, (NOW() - INTERVAL 4 DAY)),
    (9013, 'Audit qualità consegna campagna BV',   'Checklist post-mortem sul progetto chiuso.',                                        'done',        'low',    (CURDATE() - INTERVAL 8 DAY), NULL,       1, (NOW() - INTERVAL 8 DAY), 5, (NOW() - INTERVAL 20 DAY)),

    -- Marco Bianchi (6) — backend
    (9014, 'Import catalogo prodotti da CSV',      'Mappatura campi dal gestionale del cliente Rossetti.',                              'in_progress', 'high',   (CURDATE() + INTERVAL 4 DAY), NULL,       1, NULL, 6, (NOW() - INTERVAL 2 DAY)),
    (9015, 'API sincronizzazione magazzino',       'Endpoint stock + webhook aggiornamenti.',                                           'todo',        'medium', (CURDATE() + INTERVAL 9 DAY), NULL,       2, NULL, 6, (NOW() - INTERVAL 5 DAY)),
    (9016, 'Setup ambiente di staging gestionale', NULL,                                                                               'done',        'medium', (CURDATE() - INTERVAL 18 DAY), NULL,      1, (NOW() - INTERVAL 18 DAY), 6, (NOW() - INTERVAL 22 DAY)),

    -- Emily Turner (7) — content
    (9017, 'Copy schede prodotto e-commerce',      'Tono di voce del brand Rossetti, 20 schede pilota.',                                'in_progress', 'medium', (CURDATE() + INTERVAL 5 DAY), NULL,       1, NULL, 7, (NOW() - INTERVAL 3 DAY)),
    (9018, 'Newsletter mensile studio',            NULL,                                                                               'todo',        'low',    (CURDATE() + INTERVAL 12 DAY), NULL,      2, NULL, 7, (NOW() - INTERVAL 1 DAY)),

    -- Julien Moreau (8) — frontend
    (9019, 'Template scheda prodotto',             'Varianti taglia/colore, galleria immagini, zoom.',                                  'in_progress', 'high',   (CURDATE() + INTERVAL 3 DAY), NULL,       1, NULL, 8, (NOW() - INTERVAL 2 DAY)),
    (9020, 'Fix responsive header sito Rossetti',  'Su mobile il menu copre il logo sotto i 380px.',                                    'todo',        'high',   (CURDATE() + INTERVAL 1 DAY), NULL,       2, NULL, 8, (NOW() - INTERVAL 1 DAY)),
    (9021, 'Integrazione componenti UI kit',       NULL,                                                                               'done',        'medium', (CURDATE() - INTERVAL 6 DAY), NULL,       1, (NOW() - INTERVAL 6 DAY), 8, (NOW() - INTERVAL 14 DAY)),

    -- Camille Laurent (9) — design
    (9022, 'Prototipo checkout e-commerce',        'Flusso pagamento carta/PayPal, 3 step.',                                            'in_progress', 'high',   (CURDATE() + INTERVAL 6 DAY), NULL,       1, NULL, 9, (NOW() - INTERVAL 3 DAY)),
    (9023, 'Moodboard rebrand Nordlys',            'Prime direzioni visive per il cliente tedesco.',                                    'backlog',     'medium', NULL,                          NULL,       2, NULL, 9, (NOW() - INTERVAL 1 DAY)),

    -- Lukas Braun (10) — devops
    (9024, 'Pipeline CI/CD gestionale interno',    'Build + test + deploy su staging automatici.',                                      'in_progress', 'medium', (CURDATE() + INTERVAL 7 DAY), NULL,       1, NULL, 10, (NOW() - INTERVAL 4 DAY)),
    (9025, 'Aggiornare certificati TLS server',    NULL,                                                                               'todo',        'high',   (CURDATE() + INTERVAL 2 DAY), NULL,       2, NULL, 10, (NOW() - INTERVAL 2 DAY)),

    -- Diego Herrera (11) — fullstack
    (9026, 'Landing page campagna (manutenzione)', 'Piccoli fix post-lancio richiesti da Bottega Verde.',                               'todo',        'low',    (CURDATE() + INTERVAL 8 DAY), NULL,       1, NULL, 11, (NOW() - INTERVAL 2 DAY)),
    (9027, 'Modulo commesse gestionale',           'Stati commessa, assegnazioni, margini.',                                            'in_progress', 'high',   (CURDATE() + INTERVAL 11 DAY), NULL,      2, NULL, 11, (NOW() - INTERVAL 3 DAY)),

    -- Lucía Romero (12) — social & marketing
    (9028, 'Calendario editoriale social',         'Piano contenuti mensile per i canali dello studio.',                                'review',      'medium', (CURDATE() + INTERVAL 4 DAY), NULL,       1, NULL, 12, (NOW() - INTERVAL 3 DAY)),
    (9029, 'Report risultati campagna Bottega Verde', 'Numeri finali: +38% iscritti, conversione landing 4,2%.',                        'done',        'medium', (CURDATE() - INTERVAL 6 DAY), NULL,       2, (NOW() - INTERVAL 6 DAY), 12, (NOW() - INTERVAL 12 DAY));

-- Checklist su offerta (9001), import catalogo (9014) e template scheda (9019)
INSERT IGNORE INTO `task_checklist` (`id`, `task_id`, `text`, `is_done`, `position`) VALUES
    (9001, 9001, 'Verificare listino aggiornato 2026',   1, 1),
    (9002, 9001, 'Ricalcolare giornate sviluppo',        1, 2),
    (9003, 9001, 'Far validare a Giulia',                0, 3),
    (9004, 9014, 'Esportare CSV dal gestionale cliente',  1, 1),
    (9005, 9014, 'Mappare categorie e varianti',          0, 2),
    (9006, 9019, 'Galleria immagini + zoom',              1, 1),
    (9007, 9019, 'Test cross-browser',                    0, 2);

INSERT IGNORE INTO `task_tag_map` (`task_id`, `tag_id`) VALUES
    (9001, 9001), (9001, 9002),
    (9003, 9002), (9003, 9003),
    (9004, 9001),
    (9007, 9004),
    (9014, 9005), (9015, 9005),
    (9022, 9006), (9023, 9006),
    (9024, 9007), (9025, 9007),
    (9028, 9008);
