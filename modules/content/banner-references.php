<?php

return [
    [
        'consumer_module_key' => 'content',
        'label' => '콘텐츠 배너 설정',
        'reference_type' => 'content_banner',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['banner'],
        'count_function' => 'sr_content_banner_reference_count',
        'rows_function' => 'sr_content_banner_reference_rows',
        'health_function' => 'sr_content_display_reference_health',
        'admin_url_function' => 'sr_content_display_reference_admin_url',
    ],
];
