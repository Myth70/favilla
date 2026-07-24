-- ============================================================================
-- Seed 10 utenti demo — team remoto internazionale "Aurora Studio"
-- 2 per nazionalità coperta (it/en/fr/de/es), M+F: 3 manager + 7 user.
-- Username = nome+cognome minuscolo senza accenti; password = username.
--
-- ⚠️  SOLO PER SVILUPPO/DEMO — NON CARICARE IN PRODUZIONE.
--     Credenziali deboli e prevedibili (password = username) e gli account
--     NON forzano il cambio password al primo accesso.
-- ============================================================================

SET NAMES utf8mb4;

-- Insert utenti demo — ID espliciti 3-12 (cablati dai seed demo e dalle
-- assegnazioni ruolo qui sotto) + INSERT IGNORE: ricaricabile senza errori.
-- avatar_path punta ai file copiati dal DemoSeeder in public/uploads/avatars/.
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `username`, `password`, `is_active`, `must_change_password`, `avatar_path`) VALUES
    (3,  'Giulia Ferrari',   'giuliaferrari@favilla.test',   'giuliaferrari',   '$argon2id$v=19$m=65536,t=4,p=1$YVh6QTNjUi84TUJ3TnRRbA$e1yUDKnVmWH0etHCVrptjNoB7+dyS50tHGOKQUbmrQw', 1, 0, 'demo-avatar-giuliaferrari.jpg'),
    (4,  'James Whitaker',   'jameswhitaker@favilla.test',   'jameswhitaker',   '$argon2id$v=19$m=65536,t=4,p=1$SjFOVUM5OGwucFdyQTVhUA$8l3itICdtOK7SOeVOUk1SoNKxez2UNJBUmPmr9tY2A0', 1, 0, 'demo-avatar-jameswhitaker.jpg'),
    (5,  'Anna Weber',       'annaweber@favilla.test',       'annaweber',       '$argon2id$v=19$m=65536,t=4,p=1$MHdURkp6Q2hYUTBYMHZkNQ$Gs8mYdEk/3d1grjKW/C20JzU29hLx2wGKClsE9Q85SI', 1, 0, 'demo-avatar-annaweber.jpg'),
    (6,  'Marco Bianchi',    'marcobianchi@favilla.test',    'marcobianchi',    '$argon2id$v=19$m=65536,t=4,p=1$T1F3aG9ocmY4TFd2U1dJcg$AwnVBQdg4XN1+Cg7e+jLnxINcWoPfAxjwN4ILzJRzYw', 1, 0, 'demo-avatar-marcobianchi.jpg'),
    (7,  'Emily Turner',     'emilyturner@favilla.test',     'emilyturner',     '$argon2id$v=19$m=65536,t=4,p=1$aEkzcGlHdFVGWnluM0U1Yw$eJLQaaVsDsoD9HOlGJBtf0LVGgTTpbjKcCGltcnIv0g', 1, 0, 'demo-avatar-emilyturner.jpg'),
    (8,  'Julien Moreau',    'julienmoreau@favilla.test',    'julienmoreau',    '$argon2id$v=19$m=65536,t=4,p=1$REM2a3FXQUJzRFNRWkU1Zw$RQr0GtwHMfKhXrtw8h3We1L6uhsqXfQVryGIAknLYrE', 1, 0, 'demo-avatar-julienmoreau.jpg'),
    (9,  'Camille Laurent',  'camillelaurent@favilla.test',  'camillelaurent',  '$argon2id$v=19$m=65536,t=4,p=1$Y3F6OUYucnguODIzMnJERA$fxwk3gJ2bFwlGjN//z6HCcQ+KAIEAfD9/tA7yU+5GmE', 1, 0, 'demo-avatar-camillelaurent.jpg'),
    (10, 'Lukas Braun',      'lukasbraun@favilla.test',      'lukasbraun',      '$argon2id$v=19$m=65536,t=4,p=1$c2RZNUtPOGdKdkQ4TzcuNQ$rXlFXV+V+aiwEOPG96RNsQBaBk9USxEiGdTlLOP6cwk', 1, 0, 'demo-avatar-lukasbraun.jpg'),
    (11, 'Diego Herrera',    'diegoherrera@favilla.test',    'diegoherrera',    '$argon2id$v=19$m=65536,t=4,p=1$bkduREVPVmJTZjNDem40cg$7L1/Ry9ZW+v0CGrXoDebK+it4a7vcHdoi6zlxhJ2oUc', 1, 0, 'demo-avatar-diegoherrera.jpg'),
    (12, 'Lucía Romero',     'luciaromero@favilla.test',     'luciaromero',     '$argon2id$v=19$m=65536,t=4,p=1$a2V3Qkl6U1N6VlZ2ZVlVMQ$FW2M0s1aPifmpd6wzuAYc5UksW93+WgmnqsjSsaj6I4', 1, 0, 'demo-avatar-luciaromero.jpg');

-- Assegna ruoli ai nuovi utenti (ID 3-12): 3 manager + 7 user
INSERT IGNORE INTO `user_role` (`user_id`, `role_id`) VALUES
    (3, 2),   -- Giulia Ferrari   - Manager (project lead)
    (4, 2),   -- James Whitaker   - Manager (account lead)
    (5, 2),   -- Anna Weber       - Manager (ops/quality)
    (6, 3),   -- Marco Bianchi    - User (backend)
    (7, 3),   -- Emily Turner     - User (content)
    (8, 3),   -- Julien Moreau    - User (frontend)
    (9, 3),   -- Camille Laurent  - User (design)
    (10, 3),  -- Lukas Braun      - User (devops)
    (11, 3),  -- Diego Herrera    - User (fullstack)
    (12, 3);  -- Lucía Romero     - User (social/marketing)
