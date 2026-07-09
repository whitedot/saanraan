INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('message', 'message.received', '새 쪽지가 도착했습니다.', '{sender_name}님이 쪽지를 보냈습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.001',
    updated_at = NOW()
WHERE module_key = 'notification';
