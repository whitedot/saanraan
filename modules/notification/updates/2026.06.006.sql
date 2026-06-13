INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('reaction', 'target.reacted', '새 리액션이 등록되었습니다.', '{member_name}님이 {target_label}에 {reaction_label} 리액션을 남겼습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.006',
    updated_at = NOW()
WHERE module_key = 'notification';
