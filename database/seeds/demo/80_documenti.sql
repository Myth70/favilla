-- ============================================================================
-- Demo — Documenti (workflow completo: bozza → controllo → approvazione →
-- pubblicato/archiviato, protocolli sequenziati per categoria/anno).
-- Gli stored_name DEVONO combaciare con la mappa di copia del DemoSeeder
-- (destinazione: storage/uploads/documenti/demo/). Caricato solo se il
-- modulo Documenti è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `documenti_categorie`
    (`id`, `parent_id`, `nome`, `slug`, `codice`, `descrizione`, `colore`, `icona`, `approvazione_richiesta`, `ordine`, `created_by`)
VALUES
    (9001, NULL, 'Contratti', 'contratti', 'CTR', 'Contratti attivi e passivi con clienti e fornitori.', '#0d6efd', 'fa-file-signature', 1, 1, 1),
    (9002, NULL, 'Procedure', 'procedure', 'PRO', 'Procedure interne e policy dello studio.',            '#198754', 'fa-clipboard-list', 1, 2, 1);

INSERT IGNORE INTO `documenti_files`
    (`id`, `original_name`, `stored_name`, `directory`, `mime_type`, `extension`, `size_bytes`, `checksum_sha256`, `created_by`, `created_at`)
VALUES
    (9001, 'Procedura gestione ferie.pdf',   'demo-doc-procedura-ferie.pdf',    'demo', 'application/pdf', 'pdf', 17310, '4903165d87d970e7c17783a3386f7b13b1b4e79be1f49b4593a38c09c730d4db', 1, (NOW() - INTERVAL 6 DAY)),
    (9002, 'Contratto quadro Bottega Verde.pdf', 'demo-doc-contratto-quadro.pdf', 'demo', 'application/pdf', 'pdf', 18602, '05af88d9308199309609fe3a2159ca2c6439b1672ade080d6d0e27fe8e3bd118', 3, (NOW() - INTERVAL 25 DAY)),
    (9003, 'Politica di backup.pdf',         'demo-doc-politica-backup.pdf',    'demo', 'application/pdf', 'pdf', 17037, '65f3aca4b5e1e7fcfd48a6f703fc73ad9cdeb6760f6fc346e179e2f3edbdd841', 1, (NOW() - INTERVAL 40 DAY)),
    (9004, 'Offerta e-commerce 2026-14.pdf', 'demo-doc-offerta-ecommerce.pdf',  'demo', 'application/pdf', 'pdf', 19239, '54ee971e65ebde10fe769f8da8e82e1137739483eaff5573722bff33f6290d94', 3, (NOW() - INTERVAL 3 DAY)),
    (9005, 'Verbale riunione soci Q2.pdf',   'demo-doc-verbale-cda.pdf',        'demo', 'application/pdf', 'pdf', 16914, '4280cdfaf2cd052255c8200ab4f400d514b4cc1916096a4acd60144cecc9ad07', 1, (NOW() - INTERVAL 1 DAY)),
    (9006, 'Manuale brand identity.pdf',     'demo-doc-manuale-brand.pdf',      'demo', 'application/pdf', 'pdf', 20142, '4124fef310a7afb18d891f53d3d829f94ba5be91dfee049a6085ef8e1b61cceb', 8, (NOW() - INTERVAL 200 DAY));

INSERT IGNORE INTO `documenti`
    (`id`, `protocollo`, `titolo`, `descrizione`, `categoria_id`, `owner_user_id`, `versione_no`, `stato`, `approvazione_richiesta`, `step_corrente`, `pubblicato_il`, `scade_il`, `tag`, `created_by`, `created_at`)
VALUES
    (9001, NULL, 'Procedura gestione ferie e permessi', 'Regole per richiesta e approvazione di ferie e permessi.', 9002, 1, 1, 'in_approvazione', 1, 'approvazione', NULL, NULL, 'hr,procedure', 1, (NOW() - INTERVAL 6 DAY)),
    (9002, CONCAT('DOC-CTR-', YEAR(CURDATE()), '-0001'), 'Contratto quadro servizi — Bottega Verde Bio', 'Contratto quadro annuale con SLA.', 9001, 3, 1, 'pubblicato', 1, 'completato', (NOW() - INTERVAL 20 DAY), (NOW() + INTERVAL 345 DAY), 'clienti,contratti', 3, (NOW() - INTERVAL 25 DAY)),
    (9003, CONCAT('DOC-PRO-', YEAR(CURDATE()), '-0001'), 'Politica di backup e conservazione dati', 'Frequenze, cifratura e verifiche di ripristino.', 9002, 1, 1, 'pubblicato', 1, 'completato', (NOW() - INTERVAL 35 DAY), (NOW() + INTERVAL 160 DAY), 'sicurezza,policy', 1, (NOW() - INTERVAL 40 DAY)),
    (9004, NULL, 'Offerta commerciale n. 2026-14 — Sito e-commerce', 'Offerta per Rossetti Calzature, in verifica interna.', 9001, 3, 1, 'in_controllo', 1, 'controllo', NULL, NULL, 'offerte', 3, (NOW() - INTERVAL 3 DAY)),
    (9005, NULL, 'Verbale riunione soci — secondo trimestre', NULL, 9002, 1, 1, 'bozza', 1, 'redazione', NULL, NULL, 'soci', 1, (NOW() - INTERVAL 1 DAY)),
    (9006, CONCAT('DOC-PRO-', YEAR(CURDATE()), '-0002'), 'Manuale brand identity (edizione 2024)', 'Sostituito dalla guida di stile 2026 in Files.', 9002, 8, 1, 'archiviato', 1, 'completato', (NOW() - INTERVAL 200 DAY), NULL, 'brand', 8, (NOW() - INTERVAL 200 DAY));

INSERT IGNORE INTO `documenti_versioni`
    (`id`, `documento_id`, `versione_no`, `file_id`, `note_modifica`, `stato`, `created_by`, `created_at`, `pubblicato_il`)
VALUES
    (9001, 9001, 1, 9001, 'Prima stesura della procedura aggiornata.', 'in_approvazione', 1, (NOW() - INTERVAL 6 DAY), NULL),
    (9002, 9002, 1, 9002, NULL, 'pubblicato', 3, (NOW() - INTERVAL 25 DAY), (NOW() - INTERVAL 20 DAY)),
    (9003, 9003, 1, 9003, NULL, 'pubblicato', 1, (NOW() - INTERVAL 40 DAY), (NOW() - INTERVAL 35 DAY)),
    (9004, 9004, 1, 9004, 'Prima emissione per verifica interna.', 'in_controllo', 3, (NOW() - INTERVAL 3 DAY), NULL),
    (9005, 9005, 1, 9005, NULL, 'bozza', 1, (NOW() - INTERVAL 1 DAY), NULL),
    (9006, 9006, 1, 9006, NULL, 'pubblicato', 8, (NOW() - INTERVAL 200 DAY), (NOW() - INTERVAL 200 DAY));

-- Puntatori a versione/file corrente (UPDATE: idempotente per natura)
UPDATE `documenti` SET `versione_corrente_id` = 9001, `file_corrente_id` = 9001 WHERE `id` = 9001 AND `versione_corrente_id` IS NULL;
UPDATE `documenti` SET `versione_corrente_id` = 9002, `file_corrente_id` = 9002 WHERE `id` = 9002 AND `versione_corrente_id` IS NULL;
UPDATE `documenti` SET `versione_corrente_id` = 9003, `file_corrente_id` = 9003 WHERE `id` = 9003 AND `versione_corrente_id` IS NULL;
UPDATE `documenti` SET `versione_corrente_id` = 9004, `file_corrente_id` = 9004 WHERE `id` = 9004 AND `versione_corrente_id` IS NULL;
UPDATE `documenti` SET `versione_corrente_id` = 9005, `file_corrente_id` = 9005 WHERE `id` = 9005 AND `versione_corrente_id` IS NULL;
UPDATE `documenti` SET `versione_corrente_id` = 9006, `file_corrente_id` = 9006 WHERE `id` = 9006 AND `versione_corrente_id` IS NULL;

-- Storico approvazioni coerente con gli stati
INSERT IGNORE INTO `documenti_approvazioni` (`id`, `documento_id`, `versione_id`, `step`, `azione`, `user_id`, `note`, `created_at`) VALUES
    (9001, 9001, 9001, 'redazione',    'invia',   1, NULL, (NOW() - INTERVAL 5 DAY)),
    (9002, 9001, 9001, 'controllo',    'approva', 3, 'Verificata con HR, ok per approvazione.', (NOW() - INTERVAL 2 DAY)),
    (9003, 9002, 9002, 'redazione',    'invia',   3, NULL, (NOW() - INTERVAL 24 DAY)),
    (9004, 9002, 9002, 'controllo',    'approva', 5, NULL, (NOW() - INTERVAL 22 DAY)),
    (9005, 9002, 9002, 'approvazione', 'approva', 1, 'Approvato per la firma.', (NOW() - INTERVAL 20 DAY)),
    (9006, 9003, 9003, 'redazione',    'invia',   1, NULL, (NOW() - INTERVAL 38 DAY)),
    (9007, 9003, 9003, 'controllo',    'approva', 3, NULL, (NOW() - INTERVAL 37 DAY)),
    (9008, 9003, 9003, 'approvazione', 'approva', 1, NULL, (NOW() - INTERVAL 35 DAY)),
    (9009, 9004, 9004, 'redazione',    'invia',   3, NULL, (NOW() - INTERVAL 3 DAY)),
    (9010, 9004, 9004, 'controllo',    'prende_in_carico', 5, NULL, (NOW() - INTERVAL 2 DAY));

-- Collegamento tra documenti: l'offerta richiama il contratto quadro
INSERT IGNORE INTO `documenti_collegamenti` (`id`, `documento_origine_id`, `documento_destinazione_id`, `tipo`, `note`, `created_by`) VALUES
    (9001, 9004, 9002, 'riferimento', 'L''offerta richiama le condizioni del contratto quadro.', 3);

-- Sequenze protocollo allineate ai protocolli emessi sopra
INSERT IGNORE INTO `documenti_protocollo_sequenze` (`categoria_id`, `anno`, `ultimo_numero`) VALUES
    (9001, YEAR(CURDATE()), 1),
    (9002, YEAR(CURDATE()), 2);
