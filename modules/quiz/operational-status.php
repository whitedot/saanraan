<?php

declare(strict_types=1);

return [
  [
    'label' => 'quiz.reward_grants.pending',
    'title' => '퀴즈 보상 지급 대기',
    'module' => 'quiz',
    'table' => 'sr_quiz_reward_grants',
    'where' => 'status = \'pending\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '15분',
    'warn_after_seconds' => 900,
    'target_sql' => 'SELECT g.quiz_id AS target_fallback, g.quiz_id, g.attempt_id
                FROM sr_quiz_reward_grants g
                WHERE g.status = \'pending\'
                ORDER BY g.updated_at ASC, g.id ASC
                LIMIT 5',
    'target_format' => '퀴즈 #{quiz_id} / 시도 #{attempt_id}',
    'target_fallback_prefix' => '퀴즈',
    'followup' => '/admin/quiz/attempts 리워드 로그에서 보상 정책과 자산 또는 쿠폰 provider 상태를 확인합니다.',
  ],
  [
    'label' => 'quiz.reward_grants.failed',
    'title' => '퀴즈 보상 지급 실패',
    'module' => 'quiz',
    'table' => 'sr_quiz_reward_grants',
    'where' => 'status = \'failed\'',
    'age_column' => 'failed_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT g.quiz_id AS target_fallback, g.quiz_id, g.attempt_id
                FROM sr_quiz_reward_grants g
                WHERE g.status = \'failed\'
                ORDER BY g.failed_at ASC, g.id ASC
                LIMIT 5',
    'target_format' => '퀴즈 #{quiz_id} / 시도 #{attempt_id}',
    'target_fallback_prefix' => '퀴즈',
    'followup' => '/admin/quiz/attempts 리워드 로그에서 중복 지급 없이 복구할 수 있는지 확인합니다.',
  ],
];
