SET @schema_has_community_asset_logs_settlement_kind = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_asset_logs'
      AND COLUMN_NAME = 'settlement_kind'
);
SET @schema_sql = IF(
    @schema_has_community_asset_logs_settlement_kind = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_asset_logs ADD COLUMN settlement_kind VARCHAR(30) NOT NULL DEFAULT ''legacy_unknown'' AFTER purchase_power_snapshot_json',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_asset_logs_snapshot_schema_version = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_asset_logs'
      AND COLUMN_NAME = 'snapshot_schema_version'
);
SET @schema_sql = IF(
    @schema_has_community_asset_logs_snapshot_schema_version = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_asset_logs ADD COLUMN snapshot_schema_version VARCHAR(40) NOT NULL DEFAULT ''asset_settlement_snapshot_v1'' AFTER settlement_kind',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_asset_logs_rounding_policy_version = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_asset_logs'
      AND COLUMN_NAME = 'rounding_policy_version'
);
SET @schema_sql = IF(
    @schema_has_community_asset_logs_rounding_policy_version = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_asset_logs ADD COLUMN rounding_policy_version VARCHAR(40) NOT NULL DEFAULT ''asset_settlement_rounding_v1'' AFTER snapshot_schema_version',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}community_asset_logs
SET settlement_kind = CASE
        WHEN direction <> 'use' THEN 'free'
        WHEN settlement_amount > 0 THEN 'paid'
        WHEN amount = 0 THEN 'legacy_unknown'
        ELSE 'paid'
    END,
    purchase_power_snapshot_json = REPLACE(REPLACE(purchase_power_snapshot_json, '"policy_version":"asset_settlement_v1"', '"rounding_policy_version":"asset_settlement_rounding_v1"'), '"policy_version": "asset_settlement_v1"', '"rounding_policy_version": "asset_settlement_rounding_v1"'),
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.020',
    updated_at = NOW()
WHERE module_key = 'community';
