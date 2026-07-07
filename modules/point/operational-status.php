<?php

declare(strict_types=1);

return [
  [
    'label' => 'point.expiration.due',
    'title' => '포인트 만료 처리 대상',
    'module' => 'point',
    'table' => 'sr_point_transactions',
    'where' => 'expires_at IS NOT NULL AND expires_at <= NOW() AND expires_remaining > 0',
    'age_column' => 'expires_at',
    'delay_tolerance' => '24시간',
    'warn_after_seconds' => 86400,
    'target_sql' => 'SELECT \'\' AS target_label, account_id AS target_fallback
                FROM sr_point_transactions
                WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND expires_remaining > 0
                ORDER BY expires_at ASC, id ASC
                LIMIT 5',
    'target_fallback_prefix' => '계정',
    'followup' => '포인트 만료 CLI 또는 다음 포인트 거래 처리 흐름을 확인합니다.',
    'action_url' => '/admin/points/transactions',
    'action_label' => '포인트 거래 내역',
  ],
];
