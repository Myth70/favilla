-- ============================================================================
-- Demo — Teams (chat) — showcase completo del modulo.
-- Gruppi di lavoro in INGLESE (storia lunga sull'anno); dirette in LINGUA MADRE
-- tra colleghi della stessa nazione (recenti). Copre: reply, menzioni, reazioni,
-- pin, messaggio modificato con storico, messaggi di sistema, allegati immagine
-- + PDF (teams_message_attachments, file copiati in public/uploads/teams/),
-- presence. Caricato solo se il modulo Teams è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `teams_conversations` (`id`, `type`, `name`, `description`, `created_by`, `created_at`) VALUES
    (9001, 'group',  'Aurora — General',   'Studio-wide channel: announcements and quick coordination.', 1, CONCAT(YEAR(CURDATE()),'-01-08 08:00:00')),
    (9002, 'group',  'E-commerce Rossetti', 'Project channel for the Rossetti e-commerce.',              3, (NOW() - INTERVAL 30 DAY)),
    (9003, 'group',  'Nordlys Rebrand',     'New German client: rebrand + multilingual site.',           4, (NOW() - INTERVAL 18 DAY)),
    (9004, 'direct', NULL, NULL, 3,  (NOW() - INTERVAL 30 DAY)),
    (9005, 'direct', NULL, NULL, 8,  (NOW() - INTERVAL 20 DAY)),
    (9006, 'direct', NULL, NULL, 5,  (NOW() - INTERVAL 15 DAY)),
    (9007, 'direct', NULL, NULL, 11, (NOW() - INTERVAL 10 DAY)),
    (9008, 'direct', NULL, NULL, 4,  (NOW() - INTERVAL 8 DAY));

INSERT IGNORE INTO `teams_conversation_members` (`id`, `conversation_id`, `user_id`, `role`, `joined_at`, `last_read_at`) VALUES
    -- General: tutti
    (9001, 9001, 1,  'admin',  CONCAT(YEAR(CURDATE()),'-01-08 08:00:00'), (NOW() - INTERVAL 20 MINUTE)),
    (9002, 9001, 3,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 1 HOUR)),
    (9003, 9001, 4,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 3 HOUR)),
    (9004, 9001, 5,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 5 HOUR)),
    (9005, 9001, 6,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 20 MINUTE)),
    (9006, 9001, 7,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 2 DAY)),
    (9007, 9001, 8,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 1 HOUR)),
    (9008, 9001, 9,  'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 8 HOUR)),
    (9009, 9001, 10, 'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 1 DAY)),
    (9010, 9001, 11, 'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 45 MINUTE)),
    (9011, 9001, 12, 'member', CONCAT(YEAR(CURDATE()),'-01-08 08:05:00'), (NOW() - INTERVAL 3 DAY)),
    -- E-commerce Rossetti
    (9012, 9002, 3, 'admin',  (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 1 HOUR)),
    (9013, 9002, 6, 'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 2 HOUR)),
    (9014, 9002, 8, 'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 22 HOUR)),
    (9015, 9002, 9, 'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 3 HOUR)),
    (9016, 9002, 1, 'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 6 HOUR)),
    -- Nordlys Rebrand
    (9017, 9003, 4, 'admin',  (NOW() - INTERVAL 18 DAY), (NOW() - INTERVAL 4 HOUR)),
    (9018, 9003, 9, 'member', (NOW() - INTERVAL 18 DAY), (NOW() - INTERVAL 5 HOUR)),
    (9019, 9003, 8, 'member', (NOW() - INTERVAL 18 DAY), (NOW() - INTERVAL 1 DAY)),
    (9020, 9003, 7, 'member', (NOW() - INTERVAL 18 DAY), (NOW() - INTERVAL 2 DAY)),
    -- Dirette
    (9021, 9004, 3,  'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 2 HOUR)),
    (9022, 9004, 6,  'member', (NOW() - INTERVAL 30 DAY), (NOW() - INTERVAL 2 HOUR)),
    (9023, 9005, 8,  'member', (NOW() - INTERVAL 20 DAY), (NOW() - INTERVAL 1 DAY)),
    (9024, 9005, 9,  'member', (NOW() - INTERVAL 20 DAY), (NOW() - INTERVAL 1 DAY)),
    (9025, 9006, 5,  'member', (NOW() - INTERVAL 15 DAY), (NOW() - INTERVAL 5 HOUR)),
    (9026, 9006, 10, 'member', (NOW() - INTERVAL 15 DAY), (NOW() - INTERVAL 5 HOUR)),
    (9027, 9007, 11, 'member', (NOW() - INTERVAL 10 DAY), (NOW() - INTERVAL 3 HOUR)),
    (9028, 9007, 12, 'member', (NOW() - INTERVAL 10 DAY), (NOW() - INTERVAL 3 HOUR)),
    (9029, 9008, 4,  'member', (NOW() - INTERVAL 8 DAY),  (NOW() - INTERVAL 4 HOUR)),
    (9030, 9008, 7,  'member', (NOW() - INTERVAL 8 DAY),  (NOW() - INTERVAL 4 HOUR));

INSERT IGNORE INTO `teams_messages` (`id`, `conversation_id`, `user_id`, `reply_to_id`, `body`, `type`, `created_at`, `edited_at`, `pinned_at`, `pinned_by`) VALUES
    -- ===== Aurora — General (EN, long history) =====
    (9001, 9001, 1,  NULL, 'Channel "Aurora — General" created.', 'system', CONCAT(YEAR(CURDATE()),'-01-08 08:00:00'), NULL, NULL, NULL),
    (9002, 9001, 1,  NULL, 'Welcome to the general channel! Announcements and quick coordination here; use the project channels for project work.', 'text', CONCAT(YEAR(CURDATE()),'-01-08 09:00:00'), NULL, CONCAT(YEAR(CURDATE()),'-01-08 09:05:00'), 1),
    (9003, 9001, 4,  NULL, 'Bottega Verde campaign is a wrap: +38% newsletter sign-ups and 4.2% landing conversion. Great job everyone!', 'text', CONCAT(YEAR(CURDATE()),'-03-13 10:00:00'), NULL, NULL, NULL),
    (9004, 9001, 5,  9003, 'Fantastic result — well done @Lucía Romero and @Emily Turner! We''ll present it at the next commercial review.', 'text', CONCAT(YEAR(CURDATE()),'-03-13 10:20:00'), NULL, NULL, NULL),
    (9005, 9001, 1,  NULL, 'Heads up: the new leave & time-off policy is live from next Monday. Requests go through the shared calendar.', 'text', (NOW() - INTERVAL 4 DAY), NULL, NULL, NULL),
    (9006, 9001, 6,  9005, 'Does it also cover hourly permits?', 'text', (NOW() - INTERVAL 4 DAY) + INTERVAL 15 MINUTE, NULL, NULL, NULL),
    (9007, 9001, 1,  9006, 'Yes, same procedure. Details are in the "Procedura gestione ferie" document under Documenti.', 'text', (NOW() - INTERVAL 4 DAY) + INTERVAL 25 MINUTE, NULL, NULL, NULL),
    (9008, 9001, 3,  NULL, 'Reminder: weekly team sync tomorrow at 9:00 on Google Meet.', 'text', (NOW() - INTERVAL 1 DAY), NULL, NULL, NULL),
    (9009, 9001, 4,  NULL, 'We just signed Nordlys Interiors (Berlin) — kickoff in November. Refreshed our logo lockups, here''s the current one:', 'text', (NOW() - INTERVAL 6 HOUR), (NOW() - INTERVAL 5 HOUR) - INTERVAL 30 MINUTE, NULL, NULL),
    (9010, 9001, 7,  NULL, 'Anyone has the updated PrintLab contact for the fair materials?', 'text', (NOW() - INTERVAL 3 HOUR), NULL, NULL, NULL),
    (9011, 9001, 1,  9010, 'In the address book: Sara Greco, Suppliers category. Always ask for the double quote ;)', 'text', (NOW() - INTERVAL 2 HOUR), NULL, NULL, NULL),

    -- ===== E-commerce Rossetti (EN) =====
    (9012, 9002, 3,  NULL, 'Kicking off the Rossetti build here. Milestone 1 (design) is approved, moving to the catalog.', 'text', (NOW() - INTERVAL 29 DAY), NULL, NULL, NULL),
    (9013, 9002, 9,  NULL, 'Product page mockup for review — variants, gallery and zoom. Feedback welcome:', 'text', (NOW() - INTERVAL 1 DAY), NULL, NULL, NULL),
    (9014, 9002, 8,  9013, 'Looks great, the mobile gallery is much smoother. Leaving two notes on the task.', 'text', (NOW() - INTERVAL 22 HOUR), NULL, NULL, NULL),
    (9015, 9002, 6,  NULL, 'CSV import is in progress — mapping the client''s warehouse fields. Should be ready this week.', 'text', (NOW() - INTERVAL 3 HOUR), NULL, NULL, NULL),

    -- ===== Nordlys Rebrand (EN) =====
    (9016, 9003, 4,  NULL, 'Welcome to the Nordlys channel. Sharing the initial brief from Klara — please read before kickoff:', 'text', (NOW() - INTERVAL 18 DAY), NULL, NULL, NULL),
    (9017, 9003, 9,  9016, 'Read it — minimal Scandinavian direction, love it. I''ll prep three moodboards for the kickoff.', 'text', (NOW() - INTERVAL 17 DAY), NULL, NULL, NULL),
    (9018, 9003, 7,  NULL, 'I''ll draft the EN copy tone in parallel so we can show a full concept.', 'text', (NOW() - INTERVAL 16 DAY), NULL, NULL, NULL),

    -- ===== Diretta IT: Giulia (3) ↔ Marco (6) =====
    (9019, 9004, 3,  NULL, 'Marco, riesci a chiudere l''import catalogo entro giovedì? Serve per la demo al cliente.', 'text', (NOW() - INTERVAL 5 HOUR), NULL, NULL, NULL),
    (9020, 9004, 6,  9019, 'Sì, sto mappando le varianti taglia/colore. Ti aggiorno domani mattina.', 'text', (NOW() - INTERVAL 4 HOUR), NULL, NULL, NULL),
    (9021, 9004, 3,  9020, 'Perfetto, grazie! Poi ci prendiamo un caffè virtuale 😄', 'text', (NOW() - INTERVAL 3 HOUR), NULL, NULL, NULL),

    -- ===== Diretta FR: Julien (8) ↔ Camille (9) =====
    (9022, 9005, 9,  NULL, 'Julien, tu déjeunes avec l''équipe vendredi ? On teste le nouveau resto près du coworking.', 'text', (NOW() - INTERVAL 1 DAY), NULL, NULL, NULL),
    (9023, 9005, 8,  9022, 'Avec plaisir ! Je réserve pour midi et demi, ça marche pour toi ?', 'text', (NOW() - INTERVAL 1 DAY) + INTERVAL 20 MINUTE, NULL, NULL, NULL),
    (9024, 9005, 9,  9023, 'Parfait, à vendredi ! N''oublie pas les maquettes pour la revue de l''après-midi.', 'text', (NOW() - INTERVAL 1 DAY) + INTERVAL 35 MINUTE, NULL, NULL, NULL),

    -- ===== Diretta DE: Anna (5) ↔ Lukas (10) =====
    (9025, 9006, 5,  NULL, 'Lukas, kannst du bitte das Dokument "Politica di backup" gegenprüfen? Es wartet auf Freigabe.', 'text', (NOW() - INTERVAL 6 HOUR), NULL, NULL, NULL),
    (9026, 9006, 10, 9025, 'Klar, ich schaue es mir heute Nachmittag an. Die Wiederherstellung habe ich letzte Woche getestet — läuft.', 'text', (NOW() - INTERVAL 5 HOUR), NULL, NULL, NULL),
    (9027, 9006, 5,  9026, 'Danke dir! Dann gebe ich morgen die endgültige Freigabe.', 'text', (NOW() - INTERVAL 5 HOUR) + INTERVAL 10 MINUTE, NULL, NULL, NULL),

    -- ===== Diretta ES: Diego (11) ↔ Lucía (12) =====
    (9028, 9007, 12, NULL, 'Diego, ¿puedes revisar los pequeños fixes de la landing de Bottega Verde? El cliente pidió un par de ajustes.', 'text', (NOW() - INTERVAL 4 HOUR), NULL, NULL, NULL),
    (9029, 9007, 11, 9028, '¡Claro! Los miro esta tarde y te aviso. Por cierto, ¡mucha suerte mañana con la presentación! 🍀', 'text', (NOW() - INTERVAL 3 HOUR), NULL, NULL, NULL),
    (9030, 9007, 12, 9029, '¡Gracias! Te debo un café ☕', 'text', (NOW() - INTERVAL 3 HOUR) + INTERVAL 8 MINUTE, NULL, NULL, NULL),

    -- ===== Diretta EN: James (4) ↔ Emily (7) =====
    (9031, 9008, 4,  NULL, 'Emily, can you send me the updated Nordlys copy tone before Thursday''s call?', 'text', (NOW() - INTERVAL 5 HOUR), NULL, NULL, NULL),
    (9032, 9008, 7,  9031, 'Sure, I''ll share it by end of day — polishing the EN/DE voice guidelines now.', 'text', (NOW() - INTERVAL 4 HOUR), NULL, NULL, NULL),
    (9033, 9008, 4,  9032, 'Perfect, thanks!', 'text', (NOW() - INTERVAL 4 HOUR) + INTERVAL 5 MINUTE, NULL, NULL, NULL);

-- Allegati (file reali copiati in public/uploads/teams/; size_bytes reali)
INSERT IGNORE INTO `teams_message_attachments` (`id`, `message_id`, `original_name`, `stored_name`, `mime_type`, `size_bytes`, `extension`) VALUES
    (9001, 9009, 'aurora-logo.png',        'demo-teams-logo.png',       'image/png',       1619,  'png'),
    (9002, 9013, 'product-page-mockup.png', 'demo-teams-moodboard.png',  'image/png',       1453,  'png'),
    (9003, 9016, 'nordlys-brief.pdf',       'demo-teams-brandbrief.pdf', 'application/pdf', 20142, 'pdf');

-- Storico modifica del messaggio 9009 (edited_at valorizzato sopra)
INSERT IGNORE INTO `teams_message_edits` (`id`, `message_id`, `old_body`, `edited_by`, `edited_at`) VALUES
    (9001, 9009, 'We just signed a new client — kickoff in November. Sharing our current logo:', 4, (NOW() - INTERVAL 5 HOUR) - INTERVAL 30 MINUTE);

INSERT IGNORE INTO `teams_message_mentions` (`message_id`, `mentioned_user_id`) VALUES
    (9004, 12),
    (9004, 7);

INSERT IGNORE INTO `teams_message_reactions` (`message_id`, `user_id`, `emoji`) VALUES
    (9003, 1,  '🎉'),
    (9003, 3,  '🎉'),
    (9003, 5,  '👏'),
    (9003, 12, '🙌'),
    (9013, 8,  '👍'),
    (9013, 3,  '🔥'),
    (9020, 3,  '👍');

INSERT IGNORE INTO `teams_user_presence` (`user_id`, `last_seen_at`) VALUES
    (1,  NOW() - INTERVAL 5 MINUTE),
    (3,  NOW() - INTERVAL 12 MINUTE),
    (6,  NOW() - INTERVAL 2 MINUTE),
    (9,  NOW() - INTERVAL 25 MINUTE),
    (11, NOW() - INTERVAL 40 MINUTE),
    (4,  NOW() - INTERVAL 3 HOUR);
