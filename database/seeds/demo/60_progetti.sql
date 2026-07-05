-- ============================================================================
-- Demo — Progetti (3 progetti con milestone, task, dipendenze, timesheet)
-- Caricato solo se il modulo Progetti è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `projects`
    (`id`, `name`, `code`, `description`, `client_name`, `owner_user_id`, `status`, `start_date`, `end_date`, `estimated_hours`, `budget_planned`, `budget_actual_cached`, `progress_cached`, `created_by`, `created_at`)
VALUES
    (9001, 'Sito e-commerce Rossetti', 'ECOM-ROS', 'Realizzazione e-commerce: catalogo ~400 prodotti, carrello, pagamenti carta/PayPal, integrazione magazzino.', 'Rossetti Calzature S.r.l.', 3, 'active',    (CURDATE() - INTERVAL 30 DAY), (CURDATE() + INTERVAL 45 DAY), 280.00, 18500.00, 6420.00, 42.00, 3, (NOW() - INTERVAL 30 DAY)),
    (9002, 'Gestionale interno Aurora',  'GEST-INT', 'Sviluppo del gestionale interno: anagrafiche, commesse, reportistica ore.',                              NULL,                        1, 'active',    (CURDATE() - INTERVAL 21 DAY), (CURDATE() + INTERVAL 60 DAY), 200.00, 0.00,     3180.00, 35.00, 1, (NOW() - INTERVAL 21 DAY)),
    (9003, 'Campagna lancio Bottega Verde', 'CAMP-BV', 'Campagna social e landing page per il lancio della nuova linea bio.',                                 'Bottega Verde Bio',         5, 'completed', (CURDATE() - INTERVAL 50 DAY), (CURDATE() - INTERVAL 5 DAY),  120.00, 8000.00,  7650.00, 100.00, 5, (NOW() - INTERVAL 50 DAY));

INSERT IGNORE INTO `project_members` (`project_id`, `user_id`, `role`) VALUES
    (9001, 3, 'owner'), (9001, 6, 'member'), (9001, 11, 'member'), (9001, 1, 'viewer'),
    (9002, 1, 'owner'), (9002, 6, 'member'), (9002, 11, 'member'), (9002, 3, 'member'),
    (9003, 5, 'owner'), (9003, 8, 'member'), (9003, 9, 'member'), (9003, 1, 'viewer');

INSERT IGNORE INTO `project_milestones`
    (`id`, `project_id`, `name`, `description`, `due_date`, `billable`, `status`, `progress_cached`, `created_by`)
VALUES
    (9001, 9001, 'Design e prototipo',       'Wireframe, UI kit e prototipo navigabile approvato dal cliente.', (CURDATE() - INTERVAL 10 DAY), 1, 'done',        100.00, 3),
    (9002, 9001, 'Catalogo e schede prodotto', NULL,                                                            (CURDATE() + INTERVAL 10 DAY), 1, 'in_progress', 55.00,  3),
    (9003, 9001, 'Checkout e pagamenti',     'Carrello, pagamenti e integrazione col gestionale magazzino.',    (CURDATE() + INTERVAL 30 DAY), 1, 'pending',     0.00,   3),
    (9004, 9001, 'Go-live',                  'Messa in produzione e formazione del cliente.',                   (CURDATE() + INTERVAL 45 DAY), 1, 'pending',     0.00,   3),
    (9005, 9002, 'Anagrafiche',              NULL,                                                              (CURDATE() - INTERVAL 2 DAY),  0, 'done',        100.00, 1),
    (9006, 9002, 'Commesse e reportistica',  NULL,                                                              (CURDATE() + INTERVAL 25 DAY), 0, 'in_progress', 30.00,  1),
    (9007, 9003, 'Lancio campagna',          'Pubblicazione contenuti e landing.',                              (CURDATE() - INTERVAL 12 DAY), 1, 'done',        100.00, 5);

INSERT IGNORE INTO `project_tasks`
    (`id`, `project_id`, `milestone_id`, `title`, `description`, `assigned_user_id`, `priority`, `status`, `start_date`, `due_date`, `estimated_hours`, `position`, `completed_at`, `created_by`)
VALUES
    -- E-commerce Rossetti
    (9001, 9001, 9001, 'UI kit e design system',            NULL,                                                     11, 'high',   'done',        (CURDATE() - INTERVAL 28 DAY), (CURDATE() - INTERVAL 14 DAY), 40.00, 1, (NOW() - INTERVAL 14 DAY), 3),
    (9002, 9001, 9002, 'Template scheda prodotto',          'Varianti taglia/colore, galleria immagini, zoom.',        6, 'high',   'in_progress', (CURDATE() - INTERVAL 8 DAY),  (CURDATE() + INTERVAL 4 DAY),  32.00, 2, NULL, 3),
    (9003, 9001, 9002, 'Import catalogo da CSV',            'Mappatura campi dal gestionale del cliente.',             6, 'medium', 'todo',        NULL,                          (CURDATE() + INTERVAL 9 DAY),  24.00, 3, NULL, 3),
    (9004, 9001, 9003, 'Integrazione gateway pagamenti',    'Carta (Stripe) + PayPal, gestione rimborsi.',            11, 'high',   'todo',        NULL,                          (CURDATE() + INTERVAL 24 DAY), 40.00, 4, NULL, 3),
    (9005, 9001, 9003, 'Sincronizzazione magazzino',        NULL,                                                      6, 'medium', 'blocked',     NULL,                          (CURDATE() + INTERVAL 28 DAY), 30.00, 5, NULL, 3),
    (9006, 9001, 9004, 'Checklist go-live e formazione',    NULL,                                                      3, 'medium', 'todo',        NULL,                          (CURDATE() + INTERVAL 44 DAY), 16.00, 6, NULL, 3),
    -- Gestionale interno
    (9007, 9002, 9005, 'CRUD anagrafiche clienti/fornitori', NULL,                                                    11, 'high',   'done',        (CURDATE() - INTERVAL 20 DAY), (CURDATE() - INTERVAL 4 DAY),  30.00, 1, (NOW() - INTERVAL 4 DAY), 1),
    (9008, 9002, 9006, 'Modulo commesse',                   'Stati commessa, assegnazioni, margini.',                  6, 'high',   'in_progress', (CURDATE() - INTERVAL 3 DAY),  (CURDATE() + INTERVAL 12 DAY), 40.00, 2, NULL, 1),
    (9009, 9002, 9006, 'Report ore per cliente',            NULL,                                                     11, 'medium', 'review',      (CURDATE() - INTERVAL 6 DAY),  (CURDATE() + INTERVAL 2 DAY),  16.00, 3, NULL, 1),
    (9010, 9002, NULL, 'Setup ambiente di staging',         NULL,                                                      6, 'low',    'done',        (CURDATE() - INTERVAL 21 DAY), (CURDATE() - INTERVAL 18 DAY),  6.00, 4, (NOW() - INTERVAL 18 DAY), 1),
    -- Campagna Bottega Verde (chiusa)
    (9011, 9003, 9007, 'Piano editoriale e copy',           NULL,                                                      8, 'high',   'done',        (CURDATE() - INTERVAL 48 DAY), (CURDATE() - INTERVAL 30 DAY), 24.00, 1, (NOW() - INTERVAL 30 DAY), 5),
    (9012, 9003, 9007, 'Landing page campagna',             NULL,                                                      9, 'high',   'done',        (CURDATE() - INTERVAL 35 DAY), (CURDATE() - INTERVAL 15 DAY), 32.00, 2, (NOW() - INTERVAL 15 DAY), 5),
    (9013, 9003, 9007, 'Report risultati per il cliente',   NULL,                                                      8, 'medium', 'done',        (CURDATE() - INTERVAL 10 DAY), (CURDATE() - INTERVAL 6 DAY),   8.00, 3, (NOW() - INTERVAL 6 DAY), 5);

-- Dipendenze (Gantt): pagamenti dopo il catalogo; go-live dopo i pagamenti;
-- magazzino dopo l'import catalogo.
INSERT IGNORE INTO `project_task_dependencies` (`predecessor_task_id`, `successor_task_id`, `dependency_type`) VALUES
    (9002, 9004, 'FS'),
    (9003, 9005, 'FS'),
    (9004, 9006, 'FS');

-- Timesheet (ore recenti, per budget e report)
INSERT IGNORE INTO `project_timesheets` (`id`, `project_id`, `task_id`, `user_id`, `work_date`, `hours`, `note`) VALUES
    (9001, 9001, 9001, 11, (CURDATE() - INTERVAL 16 DAY), 6.00, 'Chiusura UI kit'),
    (9002, 9001, 9002, 6,  (CURDATE() - INTERVAL 5 DAY),  7.50, 'Template scheda: varianti'),
    (9003, 9001, 9002, 6,  (CURDATE() - INTERVAL 2 DAY),  6.00, NULL),
    (9004, 9001, 9002, 6,  (CURDATE() - INTERVAL 1 DAY),  4.00, 'Galleria immagini'),
    (9005, 9002, 9007, 11, (CURDATE() - INTERVAL 7 DAY),  8.00, NULL),
    (9006, 9002, 9008, 6,  (CURDATE() - INTERVAL 2 DAY),  5.50, 'Stati commessa'),
    (9007, 9002, 9009, 11, (CURDATE() - INTERVAL 3 DAY),  4.00, 'Prima bozza report'),
    (9008, 9003, 9013, 8,  (CURDATE() - INTERVAL 6 DAY),  6.00, 'Report finale campagna');
