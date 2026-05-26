ALTER TABLE sr_content_items
    ADD COLUMN asset_action_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER asset_charge_policy,
    ADD COLUMN asset_action_module VARCHAR(20) NOT NULL DEFAULT '' AFTER asset_action_enabled,
    ADD COLUMN asset_action_amount BIGINT NOT NULL DEFAULT 0 AFTER asset_action_module,
    ADD COLUMN asset_action_direction VARCHAR(20) NOT NULL DEFAULT 'grant' AFTER asset_action_amount,
    ADD COLUMN asset_action_label VARCHAR(80) NOT NULL DEFAULT '완료' AFTER asset_action_direction,
    ADD KEY idx_sr_content_items_asset_action (asset_action_enabled, asset_action_module);

ALTER TABLE sr_content_revisions
    ADD COLUMN asset_action_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER asset_charge_policy,
    ADD COLUMN asset_action_module VARCHAR(20) NOT NULL DEFAULT '' AFTER asset_action_enabled,
    ADD COLUMN asset_action_amount BIGINT NOT NULL DEFAULT 0 AFTER asset_action_module,
    ADD COLUMN asset_action_direction VARCHAR(20) NOT NULL DEFAULT 'grant' AFTER asset_action_amount,
    ADD COLUMN asset_action_label VARCHAR(80) NOT NULL DEFAULT '완료' AFTER asset_action_direction;

CREATE TABLE IF NOT EXISTS sr_content_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    original_name VARCHAR(120) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    asset_download_enabled TINYINT(1) NOT NULL DEFAULT 0,
    asset_module VARCHAR(20) NOT NULL DEFAULT '',
    asset_download_amount BIGINT NOT NULL DEFAULT 0,
    asset_charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_content_files_content_status (content_id, status, id),
    KEY idx_sr_content_files_asset_download (asset_download_enabled, asset_module),
    KEY idx_sr_content_files_storage (storage_driver, storage_key),
    KEY idx_sr_content_files_checksum (checksum_sha256)
);

CREATE TABLE IF NOT EXISTS sr_content_asset_action_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reference_type VARCHAR(60) NOT NULL,
    reference_id VARCHAR(120) NOT NULL,
    action_key VARCHAR(40) NOT NULL DEFAULT 'complete',
    direction VARCHAR(20) NOT NULL,
    amount BIGINT NOT NULL,
    dedupe_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_asset_action_dedupe (dedupe_key),
    KEY idx_sr_content_asset_action_account (account_id, created_at),
    KEY idx_sr_content_asset_action_content (content_id, account_id),
    KEY idx_sr_content_asset_action_transaction (asset_module, transaction_id)
);

UPDATE sr_modules
SET version = '2026.05.004'
WHERE module_key = 'content';
