-- ============================================================================
-- Demo — Reports (template + preset di stile + storico generazioni)
-- Sorgente reale: Contacts (source_key 'contacts') così i template generano
-- davvero. Sezione core (il modulo Reports è sempre presente).
-- ID fissi da 9001 + INSERT IGNORE.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `report_style_presets`
    (`id`, `name`, `description`, `is_default`, `primary_color`, `secondary_color`, `accent_color`, `header_bg_color`, `header_text_color`, `zebra_color`, `font_family`, `font_size_base`, `created_by`)
VALUES
    (9001, 'Aurora — Brand',    'Stile brand dello studio: blu Aurora e header scuro.', 0, '#0d6efd', '#64748b', '#fd7e14', '#0b1f3a', '#ffffff', '#eef4ff', 'dejavusans', 9, 1),
    (9002, 'Aurora — Minimal',  'Stile pulito per export interni.',                     0, '#198754', '#6c757d', '#20c997', '#1e293b', '#ffffff', '#f8fafc', 'dejavusans', 9, 1);

INSERT IGNORE INTO `report_templates`
    (`id`, `name`, `description`, `module`, `source_key`, `output_format`, `source_type`, `template_html`, `filters_config`, `sorting_config`, `style_preset_id`, `visibility`, `visible_to_roles`, `max_rows`, `created_by`)
VALUES
    (9001, 'Rubrica clienti (PDF)',     'Elenco contatti impaginato con lo stile brand.',       'contacts', 'contacts', 'pdf',   'list', NULL, NULL, '{"field":"cognome","dir":"ASC"}', 9001, 'global', NULL, 10000, 1),
    (9002, 'Contatti — export Excel',   'Estrazione completa dei contatti per elaborazioni.',   'contacts', 'contacts', 'excel', 'list', NULL, NULL, NULL, 9002, 'global', NULL, 10000, 1),
    (9003, 'Preferiti (CSV)',           'Solo i contatti marcati come preferiti.',              'contacts', 'contacts', 'csv',   'list', NULL, '{"preferito":"1"}', NULL, 9002, 'role', '[2]', 10000, 3);

INSERT IGNORE INTO `report_history`
    (`id`, `template_id`, `template_name`, `module`, `source_key`, `output_format`, `stored_filename`, `file_size`, `row_count`, `filters_used`, `generated_by`, `generated_at`)
VALUES
    (9001, 9001, 'Rubrica clienti (PDF)',   'contacts', 'contacts', 'pdf',   NULL, 84213, 14, NULL, 1, (NOW() - INTERVAL 2 DAY)),
    (9002, 9002, 'Contatti — export Excel', 'contacts', 'contacts', 'excel', NULL, 15320, 14, NULL, 3, (NOW() - INTERVAL 5 DAY)),
    (9003, 9003, 'Preferiti (CSV)',         'contacts', 'contacts', 'csv',   NULL, 640,   5,  '{"preferito":"1"}', 3, (NOW() - INTERVAL 9 DAY)),
    (9004, 9001, 'Rubrica clienti (PDF)',   'contacts', 'contacts', 'pdf',   NULL, 84980, 14, NULL, 4, CONCAT(YEAR(CURDATE()),'-03-20 10:15:00'));
