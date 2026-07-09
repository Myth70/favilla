-- ============================================================================
-- API — Personal Access Token per l'autenticazione stateless dell'API pubblica.
-- Il token in chiaro (prefisso favilla_pat_) è mostrato una sola volta; a riposo
-- si conserva solo lo sha256. scopes JSON NULL = tutti i permessi dell'utente.
-- Speculare a database/schema.sql; questa migration copre gli upgrade. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `scopes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scopes`) OR `scopes` IS NULL),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personal_access_tokens_hash` (`token_hash`),
  KEY `idx_personal_access_tokens_user` (`user_id`),
  CONSTRAINT `fk_personal_access_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kill-switch e default API (gruppo 'api').
INSERT IGNORE INTO `app_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
    ('api_enabled',              '1',   'bool', 'api', 'Abilita API pubblica (REST v1)'),
    ('api_rate_limit_per_minute','120', 'int',  'api', 'Limite richieste API per minuto per token');
