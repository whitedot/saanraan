INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('coupon', 'issue.refunded', '쿠폰·이용권 발급이 환불되었습니다.', '쿠폰·이용권: {coupon_title}\n상태: {status_label}\n환불 시각: {refunded_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.06.010',
    updated_at = NOW()
WHERE module_key = 'notification';
