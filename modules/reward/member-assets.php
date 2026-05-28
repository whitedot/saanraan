<?php

return [
    'helpers' => 'helpers.php',
    'label' => '적립금',
    'unit_label' => '원',
    'balance_function' => 'sr_reward_balance',
    'transaction_function' => 'sr_reward_create_transaction',
    'transaction_table' => 'sr_reward_transactions',
    'use_type' => 'use',
    'credit_type' => 'grant',
    'refund_type' => 'refund',
    'deduction_order' => 20,
];
