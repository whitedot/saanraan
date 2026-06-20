SET @sr_notification_table = '{{SR_TABLE_PREFIX}}notifications';

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_table AND COLUMN_NAME = 'source_module_key') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_table, '` ADD COLUMN source_module_key VARCHAR(60) NOT NULL DEFAULT '''' AFTER link_url'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_table AND COLUMN_NAME = 'event_key') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_table, '` ADD COLUMN event_key VARCHAR(120) NOT NULL DEFAULT '''' AFTER source_module_key'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_table AND COLUMN_NAME = 'metadata_json') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_table, '` ADD COLUMN metadata_json TEXT NULL AFTER event_key'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_table AND INDEX_NAME = 'idx_sr_notifications_event') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_table, '` ADD KEY idx_sr_notifications_event (source_module_key, event_key)'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('notification', 'member_push_endpoint.connected', '외부 푸시 수신처가 연결되었습니다.', 'Telegram 개인 수신처가 알림 푸시에 연결되었습니다.', '/account/notifications', '["site"]', 'active', NOW(), NOW()),
    ('notification', 'member_push_endpoint.disabled', '외부 푸시 수신처가 해제되었습니다.', 'Telegram 개인 수신처가 알림 푸시에서 해제되었습니다.', '/account/notifications', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.009',
    updated_at = NOW()
WHERE module_key = 'notification';
