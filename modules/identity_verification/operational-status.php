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
        'target_fallback_prefix' => '본인확인 시도',
        'followup' => 'provider return/callback 실패나 사용자 이탈 여부를 확인하고 보관 정리 대상에 포함합니다.',
    ],
];
