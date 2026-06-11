ALTER TABLE sr_content_items
    ADD COLUMN asset_access_settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER asset_access_amount,
    ADD COLUMN asset_action_settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER asset_action_amount;

ALTER TABLE sr_content_revisions
    ADD COLUMN asset_access_settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER asset_access_amount,
    ADD COLUMN asset_action_settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER asset_action_amount;

ALTER TABLE sr_content_files
    ADD COLUMN asset_download_settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER asset_download_amount;

ALTER TABLE sr_content_asset_access_logs
    ADD COLUMN settlement_amount BIGINT NOT NULL DEFAULT 0 AFTER amount,
    ADD COLUMN settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER settlement_amount,
    ADD COLUMN purchase_power_snapshot_json TEXT NULL AFTER settlement_currency;

ALTER TABLE sr_content_asset_action_logs
    ADD COLUMN settlement_amount BIGINT NOT NULL DEFAULT 0 AFTER amount,
    ADD COLUMN settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER settlement_amount,
    ADD COLUMN purchase_power_snapshot_json TEXT NULL AFTER settlement_currency;

UPDATE sr_content_asset_access_logs
SET settlement_amount = amount,
    settlement_currency = 'KRW',
    purchase_power_snapshot_json = '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"KRW","currency_min_unit":1,"policy_version":"asset_settlement_v1"}'
WHERE settlement_amount = 0
  AND amount <> 0;

UPDATE sr_content_asset_action_logs
SET settlement_amount = amount,
    settlement_currency = 'KRW',
    purchase_power_snapshot_json = '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"KRW","currency_min_unit":1,"policy_version":"asset_settlement_v1"}'
WHERE settlement_amount = 0
  AND amount <> 0;

UPDATE sr_modules
SET version = '2026.06.019',
    updated_at = NOW()
WHERE module_key = 'content';
