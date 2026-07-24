-- ============================================================================
-- Demo — Preferenze utente & layout Home personalizzato (per i 10 del cast)
-- Mostra: UI nella lingua di ciascuno (language), temi/skin/pattern/font diversi
-- per profilo, e una dashboard Home riordinata a mano da ogni utente.
-- L'admin (id 1) NON viene toccato: resta sulle sue preferenze reali.
-- ID fissi da 9001 + chiave naturale UNIQUE(user_id[,widget_id]) => idempotente.
-- ============================================================================

SET NAMES utf8mb4;

-- Preferenze per-utente (lingua UI = nazionalità; look variato)
INSERT IGNORE INTO `user_preferences`
    (`id`, `user_id`, `theme`, `primary_color`, `sidebar_collapsed`, `sidebar_style`, `background_pattern`, `theme_skin`, `font_family`, `language`)
VALUES
    (9001, 3,  'light', '#0d6efd', 0, 'accent',  'curves',    'soft',    'inter',     'it'),
    (9002, 4,  'dark',  '#6610f2', 1, 'default', 'grid',      'sharp',   'jetbrains', 'en'),
    (9003, 5,  'light', '#198754', 0, 'light',   'hexagons',  'compact', 'plex',      'de'),
    (9004, 6,  'dark',  '#20c997', 0, 'default', 'lines',     'default', 'jetbrains', 'it'),
    (9005, 7,  'light', '#d63384', 0, 'accent',  'mesh',      'soft',    'lora',      'en'),
    (9006, 8,  'dark',  '#fd7e14', 0, 'default', 'triangles', 'sharp',   'inter',     'fr'),
    (9007, 9,  'light', '#6f42c1', 0, 'accent',  'diamonds',  'soft',    'lora',      'fr'),
    (9008, 10, 'dark',  '#0dcaf0', 1, 'default', 'chevrons',  'compact', 'jetbrains', 'de'),
    (9009, 11, 'dark',  '#dc3545', 0, 'accent',  'pentagons', 'default', 'inter',     'es'),
    (9010, 12, 'light', '#ffc107', 0, 'light',   'circles',   'soft',    'plex',      'es');

-- Layout dashboard personale (sort_order/visible/size = colspan su griglia 12).
-- Widget scelti in base al ruolo/mansione; alcuni nascosti (visible=0) per mostrare
-- la personalizzazione. Nessun FK: righe innocue anche se un modulo è disabilitato.
INSERT IGNORE INTO `user_widget_preferences`
    (`id`, `user_id`, `widget_id`, `sort_order`, `visible`, `size`)
VALUES
    -- Giulia Ferrari (3) — Manager / project lead
    (9001, 3, 'progetti.overview',      1, 1, 6),
    (9002, 3, 'progetti.my_tasks',      2, 1, 6),
    (9003, 3, 'calendar.upcoming',      3, 1, 6),
    (9004, 3, 'documenti.da_approvare', 4, 1, 3),
    (9005, 3, 'tasks.active',           5, 1, 3),
    (9006, 3, 'home.weekly_timeline',   6, 1, 6),
    (9007, 3, 'teams.unread',           7, 0, 3),
    -- James Whitaker (4) — Manager / account lead
    (9008, 4, 'progetti.status',        1, 1, 6),
    (9009, 4, 'progetti.budget',        2, 1, 6),
    (9010, 4, 'calendar.upcoming',      3, 1, 6),
    (9011, 4, 'contacts.totali',        4, 1, 3),
    (9012, 4, 'tasks.active',           5, 1, 3),
    (9013, 4, 'notifications.list',     6, 1, 6),
    -- Anna Weber (5) — Manager / ops & quality
    (9014, 5, 'documenti.da_approvare', 1, 1, 6),
    (9015, 5, 'documenti.inbox_list',   2, 1, 6),
    (9016, 5, 'calendar.upcoming',      3, 1, 6),
    (9017, 5, 'progetti.overview',      4, 1, 6),
    (9018, 5, 'tasks.active',           5, 1, 3),
    (9019, 5, 'home.quick_links',       6, 1, 3),
    -- Marco Bianchi (6) — backend dev
    (9020, 6, 'tasks.upcoming',         1, 1, 6),
    (9021, 6, 'progetti.my_tasks',      2, 1, 6),
    (9022, 6, 'tasks.active',           3, 1, 3),
    (9023, 6, 'teams.unread_list',      4, 1, 6),
    (9024, 6, 'calendar.upcoming',      5, 1, 6),
    (9025, 6, 'tasks.trend',            6, 0, 6),
    -- Emily Turner (7) — content & copy
    (9026, 7, 'blog.list',              1, 1, 6),
    (9027, 7, 'tasks.upcoming',         2, 1, 6),
    (9028, 7, 'calendar.upcoming',      3, 1, 6),
    (9029, 7, 'files.recent',           4, 1, 6),
    (9030, 7, 'blog.stat',              5, 1, 3),
    (9031, 7, 'home.weekly_timeline',   6, 1, 6),
    -- Julien Moreau (8) — frontend dev
    (9032, 8, 'tasks.upcoming',         1, 1, 6),
    (9033, 8, 'progetti.my_tasks',      2, 1, 6),
    (9034, 8, 'tasks.active',           3, 1, 3),
    (9035, 8, 'files.recent',           4, 1, 6),
    (9036, 8, 'calendar.upcoming',      5, 1, 6),
    (9037, 8, 'teams.unread',           6, 1, 3),
    -- Camille Laurent (9) — UX/UI designer
    (9038, 9, 'files.recent',           1, 1, 6),
    (9039, 9, 'tasks.upcoming',         2, 1, 6),
    (9040, 9, 'files.stat',             3, 1, 3),
    (9041, 9, 'progetti.my_tasks',      4, 1, 6),
    (9042, 9, 'calendar.upcoming',      5, 1, 6),
    (9043, 9, 'home.quick_links',       6, 1, 3),
    -- Lukas Braun (10) — devops / infra
    (9044, 10, 'tasks.upcoming',        1, 1, 6),
    (9045, 10, 'progetti.my_tasks',     2, 1, 6),
    (9046, 10, 'calendar.upcoming',     3, 1, 6),
    (9047, 10, 'tasks.active',          4, 1, 3),
    (9048, 10, 'notifications.list',    5, 1, 6),
    (9049, 10, 'home.weekly_timeline',  6, 0, 6),
    -- Diego Herrera (11) — fullstack dev
    (9050, 11, 'tasks.upcoming',        1, 1, 6),
    (9051, 11, 'progetti.my_tasks',     2, 1, 6),
    (9052, 11, 'tasks.active',          3, 1, 3),
    (9053, 11, 'teams.unread_list',     4, 1, 6),
    (9054, 11, 'calendar.upcoming',     5, 1, 6),
    (9055, 11, 'files.recent',          6, 0, 6),
    -- Lucía Romero (12) — social & marketing
    (9056, 12, 'blog.list',             1, 1, 6),
    (9057, 12, 'tasks.upcoming',        2, 1, 6),
    (9058, 12, 'calendar.upcoming',     3, 1, 6),
    (9059, 12, 'contacts.ricorrenze',   4, 1, 3),
    (9060, 12, 'blog.stat',             5, 1, 3),
    (9061, 12, 'home.weekly_timeline',  6, 1, 6);
