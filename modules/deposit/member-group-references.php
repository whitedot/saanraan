<?php

return [
    [
        'consumer_module_key' => 'deposit',
        'label' => '예치금 환불 신청 대상',
        'reference_type' => 'deposit_refund_group_policy',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['member_group'],
        'count_function' => 'sr_deposit_member_group_reference_count',
        'rows_function' => 'sr_deposit_member_group_reference_rows',
        'health_function' => 'sr_deposit_member_group_reference_health',
        'admin_url_function' => 'sr_deposit_member_group_reference_admin_url',
    ],
];
