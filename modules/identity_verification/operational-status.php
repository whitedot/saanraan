<?php

declare(strict_types=1);

return [
    [
        'label' => 'identity_verification.expired_pending',
        'title' => '만료된 본인확인 대기',
        'module' => 'identity_verification',
        'table' => 'sr_identity_verification_attempts',
        'where' => "status IN ('ready', 'pending') AND expires_at < UTC_TIMESTAMP()",
        'age_column' => 'expires_at',
        'delay_tolerance' => '즉시',
        'warn_after_seconds' => 0,
        'target_sql' => "SELECT provider_key, purpose, id AS target_fallback
                    FROM sr_identity_verification_attempts
                    WHERE status IN ('ready', 'pending')
                      AND expires_at < UTC_TIMESTAMP()
                    ORDER BY expires_at ASC, id ASC
                    LIMIT 5",
        'target_format' => '{provider_key} {purpose}',
        'target_value_labels' => [
            'purpose' => [
                'registration' => '회원가입',
                'password_reset' => '비밀번호 재설정',
                'content.author_application' => '콘텐츠 작성자 신청',
                'member.withdrawal' => '회원 탈퇴',
            ],
        ],
        'target_fallback_prefix' => '본인확인 시도',
        'followup' => '본인확인 제공자 응답 실패나 사용자 이탈 여부를 확인하고 보관 정리 대상에 포함합니다.',
        'action_url' => '/admin/identity-verifications',
        'action_label' => '본인확인 이력',
    ],
];
