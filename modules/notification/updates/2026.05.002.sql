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
    ('deposit', 'transaction.adjustment.decrease', '예치금이 감소했습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.05.002',
    updated_at = NOW()
WHERE module_key = 'notification';
