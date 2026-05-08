<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
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
        'evaluator' => 'toy_community_member_group_rule_post_count_at_least',
    ],
];
