INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/coupons/settings', action_key, NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/coupons';

UPDATE {{SR_TABLE_PREFIX}}modules
SET name = '쿠폰·이용권',
    version = '2026.06.007',
    updated_at = NOW()
WHERE module_key = 'coupon';
