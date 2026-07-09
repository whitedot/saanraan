INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/delivery-templates', action_key, NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/settings'
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.003',
    updated_at = NOW()
WHERE module_key = 'admin';
