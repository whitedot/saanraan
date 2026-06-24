ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions
    ADD COLUMN amount BIGINT NOT NULL DEFAULT 0 AFTER reference_id,
    ADD COLUMN currency_code VARCHAR(3) NOT NULL DEFAULT '' AFTER amount,
    ADD COLUMN asset_unit VARCHAR(40) NOT NULL DEFAULT '' AFTER currency_code,
    ADD COLUMN policy_summary VARCHAR(255) NOT NULL DEFAULT '' AFTER asset_unit,
    ADD COLUMN priced_at DATETIME NULL AFTER policy_summary,
    ADD COLUMN target_snapshot_json TEXT NULL AFTER priced_at;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'coupon';
