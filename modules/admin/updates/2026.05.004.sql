DELETE FROM sr_admin_account_permissions
WHERE menu_path = '/admin/menu';

UPDATE sr_modules
SET version = '2026.05.004'
WHERE module_key = 'admin';
