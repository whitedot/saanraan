<?php

return [
    'helpers' => 'helpers.php',
    'label' => '포인트',
    'unit_label' => 'P',
    'label_function' => 'sr_point_display_name',
    'unit_function' => 'sr_point_unit_label',
    'balance_table' => 'sr_point_balances',
    'transaction_table' => 'sr_point_transactions',
    'balance_function' => 'sr_point_balance',
    'transaction_function' => 'sr_point_create_transaction',
    'transaction_type' => 'expire',
    'process_label' => '소멸',
    'ledger_process_label' => 'expire',
    'sort_order' => 10,
];
