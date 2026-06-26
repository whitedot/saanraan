<?php

return [
    'helpers' => 'helpers.php',
    'label' => '포인트',
    'unit_label' => 'P',
    'label_function' => 'sr_point_display_name',
    'unit_function' => 'sr_point_unit_label',
    'available_function' => 'sr_point_usage_enabled',
    'balance_function' => 'sr_point_balance',
    'transaction_function' => 'sr_point_create_transaction',
    'transaction_lookup_function' => 'sr_point_transaction_by_reference',
    'transaction_table' => 'sr_point_transactions',
    'use_type' => 'use',
    'credit_type' => 'grant',
    'refund_type' => 'refund',
    'deduction_order' => 10,
    'purchase_power' => [
        'asset_units' => 1,
        'settlement_units' => 1,
    ],
];
