<?php

return [
    'helpers' => 'helpers.php',
    'label' => '적립금',
    'label_function' => 'sr_reward_display_name',
    'unit_label' => '원',
    'unit_function' => 'sr_reward_unit_label',
    'available_function' => 'sr_reward_usage_enabled',
    'balance_function' => 'sr_reward_balance',
    'summary_url' => '/account/rewards',
    'summary_icon' => 'savings',
    'transaction_function' => 'sr_reward_create_transaction',
    'transaction_lookup_function' => 'sr_reward_transaction_by_reference',
    'balance_table' => 'sr_reward_balances',
    'transaction_table' => 'sr_reward_transactions',
    'use_type' => 'use',
    'credit_type' => 'grant',
    'refund_type' => 'refund',
    'deduction_order' => 20,
    'purchase_power' => [
        'asset_units' => 1,
        'settlement_units' => 1,
    ],
];
