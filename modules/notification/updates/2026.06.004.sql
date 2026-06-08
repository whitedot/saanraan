INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('quiz', 'comment.mention', '퀴즈 댓글에서 회원님을 언급했습니다.', '{member_name}님이 퀴즈 댓글에서 회원님을 언급했습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW()),
    ('survey', 'comment.mention', '설문 댓글에서 회원님을 언급했습니다.', '{member_name}님이 설문 댓글에서 회원님을 언급했습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.004',
    updated_at = NOW()
WHERE module_key = 'notification';
