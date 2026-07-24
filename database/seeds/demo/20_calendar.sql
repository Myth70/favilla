-- ============================================================================
-- Demo — Calendar (eventi personali, condivisi per ruolo, pubblici + ricorrenti)
-- Ruoli: 1=admin, 2=manager, 3=user. Anno corrente dinamico via CONCAT(YEAR()).
-- Eventi ricorrenti (RRULE FREQ=WEEKLY/MONTHLY + recurrence_end) riempiono l'anno;
-- sommati agli eventi pubblici, ogni utente vede >15 occorrenze nell'anno.
-- ============================================================================

SET NAMES utf8mb4;

-- ---- Ricorrenti (riempiono tutto l'anno con poche righe) -------------------
INSERT IGNORE INTO `calendar_events`
    (`id`, `title`, `description`, `start_datetime`, `end_datetime`, `all_day`, `color`, `category`, `location`, `visibility`, `visible_to_role`, `reminder_minutes`, `recurrence_rule`, `recurrence_end`, `created_by`, `created_at`)
VALUES
    (9001, 'Weekly team sync', 'Allineamento settimanale di tutto lo studio: priorità e blocchi.',
        CONCAT(YEAR(CURDATE()),'-01-08 09:00:00'), CONCAT(YEAR(CURDATE()),'-01-08 09:30:00'),
        0, '#0d6efd', 'riunione', 'Google Meet', 'public', NULL, 15, 'FREQ=WEEKLY', CONCAT(YEAR(CURDATE()),'-12-23 23:59:59'), 1, (NOW() - INTERVAL 120 DAY)),
    (9002, 'Retrospettiva mensile', 'Cosa ha funzionato, cosa migliorare. Tutto il team.',
        CONCAT(YEAR(CURDATE()),'-01-31 16:00:00'), CONCAT(YEAR(CURDATE()),'-01-31 17:00:00'),
        0, '#6f42c1', 'riunione', 'Google Meet', 'public', NULL, 30, 'FREQ=MONTHLY', CONCAT(YEAR(CURDATE()),'-12-31 23:59:59'), 1, (NOW() - INTERVAL 120 DAY)),
    (9003, '1:1 Giulia · Marco', 'Punto quindicinale sul progetto Rossetti.',
        CONCAT(YEAR(CURDATE()),'-01-13 11:00:00'), CONCAT(YEAR(CURDATE()),'-01-13 11:30:00'),
        0, '#20c997', 'riunione', 'Meet', 'personal', NULL, 10, 'FREQ=WEEKLY;INTERVAL=2', CONCAT(YEAR(CURDATE()),'-12-15 23:59:59'), 3, (NOW() - INTERVAL 90 DAY)),
    (9004, 'Revisione pipeline commerciale', 'Solo responsabili: stato trattative e forecast.',
        CONCAT(YEAR(CURDATE()),'-01-20 15:00:00'), CONCAT(YEAR(CURDATE()),'-01-20 16:00:00'),
        0, '#fd7e14', 'riunione', 'Meet', 'role', 2, 30, 'FREQ=MONTHLY', CONCAT(YEAR(CURDATE()),'-12-31 23:59:59'), 4, (NOW() - INTERVAL 100 DAY));

-- ---- Eventi pubblici / condivisi, sparsi sull'anno ------------------------
INSERT IGNORE INTO `calendar_events`
    (`id`, `title`, `description`, `start_datetime`, `end_datetime`, `all_day`, `color`, `category`, `location`, `visibility`, `visible_to_role`, `reminder_minutes`, `created_by`, `created_at`)
VALUES
    (9010, 'Chiusura campagna Bottega Verde', 'Consegna finale e report al cliente.',
        CONCAT(YEAR(CURDATE()),'-03-12 15:00:00'), CONCAT(YEAR(CURDATE()),'-03-12 16:00:00'),
        0, '#198754', 'cliente', 'Videochiamata', 'public', NULL, 30, 4, (NOW() - INTERVAL 60 DAY)),
    (9011, 'Kickoff e-commerce Rossetti', 'Avvio ufficiale: obiettivi, milestone, assegnazioni.',
        (CURDATE() - INTERVAL 30 DAY) + INTERVAL 9 HOUR, (CURDATE() - INTERVAL 30 DAY) + INTERVAL 11 HOUR,
        0, '#0d6efd', 'riunione', 'Sala grande', 'public', NULL, NULL, 3, (NOW() - INTERVAL 32 DAY)),
    (9012, 'Demo e-commerce al cliente Rossetti', 'Stato avanzamento: catalogo, carrello, checkout.',
        (CURDATE() + INTERVAL 4 DAY) + INTERVAL 15 HOUR, (CURDATE() + INTERVAL 4 DAY) + INTERVAL 16 HOUR + INTERVAL 30 MINUTE,
        0, '#dc3545', 'cliente', 'Videochiamata', 'public', NULL, 60, 3, (NOW() - INTERVAL 5 DAY)),
    (9013, 'Go-live e-commerce Rossetti', 'Messa in produzione e formazione del cliente.',
        (CURDATE() + INTERVAL 60 DAY) + INTERVAL 9 HOUR, (CURDATE() + INTERVAL 60 DAY) + INTERVAL 13 HOUR,
        0, '#0d6efd', 'cliente', NULL, 'public', NULL, 1440, 3, (NOW() - INTERVAL 20 DAY)),
    (9014, 'Team offsite annuale', 'Due giorni di ritrovo dello studio: workshop + team building.',
        CONCAT(YEAR(CURDATE()),'-06-18'), CONCAT(YEAR(CURDATE()),'-06-20'),
        1, '#ffc107', 'team', 'Lago di Garda', 'public', NULL, 1440, 1, (NOW() - INTERVAL 45 DAY)),
    (9015, 'Chiusura estiva ufficio', 'Ferie collettive: studio chiuso.',
        CONCAT(YEAR(CURDATE()),'-08-11'), CONCAT(YEAR(CURDATE()),'-08-25'),
        1, '#198754', 'ferie', NULL, 'public', NULL, NULL, 1, (NOW() - INTERVAL 30 DAY)),
    (9016, 'Formazione interna: HTMX in pratica', 'Sessione tenuta da Julien, esempi dal gestionale.',
        CONCAT(YEAR(CURDATE()),'-10-09 14:00:00'), CONCAT(YEAR(CURDATE()),'-10-09 16:00:00'),
        0, '#6f42c1', 'formazione', 'Meet', 'public', NULL, 15, 8, (NOW() - INTERVAL 10 DAY)),
    (9017, 'Kickoff Nordlys Rebrand', 'Avvio progetto col nuovo cliente tedesco (Berlino).',
        CONCAT(YEAR(CURDATE()),'-11-05 10:00:00'), CONCAT(YEAR(CURDATE()),'-11-05 11:30:00'),
        0, '#0dcaf0', 'cliente', 'Videochiamata', 'public', NULL, 60, 4, (NOW() - INTERVAL 5 DAY)),
    (9018, 'Colloquio candidato frontend', NULL,
        (CURDATE() - INTERVAL 4 DAY) + INTERVAL 15 HOUR, (CURDATE() - INTERVAL 4 DAY) + INTERVAL 16 HOUR,
        0, '#6c757d', 'colloquio', 'Videochiamata', 'role', 2, NULL, 3, (NOW() - INTERVAL 6 DAY)),
    (9019, 'Aperitivo di team', 'Festeggiamo la consegna della campagna lancio!',
        (CURDATE() + INTERVAL 8 DAY) + INTERVAL 18 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 8 DAY) + INTERVAL 20 HOUR,
        0, '#ffc107', 'team', 'Bar Centrale', 'public', NULL, 60, 12, (NOW() - INTERVAL 1 DAY));

-- ---- Personali per ogni utente (sparsi sull'anno) ------------------------
INSERT IGNORE INTO `calendar_events`
    (`id`, `title`, `description`, `start_datetime`, `end_datetime`, `all_day`, `color`, `category`, `location`, `visibility`, `visible_to_role`, `reminder_minutes`, `created_by`, `created_at`)
VALUES
    -- Admin (1)
    (9030, 'Call banca — rinnovo fido', NULL, (CURDATE() + INTERVAL 2 DAY) + INTERVAL 10 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 2 DAY) + INTERVAL 11 HOUR, 0, '#20c997', 'personale', NULL, 'personal', NULL, 15, 1, (NOW() - INTERVAL 2 DAY)),
    (9031, 'Scadenza F24', 'Verificare col commercialista prima del pagamento.', CONCAT(YEAR(CURDATE()),'-05-16'), NULL, 1, '#dc3545', 'scadenza', NULL, 'personal', NULL, 1440, 1, (NOW() - INTERVAL 40 DAY)),
    (9032, 'Bilancio annuale', 'Chiusura conti con Fontana & Partners.', CONCAT(YEAR(CURDATE()),'-04-28 09:30:00'), CONCAT(YEAR(CURDATE()),'-04-28 11:00:00'), 0, '#6c757d', 'scadenza', NULL, 'personal', NULL, 1440, 1, (NOW() - INTERVAL 50 DAY)),
    -- Giulia (3)
    (9033, 'Dentista', NULL, CONCAT(YEAR(CURDATE()),'-02-19 17:00:00'), CONCAT(YEAR(CURDATE()),'-02-19 18:00:00'), 0, '#0d6efd', 'personale', NULL, 'personal', NULL, 60, 3, (NOW() - INTERVAL 30 DAY)),
    (9034, 'Ferie', 'Settimana bianca.', CONCAT(YEAR(CURDATE()),'-02-09'), CONCAT(YEAR(CURDATE()),'-02-14'), 1, '#198754', 'ferie', NULL, 'personal', NULL, NULL, 3, (NOW() - INTERVAL 60 DAY)),
    -- James (4)
    (9035, 'Fiera settore retail', 'Networking e nuovi lead.', CONCAT(YEAR(CURDATE()),'-09-24'), CONCAT(YEAR(CURDATE()),'-09-26'), 1, '#6610f2', 'viaggio', 'Milano', 'personal', NULL, 1440, 4, (NOW() - INTERVAL 15 DAY)),
    (9036, 'Palestra', NULL, CONCAT(YEAR(CURDATE()),'-07-02 07:00:00'), CONCAT(YEAR(CURDATE()),'-07-02 08:00:00'), 0, '#6610f2', 'personale', NULL, 'personal', NULL, NULL, 4, (NOW() - INTERVAL 20 DAY)),
    -- Anna (5)
    (9037, 'Certificazione ISO — audit', 'Preparazione documentale.', CONCAT(YEAR(CURDATE()),'-10-21 09:00:00'), CONCAT(YEAR(CURDATE()),'-10-21 13:00:00'), 0, '#198754', 'scadenza', NULL, 'personal', NULL, 1440, 5, (NOW() - INTERVAL 10 DAY)),
    (9038, 'Yoga', NULL, CONCAT(YEAR(CURDATE()),'-03-05 18:30:00'), CONCAT(YEAR(CURDATE()),'-03-05 19:30:00'), 0, '#198754', 'personale', NULL, 'personal', NULL, NULL, 5, (NOW() - INTERVAL 25 DAY)),
    -- Marco (6)
    (9039, 'Conferenza PHP', 'Talk su performance e architettura.', CONCAT(YEAR(CURDATE()),'-11-13'), CONCAT(YEAR(CURDATE()),'-11-14'), 1, '#20c997', 'formazione', 'Verona', 'personal', NULL, 1440, 6, (NOW() - INTERVAL 8 DAY)),
    (9040, 'Visita medica', NULL, CONCAT(YEAR(CURDATE()),'-05-27 15:00:00'), CONCAT(YEAR(CURDATE()),'-05-27 16:00:00'), 0, '#20c997', 'personale', NULL, 'personal', NULL, 120, 6, (NOW() - INTERVAL 12 DAY)),
    -- Emily (7)
    (9041, 'Corso copywriting', NULL, CONCAT(YEAR(CURDATE()),'-04-15 10:00:00'), CONCAT(YEAR(CURDATE()),'-04-15 13:00:00'), 0, '#d63384', 'formazione', 'Online', 'personal', NULL, 60, 7, (NOW() - INTERVAL 18 DAY)),
    (9042, 'Weekend a Londra', NULL, CONCAT(YEAR(CURDATE()),'-09-05'), CONCAT(YEAR(CURDATE()),'-09-08'), 1, '#d63384', 'viaggio', NULL, 'personal', NULL, NULL, 7, (NOW() - INTERVAL 22 DAY)),
    -- Julien (8)
    (9043, 'Rendez-vous notaire', NULL, CONCAT(YEAR(CURDATE()),'-06-11 09:00:00'), CONCAT(YEAR(CURDATE()),'-06-11 10:00:00'), 0, '#fd7e14', 'personale', NULL, 'personal', NULL, 120, 8, (NOW() - INTERVAL 14 DAY)),
    (9044, 'Congé', 'Vacances d''été en famille.', CONCAT(YEAR(CURDATE()),'-08-04'), CONCAT(YEAR(CURDATE()),'-08-10'), 1, '#fd7e14', 'ferie', NULL, 'personal', NULL, NULL, 8, (NOW() - INTERVAL 40 DAY)),
    -- Camille (9)
    (9045, 'Atelier design', 'Workshop typographie.', CONCAT(YEAR(CURDATE()),'-05-06 14:00:00'), CONCAT(YEAR(CURDATE()),'-05-06 17:00:00'), 0, '#6f42c1', 'formazione', 'Paris', 'personal', NULL, 60, 9, (NOW() - INTERVAL 16 DAY)),
    (9046, 'Anniversaire maman', NULL, CONCAT(YEAR(CURDATE()),'-07-19'), NULL, 1, '#6f42c1', 'personale', NULL, 'personal', NULL, 1440, 9, (NOW() - INTERVAL 5 DAY)),
    -- Lukas (10)
    (9047, 'Serverwartung', 'Geplantes Wartungsfenster nachts.', CONCAT(YEAR(CURDATE()),'-10-02 23:00:00'), CONCAT(YEAR(CURDATE()),'-10-03 02:00:00'), 0, '#0dcaf0', 'scadenza', NULL, 'personal', NULL, 60, 10, (NOW() - INTERVAL 9 DAY)),
    (9048, 'Urlaub', NULL, CONCAT(YEAR(CURDATE()),'-12-22'), CONCAT(YEAR(CURDATE()),'-12-31'), 1, '#0dcaf0', 'ferie', NULL, 'personal', NULL, NULL, 10, (NOW() - INTERVAL 3 DAY)),
    -- Diego (11)
    (9049, 'Cita médica', NULL, CONCAT(YEAR(CURDATE()),'-03-27 09:30:00'), CONCAT(YEAR(CURDATE()),'-03-27 10:30:00'), 0, '#dc3545', 'personale', NULL, 'personal', NULL, 120, 11, (NOW() - INTERVAL 28 DAY)),
    (9050, 'Vacaciones', 'Semana en Andalucía.', CONCAT(YEAR(CURDATE()),'-07-14'), CONCAT(YEAR(CURDATE()),'-07-20'), 1, '#dc3545', 'ferie', NULL, 'personal', NULL, NULL, 11, (NOW() - INTERVAL 30 DAY)),
    -- Lucía (12)
    (9051, 'Webinar social media', NULL, CONCAT(YEAR(CURDATE()),'-04-09 11:00:00'), CONCAT(YEAR(CURDATE()),'-04-09 12:00:00'), 0, '#ffc107', 'formazione', 'Online', 'personal', NULL, 30, 12, (NOW() - INTERVAL 20 DAY)),
    (9052, 'Cumpleaños hermana', NULL, CONCAT(YEAR(CURDATE()),'-11-28'), NULL, 1, '#ffc107', 'personale', NULL, 'personal', NULL, 1440, 12, (NOW() - INTERVAL 6 DAY));
