CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}notification_push_endpoints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    provider_key VARCHAR(30) NOT NULL,
    recipient_type VARCHAR(40) NOT NULL DEFAULT 'personal',
    endpoint_ciphertext TEXT NOT NULL,
    endpoint_fingerprint CHAR(64) NOT NULL,
    recipient_label VARCHAR(120) NOT NULL DEFAULT '',
    recipient_masked VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    key_version VARCHAR(30) NOT NULL DEFAULT 'v1',
    verified_at DATETIME NULL,
    disabled_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_notification_push_endpoint_fingerprint (provider_key, endpoint_fingerprint),
    KEY idx_sr_notification_push_endpoints_account (account_id, provider_key, status, id)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.008',
    updated_at = NOW()
WHERE module_key = 'notification';
