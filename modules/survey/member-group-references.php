<?php

require_once __DIR__ . '/helpers.php';

return [
    [
        'consumer_module_key' => 'survey',
        'reference_type' => 'survey_member_group_target',
        'label' => '설문 참여 대상',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['member_group'],
        'count_function' => 'sr_survey_member_group_reference_count',
        'rows_function' => 'sr_survey_member_group_reference_rows',
        'health_function' => 'sr_survey_member_group_reference_health',
        'admin_url_function' => 'sr_survey_member_group_reference_admin_url',
    ],
];
