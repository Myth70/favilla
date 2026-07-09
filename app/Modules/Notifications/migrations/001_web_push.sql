-- ============================================================================
-- Notifications — Web Push: subscription push per utente + canale 'web_push'
-- + chiavi VAPID in app_settings (gruppo 'notifications').
-- Speculare a database/schema.sql e database/seeds/required.sql (installazioni
-- fresh); questa migration copre gli upgrade. Idempotente.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `content_encoding` varchar(32) NOT NULL DEFAULT 'aes128gcm',
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_subscriptions_endpoint` (`endpoint_hash`),
  KEY `idx_push_subscriptions_user` (`user_id`),
  CONSTRAINT `fk_push_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canale di consegna (FK da deliveries/queue/preferences: deve esistere prima
-- di qualsiasi dispatch sul canale).
INSERT IGNORE INTO `notification_channels` (`slug`, `name`, `description`, `is_enabled`, `sort_order`) VALUES
    ('web_push', 'Web Push', 'Notifiche push su browser e app installata (PWA)', 1, 40);

-- Chiavi VAPID: generate dal pannello Admin → Notifiche (mai rigenerate in
-- automatico: una rigenerazione invalida tutte le subscription esistenti).
INSERT IGNORE INTO `app_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
    ('webpush_vapid_public_key',  '', 'string', 'notifications', 'Chiave pubblica VAPID (Web Push)'),
    ('webpush_vapid_private_key', '', 'string', 'notifications', 'Chiave privata VAPID (Web Push)'),
    ('webpush_subject',           '', 'string', 'notifications', 'Subject VAPID (URL o mailto: del gestore)');
