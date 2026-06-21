<?php

return [
    [
        'key' => 'quiz',
        'title' => '퀴즈·테스트',
        'order' => 60,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => '공개 퀴즈',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_quiz_sets WHERE status = 'published'",
                'detail_prefix' => '초안 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_quiz_sets WHERE status = 'draft'",
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '완료 시도',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_quiz_attempts WHERE status IN ('submitted', 'scored', 'rewarded')",
                'detail_prefix' => '보상 대기 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_quiz_reward_grants WHERE status = 'pending'",
                'state' => 'success',
            ],
        ],
    ],
];
