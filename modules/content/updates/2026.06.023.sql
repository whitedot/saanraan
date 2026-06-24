INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/author-rewards',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path IN ('/admin/content/submissions', '/admin/content/authors', '/admin/content/settings')
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.023',
    updated_at = NOW()
WHERE module_key = 'content';
