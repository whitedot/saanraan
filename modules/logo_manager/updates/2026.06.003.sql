CREATE TABLE IF NOT EXISTS sr_logo_manager_icon_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    logo_id BIGINT UNSIGNED NOT NULL,
    batch_key VARCHAR(40) NOT NULL,
    variant_key VARCHAR(40) NOT NULL,
    purpose VARCHAR(30) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(255) NOT NULL,
    public_url VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(80) NOT NULL DEFAULT 'image/png',
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_logo_manager_icon_variants_logo_variant_batch (logo_id, variant_key, batch_key),
    KEY idx_sr_logo_manager_icon_variants_logo_status (logo_id, status, purpose, width),
    KEY idx_sr_logo_manager_icon_variants_storage (storage_driver, storage_key)
);

UPDATE sr_modules
SET version = '2026.06.003'
WHERE module_key = 'logo_manager'
  AND version < '2026.06.003';
