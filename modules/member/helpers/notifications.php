<?php

declare(strict_types=1);

function sr_member_security_notification_templates(): array
{
    return [
        'security.email_verified' => [
            'title_template' => '이메일 인증이 완료되었습니다.',
            'body_template' => '회원님의 이메일 인증이 완료되었습니다.',
        ],
        'security.password_changed' => [
            'title_template' => '비밀번호가 변경되었습니다.',
            'body_template' => '회원님의 비밀번호가 변경되었습니다. 직접 변경한 것이 아니라면 즉시 관리자에게 문의하세요.',
        ],
        'security.password_reset_completed' => [
            'title_template' => '비밀번호 재설정이 완료되었습니다.',
            'body_template' => '회원님의 비밀번호 재설정이 완료되었습니다. 직접 진행한 것이 아니라면 즉시 관리자에게 문의하세요.',
        ],
        'security.mfa_enabled' => [
            'title_template' => '2차 인증이 설정되었습니다.',
            'body_template' => '회원님의 계정에 2차 인증이 설정되었습니다.',
        ],
        'security.mfa_recovery_rotated' => [
            'title_template' => '2차 인증 복구 코드가 재발급되었습니다.',
            'body_template' => '회원님의 2차 인증 복구 코드가 재발급되었습니다.',
        ],
        'security.mfa_disabled' => [
            'title_template' => '2차 인증이 해제되었습니다.',
            'body_template' => '회원님의 계정에서 2차 인증이 해제되었습니다. 직접 해제한 것이 아니라면 즉시 관리자에게 문의하세요.',
        ],
        'security.oauth_linked' => [
            'title_template' => '외부 로그인이 연결되었습니다.',
            'body_template' => '{provider_label} 외부 로그인이 회원님의 계정에 연결되었습니다.',
        ],
        'security.oauth_unlinked' => [
            'title_template' => '외부 로그인이 해제되었습니다.',
            'body_template' => '{provider_label} 외부 로그인이 회원님의 계정에서 해제되었습니다.',
        ],
    ];
}

function sr_member_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_member_ensure_notification_templates(PDO $pdo): void
{
    if (sr_member_notification_event_function($pdo) === '') {
        return;
    }

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertVerb = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $stmt = $pdo->prepare(
            $insertVerb . ' INTO sr_notification_event_templates
                (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
             VALUES
                (:module_key, :event_key, :title_template, :body_template, :link_template, :channels_json, :status, :created_at, :updated_at)'
        );
        $now = sr_now();
        foreach (sr_member_security_notification_templates() as $eventKey => $template) {
            $stmt->execute([
                'module_key' => 'member',
                'event_key' => (string) $eventKey,
                'title_template' => (string) ($template['title_template'] ?? ''),
                'body_template' => (string) ($template['body_template'] ?? ''),
                'link_template' => '/mypage/security',
                'channels_json' => '["site"]',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'member_notification_template_ensure');
    }
}

function sr_member_create_security_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata = [], ?int $createdByAccountId = null): bool
{
    $createAccountEventFunction = sr_member_notification_event_function($pdo);
    if ($accountId < 1 || $createAccountEventFunction === '') {
        return false;
    }

    sr_member_ensure_notification_templates($pdo);
    $metadata = array_merge([
        'link_url' => '/mypage/security',
        'created_at' => sr_now(),
    ], $metadata);

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $accountId,
            'module_key' => 'member',
            'event_key' => $eventKey,
            'metadata' => $metadata,
            'created_by_account_id' => $createdByAccountId,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'member_security_notification_create');
    }

    return false;
}
