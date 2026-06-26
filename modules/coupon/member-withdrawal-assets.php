<?php

return [
    'helpers' => 'helpers.php',
    'label' => '쿠폰·이용권',
    'unit_label' => '개',
    'available_function' => 'sr_coupon_usage_enabled',
    'balance_function' => 'sr_coupon_active_account_issue_count',
    'process_function' => 'sr_coupon_process_account_withdrawal',
    'process_label' => '소멸/환급 검토',
    'sort_order' => 40,
];
