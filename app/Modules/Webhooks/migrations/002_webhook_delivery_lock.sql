-- ============================================================================
-- Webhooks — lease di consegna per evitare il double-send concorrente.
-- Aggiunge lo stato 'processing' e la colonna locked_at usati dal claim atomico
-- del dispatcher. Idempotente (ADD COLUMN / MODIFY IF NOT EXISTS su MariaDB).
-- Speculare a database/schema.sql e alla CREATE aggiornata in 001_webhooks.sql.
-- ============================================================================

ALTER TABLE `webhook_deliveries`
  ADD COLUMN IF NOT EXISTS `locked_at` timestamp NULL DEFAULT NULL AFTER `next_retry_at`;

ALTER TABLE `webhook_deliveries`
  MODIFY `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending';
