-- ============================================================================
-- Demo — Calendar (eventi personali, condivisi per ruolo, pubblici)
-- Ruoli: 1=admin, 2=manager, 3=user. Date relative a NOW().
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `calendar_events`
    (`id`, `title`, `description`, `start_datetime`, `end_datetime`, `all_day`, `color`, `category`, `location`, `visibility`, `visible_to_role`, `reminder_minutes`, `created_by`, `created_at`)
VALUES
    -- Pubblici (visibili a tutti)
    (9001, 'Riunione settimanale Aurora', 'Allineamento su progetti e priorità della settimana.',
        (CURDATE() + INTERVAL 1 DAY) + INTERVAL 9 HOUR, (CURDATE() + INTERVAL 1 DAY) + INTERVAL 10 HOUR,
        0, '#0d6efd', 'riunione', 'Sala grande', 'public', NULL, 30, 1, (NOW() - INTERVAL 20 DAY)),
    (9002, 'Demo sito e-commerce al cliente Rossetti', 'Presentazione dello stato di avanzamento: catalogo, carrello, checkout.',
        (CURDATE() + INTERVAL 4 DAY) + INTERVAL 15 HOUR, (CURDATE() + INTERVAL 4 DAY) + INTERVAL 16 HOUR + INTERVAL 30 MINUTE,
        0, '#dc3545', 'cliente', 'Videochiamata', 'public', NULL, 60, 3, (NOW() - INTERVAL 5 DAY)),
    (9003, 'Chiusura estiva ufficio', 'Ferie collettive: ufficio chiuso.',
        (CURDATE() + INTERVAL 25 DAY), (CURDATE() + INTERVAL 39 DAY),
        1, '#198754', 'ferie', NULL, 'public', NULL, NULL, 1, (NOW() - INTERVAL 15 DAY)),
    (9004, 'Formazione interna: HTMX in pratica', 'Sessione tenuta da Serena, esempi dal gestionale.',
        (CURDATE() + INTERVAL 6 DAY) + INTERVAL 14 HOUR, (CURDATE() + INTERVAL 6 DAY) + INTERVAL 16 HOUR,
        0, '#6f42c1', 'formazione', 'Sala piccola', 'public', NULL, 15, 11, (NOW() - INTERVAL 3 DAY)),

    -- Condivisi con i manager (role 2)
    (9005, 'Revisione pipeline commerciale Q3', 'Solo responsabili: stato trattative e forecast.',
        (CURDATE() + INTERVAL 3 DAY) + INTERVAL 11 HOUR, (CURDATE() + INTERVAL 3 DAY) + INTERVAL 12 HOUR,
        0, '#fd7e14', 'riunione', 'Sala grande', 'role', 2, 30, 1, (NOW() - INTERVAL 4 DAY)),

    -- Personali dell'admin
    (9006, 'Call banca — rinnovo fido', NULL,
        (CURDATE() + INTERVAL 2 DAY) + INTERVAL 10 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 2 DAY) + INTERVAL 11 HOUR,
        0, '#20c997', 'personale', NULL, 'personal', NULL, 15, 1, (NOW() - INTERVAL 2 DAY)),
    (9007, 'Scadenza F24', 'Verificare con il commercialista prima del pagamento.',
        (CURDATE() + INTERVAL 11 DAY), NULL,
        1, '#dc3545', 'scadenza', NULL, 'personal', NULL, 1440, 1, (NOW() - INTERVAL 9 DAY)),

    -- Passati (storico credibile)
    (9008, 'Kickoff progetto gestionale interno', 'Avvio ufficiale: obiettivi, milestone, assegnazioni.',
        (CURDATE() - INTERVAL 21 DAY) + INTERVAL 9 HOUR + INTERVAL 30 MINUTE, (CURDATE() - INTERVAL 21 DAY) + INTERVAL 11 HOUR,
        0, '#0d6efd', 'riunione', 'Sala grande', 'public', NULL, NULL, 1, (NOW() - INTERVAL 25 DAY)),
    (9009, 'Colloquio candidato frontend', NULL,
        (CURDATE() - INTERVAL 4 DAY) + INTERVAL 15 HOUR, (CURDATE() - INTERVAL 4 DAY) + INTERVAL 16 HOUR,
        0, '#6c757d', 'colloquio', 'Videochiamata', 'role', 2, NULL, 3, (NOW() - INTERVAL 6 DAY)),
    (9010, 'Aperitivo di team', 'Festeggiamo la consegna della campagna lancio!',
        (CURDATE() + INTERVAL 8 DAY) + INTERVAL 18 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 8 DAY) + INTERVAL 20 HOUR,
        0, '#ffc107', 'team', 'Bar Centrale', 'public', NULL, 60, 8, (NOW() - INTERVAL 1 DAY));
