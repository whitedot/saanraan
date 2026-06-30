<?php

declare(strict_types=1);

return [
  [
    'label' => 'asset_recovery.open',
    'title' => '포인트/금액 미회수',
    'module' => 'asset_ledger',
    'table' => 'sr_asset_recovery_failures',
    'where' => 'status = \'open\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT source_module, subject_type, subject_id, account_id, id AS target_fallback
                FROM sr_asset_recovery_failures
                WHERE status = \'open\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '{source_module} {subject_type}#{subject_id} / 계정 #{account_id}',
    'target_fallback_prefix' => '미회수',
    'followup' => '/admin/assets/recovery-failures에서 재회수, 수동 해소, 취소 기준을 적용합니다.',
  ],
];
