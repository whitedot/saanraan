<?php

return [
    'helpers' => 'helpers.php',
    'label' => '적립금',
    'label_function' => 'sr_reward_display_name',
    'unit_label' => '원',
    'unit_function' => 'sr_reward_unit_label',
    'available_function' => 'sr_reward_usage_enabled',
    'balance_table' => 'sr_reward_balances',
    'transaction_table' => 'sr_reward_transactions',
    'balance_function' => 'sr_reward_balance',
    'transaction_function' => 'sr_reward_create_transaction',
    'transaction_type' => 'expire',
    'process_label' => '소멸',
    'ledger_process_label' => 'expire',
    'sort_order' => 20,
];
