<?php

declare(strict_types=1);

return [
  [
    'label' => 'community.asset_recovery_legacy.open',
    'title' => '커뮤니티 이전 자산 미회수',
    'module' => 'community',
    'table' => 'sr_community_asset_recovery_failures',
    'where' => 'status = \'open\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT asset_module, subject_type, subject_id, account_id, id AS target_fallback
                FROM sr_community_asset_recovery_failures
                WHERE status = \'open\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '{asset_module} {subject_type}#{subject_id} / 계정 #{account_id}',
    'target_value_labels' => [
      'asset_module' => [
        'point' => '포인트',
        'reward' => '적립금',
        'deposit' => '예치금',
      ],
      'subject_type' => [
        'community.post' => '커뮤니티 게시글',
        'community.comment' => '커뮤니티 댓글',
        'community.attachment' => '커뮤니티 첨부',
        'community.post.read' => '커뮤니티 게시글 열람',
        'community.attachment.download' => '커뮤니티 첨부 다운로드',
      ],
    ],
    'target_fallback_prefix' => '이전 미회수',
    'followup' => '/admin/assets/recovery-failures에서 공통 미회수 대기열을 우선 확인하고 이전 잔여 기록을 해소합니다.',
    'action_url' => '/admin/assets/recovery-failures?status=open&source_module=community',
    'action_label' => '미회수 관리',
  ],
  [
    'label' => 'community.publisher_rewards.pending',
    'title' => '커뮤니티 게시자 보상 대기',
    'module' => 'community',
    'table' => 'sr_community_publisher_reward_logs',
    'where' => 'status = \'pending\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT post_id, attachment_id, publisher_account_id, id AS target_fallback
                FROM sr_community_publisher_reward_logs
                WHERE status = \'pending\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '게시글 #{post_id} / 첨부 #{attachment_id} / 게시자 #{publisher_account_id}',
    'target_fallback_prefix' => '로그',
    'followup' => '/admin/community/publisher-rewards에서 대기 보상 로그와 자산 지급 상태를 확인합니다.',
    'action_url' => '/admin/community/publisher-rewards?status=pending',
    'action_label' => '커뮤니티 보상 로그',
  ],
  [
    'label' => 'community.publisher_rewards.failed',
    'title' => '커뮤니티 게시자 보상 실패',
    'module' => 'community',
    'table' => 'sr_community_publisher_reward_logs',
    'where' => 'status = \'failed\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT post_id, attachment_id, publisher_account_id, id AS target_fallback
                FROM sr_community_publisher_reward_logs
                WHERE status = \'failed\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '게시글 #{post_id} / 첨부 #{attachment_id} / 게시자 #{publisher_account_id}',
    'target_fallback_prefix' => '로그',
    'followup' => '/admin/community/publisher-rewards에서 실패 사유와 중복 지급 가능성을 확인합니다.',
    'action_url' => '/admin/community/publisher-rewards?status=failed',
    'action_label' => '커뮤니티 보상 로그',
  ],
  [
    'label' => 'community.storage_cleanup.pending',
    'title' => '커뮤니티 저장소 정리 대기',
    'module' => 'community',
    'table' => 'sr_community_storage_cleanup_failures',
    'where' => 'status = \'pending\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '24시간',
    'warn_after_seconds' => 86400,
    'target_sql' => 'SELECT storage_key AS target_label, source_id AS target_fallback
                FROM sr_community_storage_cleanup_failures
                WHERE status = \'pending\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'followup' => '커뮤니티 저장소 정리 실패 목록과 파일 권한을 확인합니다.',
    'action_url' => '/admin/community/boards',
    'action_label' => '커뮤니티 저장소 정리 실패',
  ],
  [
    'label' => 'community.board_copy.active',
    'title' => '게시판 복사 진행 중',
    'module' => 'community',
    'table' => 'sr_community_board_copy_jobs',
    'where' => 'status IN (\'pending\', \'running\')',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_copy_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.source_board_id
                WHERE j.status IN (\'pending\', \'running\')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '작업',
    'followup' => '진행 상태와 작업 잠금 만료, 이어받기 필요 여부를 확인합니다.',
    'action_url' => '/admin/community/board-copy-jobs',
    'action_label' => '게시판 작업 관리',
  ],
  [
    'label' => 'community.board_copy.failed',
    'title' => '게시판 복사 실패',
    'module' => 'community',
    'table' => 'sr_community_board_copy_jobs',
    'where' => 'status IN (\'failed\', \'cancelled\')',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_copy_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.source_board_id
                WHERE j.status IN (\'failed\', \'cancelled\')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '작업',
    'followup' => '/admin/community/board-copy-jobs에서 실패 단계, 실패 항목, 부분 생성물 정리 필요 여부를 확인합니다.',
    'action_url' => '/admin/community/board-copy-jobs',
    'action_label' => '게시판 작업 관리',
  ],
  [
    'label' => 'community.board_delete.active',
    'title' => '게시판 삭제 진행 중',
    'module' => 'community',
    'table' => 'sr_community_board_delete_jobs',
    'where' => 'status IN (\'pending\', \'running\')',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_delete_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.board_id
                WHERE j.status IN (\'pending\', \'running\')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '삭제 작업',
    'followup' => '진행 상태와 작업 잠금 만료, 이어받기 필요 여부를 확인합니다.',
    'action_url' => '/admin/community/board-delete-jobs',
    'action_label' => '게시판 삭제 작업 관리',
  ],
  [
    'label' => 'community.board_delete.failed',
    'title' => '게시판 삭제 실패',
    'module' => 'community',
    'table' => 'sr_community_board_delete_jobs',
    'where' => 'status IN (\'failed\', \'cleanup_required\')',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_delete_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.board_id
                WHERE j.status IN (\'failed\', \'cleanup_required\')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '삭제 작업',
    'followup' => '/admin/community/board-delete-jobs에서 실패 단계, 실패 항목, 저장소 정리 필요 여부를 확인합니다.',
    'action_url' => '/admin/community/board-delete-jobs',
    'action_label' => '게시판 삭제 작업 관리',
  ],
  [
    'label' => 'community.level_recalculate.running',
    'title' => '커뮤니티 레벨 재계산 진행 중',
    'module' => 'community',
    'table' => 'sr_community_level_recalculate_jobs',
    'where' => 'status = \'running\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT id AS target_fallback, requested_by, processed_total, total_count
                FROM sr_community_level_recalculate_jobs
                WHERE status = \'running\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '작업 #{target_fallback} / 요청자 #{requested_by} / {processed_total}/{total_count}',
    'target_fallback_prefix' => '작업',
    'followup' => '/admin/community/levels에서 재계산 진행 상태를 확인하고 필요하면 새 재계산을 실행합니다.',
    'action_url' => '/admin/community/levels',
    'action_label' => '커뮤니티 레벨 관리',
  ],
  [
    'label' => 'community.level_recalculate.failed',
    'title' => '커뮤니티 레벨 재계산 실패',
    'module' => 'community',
    'table' => 'sr_community_level_recalculate_jobs',
    'where' => 'status = \'failed\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT id AS target_fallback, requested_by, processed_total, total_count
                FROM sr_community_level_recalculate_jobs
                WHERE status = \'failed\'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5',
    'target_format' => '작업 #{target_fallback} / 요청자 #{requested_by} / {processed_total}/{total_count}',
    'target_fallback_prefix' => '작업',
    'followup' => '/admin/community/levels에서 실패 사유를 확인하고 재계산을 다시 실행합니다.',
    'action_url' => '/admin/community/levels',
    'action_label' => '커뮤니티 레벨 관리',
  ],
];
