-- ============================================================================
-- Demo — Progetti (4 progetti scaglionati sull'anno: milestone, task,
-- dipendenze, timesheet). Cast e clienti da NARRATIVE.md.
-- Caricato solo se il modulo Progetti è abilitato.
-- Include un grant demo-only dei permessi Progetti a Manager/User: di default
-- solo l'admin li ha, ma qui il team DEVE poter vedere/usare i progetti.
-- ============================================================================

SET NAMES utf8mb4;

-- Grant demo-only: rende i Progetti visibili/usabili dal cast (non solo admin).
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
    SELECT 2, `id` FROM `permissions`
    WHERE `slug` IN ('progetti.view','progetti.create','progetti.edit','progetti.log_time','progetti.view_all','progetti.export','progetti.manage_members');
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
    SELECT 3, `id` FROM `permissions`
    WHERE `slug` IN ('progetti.view','progetti.create','progetti.edit','progetti.log_time');

INSERT IGNORE INTO `projects`
    (`id`, `name`, `code`, `description`, `client_name`, `owner_user_id`, `status`, `start_date`, `end_date`, `estimated_hours`, `budget_planned`, `budget_actual_cached`, `progress_cached`, `created_by`, `created_at`)
VALUES
    (9001, 'Sito e-commerce Rossetti', 'ECOM-ROS', 'Realizzazione e-commerce: catalogo ~400 prodotti, carrello, pagamenti carta/PayPal, integrazione magazzino.', 'Rossetti Calzature S.r.l.', 3, 'active',    (CURDATE() - INTERVAL 30 DAY), (CURDATE() + INTERVAL 45 DAY), 280.00, 18500.00, 6420.00, 42.00, 3, (NOW() - INTERVAL 30 DAY)),
    (9002, 'Campagna lancio Bottega Verde', 'CAMP-BV', 'Campagna social e landing page per il lancio della nuova linea bio.', 'Bottega Verde Bio', 4, 'completed', CONCAT(YEAR(CURDATE()),'-01-15'), CONCAT(YEAR(CURDATE()),'-03-15'), 120.00, 8000.00, 7650.00, 100.00, 4, (NOW() - INTERVAL 150 DAY)),
    (9003, 'Gestionale interno Aurora', 'GEST-INT', 'Sviluppo del gestionale interno: anagrafiche, commesse, reportistica ore.', NULL, 5, 'active', (CURDATE() - INTERVAL 21 DAY), (CURDATE() + INTERVAL 60 DAY), 200.00, 0.00, 3180.00, 35.00, 5, (NOW() - INTERVAL 21 DAY)),
    (9004, 'Nordlys Rebrand & Site', 'NORD-BR', 'Rebrand completo + sito multilingua (DE/EN) per il nuovo cliente tedesco.', 'Nordlys Interiors GmbH', 4, 'planning', CONCAT(YEAR(CURDATE()),'-11-05'), CONCAT(YEAR(CURDATE())+1,'-02-28'), 150.00, 12000.00, 0.00, 0.00, 4, (NOW() - INTERVAL 20 DAY));

INSERT IGNORE INTO `project_members` (`project_id`, `user_id`, `role`) VALUES
    (9001, 3, 'owner'), (9001, 6, 'member'), (9001, 8, 'member'), (9001, 9, 'member'), (9001, 1, 'viewer'),
    (9002, 4, 'owner'), (9002, 7, 'member'), (9002, 12, 'member'), (9002, 11, 'member'), (9002, 1, 'viewer'),
    (9003, 5, 'owner'), (9003, 6, 'member'), (9003, 10, 'member'), (9003, 11, 'member'),
    (9004, 4, 'owner'), (9004, 9, 'member'), (9004, 8, 'member'), (9004, 7, 'member');

INSERT IGNORE INTO `project_milestones`
    (`id`, `project_id`, `name`, `description`, `due_date`, `billable`, `status`, `progress_cached`, `created_by`)
VALUES
    (9001, 9001, 'Design e prototipo',        'Wireframe, UI kit e prototipo navigabile approvato dal cliente.', (CURDATE() - INTERVAL 10 DAY), 1, 'done',        100.00, 3),
    (9002, 9001, 'Catalogo e schede prodotto', NULL,                                                           (CURDATE() + INTERVAL 10 DAY), 1, 'in_progress', 55.00,  3),
    (9003, 9001, 'Checkout e pagamenti',       'Carrello, pagamenti e integrazione col gestionale magazzino.',  (CURDATE() + INTERVAL 30 DAY), 1, 'pending',     0.00,   3),
    (9004, 9001, 'Go-live',                     'Messa in produzione e formazione del cliente.',                 (CURDATE() + INTERVAL 45 DAY), 1, 'pending',     0.00,   3),
    (9005, 9002, 'Lancio campagna',             'Pubblicazione contenuti e landing.',                            CONCAT(YEAR(CURDATE()),'-03-10'), 1, 'done',    100.00, 4),
    (9006, 9003, 'Anagrafiche',                 NULL,                                                            (CURDATE() - INTERVAL 2 DAY),  0, 'done',        100.00, 5),
    (9007, 9003, 'Commesse e reportistica',     NULL,                                                            (CURDATE() + INTERVAL 25 DAY), 0, 'in_progress', 30.00,  5),
    (9008, 9004, 'Brand concept',               'Direzioni visive, naming, logo.',                               CONCAT(YEAR(CURDATE()),'-12-05'), 1, 'pending',  0.00,   4),
    (9009, 9004, 'Sito multilingua',            'Sviluppo sito DE/EN.',                                          CONCAT(YEAR(CURDATE())+1,'-02-15'), 1, 'pending', 0.00,  4);

INSERT IGNORE INTO `project_tasks`
    (`id`, `project_id`, `milestone_id`, `title`, `description`, `assigned_user_id`, `priority`, `status`, `start_date`, `due_date`, `estimated_hours`, `position`, `completed_at`, `created_by`)
VALUES
    -- E-commerce Rossetti
    (9001, 9001, 9001, 'UI kit e design system',         NULL,                                                 9,  'high',   'done',        (CURDATE() - INTERVAL 28 DAY), (CURDATE() - INTERVAL 14 DAY), 40.00, 1, (NOW() - INTERVAL 14 DAY), 3),
    (9002, 9001, 9002, 'Template scheda prodotto',       'Varianti taglia/colore, galleria immagini, zoom.',    8,  'high',   'in_progress', (CURDATE() - INTERVAL 8 DAY),  (CURDATE() + INTERVAL 4 DAY),  32.00, 2, NULL, 3),
    (9003, 9001, 9002, 'Import catalogo da CSV',         'Mappatura campi dal gestionale del cliente.',         6,  'medium', 'todo',        NULL,                          (CURDATE() + INTERVAL 9 DAY),  24.00, 3, NULL, 3),
    (9004, 9001, 9003, 'Integrazione gateway pagamenti', 'Carta (Stripe) + PayPal, gestione rimborsi.',         6,  'high',   'todo',        NULL,                          (CURDATE() + INTERVAL 24 DAY), 40.00, 4, NULL, 3),
    (9005, 9001, 9003, 'Sincronizzazione magazzino',     NULL,                                                  6,  'medium', 'blocked',     NULL,                          (CURDATE() + INTERVAL 28 DAY), 30.00, 5, NULL, 3),
    (9006, 9001, 9004, 'Checklist go-live e formazione', NULL,                                                  3,  'medium', 'todo',        NULL,                          (CURDATE() + INTERVAL 44 DAY), 16.00, 6, NULL, 3),
    -- Campagna Bottega Verde (chiusa)
    (9007, 9002, 9005, 'Piano editoriale e copy',        NULL,                                                  7,  'high',   'done',        CONCAT(YEAR(CURDATE()),'-01-16'), CONCAT(YEAR(CURDATE()),'-02-05'), 24.00, 1, CONCAT(YEAR(CURDATE()),'-02-05 12:00:00'), 4),
    (9008, 9002, 9005, 'Landing page campagna',          NULL,                                                  11, 'high',   'done',        CONCAT(YEAR(CURDATE()),'-02-01'), CONCAT(YEAR(CURDATE()),'-02-25'), 32.00, 2, CONCAT(YEAR(CURDATE()),'-02-25 12:00:00'), 4),
    (9009, 9002, 9005, 'Report risultati per il cliente', NULL,                                                 12, 'medium', 'done',        CONCAT(YEAR(CURDATE()),'-03-01'), CONCAT(YEAR(CURDATE()),'-03-12'), 8.00,  3, CONCAT(YEAR(CURDATE()),'-03-12 12:00:00'), 4),
    -- Gestionale interno
    (9010, 9003, 9006, 'CRUD anagrafiche clienti/fornitori', NULL,                                             6,  'high',   'done',        (CURDATE() - INTERVAL 20 DAY), (CURDATE() - INTERVAL 4 DAY),  30.00, 1, (NOW() - INTERVAL 4 DAY), 5),
    (9011, 9003, 9007, 'Modulo commesse',                'Stati commessa, assegnazioni, margini.',              11, 'high',   'in_progress', (CURDATE() - INTERVAL 3 DAY),  (CURDATE() + INTERVAL 12 DAY), 40.00, 2, NULL, 5),
    (9012, 9003, 9007, 'Report ore per cliente',         NULL,                                                  6,  'medium', 'review',      (CURDATE() - INTERVAL 6 DAY),  (CURDATE() + INTERVAL 2 DAY),  16.00, 3, NULL, 5),
    (9013, 9003, NULL, 'Setup ambiente di staging',      NULL,                                                  10, 'low',    'done',        (CURDATE() - INTERVAL 21 DAY), (CURDATE() - INTERVAL 18 DAY),  6.00, 4, (NOW() - INTERVAL 18 DAY), 5),
    -- Nordlys (planning)
    (9014, 9004, 9008, 'Moodboard e direzioni visive',   'Prime 3 direzioni per il cliente.',                   9,  'medium', 'todo',        NULL,                          CONCAT(YEAR(CURDATE()),'-11-25'), 16.00, 1, NULL, 4),
    (9015, 9004, 9008, 'Naming e logo',                  NULL,                                                  9,  'medium', 'todo',        NULL,                          CONCAT(YEAR(CURDATE()),'-12-03'), 20.00, 2, NULL, 4);

-- Dipendenze (Gantt): pagamenti dopo il catalogo; magazzino dopo l'import; go-live dopo i pagamenti.
INSERT IGNORE INTO `project_task_dependencies` (`predecessor_task_id`, `successor_task_id`, `dependency_type`) VALUES
    (9002, 9004, 'FS'),
    (9003, 9005, 'FS'),
    (9004, 9006, 'FS');

-- Timesheet (ore, per budget e report)
INSERT IGNORE INTO `project_timesheets` (`id`, `project_id`, `task_id`, `user_id`, `work_date`, `hours`, `note`) VALUES
    (9001, 9001, 9001, 9,  (CURDATE() - INTERVAL 16 DAY), 6.00, 'Chiusura UI kit'),
    (9002, 9001, 9002, 8,  (CURDATE() - INTERVAL 5 DAY),  7.50, 'Template scheda: varianti'),
    (9003, 9001, 9002, 8,  (CURDATE() - INTERVAL 2 DAY),  6.00, NULL),
    (9004, 9001, 9002, 8,  (CURDATE() - INTERVAL 1 DAY),  4.00, 'Galleria immagini'),
    (9005, 9003, 9010, 6,  (CURDATE() - INTERVAL 7 DAY),  8.00, NULL),
    (9006, 9003, 9011, 11, (CURDATE() - INTERVAL 2 DAY),  5.50, 'Stati commessa'),
    (9007, 9003, 9012, 6,  (CURDATE() - INTERVAL 3 DAY),  4.00, 'Prima bozza report'),
    (9008, 9002, 9009, 12, CONCAT(YEAR(CURDATE()),'-03-11'), 6.00, 'Report finale campagna');
