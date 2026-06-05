<?php

return [
    [
        'consumer_module_key' => 'content',
        'label' => '콘텐츠 회원 그룹 정책',
        'reference_type' => 'content_member_group_policy',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['member_group'],
        'count_function' => 'sr_content_member_group_reference_count',
        'rows_function' => 'sr_content_member_group_reference_rows',
        'health_function' => 'sr_content_member_group_reference_health',
        'admin_url_function' => 'sr_content_display_reference_admin_url',
    ],
];
