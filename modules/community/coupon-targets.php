<?php

return [
    [
        'target_type' => 'community_board',
        'label' => '커뮤니티 게시판',
        'helpers' => 'helpers.php',
        'capabilities' => ['search', 'health', 'admin_url'],
        'search_function' => 'sr_community_coupon_target_search',
        'revoke_access_function' => 'sr_community_coupon_revoke_access',
        'health_function' => 'sr_community_coupon_target_health',
        'admin_url_function' => 'sr_community_coupon_target_admin_url',
    ],
    [
        'target_type' => 'community_post',
        'label' => '커뮤니티 게시글',
        'helpers' => 'helpers.php',
        'capabilities' => ['search', 'health', 'admin_url', 'pricing', 'redeem', 'revoke_access'],
        'search_function' => 'sr_community_coupon_target_search',
        'revoke_access_function' => 'sr_community_coupon_revoke_access',
        'health_function' => 'sr_community_coupon_target_health',
        'admin_url_function' => 'sr_community_coupon_target_admin_url',
        'pricing_function' => 'sr_community_coupon_target_pricing',
        'redeem_function' => 'sr_community_try_paid_read_coupon_access',
    ],
];
