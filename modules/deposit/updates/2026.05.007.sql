INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/deposits/settings',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/deposits/balances'
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.05.007',
    updated_at = NOW()
WHERE module_key = 'deposit';
