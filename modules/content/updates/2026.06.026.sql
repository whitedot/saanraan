DELETE FROM {{SR_TABLE_PREFIX}}content_asset_access_logs
WHERE purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%'
   OR settlement_kind = 'legacy_unknown';

DELETE FROM {{SR_TABLE_PREFIX}}content_asset_action_logs
WHERE purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%'
   OR settlement_kind = 'legacy_unknown';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.026',
    updated_at = NOW()
WHERE module_key = 'content';
