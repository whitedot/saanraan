<?php

return [
    'helpers' => 'helpers.php',
    'label' => '예치금',
    'unit_label' => '원',
    'available_function' => 'sr_deposit_usage_enabled',
    'balance_function' => 'sr_deposit_balance',
    'transaction_function' => 'sr_deposit_create_transaction',
    'cash_like' => true,
    'exchange_out_type' => 'exchange_out',
    'exchange_in_type' => 'exchange_in',
    'exchange_fee_type' => 'exchange_fee',
];
