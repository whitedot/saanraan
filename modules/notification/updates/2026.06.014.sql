INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('notification', 'member_push_endpoint.connected', '외부 푸시 수신처가 연결되었습니다.', '개인 외부 수신처가 알림 푸시에 연결되었습니다.', '/account/notifications', '["site"]', 'active', NOW(), NOW()),
    ('notification', 'member_push_endpoint.disabled', '외부 푸시 수신처가 해제되었습니다.', '개인 외부 수신처가 알림 푸시에서 해제되었습니다.', '/account/notifications', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.014',
    updated_at = NOW()
WHERE module_key = 'notification';
