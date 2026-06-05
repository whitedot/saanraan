<?php

return [
    [
        'consumer_module_key' => 'logo_manager',
        'label' => '로고 대체 텍스트',
        'reference_type' => 'logo_manager_site_setting_text',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['site_setting'],
        'count_function' => 'sr_logo_manager_site_setting_reference_count',
        'rows_function' => 'sr_logo_manager_site_setting_reference_rows',
        'health_function' => 'sr_logo_manager_site_setting_reference_health',
        'admin_url_function' => 'sr_logo_manager_site_setting_reference_admin_url',
    ],
];
