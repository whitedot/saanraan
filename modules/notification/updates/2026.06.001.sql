INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('reward', 'transaction.reclaim', '적립금이 회수되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = NOW()
WHERE module_key = 'notification';
