<?php

return [
    [
        'consumer_module_key' => 'coupon',
        'label' => '쿠폰 발급/사용 이력',
        'reference_type' => 'coupon_history',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['coupon_definition'],
        'count_function' => 'sr_coupon_definition_reference_count',
        'rows_function' => 'sr_coupon_definition_reference_rows',
        'health_function' => 'sr_coupon_definition_reference_health',
        'admin_url_function' => 'sr_coupon_definition_reference_admin_url',
    ],
];
