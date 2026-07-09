-- ============================================================================
-- Webhooks — endpoint HTTP in uscita + coda di consegna con retry.
-- Ogni evento delle notifiche può fare fan-out anche come webhook firmato HMAC.
-- Speculare a database/schema.sql; questa migration copre gli upgrade. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(1024) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `event_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`event_types`)),
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_endpoints_active` (`is_active`,`deleted_at`),
  KEY `idx_webhook_endpoints_owner` (`created_by`),
  CONSTRAINT `fk_webhook_endpoints_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_id` int(10) unsigned NOT NULL,
  `event_type` varchar(150) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_deliveries_dispatch` (`status`,`next_retry_at`),
  KEY `idx_webhook_deliveries_endpoint` (`endpoint_id`),
  CONSTRAINT `fk_webhook_deliveries_endpoint` FOREIGN KEY (`endpoint_id`) REFERENCES `webhook_endpoints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job scheduler: drena la coda con backoff (un solo cron: php favilla scheduler:run).
INSERT IGNORE INTO `scheduler_jobs` (`slug`, `name`, `command`, `args_json`, `interval_minutes`, `enabled`) VALUES
    ('webhooks.dispatch', 'Consegna webhook in uscita', 'webhooks:dispatch', '[\"--limit=50\"]', 5, 1);

-- Permessi (idempotenti). La fonte è permissions.php (sync via context:generate);
-- qui li seminiamo per gli upgrade eseguiti solo con migrate.php.
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`) VALUES
    ('Visualizza Webhook', 'webhooks.view',   'Webhooks'),
    ('Gestisci Webhook',   'webhooks.manage', 'Webhooks');
