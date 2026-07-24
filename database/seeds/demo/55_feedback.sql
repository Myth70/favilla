-- ============================================================================
-- Demo — Feedback (segnalazioni) + Preferenze notifiche per-utente.
-- Sezione core (moduli sempre presenti). ID fissi da 9001 + INSERT IGNORE.
-- user_notification_preferences.channel_slug ha FK verso notification_channels
-- (slug validi: in_app, email, telegram, web_push).
-- ============================================================================

SET NAMES utf8mb4;

-- Segnalazioni in stati/tipi diversi (triage vivo)
INSERT IGNORE INTO `feedback`
    (`id`, `ref_code`, `tipo`, `severita`, `stato`, `titolo`, `descrizione`, `passi`, `modulo`, `created_by`, `assegnata_a`, `note_admin`, `created_at`)
VALUES
    (9001, 'FBK-0001', 'bug',          'alta',    'nuova',          'Menu mobile copre il logo sotto i 380px', 'Su smartphone stretti il menu hamburger si sovrappone al logo in alto a sinistra.', '1) Apri su mobile <380px 2) Osserva l''header', 'Home', 8, NULL, NULL, (NOW() - INTERVAL 2 DAY)),
    (9002, 'FBK-0002', 'funzionalita', 'media',   'in_lavorazione', 'Esportare il calendario in iCal', 'Sarebbe utile poter esportare gli eventi in formato .ics per Google/Apple Calendar.', NULL, 'Calendar', 3, 5, 'Valutare per la prossima release.', (NOW() - INTERVAL 8 DAY)),
    (9003, 'FBK-0003', 'domanda',      'bassa',   'risolta',        'Come collego il bot Telegram?', 'Non trovo dove inserire il token del bot per le notifiche.', NULL, 'Notifications', 10, 1, 'Risposto: Profilo → Preferenze notifiche.', (NOW() - INTERVAL 20 DAY)),
    (9004, 'FBK-0004', 'bug',          'critica', 'in_lavorazione', 'Import CSV contatti salta la prima riga', 'Se il CSV non ha intestazione, il primo contatto viene ignorato.', '1) Carica CSV senza header 2) Manca il primo record', 'Contacts', 6, 6, NULL, (NOW() - INTERVAL 3 DAY)),
    (9005, 'FBK-0005', 'funzionalita', 'bassa',   'chiusa',         'Tema scuro anche nei PDF dei report', 'Bello avere l''opzione dark anche nell''export PDF.', NULL, 'Reports', 12, 1, 'Non pianificato: i PDF restano su stile chiaro per leggibilità.', (NOW() - INTERVAL 30 DAY)),
    (9006, 'FBK-0006', 'bug',          'media',   'nuova',          'Notifica doppia sull''assegnazione task', 'A volte arrivano due notifiche per la stessa assegnazione.', NULL, 'Tasks', 11, NULL, NULL, (NOW() - INTERVAL 1 DAY));

-- Preferenze notifiche per-utente (livello modulo, event_slug='')
INSERT IGNORE INTO `user_notification_preferences`
    (`id`, `user_id`, `module_slug`, `event_slug`, `channel_slug`, `is_enabled`)
VALUES
    -- Admin (1)
    (9001, 1,  'backup',     '', 'in_app',   1),
    (9002, 1,  'health_check','', 'in_app',  1),
    (9003, 1,  'health_check','', 'email',   1),
    -- Giulia (3)
    (9004, 3,  'tasks',      '', 'in_app',   1),
    (9005, 3,  'calendar',   '', 'email',    1),
    (9006, 3,  'contacts',   '', 'telegram', 0),
    -- James (4)
    (9007, 4,  'tasks',      '', 'in_app',   1),
    (9008, 4,  'calendar',   '', 'email',    0),
    -- Anna (5)
    (9009, 5,  'tasks',      '', 'in_app',   1),
    (9010, 5,  'contacts',   '', 'email',    1),
    -- Marco (6)
    (9011, 6,  'tasks',      '', 'in_app',   1),
    (9012, 6,  'tasks',      '', 'telegram', 1),
    -- Emily (7)
    (9013, 7,  'calendar',   '', 'in_app',   1),
    (9014, 7,  'tasks',      '', 'email',    0),
    -- Julien (8)
    (9015, 8,  'tasks',      '', 'in_app',   1),
    (9016, 8,  'calendar',   '', 'email',    1),
    -- Camille (9)
    (9017, 9,  'tasks',      '', 'in_app',   1),
    (9018, 9,  'contacts',   '', 'telegram', 0),
    -- Lukas (10)
    (9019, 10, 'tasks',      '', 'in_app',   1),
    (9020, 10, 'health_check','', 'telegram', 1),
    -- Diego (11)
    (9021, 11, 'tasks',      '', 'in_app',   1),
    (9022, 11, 'calendar',   '', 'email',    0),
    -- Lucía (12)
    (9023, 12, 'calendar',   '', 'in_app',   1),
    (9024, 12, 'contacts',   '', 'email',    1);
