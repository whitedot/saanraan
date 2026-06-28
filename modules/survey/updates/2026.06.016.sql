DELETE FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/surveys/manual';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.016',
    name = '설문·여론조사',
    updated_at = NOW()
WHERE module_key = 'survey';
