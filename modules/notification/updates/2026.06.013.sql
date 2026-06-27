INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('community', 'followed_author.post_created', '{member_name}님이 새 게시글을 등록했습니다.', '게시판: {board_title}\n게시글: {post_title}\n등록 시각: {created_at}', '{link_url}', '["site"]', 'active', NOW(), NOW()),
    ('content', 'followed_author.content_created', '{member_name}님이 새 콘텐츠를 등록했습니다.', '콘텐츠: {content_title}\n등록 시각: {created_at}', '{link_url}', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.013',
    updated_at = NOW()
WHERE module_key = 'notification';
