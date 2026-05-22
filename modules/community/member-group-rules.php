<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    [
        'rule_key' => 'community.post_count_at_least',
        'label' => sr_t('community::ui.all.3f3ee356'),
        'description' => sr_t('community::ui.member.8e3972ec'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('community::ui.text.5df51859'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 10,
            ],
        ],
        'evaluator' => 'sr_community_member_group_rule_total_post_count_at_least',
    ],
    [
        'rule_key' => 'community.comment_count_at_least',
        'label' => sr_t('community::ui.all.38e92242'),
        'description' => sr_t('community::ui.member.a4ab0568'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('community::ui.text.697632bb'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 20,
            ],
        ],
        'evaluator' => 'sr_community_member_group_rule_comment_count_at_least',
    ],
    [
        'rule_key' => 'community.level_at_least',
        'label' => sr_t('community::ui.community.ff797a5c'),
        'description' => sr_t('community::ui.community.member.2083be7f'),
        'params' => [
            [
                'key' => 'min_level',
                'label' => sr_t('community::ui.text.42f1836e'),
                'type' => 'int',
                'min' => 0,
                'max' => 10,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_community_member_group_rule_level_at_least',
    ],
    [
        'rule_key' => 'community.board.post_count_at_least',
        'label' => sr_t('community::ui.text.2bb5c00c'),
        'description' => sr_t('community::ui.select.member.48ac4189'),
        'params' => [
            [
                'key' => 'board_id',
                'label' => sr_t('community::ui.text.4732a58f'),
                'type' => 'subject',
                'default' => 0,
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('community::ui.text.5df51859'),
                'type' => 'int',
                'min' => 1,
                'max' => 1000,
                'default' => 5,
            ],
        ],
        'evaluator' => 'sr_community_member_group_rule_post_count_at_least',
    ],
];
