SET @schema_has_popup_layers_coupon_claim_campaign_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}popup_layers'
      AND COLUMN_NAME = 'coupon_claim_campaign_key'
);
SET @schema_sql = IF(
    @schema_has_popup_layers_coupon_claim_campaign_key = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}popup_layers ADD COLUMN coupon_claim_campaign_key VARCHAR(60) NOT NULL DEFAULT '''' AFTER body_format',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'popup_layer';
