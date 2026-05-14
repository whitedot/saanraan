<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    [
        'rule_key' => 'community.post_count_at_least',
        'label' => '전체 게시글 수 이상',
        'description' => '모든 게시판의 공개 게시글 작성 수가 지정 수 이상인 회원입니다.',
        'params' => [
            [
                'key' => 'min_count',
                'label' => '최소 게시글 수',
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
        'label' => '전체 댓글 수 이상',
        'description' => '공개 댓글 작성 수가 지정 수 이상인 회원입니다.',
        'params' => [
            [
                'key' => 'min_count',
                'label' => '최소 댓글 수',
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
        'label' => '커뮤니티 레벨 이상',
        'description' => '현재 커뮤니티 레벨이 지정 레벨 이상인 회원입니다.',
        'params' => [
            [
                'key' => 'min_level',
                'label' => '최소 레벨',
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
        'label' => '특정 게시판 게시글 수 이상',
        'description' => '선택한 게시판에 공개 게시글을 지정 수 이상 작성한 회원입니다.',
        'params' => [
            [
                'key' => 'board_id',
                'label' => '게시판',
                'type' => 'subject',
                'default' => 0,
            ],
            [
                'key' => 'min_count',
                'label' => '최소 게시글 수',
                'type' => 'int',
                'min' => 1,
                'max' => 1000,
                'default' => 5,
            ],
        ],
        'evaluator' => 'sr_community_member_group_rule_post_count_at_least',
    ],
];
