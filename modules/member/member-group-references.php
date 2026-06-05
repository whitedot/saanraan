<?php

return [
    [
        'consumer_module_key' => 'member',
        'label' => '회원 그룹 자동 규칙',
        'reference_type' => 'member_group_rule',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['member_group'],
        'count_function' => 'sr_member_group_reference_count',
        'rows_function' => 'sr_member_group_reference_rows',
        'health_function' => 'sr_member_group_reference_health',
        'admin_url_function' => 'sr_member_group_reference_admin_url',
    ],
];
