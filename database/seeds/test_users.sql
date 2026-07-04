-- ============================================================================
-- Seed 10 utenti test (3 manager + 7 operatori)
-- Username e password: nome cognome in minuscolo senza reset password
--
-- ⚠️  SOLO PER SVILUPPO/DEMO — NON CARICARE IN PRODUZIONE.
--     Le credenziali sono deboli e prevedibili (password = username) e
--     gli account NON forzano il cambio password al primo accesso.
-- ============================================================================

SET NAMES utf8mb4;

-- Insert utenti test
INSERT INTO `users` (`name`, `email`, `username`, `password`, `is_active`, `must_change_password`) VALUES
    ('Luca Marinelli', 'lucamarinelli@favilla.test', 'lucamarinelli', '$argon2id$v=19$m=65536,t=4,p=1$NjlYckVQNHYwcGdJLlhObw$TnP+dYAGlhs+yRYqS9DanqrUQm8BJjsHIlwFQ1TaAeE', 1, 0),
    ('Riccardo Scamarcio', 'riccardoscamarcio@favilla.test', 'riccardoscamarcio', '$argon2id$v=19$m=65536,t=4,p=1$dEZVWklRbW1OZy9WTVZuZw$ONoGGU3SLVXuw8qbaQEG7zN+EsyXPVsT+CGGe5yt/tw', 1, 0),
    ('Alessio Boni', 'alessioboni@favilla.test', 'alessioboni', '$argon2id$v=19$m=65536,t=4,p=1$dzlBTG1pWVpKbktQT0prNw$bedEKFXuE+C42MN9ZS810W1oJF8pyHY96F4MpUSrFgI', 1, 0),
    ('Michele Morrone', 'michelemorrone@favilla.test', 'michelemorrone', '$argon2id$v=19$m=65536,t=4,p=1$R3FRd2V1TUF0a0RIdjhRcw$TivbXbMvcdtO+GPRx4erGiXiMTfu67CJlm3V1VOZBmw', 1, 0),
    ('Raoul Bova', 'raoulbova@favilla.test', 'raoulbova', '$argon2id$v=19$m=65536,t=4,p=1$eVVidVVnWFkuaUZ5alRQbA$86OAZ9XaORaYt0ccIMM5FDF+UndxumlVyPJJ2l0VdMU', 1, 0),
    ('Isabella Ragonese', 'isabellragonese@favilla.test', 'isabellragonese', '$argon2id$v=19$m=65536,t=4,p=1$dDVCdEFNY1pJRjYwTHpOMg$nUpCSw3KTJrmmo3IowzOZkBe3KMkw4+mkjikdaAkkEE', 1, 0),
    ('Micaela Ramazzotti', 'micaelaramazzotti@favilla.test', 'micaelaramazzotti', '$argon2id$v=19$m=65536,t=4,p=1$dkpaaVRpRkpML1VvSGQ5NA$OnVMRT/YVC8DOm1kuAD+P9hvsw+a55J0kSX1pdsEcPk', 1, 0),
    ('Paola Cortellesi', 'paolacortellesi@favilla.test', 'paolacortellesi', '$argon2id$v=19$m=65536,t=4,p=1$NTF5aVVMWXZpcFBQN3RrOQ$/CVRwmTRRyZvmtSaLi7oL5OBxmxliZm+uTrdIjxIDuo', 1, 0),
    ('Serena Rossi', 'serenarrossi@favilla.test', 'serenarrossi', '$argon2id$v=19$m=65536,t=4,p=1$TllnejQ1SGNFd0VDR3lSMQ$61W9r2+TnZI/jwT66kL1yaf6BuA6it8azTBJwm8jUeo', 1, 0),
    ('Valerina Costantino', 'valeriacostantino@favilla.test', 'valeriacostantino', '$argon2id$v=19$m=65536,t=4,p=1$dXExN1JvUlA0eTlLZHI5WA$JCjV4pL3/s4k2u0grF1dEzU8fXBwiG4HvGh8B3oQ1GQ', 1, 0);

-- Assegna ruoli ai nuovi utenti (ID 3-12): 3 manager + 7 user
INSERT INTO `user_role` (`user_id`, `role_id`) VALUES
    (3, 2),   -- Luca Marinelli - Manager
    (4, 2),   -- Riccardo Scamarcio - Manager
    (5, 2),   -- Alessio Boni - Manager
    (6, 3),   -- Michele Morrone - User
    (7, 3),   -- Raoul Bova - User
    (8, 3),   -- Isabella Ragonese - User
    (9, 3),   -- Micaela Ramazzotti - User
    (10, 3),  -- Paola Cortellesi - User
    (11, 3),  -- Serena Rossi - User
    (12, 3);  -- Valerina Costantino - User
