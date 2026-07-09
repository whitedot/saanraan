CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}delivery_template_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key VARCHAR(190) NOT NULL,
    owner_module VARCHAR(60) NOT NULL,
    category VARCHAR(40) NOT NULL,
    subject_template VARCHAR(190) NOT NULL DEFAULT '',
    body_template TEXT NULL,
    link_template VARCHAR(255) NOT NULL DEFAULT '',
    channels_json TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    updated_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_delivery_template_overrides_key (template_key),
    KEY idx_sr_delivery_template_overrides_owner (owner_module, category, status),
    KEY idx_sr_delivery_template_overrides_updated (updated_at)
);
