<?php

return [
    [
        'consumer_module_key' => 'community',
        'label' => '커뮤니티 배너 설정',
        'reference_type' => 'community_banner',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['banner'],
        'count_function' => 'sr_community_banner_reference_count',
        'rows_function' => 'sr_community_banner_reference_rows',
        'health_function' => 'sr_community_reference_health',
        'admin_url_function' => 'sr_community_reference_admin_url',
    ],
];
