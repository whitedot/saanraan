INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('coupon', 'redemption.refunded', '쿠폰·이용권 사용이 환불되었습니다.', '쿠폰·이용권: {coupon_title}\n사용 횟수: {used_count}/{max_uses_per_issue}회\n상태: {status_label}\n환불 시각: {refunded_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.05.005',
    updated_at = NOW()
WHERE module_key = 'notification';
