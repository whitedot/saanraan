<?php

return [
    [
        'consumer_module_key' => 'reward',
        'label' => '적립금 출금 신청 대상',
        'reference_type' => 'reward_withdrawal_group_policy',
        'helpers' => 'helpers.php',
        'supports_target_types' => ['member_group'],
        'count_function' => 'sr_reward_member_group_reference_count',
        'rows_function' => 'sr_reward_member_group_reference_rows',
        'health_function' => 'sr_reward_member_group_reference_health',
        'admin_url_function' => 'sr_reward_member_group_reference_admin_url',
    ],
];
