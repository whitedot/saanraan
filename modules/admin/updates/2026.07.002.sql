CREATE TABLE IF NOT EXISTS sr_admin_form_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    form_key VARCHAR(80) NOT NULL,
    context_key VARCHAR(190) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    base_fingerprint CHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_form_drafts_target (account_id, form_key, context_key),
    KEY idx_sr_admin_form_drafts_updated (updated_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'admin';
