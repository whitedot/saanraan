UPDATE sr_asset_exchange_logs AS l
LEFT JOIN sr_asset_exchange_policies AS p ON p.id = l.policy_id
SET l.policy_id = NULL
WHERE l.policy_id IS NOT NULL
  AND (
    p.id IS NULL
    OR p.from_module_key = p.to_module_key
    OR p.from_module_key NOT IN ('point', 'reward', 'deposit')
    OR p.to_module_key NOT IN ('point', 'reward', 'deposit')
  );

DELETE FROM sr_asset_exchange_policies
WHERE from_module_key = to_module_key
   OR from_module_key NOT IN ('point', 'reward', 'deposit')
   OR to_module_key NOT IN ('point', 'reward', 'deposit');

INSERT IGNORE INTO sr_asset_exchange_policies
    (from_module_key, to_module_key, status, rate_numerator, rate_denominator, min_amount, max_amount, rounding_mode,
     fee_trigger, fee_basis, fee_rate_numerator, fee_rate_denominator, fee_fixed_amount, fee_min_amount, fee_max_amount,
     sort_order, created_at, updated_at)
VALUES
    ('point', 'reward', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 0, NOW(), NOW()),
    ('point', 'deposit', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 1, NOW(), NOW()),
    ('reward', 'point', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 2, NOW(), NOW()),
    ('reward', 'deposit', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 3, NOW(), NOW()),
    ('deposit', 'point', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 4, NOW(), NOW()),
    ('deposit', 'reward', 'disabled', 1, 1, 1, NULL, 'floor', 'none', 'to_amount', 0, 100, 0, NULL, NULL, 5, NOW(), NOW());

UPDATE sr_asset_exchange_policies
SET status = 'disabled',
    rate_numerator = 1,
    rate_denominator = 1,
    min_amount = 1,
    max_amount = NULL,
    rounding_mode = 'floor',
    fee_trigger = 'none',
    fee_basis = 'to_amount',
    fee_rate_numerator = 0,
    fee_rate_denominator = 100,
    fee_fixed_amount = 0,
    fee_min_amount = NULL,
    fee_max_amount = NULL,
    sort_order = CASE
        WHEN from_module_key = 'point' AND to_module_key = 'reward' THEN 0
        WHEN from_module_key = 'point' AND to_module_key = 'deposit' THEN 1
        WHEN from_module_key = 'reward' AND to_module_key = 'point' THEN 2
        WHEN from_module_key = 'reward' AND to_module_key = 'deposit' THEN 3
        WHEN from_module_key = 'deposit' AND to_module_key = 'point' THEN 4
        WHEN from_module_key = 'deposit' AND to_module_key = 'reward' THEN 5
        ELSE sort_order
    END,
    updated_at = NOW()
WHERE from_module_key IN ('point', 'reward', 'deposit')
  AND to_module_key IN ('point', 'reward', 'deposit')
  AND from_module_key <> to_module_key;

INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT id, 'exchange_enabled', '1', 'string', NOW(), NOW()
FROM sr_modules
WHERE module_key = 'asset_exchange'
ON DUPLICATE KEY UPDATE setting_value = sr_module_settings.setting_value;

INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT id, 'relative_value_point', '1', 'string', NOW(), NOW()
FROM sr_modules
WHERE module_key = 'asset_exchange'
ON DUPLICATE KEY UPDATE setting_value = sr_module_settings.setting_value;

INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT id, 'relative_value_reward', '1', 'string', NOW(), NOW()
FROM sr_modules
WHERE module_key = 'asset_exchange'
ON DUPLICATE KEY UPDATE setting_value = sr_module_settings.setting_value;

INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT id, 'relative_value_deposit', '1', 'string', NOW(), NOW()
FROM sr_modules
WHERE module_key = 'asset_exchange'
ON DUPLICATE KEY UPDATE setting_value = sr_module_settings.setting_value;

DELETE ms
FROM sr_module_settings AS ms
INNER JOIN sr_modules AS m ON m.id = ms.module_id
WHERE m.module_key = 'asset_exchange'
  AND ms.setting_key IN ('policy_default_rate_ratio', 'policy_default_sort_order');

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'asset_exchange';
