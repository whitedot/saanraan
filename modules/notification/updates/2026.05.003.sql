INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('coupon', 'issue.created', '쿠폰·이용권이 발급되었습니다.', '쿠폰·이용권: {coupon_title}\n사유: {issued_reason}\n사용 가능 횟수: {max_uses_per_issue}회\n발급 시각: {issued_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'redemption.redeemed', '쿠폰·이용권이 사용되었습니다.', '쿠폰·이용권: {coupon_title}\n사용 횟수: {used_count}/{max_uses_per_issue}회\n상태: {status_label}\n사용 시각: {created_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'issue.status_updated', '쿠폰·이용권 상태가 변경되었습니다.', '쿠폰·이용권: {coupon_title}\n상태: {status_label}\n발급 시각: {issued_at}\n만료 시각: {expires_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.05.003',
    updated_at = NOW()
WHERE module_key = 'notification';
