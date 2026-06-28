INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/quiz/embed-cache', 'view', NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path IN ('/admin/quiz', '/admin/quiz/settings')
  AND action_key IN ('view', 'edit', 'delete');

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/quiz/embed-cache', 'delete', NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path IN ('/admin/quiz', '/admin/quiz/settings')
  AND action_key IN ('edit', 'delete');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.018',
    updated_at = NOW()
WHERE module_key = 'quiz';
