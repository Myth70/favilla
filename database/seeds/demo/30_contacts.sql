-- ============================================================================
-- Demo — Contacts (rubrica dell'admin: clienti/fornitori/partner + geo)
-- Clienti coerenti con NARRATIVE.md (Rossetti, Bottega Verde, Nordlys).
-- Contatti geolocalizzati internazionali per la vista mappa; ricorrenze per i
-- promemoria; 1 condivisione per ruolo. Rubriche non vuote anche per il team.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `contact_categories` (`id`, `user_id`, `nome`, `colore`) VALUES
    (9001, 1, 'Clienti',    '#0d6efd'),
    (9002, 1, 'Fornitori',  '#fd7e14'),
    (9003, 1, 'Partner',    '#198754');

INSERT IGNORE INTO `contacts`
    (`id`, `user_id`, `categoria_id`, `nome`, `cognome`, `azienda`, `ruolo`, `email`, `telefono`, `indirizzo`, `latitude`, `longitude`, `geocoding_source`, `sito_web`, `tags`, `note`, `preferito`, `created_at`)
VALUES
    -- Clienti (con referenti reali, distinti dal team)
    (9001, 1, 9001, 'Giulia',   'Rossetti',  'Rossetti Calzature S.r.l.', 'Titolare',               'giulia@rossetticalzature.it',  '+39 331 2045871', 'Via Montenapoleone 8, Milano',        45.46862000,  9.19582000, 'manual', 'https://www.rossetticalzature.it', 'e-commerce,retail', 'Committente del progetto e-commerce. Preferisce essere contattata al mattino.', 1, (NOW() - INTERVAL 120 DAY)),
    (9002, 1, 9001, 'Paolo',    'Sartori',   'Bottega Verde Bio',         'Responsabile marketing', 'p.sartori@bottegaverdebio.it', '+39 348 7712930', 'Corso Vittorio Emanuele 12, Torino',  45.06770000,  7.68682000, 'manual', 'https://www.bottegaverdebio.it', 'campagna,social', 'Referente della campagna di lancio bio (chiusa +38%).', 1, (NOW() - INTERVAL 90 DAY)),
    (9003, 1, 9001, 'Klara',    'Hoffmann',  'Nordlys Interiors GmbH',    'Head of Brand',          'k.hoffmann@nordlys-interiors.de', '+49 30 12045870', 'Torstraße 140, Berlin',            52.52969000, 13.40329000, 'manual', 'https://www.nordlys-interiors.de', 'rebrand,sito', 'Nuovo cliente: rebrand + sito. Parla inglese e tedesco.', 1, (NOW() - INTERVAL 20 DAY)),
    (9004, 1, 9001, 'Paolo',    'Marini',    'Trattoria da Paolo',        'Titolare',               'info@trattoriadapaolo.it',     '+39 051 234561',  'Via Indipendenza 33, Bologna',        44.49890000, 11.34260000, 'manual', NULL, 'sito,menu', 'Vuole il menù digitale con QR code entro l''autunno.', 0, (NOW() - INTERVAL 14 DAY)),
    -- Fornitori
    (9005, 1, 9002, 'Davide',   'Colombo',   'ServerPlanet Hosting',      'Account manager',        'd.colombo@serverplanet.io',    '+39 02 8837012',  NULL, NULL, NULL, NULL, 'https://serverplanet.io', 'hosting', 'Rinnovo contratto hosting a fine mese.', 0, (NOW() - INTERVAL 200 DAY)),
    (9006, 1, 9002, 'Sara',     'Greco',     'PrintLab',                  'Commerciale',            'sara@printlab.it',             '+39 055 291845',  'Via Nazionale 5, Firenze',            43.77925000, 11.24626000, 'manual', NULL, 'stampa,materiali', 'Stampe materiale fieristico: chiedere sempre il preventivo doppio.', 0, (NOW() - INTERVAL 150 DAY)),
    (9007, 1, 9002, 'Luca',     'Esposito',  'CleanOffice',               'Referente pulizie',      'info@cleanoffice.na.it',       '+39 081 5540872', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, (NOW() - INTERVAL 400 DAY)),
    -- Partner
    (9008, 1, 9003, 'Andrea',   'Fontana',   'Fontana & Partners',        'Consulente fiscale',     'a.fontana@fontanapartners.it', '+39 349 5518274', 'Via Roma 20, Milano',                 45.46270000,  9.18800000, 'manual', NULL, 'commercialista', 'Commercialista dello studio. Scadenze fiscali con lui.', 1, (NOW() - INTERVAL 300 DAY)),
    (9009, 1, 9003, 'Chiara',   'Ricci',     'Ricci Fotografia',          'Fotografa',              'ciao@chiararicci.photo',       '+39 340 9982716', NULL, NULL, NULL, NULL, 'https://chiararicci.photo', 'shooting,freelance', 'Shooting prodotti per i cataloghi clienti.', 0, (NOW() - INTERVAL 30 DAY)),
    (9010, 1, 9003, 'Sophie',   'Dubois',    'Atelier Lumière',           'Art director',           'sophie@atelier-lumiere.fr',    '+33 1 45872030',  '18 Rue de Rivoli, Paris',             48.85661000,  2.35222000, 'manual', 'https://atelier-lumiere.fr', 'partner,design', 'Partner freelance per progetti di brand internazionali.', 0, (NOW() - INTERVAL 70 DAY)),

    -- Contatti personali del team (rubriche non vuote)
    (9020, 3, NULL, 'Federico', 'Villa',     'Villa Web Agency',          'CTO',                    'federico@villaweb.dev',        '+39 333 1029384', NULL, NULL, NULL, NULL, NULL, NULL, 'Ex collega, sentirlo per la partnership white-label.', 0, (NOW() - INTERVAL 10 DAY)),
    (9021, 6, NULL, 'Support',  'JetBrains', 'JetBrains',                 'Supporto licenze',       'support@jetbrains.com',        NULL,              NULL, NULL, NULL, NULL, 'https://www.jetbrains.com', 'licenze', NULL, 0, (NOW() - INTERVAL 50 DAY)),
    (9022, 12, NULL,'Martina',  'Conti',     'Influencer',                'Content creator',        'martina.conti@collab.social',  '+39 347 6651209', NULL, NULL, NULL, NULL, NULL, 'campagna,social', 'Collaborazione campagna: 3 post + 2 stories.', 1, (NOW() - INTERVAL 7 DAY)),
    (9023, 10, NULL,'Support',  'Hetzner',   'Hetzner',                   'Infrastructure',         'support@hetzner.com',          NULL,              NULL, NULL, NULL, NULL, 'https://www.hetzner.com', 'hosting,infra', NULL, 0, (NOW() - INTERVAL 60 DAY));

-- Ricorrenze: compleanno cliente + rinnovo contratto hosting
INSERT IGNORE INTO `contact_recurrences`
    (`id`, `contatto_id`, `user_id`, `tipo`, `titolo`, `data_ricorrenza`, `annuale`, `promemoria_giorni_prima`, `notifica_giorno_stesso`)
VALUES
    (9001, 9001, 1, 'compleanno',   'Compleanno Giulia Rossetti',             CONCAT(YEAR(CURDATE()),'-06-14'), 1, 3, 1),
    (9002, 9005, 1, 'anniversario', 'Rinnovo contratto hosting ServerPlanet', (CURDATE() + INTERVAL 20 DAY),    1, 14, 1),
    (9003, 9003, 1, 'evento',       'Check-in trimestrale Nordlys',           CONCAT(YEAR(CURDATE()),'-11-15'), 0, 7, 1);

-- Il contatto del commercialista è condiviso con i manager (role 2)
INSERT IGNORE INTO `contact_shares` (`id`, `contatto_id`, `role_id`, `shared_by_user_id`) VALUES
    (9001, 9008, 2, 1);
