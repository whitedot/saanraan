SET @schema_has_coupon_issues_claim_type = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'claim_type'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_claim_type = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN claim_type VARCHAR(20) NOT NULL DEFAULT ''manual'' AFTER issued_by_account_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_claim_campaign_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'claim_campaign_id'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_claim_campaign_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN claim_campaign_id BIGINT UNSIGNED NULL AFTER claim_type',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_claim_log_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'claim_log_id'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_claim_log_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN claim_log_id BIGINT UNSIGNED NULL AFTER claim_campaign_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_nominal_price_amount = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'nominal_price_amount'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_nominal_price_amount = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN nominal_price_amount BIGINT NOT NULL DEFAULT 0 AFTER claim_log_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_nominal_price_currency_code = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'nominal_price_currency_code'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_nominal_price_currency_code = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN nominal_price_currency_code VARCHAR(3) NOT NULL DEFAULT '''' AFTER nominal_price_amount',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_asset_reference_module = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'asset_reference_module'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_asset_reference_module = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN asset_reference_module VARCHAR(60) NOT NULL DEFAULT '''' AFTER nominal_price_currency_code',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_asset_reference_type = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'asset_reference_type'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_asset_reference_type = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN asset_reference_type VARCHAR(80) NOT NULL DEFAULT '''' AFTER asset_reference_module',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_asset_reference_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'asset_reference_id'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_asset_reference_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN asset_reference_id VARCHAR(120) NOT NULL DEFAULT '''' AFTER asset_reference_type',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_claim_snapshot_json = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'claim_snapshot_json'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_claim_snapshot_json = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN claim_snapshot_json TEXT NULL AFTER asset_reference_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_issues_claim_campaign_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND INDEX_NAME = 'idx_sr_coupon_issues_claim_campaign'
);
SET @schema_sql = IF(
    @schema_has_coupon_issues_claim_campaign_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD INDEX idx_sr_coupon_issues_claim_campaign (claim_campaign_id, claim_log_id, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.003',
    updated_at = NOW()
WHERE module_key = 'coupon';
