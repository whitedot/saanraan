<?php

declare(strict_types=1);

return [
  [
    'label' => 'notification.deliveries.queued',
    'title' => '알림 delivery 대기',
    'module' => 'notification',
    'table' => 'sr_notification_deliveries',
    'where' => 'status IN (\'queued\', \'processing\')',
    'age_column' => 'created_at',
    'delay_tolerance' => '1시간',
    'warn_after_seconds' => 3600,
    'target_sql' => 'SELECT n.title AS target_label, d.id AS target_fallback
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id
                WHERE d.status IN (\'queued\', \'processing\')
                ORDER BY d.created_at ASC, d.id ASC
                LIMIT 5',
    'target_fallback_prefix' => 'delivery',
    'followup' => '알림 발송 provider 설정과 delivery queue를 확인합니다.',
  ],
  [
    'label' => 'notification.deliveries.failed',
    'title' => '알림 delivery 실패',
    'module' => 'notification',
    'table' => 'sr_notification_deliveries',
    'where' => 'status IN (\'failed\', \'dead\')',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT n.title AS target_label, d.id AS target_fallback
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id
                WHERE d.status IN (\'failed\', \'dead\')
                ORDER BY d.updated_at ASC, d.id ASC
                LIMIT 5',
    'target_fallback_prefix' => 'delivery',
    'followup' => '실패 사유를 확인하고 재발송 또는 취소 기준을 적용합니다.',
  ],
];
