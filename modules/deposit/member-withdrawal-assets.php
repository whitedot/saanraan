<?php

return [
    'helpers' => 'helpers.php',
    'label' => '예치금',
    'unit_label' => '원',
    'available_function' => 'sr_deposit_usage_enabled',
    'balance_table' => 'sr_deposit_balances',
    'transaction_table' => 'sr_deposit_transactions',
    'balance_function' => 'sr_deposit_balance',
    'transaction_function' => 'sr_deposit_create_transaction',
    'transaction_type' => 'withdraw',
    'process_label' => '환급',
    'ledger_process_label' => 'refund',
    'sort_order' => 30,
];
