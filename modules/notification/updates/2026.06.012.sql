INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('point', 'transaction.exchange_in', '포인트가 환전 입금되었습니다.', '변동 금액: {amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.exchange_out', '포인트가 환전 출금되었습니다.', '변동 금액: -{amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('point', 'transaction.exchange_fee', '포인트 환전 수수료가 차감되었습니다.', '변동 금액: -{amount_abs}포인트\n변동 후 잔액: {balance_after}포인트\n사유: {reason}\n발생 시각: {created_at}', '/account/points', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.exchange_in', '적립금이 환전 입금되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.exchange_out', '적립금이 환전 출금되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.exchange_fee', '적립금 환전 수수료가 차감되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('reward', 'transaction.withdraw', '적립금 출금이 처리되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/rewards', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.exchange_in', '예치금이 환전 입금되었습니다.', '변동 금액: {amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.exchange_out', '예치금이 환전 출금되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW()),
    ('deposit', 'transaction.exchange_fee', '예치금 환전 수수료가 차감되었습니다.', '변동 금액: -{amount_abs}원\n변동 후 잔액: {balance_after}원\n사유: {reason}\n발생 시각: {created_at}', '/account/deposits', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.012',
    updated_at = NOW()
WHERE module_key = 'notification';
