SET @schema_has_coupon_claim_campaigns_allowed_assets = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_claim_campaigns'
      AND COLUMN_NAME = 'allowed_asset_modules_json'
);
SET @schema_sql = IF(
    @schema_has_coupon_claim_campaigns_allowed_assets = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_claim_campaigns ADD COLUMN allowed_asset_modules_json TEXT NULL AFTER price_currency_code',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.004',
    updated_at = NOW()
WHERE module_key = 'coupon';
