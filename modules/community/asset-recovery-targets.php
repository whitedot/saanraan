<?php

return [
    'helpers' => 'helpers.php',
    'label' => '커뮤니티',
    'retry_function' => 'sr_community_reverse_asset_grant_for_operation',
    'subject_links' => [
        'community.post' => [
            'label' => '게시글',
            'url_template' => '/admin/community/posts?q={subject_id}',
        ],
        'community.comment' => [
            'label' => '댓글',
            'url_template' => '/admin/community/comments?q={subject_id}',
        ],
    ],
];
