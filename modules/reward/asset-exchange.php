<?php

return [
    'helpers' => 'helpers.php',
    'label' => '적립금',
    'label_function' => 'sr_reward_display_name',
    'unit_label' => '원',
    'unit_function' => 'sr_reward_unit_label',
    'available_function' => 'sr_reward_usage_enabled',
    'balance_function' => 'sr_reward_balance',
    'transaction_function' => 'sr_reward_create_transaction',
    'cash_like' => false,
    'exchange_out_type' => 'exchange_out',
    'exchange_in_type' => 'exchange_in',
    'exchange_fee_type' => 'exchange_fee',
];
