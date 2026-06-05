<?php

return [
    [
        'consumer_module_key' => 'community',
        'label' => '커뮤니티 팝업레이어 설정',
        'reference_type' => 'community_popup_layer',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['popup_layer'],
        'count_function' => 'sr_community_popup_layer_reference_count',
        'rows_function' => 'sr_community_popup_layer_reference_rows',
        'health_function' => 'sr_community_reference_health',
        'admin_url_function' => 'sr_community_reference_admin_url',
    ],
];
