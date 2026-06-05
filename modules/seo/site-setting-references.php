<?php

return [
    [
        'consumer_module_key' => 'seo',
        'label' => 'SEO 사이트명 문구',
        'reference_type' => 'seo_site_setting_text',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['site_setting'],
        'count_function' => 'sr_seo_site_setting_reference_count',
        'rows_function' => 'sr_seo_site_setting_reference_rows',
        'health_function' => 'sr_seo_site_setting_reference_health',
        'admin_url_function' => 'sr_seo_site_setting_reference_admin_url',
    ],
];
