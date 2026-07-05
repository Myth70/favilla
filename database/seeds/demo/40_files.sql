-- ============================================================================
-- Demo — Files (file reali copiati dal seeder in public/uploads/files/)
-- Gli stored_name qui sotto DEVONO combaciare con la mappa di copia in
-- App\Setup\DemoSeeder; size e sha256 sono quelli reali degli asset in
-- database/seeds/demo/files/.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `files`
    (`id`, `original_name`, `stored_name`, `directory`, `mime_type`, `extension`, `size_bytes`, `checksum_sha256`, `folder`, `description`, `tags`, `visibility`, `created_by`, `created_at`)
VALUES
    (9001, 'Contratto Rossetti firmato.pdf', 'demo-contratto-rossetti.pdf', 'files', 'application/pdf', 'pdf', 19958, '32b346ae0ab08afead3c1b72120243dd20bccc75485d0d707d516b345edd11ff', '', 'Contratto di sviluppo e-commerce controfirmato dal cliente.', 'contratti,clienti', 'private',  1, (NOW() - INTERVAL 30 DAY)),
    (9002, 'Logo Aurora Studio.png',         'demo-logo-aurora.png',        'files', 'image/png',       'png', 1619,  '856b4dd05deaeb82d3861d51dd8595b15a010d74872cd797e6958fba5729eab6', '', 'Logo ufficiale in versione positiva.',                        'brand',            'internal', 8, (NOW() - INTERVAL 120 DAY)),
    (9003, 'Listino servizi 2026.csv',       'demo-listino-2026.csv',       'files', 'text/csv',        'csv', 270,   '0cce63ca97cb93048a0af35b44906424988bcffe95074757464692eff33bb29e', '', 'Listino interno — non inviare ai clienti senza sconto applicato.', 'commerciale',  'private',  1, (NOW() - INTERVAL 15 DAY)),
    (9004, 'Verbale kickoff gestionale.txt', 'demo-verbale-kickoff.txt',    'files', 'text/plain',      'txt', 301,   'a4fbeda78b3dcf8bb049190a25d6f9186271f7eb2217f4819edaf730feae5ef2', '', 'Note della riunione di avvio del gestionale interno.',        'progetti',         'internal', 3, (NOW() - INTERVAL 21 DAY)),
    (9005, 'Moodboard campagna lancio.png',  'demo-moodboard-campagna.png', 'files', 'image/png',       'png', 1453,  '62253dca389ab3d4dc6f12f90af91e528320575687d67f3f0e37f9052ba8f04e', '', 'Palette e riferimenti visivi per la campagna.',               'campagna,design',  'private',  8, (NOW() - INTERVAL 7 DAY)),
    (9006, 'Guida di stile Aurora.pdf',      'demo-guida-stile.pdf',        'files', 'application/pdf', 'pdf', 20142, '4124fef310a7afb18d891f53d3d829f94ba5be91dfee049a6085ef8e1b61cceb', '', 'Brand guideline: logo, colori, tipografia.',                  'brand,design',     'internal', 1, (NOW() - INTERVAL 60 DAY));

-- Versione precedente della guida di stile (storico versioni)
INSERT IGNORE INTO `file_versions`
    (`id`, `file_id`, `version_no`, `original_name`, `stored_name`, `mime_type`, `extension`, `size_bytes`, `checksum_sha256`, `created_by`, `created_at`)
VALUES
    (9001, 9006, 1, 'Guida di stile Aurora (bozza).pdf', 'demo-guida-stile-v1.pdf', 'application/pdf', 'pdf', 20142, '4124fef310a7afb18d891f53d3d829f94ba5be91dfee049a6085ef8e1b61cceb', 1, (NOW() - INTERVAL 90 DAY));

-- Condivisioni: listino ai manager, moodboard all'admin
INSERT IGNORE INTO `file_shares` (`id`, `file_id`, `target_type`, `target_id`, `permission`, `created_by`) VALUES
    (9001, 9003, 'role', 2, 'view', 1),
    (9002, 9005, 'user', 1, 'view', 8);
