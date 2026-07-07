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
    'target_value_labels' => [
      'source_module' => [
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈',
        'survey' => '설문',
      ],
      'subject_type' => [
        'post' => '게시글',
        'comment' => '댓글',
        'content.view' => '콘텐츠 열람',
        'content.download' => '콘텐츠 다운로드',
        'community.post.read' => '커뮤니티 게시글 열람',
        'community.attachment.download' => '커뮤니티 첨부 다운로드',
        'quiz.reward' => '퀴즈 보상',
        'survey.reward' => '설문 보상',
      ],
    ],
    'target_fallback_prefix' => '미회수',
    'followup' => '/admin/assets/recovery-failures에서 재회수, 수동 해소, 취소 기준을 적용합니다.',
    'action_url' => '/admin/assets/recovery-failures?status=open',
    'action_label' => '미회수 관리',
  ],
];
