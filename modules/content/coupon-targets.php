<?php

return [
    [
        'target_type' => 'content',
        'label' => '콘텐츠',
        'helpers' => 'helpers.php',
        'capabilities' => ['search', 'health', 'admin_url', 'pricing', 'redeem', 'revoke_access'],
        'search_function' => 'sr_content_coupon_target_search',
        'revoke_access_function' => 'sr_content_coupon_revoke_access',
        'health_function' => 'sr_content_coupon_target_health',
        'admin_url_function' => 'sr_content_coupon_target_admin_url',
        'pricing_function' => 'sr_content_coupon_target_pricing',
        'redeem_function' => 'sr_content_try_coupon_access',
    ],
    [
        'target_type' => 'content_file',
        'label' => '콘텐츠 다운로드 파일',
        'helpers' => 'helpers.php',
        'capabilities' => ['search', 'health', 'admin_url', 'pricing', 'redeem', 'revoke_access'],
        'search_function' => 'sr_content_coupon_target_search',
        'revoke_access_function' => 'sr_content_coupon_revoke_access',
        'health_function' => 'sr_content_coupon_target_health',
        'admin_url_function' => 'sr_content_coupon_target_admin_url',
        'pricing_function' => 'sr_content_coupon_target_pricing',
        'redeem_function' => 'sr_content_try_coupon_download_access',
    ],
];
