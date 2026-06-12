<?php

return [
    [
        'target_type' => 'content',
        'label' => '콘텐츠',
        'helpers' => 'helpers.php',
        'search_function' => 'sr_content_coupon_target_search',
        'revoke_access_function' => 'sr_content_coupon_revoke_access',
        'health_function' => 'sr_content_coupon_target_health',
        'admin_url_function' => 'sr_content_coupon_target_admin_url',
    ],
    [
        'target_type' => 'content_file',
        'label' => '콘텐츠 다운로드 파일',
        'helpers' => 'helpers.php',
        'search_function' => 'sr_content_coupon_target_search',
        'revoke_access_function' => 'sr_content_coupon_revoke_access',
        'health_function' => 'sr_content_coupon_target_health',
        'admin_url_function' => 'sr_content_coupon_target_admin_url',
    ],
];
