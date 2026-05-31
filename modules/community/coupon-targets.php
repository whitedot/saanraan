<?php

return [
    [
        'target_type' => 'community_board',
        'label' => '커뮤니티 게시판',
        'helpers' => 'helpers.php',
        'search_function' => 'sr_community_coupon_target_search',
        'revoke_access_function' => 'sr_community_coupon_revoke_access',
    ],
    [
        'target_type' => 'community_post',
        'label' => '커뮤니티 게시글',
        'helpers' => 'helpers.php',
        'search_function' => 'sr_community_coupon_target_search',
        'revoke_access_function' => 'sr_community_coupon_revoke_access',
    ],
];
