ALTER TABLE sr_content_items
    ADD COLUMN asset_access_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN asset_module VARCHAR(20) NOT NULL DEFAULT 'point' AFTER asset_access_enabled,
    ADD COLUMN asset_access_amount BIGINT NOT NULL DEFAULT 0 AFTER asset_module,
    ADD COLUMN asset_charge_policy VARCHAR(20) NOT NULL DEFAULT 'once' AFTER asset_access_amount,
    ADD KEY idx_sr_content_items_asset_access (asset_access_enabled, asset_module);

ALTER TABLE sr_content_revisions
    ADD COLUMN asset_access_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN asset_module VARCHAR(20) NOT NULL DEFAULT 'point' AFTER asset_access_enabled,
    ADD COLUMN asset_access_amount BIGINT NOT NULL DEFAULT 0 AFTER asset_module,
    ADD COLUMN asset_charge_policy VARCHAR(20) NOT NULL DEFAULT 'once' AFTER asset_access_amount;

CREATE TABLE IF NOT EXISTS sr_content_asset_access_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reference_type VARCHAR(60) NOT NULL,
    reference_id VARCHAR(120) NOT NULL,
    access_kind VARCHAR(40) NOT NULL DEFAULT 'view',
    charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    amount BIGINT NOT NULL,
    dedupe_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_asset_access_dedupe (dedupe_key),
    KEY idx_sr_content_asset_access_account (account_id, created_at),
    KEY idx_sr_content_asset_access_content (content_id, account_id),
    KEY idx_sr_content_asset_access_transaction (asset_module, transaction_id)
);

UPDATE sr_modules
SET version = '2026.05.003'
WHERE module_key = 'content';
