-- ================================================================
-- MIGRAZIONE DB — Template per nuovo modulo
-- ================================================================
--
-- ISTRUZIONI:
-- 1. Copia questa directory migrations/ nel tuo nuovo modulo
-- 2. Rinomina questo file come 001_nomemodulo.sql
-- 3. Il migration runner lo scopre automaticamente dalla cartella del modulo
-- 4. Esegui: php database/migrate.php
-- 5. Aggiorna $table nel Repository del modulo
--
-- CONVENZIONI:
-- - IF NOT EXISTS su tutte le CREATE TABLE (idempotenza)
-- - INSERT IGNORE per permessi (idempotenza)
-- - Charset: utf8mb4 / utf8mb4_unicode_ci
-- - Timestamps: created_at + updated_at
-- - Soft delete: deleted_at (vedi $softDelete nel Repository)
-- - FK autore: created_by -> users(id) ON DELETE SET NULL
-- - Nomi tabella: snake_case plurale
-- - Le colonne devono combaciare con $fillable del Repository e con l'ENUM status
--   usato da Controller/View.
-- ================================================================

CREATE TABLE IF NOT EXISTS examples (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NULL,
    description TEXT NULL,
    status      ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_examples_status  (status),
    INDEX idx_examples_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permessi ────────────────────────────────────────────────────

INSERT IGNORE INTO permissions (name, slug, module) VALUES
    ('Visualizza Example', 'example.view',   'Example'),
    ('Crea Example',       'example.create', 'Example'),
    ('Modifica Example',   'example.edit',   'Example'),
    ('Elimina Example',    'example.delete', 'Example');

-- Assegna tutti i permessi Example ad Administrator (id=1)
INSERT IGNORE INTO role_permission (role_id, permission_id)
    SELECT 1, id FROM permissions WHERE module = 'Example';
