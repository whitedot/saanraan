<?php

return [
    'helpers' => 'helpers.php',
    'label' => '예치금',
    'label_function' => 'sr_deposit_display_name',
    'unit_label' => '원',
    'unit_function' => 'sr_deposit_unit_label',
    'available_function' => 'sr_deposit_usage_enabled',
    'balance_function' => 'sr_deposit_balance',
    'summary_url' => '/account/deposits',
    'summary_icon' => 'payments',
    'transaction_function' => 'sr_deposit_create_transaction',
    'transaction_lookup_function' => 'sr_deposit_transaction_by_reference',
    'balance_table' => 'sr_deposit_balances',
    'transaction_table' => 'sr_deposit_transactions',
    'use_type' => 'use',
    'credit_type' => 'deposit',
    'refund_type' => 'refund',
    'deduction_order' => 30,
    'purchase_power' => [
        'asset_units' => 1,
        'settlement_units' => 1,
    ],
];
