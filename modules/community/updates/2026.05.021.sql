CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_access_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(60) NOT NULL,
    source_kind VARCHAR(30) NOT NULL DEFAULT 'asset',
    source_asset_module VARCHAR(20) NOT NULL DEFAULT '',
    source_charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    source_reference VARCHAR(160) NOT NULL DEFAULT '',
    granted_at DATETIME NOT NULL,
    anonymized_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_access_entitlements_account_subject (account_id, subject_type, subject_id, event_key),
    KEY idx_sr_community_access_entitlements_subject (subject_type, subject_id, event_key),
    KEY idx_sr_community_access_entitlements_account (account_id, granted_at),
    KEY idx_sr_community_access_entitlements_anonymized (anonymized_at)
);

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}community_access_entitlements
    (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
SELECT l.account_id,
       l.subject_type,
       l.subject_id,
       l.event_key,
       'asset',
       l.asset_module,
       l.charge_policy,
       CONCAT(l.asset_module, ':', l.transaction_id),
       l.created_at,
       l.created_at
FROM {{SR_TABLE_PREFIX}}community_asset_logs l
WHERE l.direction = 'use'
  AND l.transaction_id > 0
  AND l.event_key IN ('post_read', 'attachment_download');

SET @sr_has_coupon_redemptions = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
);
SET @sr_community_coupon_entitlement_sql = IF(
    @sr_has_coupon_redemptions > 0,
    'INSERT IGNORE INTO {{SR_TABLE_PREFIX}}community_access_entitlements
        (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
     SELECT r.account_id,
            ''community.post'',
            CAST(r.reference_id AS UNSIGNED) AS subject_id,
            ''post_read'',
            ''coupon'',
            '''',
            ''once'',
            r.dedupe_key,
            COALESCE(r.redeemed_at, r.created_at),
            r.created_at
     FROM {{SR_TABLE_PREFIX}}coupon_redemptions r
     WHERE r.status = ''redeemed''
       AND r.reference_module = ''community''
       AND r.reference_type = ''community.post''
       AND r.reference_id REGEXP ''^[0-9]+$''',
    'DO 0'
);
PREPARE sr_community_coupon_entitlement_stmt FROM @sr_community_coupon_entitlement_sql;
EXECUTE sr_community_coupon_entitlement_stmt;
DEALLOCATE PREPARE sr_community_coupon_entitlement_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.05.021',
    updated_at = NOW()
WHERE module_key = 'community';
