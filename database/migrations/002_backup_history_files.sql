-- ============================================================================
-- Backup completo (roadmap A1): lo storico backup registra anche il riepilogo
-- dei file utente inclusi nell'archivio (files_json) e size_bytes passa a
-- BIGINT (con gli upload inclusi un set può superare i 4 GB del vecchio INT).
--
-- Idempotente: ADD COLUMN IF NOT EXISTS (MariaDB) + MODIFY (rieseguibile).
-- Il DDL è replicato in database/schema.sql perché `migrate --fresh` non
-- esegue le migrazioni core.
-- ============================================================================

ALTER TABLE `backup_history`
  MODIFY `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0;

ALTER TABLE `backup_history`
  ADD COLUMN IF NOT EXISTS `files_json` longtext DEFAULT NULL AFTER `databases_json`;
