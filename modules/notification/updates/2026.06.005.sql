CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}admin_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    body_text TEXT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    source_module_key VARCHAR(60) NOT NULL DEFAULT '',
    event_key VARCHAR(120) NOT NULL DEFAULT '',
    target_type VARCHAR(80) NOT NULL DEFAULT '',
    target_id VARCHAR(80) NOT NULL DEFAULT '',
    action_url VARCHAR(255) NOT NULL DEFAULT '',
    permission_path VARCHAR(120) NOT NULL DEFAULT '',
    permission_action VARCHAR(20) NOT NULL DEFAULT 'view',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    dedupe_key VARCHAR(190) NOT NULL DEFAULT '',
    occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_account_id BIGINT UNSIGNED NULL,
    processed_by_account_id BIGINT UNSIGNED NULL,
    processed_at DATETIME NULL,
    archived_at DATETIME NULL,
    last_occurred_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_notifications_dedupe (dedupe_key),
    KEY idx_sr_admin_notifications_status (status, severity, updated_at, id),
    KEY idx_sr_admin_notifications_permission (permission_path, permission_action, status, id),
    KEY idx_sr_admin_notifications_source (source_module_key, event_key, target_type, target_id)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}admin_notification_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_notification_reads (notification_id, account_id),
    KEY idx_sr_admin_notification_reads_account (account_id, read_at, acknowledged_at),
    KEY idx_sr_admin_notification_reads_notification (notification_id)
);

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, '/admin/admin-notifications', 'view', NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/notifications'
  AND action_key = 'view';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.005',
    updated_at = NOW()
WHERE module_key = 'notification';
