CREATE TABLE IF NOT EXISTS sr_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    audience VARCHAR(30) NOT NULL DEFAULT 'account',
    title VARCHAR(160) NOT NULL,
    body_text TEXT NULL,
    body_format VARCHAR(20) NOT NULL DEFAULT 'plain',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    read_at DATETIME NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_notifications_account (account_id, status, read_at, id),
    KEY idx_sr_notifications_audience (audience, status, id)
);

CREATE TABLE IF NOT EXISTS sr_notification_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(30) NOT NULL,
    recipient VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    provider_message_id VARCHAR(120) NOT NULL DEFAULT '',
    error_message VARCHAR(255) NOT NULL DEFAULT '',
    attempted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_notification_deliveries_notification (notification_id),
    KEY idx_sr_notification_deliveries_channel_status (channel, status, id)
);

CREATE TABLE IF NOT EXISTS sr_notification_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_notification_reads (notification_id, account_id),
    KEY idx_sr_notification_reads_account (account_id, read_at)
);

CREATE TABLE IF NOT EXISTS sr_notification_event_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(60) NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    title_template VARCHAR(160) NOT NULL,
    body_template TEXT NULL,
    link_template VARCHAR(255) NOT NULL DEFAULT '',
    channels_json TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_notification_event_templates_key (module_key, event_key),
    KEY idx_sr_notification_event_templates_status (status, module_key, event_key)
);

INSERT INTO sr_notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('point', 'transaction.grant', '포인트가 지급되었습니다.', '변동 금액: {amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.refund', '포인트가 복원되었습니다.', '변동 금액: {amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.use', '포인트가 사용되었습니다.', '변동 금액: -{amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.expire', '포인트가 만료되었습니다.', '변동 금액: -{amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.adjustment.increase', '포인트가 증가했습니다.', '변동 금액: {amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.adjustment.decrease', '포인트가 감소했습니다.', '변동 금액: -{amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.grant', '적립금이 지급되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.refund', '적립금이 복원되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.use', '적립금이 사용되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.expire', '적립금이 만료되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.adjustment.increase', '적립금이 증가했습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.adjustment.decrease', '적립금이 감소했습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.deposit', '예치금이 입금되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.refund', '예치금이 복원되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.use', '예치금이 사용되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.withdraw', '예치금 출금이 처리되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.adjustment.increase', '예치금이 증가했습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.adjustment.decrease', '예치금이 감소했습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'issue.created', '쿠폰·이용권이 발급되었습니다.', '쿠폰·이용권: {coupon_title}\n사유: {issued_reason}\n사용 가능 횟수: {max_uses_per_issue}회\n발급 시각: {issued_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'redemption.redeemed', '쿠폰·이용권이 사용되었습니다.', '쿠폰·이용권: {coupon_title}\n사용 횟수: {used_count}/{max_uses_per_issue}회\n상태: {status_label}\n사용 시각: {created_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW()),
    ('coupon', 'issue.status_updated', '쿠폰·이용권 상태가 변경되었습니다.', '쿠폰·이용권: {coupon_title}\n상태: {status_label}\n발급 시각: {issued_at}\n만료 시각: {expires_at}', '/account/coupons', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);
