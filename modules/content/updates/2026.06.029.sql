INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/payments',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path IN ('/admin/content', '/admin/content/files', '/admin/content/file-downloads')
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.029',
    updated_at = NOW()
WHERE module_key = 'content';
