INSERT INTO {{SR_TABLE_PREFIX}}notification_event_templates
    (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
VALUES
    ('member', 'security.email_verified', '이메일 인증이 완료되었습니다.', '회원님의 이메일 인증이 완료되었습니다.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.password_changed', '비밀번호가 변경되었습니다.', '회원님의 비밀번호가 변경되었습니다. 직접 변경한 것이 아니라면 즉시 관리자에게 문의하세요.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.password_reset_completed', '비밀번호 재설정이 완료되었습니다.', '회원님의 비밀번호 재설정이 완료되었습니다. 직접 진행한 것이 아니라면 즉시 관리자에게 문의하세요.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.mfa_enabled', '2차 인증이 설정되었습니다.', '회원님의 계정에 2차 인증이 설정되었습니다.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.mfa_recovery_rotated', '2차 인증 복구 코드가 재발급되었습니다.', '회원님의 2차 인증 복구 코드가 재발급되었습니다.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.mfa_disabled', '2차 인증이 해제되었습니다.', '회원님의 계정에서 2차 인증이 해제되었습니다. 직접 해제한 것이 아니라면 즉시 관리자에게 문의하세요.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.oauth_linked', '외부 로그인이 연결되었습니다.', '{provider_label} 외부 로그인이 회원님의 계정에 연결되었습니다.', '/mypage/security', '["site"]', 'active', NOW(), NOW()),
    ('member', 'security.oauth_unlinked', '외부 로그인이 해제되었습니다.', '{provider_label} 외부 로그인이 회원님의 계정에서 해제되었습니다.', '/mypage/security', '["site"]', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    title_template = VALUES(title_template),
    body_template = VALUES(body_template),
    link_template = VALUES(link_template),
    channels_json = VALUES(channels_json),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'notification';
