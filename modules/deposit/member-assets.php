<?php

return [
    'helpers' => 'helpers.php',
    'label' => '예치금',
    'unit_label' => '원',
    'balance_function' => 'sr_deposit_balance',
    'transaction_function' => 'sr_deposit_create_transaction',
    'transaction_lookup_function' => 'sr_deposit_transaction_by_reference',
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
