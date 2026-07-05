-- ============================================================================
-- Demo — Teams (chat di gruppo + 2 dirette, menzioni, reazioni, presenza)
-- Caricato solo se il modulo Teams è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `teams_conversations` (`id`, `type`, `name`, `description`, `created_by`, `created_at`) VALUES
    (9001, 'group',  'Aurora — Generale', 'Canale di tutto lo studio: annunci e coordinamento.', 1, (NOW() - INTERVAL 60 DAY)),
    (9002, 'direct', NULL, NULL, 1, (NOW() - INTERVAL 10 DAY)),
    (9003, 'direct', NULL, NULL, 8, (NOW() - INTERVAL 4 DAY));

INSERT IGNORE INTO `teams_conversation_members` (`id`, `conversation_id`, `user_id`, `role`, `joined_at`, `last_read_at`) VALUES
    (9001, 9001, 1,  'admin',  (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 30 MINUTE)),
    (9002, 9001, 3,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 1 HOUR)),
    (9003, 9001, 4,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 3 HOUR)),
    (9004, 9001, 5,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 5 HOUR)),
    (9005, 9001, 6,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 20 MINUTE)),
    (9006, 9001, 7,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 2 DAY)),
    (9007, 9001, 8,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 1 HOUR)),
    (9008, 9001, 9,  'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 8 HOUR)),
    (9009, 9001, 10, 'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 1 DAY)),
    (9010, 9001, 11, 'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 45 MINUTE)),
    (9011, 9001, 12, 'member', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 3 DAY)),
    (9012, 9002, 1,  'member', (NOW() - INTERVAL 10 DAY), (NOW() - INTERVAL 2 HOUR)),
    (9013, 9002, 3,  'member', (NOW() - INTERVAL 10 DAY), (NOW() - INTERVAL 2 HOUR)),
    (9014, 9003, 8,  'member', (NOW() - INTERVAL 4 DAY),  (NOW() - INTERVAL 1 DAY)),
    (9015, 9003, 6,  'member', (NOW() - INTERVAL 4 DAY),  (NOW() - INTERVAL 1 DAY));

INSERT IGNORE INTO `teams_messages` (`id`, `conversation_id`, `user_id`, `reply_to_id`, `body`, `type`, `created_at`, `pinned_at`, `pinned_by`) VALUES
    -- Gruppo "Aurora — Generale"
    (9001, 9001, 1,  NULL, 'Benvenuti nel canale generale! Qui annunci e coordinamento veloce; per i progetti usate le chat dedicate.', 'text', (NOW() - INTERVAL 60 DAY), (NOW() - INTERVAL 59 DAY), 1),
    (9002, 9001, 3,  NULL, 'Ricordo a tutti la riunione settimanale domani alle 9:00 in sala grande.', 'text', (NOW() - INTERVAL 2 DAY), NULL, NULL),
    (9003, 9001, 6,  NULL, 'Il template della scheda prodotto Rossetti è in review: feedback benvenuti entro domani.', 'text', (NOW() - INTERVAL 1 DAY), NULL, NULL),
    (9004, 9001, 11, 9003, 'Visto ora: la galleria su mobile è molto meglio. Lascio due note sul task.', 'text', (NOW() - INTERVAL 22 HOUR), NULL, NULL),
    (9005, 9001, 8,  NULL, 'La campagna Bottega Verde ha chiuso con +38% di iscritti alla newsletter. Report in arrivo!', 'text', (NOW() - INTERVAL 6 DAY), NULL, NULL),
    (9006, 9001, 5,  9005, 'Grande risultato, brava @Isabella Ragonese! Lo presentiamo alla riunione commerciale.', 'text', (NOW() - INTERVAL 6 DAY) + INTERVAL 20 MINUTE, NULL, NULL),
    (9007, 9001, 1,  NULL, 'Da lunedì è attivo il nuovo gestionale ferie: le richieste passano dal calendario condiviso.', 'text', (NOW() - INTERVAL 4 DAY), NULL, NULL),
    (9008, 9001, 10, 9007, 'Vale anche per i permessi orari?', 'text', (NOW() - INTERVAL 4 DAY) + INTERVAL 15 MINUTE, NULL, NULL),
    (9009, 9001, 1,  9008, 'Sì, stessa procedura. I dettagli sono nel documento "Procedura gestione ferie" in Documenti.', 'text', (NOW() - INTERVAL 4 DAY) + INTERVAL 25 MINUTE, NULL, NULL),
    (9010, 9001, 7,  NULL, 'Qualcuno ha il contatto aggiornato di PrintLab per le stampe della fiera?', 'text', (NOW() - INTERVAL 3 HOUR), NULL, NULL),
    (9011, 9001, 1,  9010, 'In rubrica: Sara Greco, categoria Fornitori. Chiedi sempre il preventivo doppio ;)', 'text', (NOW() - INTERVAL 2 HOUR), NULL, NULL),
    -- Diretta admin ↔ Luca
    (9012, 9002, 1,  NULL, 'Luca, riesci a mandarmi l''offerta Rossetti aggiornata prima della call di giovedì?', 'text', (NOW() - INTERVAL 5 HOUR), NULL, NULL),
    (9013, 9002, 3,  9012, 'Te la giro entro stasera, sto ricalcolando le giornate di sviluppo.', 'text', (NOW() - INTERVAL 4 HOUR), NULL, NULL),
    (9014, 9002, 1,  9013, 'Perfetto, grazie!', 'text', (NOW() - INTERVAL 4 HOUR) + INTERVAL 5 MINUTE, NULL, NULL),
    -- Diretta Isabella ↔ Michele
    (9015, 9003, 8,  NULL, 'Michele, mi passi gli screenshot della landing per il report?', 'text', (NOW() - INTERVAL 1 DAY), NULL, NULL),
    (9016, 9003, 6,  9015, 'Te li carico nella cartella della campagna entro pranzo.', 'text', (NOW() - INTERVAL 1 DAY) + INTERVAL 30 MINUTE, NULL, NULL);

INSERT IGNORE INTO `teams_message_mentions` (`message_id`, `mentioned_user_id`) VALUES
    (9006, 8);

INSERT IGNORE INTO `teams_message_reactions` (`message_id`, `user_id`, `emoji`) VALUES
    (9005, 1,  '🎉'),
    (9005, 3,  '🎉'),
    (9005, 5,  '👏'),
    (9003, 11, '👍'),
    (9013, 1,  '👍');

INSERT IGNORE INTO `teams_user_presence` (`user_id`, `last_seen_at`) VALUES
    (1,  NOW() - INTERVAL 5 MINUTE),
    (3,  NOW() - INTERVAL 12 MINUTE),
    (6,  NOW() - INTERVAL 2 MINUTE),
    (8,  NOW() - INTERVAL 40 MINUTE),
    (11, NOW() - INTERVAL 3 HOUR);
