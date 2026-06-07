INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/quiz/manual', 'view', NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/quiz'
  AND action_key = 'view';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.004',
    updated_at = NOW()
WHERE module_key = 'quiz';
