CREATE TABLE IF NOT EXISTS sr_logo_manager_logo_usage_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    logo_id BIGINT UNSIGNED NOT NULL,
    layout_provider_key VARCHAR(40) NOT NULL,
    slot_key VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_logo_manager_usage_logo_provider_slot (logo_id, layout_provider_key, slot_key),
    KEY idx_sr_logo_manager_usage_lookup (layout_provider_key, slot_key, logo_id)
);
