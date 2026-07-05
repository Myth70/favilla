-- ============================================================================
-- Demo — Contacts (rubrica dell'admin: clienti/fornitori/partner)
-- Categorie per-utente; 3 contatti geolocalizzati per la vista mappa;
-- ricorrenze (compleanno, rinnovo) per i promemoria; 1 condivisione per ruolo.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `contact_categories` (`id`, `user_id`, `nome`, `colore`) VALUES
    (9001, 1, 'Clienti',    '#0d6efd'),
    (9002, 1, 'Fornitori',  '#fd7e14'),
    (9003, 1, 'Partner',    '#198754');

INSERT IGNORE INTO `contacts`
    (`id`, `user_id`, `categoria_id`, `nome`, `cognome`, `azienda`, `ruolo`, `email`, `telefono`, `indirizzo`, `latitude`, `longitude`, `geocoding_source`, `sito_web`, `tags`, `note`, `preferito`, `created_at`)
VALUES
    (9001, 1, 9001, 'Giulia',   'Rossetti',  'Rossetti Calzature S.r.l.', 'Titolare',              'giulia@rossetticalzature.it', '+39 331 2045871', 'Via Montenapoleone 8, Milano',      45.46862000,  9.19582000, 'manual', 'https://www.rossetticalzature.it', 'e-commerce,retail', 'Cliente del progetto e-commerce. Preferisce essere contattata al mattino.', 1, (NOW() - INTERVAL 90 DAY)),
    (9002, 1, 9001, 'Marco',    'Bianchi',   'Bottega Verde Bio',         'Responsabile marketing', 'm.bianchi@bottegaverdebio.it', '+39 348 7712930', 'Corso Vittorio Emanuele 12, Torino', 45.06770000,  7.68682000, 'manual', NULL, 'sito,restyling', 'Restyling sito in corso, sprint quindicinali.', 1, (NOW() - INTERVAL 60 DAY)),
    (9003, 1, 9001, 'Elena',    'Ferraro',   'Studio Legale Ferraro',     'Avvocato',              'e.ferraro@studioferraro.legal', '+39 06 4890221',  'Via del Corso 101, Roma',           41.90311000, 12.47898000, 'manual', NULL, 'consulenza', NULL, 0, (NOW() - INTERVAL 45 DAY)),
    (9004, 1, 9002, 'Davide',   'Colombo',   'ServerPlanet Hosting',      'Account manager',       'd.colombo@serverplanet.io',   '+39 02 8837012',  NULL, NULL, NULL, NULL, 'https://serverplanet.io', 'hosting', 'Rinnovo contratto hosting a fine mese.', 0, (NOW() - INTERVAL 200 DAY)),
    (9005, 1, 9002, 'Sara',     'Greco',     'PrintLab',                  'Commerciale',           'sara@printlab.it',            '+39 055 291845',  NULL, NULL, NULL, NULL, NULL, 'stampa,materiali', 'Stampe materiale fieristico: chiedere sempre il preventivo doppio.', 0, (NOW() - INTERVAL 120 DAY)),
    (9006, 1, 9003, 'Andrea',   'Fontana',   'Fontana & Partners',        'Consulente fiscale',    'a.fontana@fontanapartners.it', '+39 349 5518274', NULL, NULL, NULL, NULL, NULL, 'commercialista', 'Commercialista dello studio. Scadenze fiscali con lui.', 1, (NOW() - INTERVAL 300 DAY)),
    (9007, 1, 9003, 'Chiara',   'Ricci',     'Ricci Fotografia',          'Fotografa',             'ciao@chiararicci.photo',      '+39 340 9982716', NULL, NULL, NULL, NULL, 'https://chiararicci.photo', 'shooting,freelance', 'Shooting prodotti per i cataloghi clienti.', 0, (NOW() - INTERVAL 30 DAY)),
    (9008, 1, 9001, 'Paolo',    'Martini',   'Trattoria da Paolo',        'Titolare',              'info@trattoriadapaolo.it',    '+39 051 234561',  'Via Indipendenza 33, Bologna',      44.49890000, 11.34260000, 'manual', NULL, 'sito,menu', 'Vuole il menù digitale con QR code entro settembre.', 0, (NOW() - INTERVAL 14 DAY)),

    -- Contatti personali di altri utenti (rubriche non vuote)
    (9009, 3, NULL, 'Federico', 'Villa',     'Villa Web Agency',          'CTO',                   'federico@villaweb.dev',       '+39 333 1029384', NULL, NULL, NULL, NULL, NULL, NULL, 'Ex collega, sentirlo per la partnership white-label.', 0, (NOW() - INTERVAL 10 DAY)),
    (9010, 6, NULL, 'Support',  'JetBrains', 'JetBrains',                 'Supporto licenze',      'support@jetbrains.com',       NULL,              NULL, NULL, NULL, NULL, 'https://www.jetbrains.com', 'licenze', NULL, 0, (NOW() - INTERVAL 50 DAY)),
    (9011, 8, NULL, 'Martina',  'Conti',     'Influencer',                'Content creator',       'martina.conti@collab.social', '+39 347 6651209', NULL, NULL, NULL, NULL, NULL, 'campagna,social', 'Collaborazione per la campagna lancio: 3 post + 2 stories.', 1, (NOW() - INTERVAL 7 DAY)),
    (9012, 1, 9002, 'Luca',     'Esposito',  'CleanOffice',               'Referente pulizie',     'info@cleanoffice.na.it',      '+39 081 5540872', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, (NOW() - INTERVAL 400 DAY));

-- Ricorrenze: compleanno cliente + rinnovo contratto hosting
INSERT IGNORE INTO `contact_recurrences`
    (`id`, `contatto_id`, `user_id`, `tipo`, `titolo`, `data_ricorrenza`, `annuale`, `promemoria_giorni_prima`, `notifica_giorno_stesso`)
VALUES
    (9001, 9001, 1, 'compleanno',   'Compleanno Giulia Rossetti',            (CURDATE() + INTERVAL 12 DAY), 1, 3, 1),
    (9002, 9004, 1, 'anniversario', 'Rinnovo contratto hosting ServerPlanet', (CURDATE() + INTERVAL 20 DAY), 1, 14, 1);

-- Il contatto del commercialista è condiviso con i manager (role 2)
INSERT IGNORE INTO `contact_shares` (`id`, `contatto_id`, `role_id`, `shared_by_user_id`) VALUES
    (9001, 9006, 2, 1);
