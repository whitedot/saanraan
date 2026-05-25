UPDATE sr_notifications
SET status = 'active',
    updated_at = NOW()
WHERE status IN ('queued', 'read');

ALTER TABLE sr_notifications
ALTER status SET DEFAULT 'active';

DELETE FROM sr_notification_deliveries
WHERE channel = 'site';

UPDATE sr_notification_deliveries
SET status = 'queued',
    updated_at = NOW()
WHERE status = 'ready';

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/notifications/settings', action_key, NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/notifications'
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.05.001',
    updated_at = NOW()
WHERE module_key = 'notification';
