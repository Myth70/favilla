-- ============================================================================
-- Demo — Integrazioni (Webhooks + API token), INERTI per sicurezza.
-- L'endpoint webhook è DISABILITATO verso un URL fittizio; il token API è
-- REVOCATO (nessun segreto reale, solo un hash placeholder). Mostra l'epic
-- Reach & Integrazioni senza rischi. Sezione core. ID fissi da 9001.
-- ============================================================================

SET NAMES utf8mb4;

-- Endpoint webhook DISABILITATO (is_active=0), URL fittizio.
INSERT IGNORE INTO `webhook_endpoints`
    (`id`, `url`, `secret`, `event_types`, `description`, `is_active`, `created_by`, `created_at`)
VALUES
    (9001, 'https://example.com/aurora-demo-webhook', 'demo-secret-not-a-real-key', '["task.created","calendar.event.created","document.published"]', 'Endpoint dimostrativo (disabilitato) verso un servizio esterno fittizio.', 0, 1, (NOW() - INTERVAL 25 DAY));

-- Delivery storiche simulate (una consegnata, una fallita)
INSERT IGNORE INTO `webhook_deliveries`
    (`id`, `endpoint_id`, `event_type`, `payload`, `status`, `attempts`, `response_code`, `last_error`, `delivered_at`, `created_at`)
VALUES
    (9001, 9001, 'task.created',            '{"id":9007,"title":"Pianificare sprint e-commerce Rossetti","user_id":3}', 'sent',   1, 200,  NULL,                       (NOW() - INTERVAL 24 DAY), (NOW() - INTERVAL 24 DAY)),
    (9002, 9001, 'document.published',      '{"id":9002,"titolo":"Contratto quadro servizi — Bottega Verde Bio"}',     'sent',   1, 200,  NULL,                       (NOW() - INTERVAL 20 DAY), (NOW() - INTERVAL 20 DAY)),
    (9003, 9001, 'calendar.event.created',  '{"id":9017,"title":"Kickoff Nordlys Rebrand"}',                            'failed', 3, 500,  'Connection timeout (endpoint disabilitato)', NULL, (NOW() - INTERVAL 5 DAY));

-- Token API DEMO — REVOCATO (inerte). token_hash è un placeholder, non un token reale.
INSERT IGNORE INTO `personal_access_tokens`
    (`id`, `user_id`, `name`, `token_hash`, `scopes`, `last_used_at`, `expires_at`, `revoked_at`, `created_at`)
VALUES
    (9001, 1, 'Demo integration token (revoked)', '0000000000000000000000000000000000000000000000000000000000000000', '["read:tasks","read:calendar"]', (NOW() - INTERVAL 26 DAY), NULL, (NOW() - INTERVAL 15 DAY), (NOW() - INTERVAL 40 DAY));
