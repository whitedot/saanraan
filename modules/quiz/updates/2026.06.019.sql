DELETE FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/quiz/manual';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.019',
    name = '퀴즈·테스트',
    updated_at = NOW()
WHERE module_key = 'quiz';
