-- ============================================================================
-- SSO OIDC: tabella dei collegamenti identità esterne + settings di
-- configurazione (group 'sso'). Prima migrazione core post-consolidamento
-- (lo stato al 2026-07-04 vive in schema.sql).
--
-- Idempotente: CREATE TABLE IF NOT EXISTS + INSERT IGNORE. Il DDL e i seed
-- sono replicati in database/schema.sql e database/seeds/required.sql perché
-- `migrate --fresh` non esegue le migrazioni core.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `oidc_identities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'oidc',
  `issuer` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `email_at_link` varchar(255) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oidc_identity` (`provider`,`issuer`,`subject`),
  UNIQUE KEY `uq_oidc_user_provider` (`user_id`,`provider`),
  CONSTRAINT `fk_oidc_identities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `app_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
    ('sso_oidc_enabled',          '0',                    'bool',   'sso', 'Abilita accesso SSO (OIDC)'),
    ('sso_oidc_issuer',           '',                     'string', 'sso', 'Issuer URL del provider'),
    ('sso_oidc_client_id',        '',                     'string', 'sso', 'Client ID'),
    ('sso_oidc_client_secret',    '',                     'string', 'sso', 'Client Secret'),
    ('sso_oidc_scopes',           'openid profile email', 'string', 'sso', 'Scope richiesti'),
    ('sso_oidc_button_label',     '',                     'string', 'sso', 'Etichetta pulsante login (vuoto = predefinita)'),
    ('sso_oidc_jit_enabled',      '0',                    'bool',   'sso', 'Crea automaticamente i nuovi utenti (JIT)'),
    ('sso_oidc_jit_default_role', 'user',                 'string', 'sso', 'Ruolo di default per utenti JIT'),
    ('sso_only',                  '0',                    'bool',   'sso', 'Solo SSO (nasconde il login con password)');
