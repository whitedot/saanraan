INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('content', 'comment.created', '새 콘텐츠 댓글이 등록되었습니다.', '{member_name}님이 회원님의 콘텐츠에 댓글을 남겼습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW()),
    ('content', 'comment.mention', '콘텐츠 댓글에서 회원님을 언급했습니다.', '{member_name}님이 콘텐츠 댓글에서 회원님을 언급했습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW()),
    ('community', 'comment.created', '새 댓글이 등록되었습니다.', '{member_name}님이 회원님의 게시글에 댓글을 남겼습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW()),
    ('community', 'comment.mention', '댓글에서 회원님을 언급했습니다.', '{member_name}님이 커뮤니티 댓글에서 회원님을 언급했습니다.', '{link_url}', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'notification';
