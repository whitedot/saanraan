<?php

return [
    [
        'consumer_module_key' => 'survey',
        'label' => '설문 쿠폰 보상',
        'reference_type' => 'survey_reward_coupon',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['coupon_definition'],
        'count_function' => 'sr_survey_coupon_definition_reference_count',
        'rows_function' => 'sr_survey_coupon_definition_reference_rows',
        'health_function' => 'sr_survey_coupon_definition_reference_health',
        'admin_url_function' => 'sr_survey_coupon_definition_reference_admin_url',
    ],
];
