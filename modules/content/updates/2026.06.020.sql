ALTER TABLE sr_content_asset_access_logs
    ADD COLUMN settlement_kind VARCHAR(30) NOT NULL DEFAULT 'legacy_unknown' AFTER purchase_power_snapshot_json,
    ADD COLUMN snapshot_schema_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_snapshot_v1' AFTER settlement_kind,
    ADD COLUMN rounding_policy_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_rounding_v1' AFTER snapshot_schema_version;

ALTER TABLE sr_content_asset_action_logs
    ADD COLUMN settlement_kind VARCHAR(30) NOT NULL DEFAULT 'legacy_unknown' AFTER purchase_power_snapshot_json,
    ADD COLUMN snapshot_schema_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_snapshot_v1' AFTER settlement_kind,
    ADD COLUMN rounding_policy_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_rounding_v1' AFTER snapshot_schema_version;

UPDATE sr_content_asset_access_logs
SET settlement_kind = CASE
        WHEN settlement_amount > 0 THEN 'paid'
        WHEN amount = 0 THEN 'legacy_unknown'
        ELSE 'paid'
    END,
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1';

UPDATE sr_content_asset_action_logs
SET settlement_kind = CASE
        WHEN direction <> 'use' THEN 'free'
        WHEN settlement_amount > 0 THEN 'paid'
        WHEN amount = 0 THEN 'legacy_unknown'
        ELSE 'paid'
    END,
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1';

UPDATE sr_modules
SET version = '2026.06.020',
    updated_at = NOW()
WHERE module_key = 'content';
