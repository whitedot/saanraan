INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('coupon', 'issue.refunded', '쿠폰·이용권 발급이 환불되었습니다.', '쿠폰·이용권: {coupon_title}\n상태: {status_label}\n환불 시각: {refunded_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'issue.definition_disabled', '쿠폰·이용권 사용이 중지되었습니다.', '쿠폰·이용권: {coupon_title}\n운영상 사유로 해당 쿠폰·이용권을 더 이상 사용할 수 없습니다.\n발급 시각: {issued_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.011',
    updated_at = NOW()
WHERE module_key = 'notification';
