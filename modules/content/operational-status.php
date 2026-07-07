<?php

declare(strict_types=1);

return [
  [
    'label' => 'content.author_rewards.pending',
    'title' => '콘텐츠 작성자 보상 대기',
    'module' => 'content',
    'table' => 'sr_content_author_reward_logs',
    'where' => 'status = \'pending\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT submission_id, content_id, author_account_id, id AS target_fallback
                FROM sr_content_author_reward_logs
                WHERE status = \'pending\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '제출본 #{submission_id} / 콘텐츠 #{content_id} / 작성자 #{author_account_id}',
    'target_fallback_prefix' => '로그',
    'followup' => '/admin/content/author-rewards에서 대기 보상 로그와 자산 지급 상태를 확인합니다.',
    'action_url' => '/admin/content/author-rewards?status=pending',
    'action_label' => '작성자 보상 로그',
  ],
  [
    'label' => 'content.author_rewards.failed',
    'title' => '콘텐츠 작성자 보상 실패',
    'module' => 'content',
    'table' => 'sr_content_author_reward_logs',
    'where' => 'status = \'failed\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT submission_id, content_id, author_account_id, id AS target_fallback
                FROM sr_content_author_reward_logs
                WHERE status = \'failed\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '제출본 #{submission_id} / 콘텐츠 #{content_id} / 작성자 #{author_account_id}',
    'target_fallback_prefix' => '로그',
    'followup' => '/admin/content/author-rewards에서 실패 사유와 중복 지급 가능성을 확인합니다.',
    'action_url' => '/admin/content/author-rewards?status=failed',
    'action_label' => '작성자 보상 로그',
  ],
  [
    'label' => 'content.storage_cleanup.pending',
    'title' => '콘텐츠 저장소 정리 대기',
    'module' => 'content',
    'table' => 'sr_content_storage_cleanup_failures',
    'where' => 'status = \'pending\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '24시간',
    'warn_after_seconds' => 86400,
    'target_sql' => 'SELECT storage_key AS target_label, source_id AS target_fallback
                FROM sr_content_storage_cleanup_failures
                WHERE status = \'pending\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'followup' => '콘텐츠 저장소 정리 실패 목록과 파일 권한을 확인합니다.',
    'action_url' => '/admin/content-groups',
    'action_label' => '콘텐츠 저장소 정리 실패',
  ],
];
