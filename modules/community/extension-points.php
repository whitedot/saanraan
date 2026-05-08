<?php

return [
    [
        'point_key' => 'community.home',
        'label' => '커뮤니티 홈',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => '본문 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => '본문 아래',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'community.board.list',
        'label' => '커뮤니티 게시판 목록',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_list',
                'label' => '목록 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_list',
                'label' => '목록 아래',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'community.post.view',
        'label' => '커뮤니티 게시글 보기',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => '본문 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => '본문 아래',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'before_comments',
                'label' => '댓글 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_comments',
                'label' => '댓글 아래',
                'kind' => 'content',
            ],
        ],
    ],
];
