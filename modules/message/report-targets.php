<?php

declare(strict_types=1);

return [
    [
        'target_type' => 'message',
        'label' => '쪽지',
        'helpers' => 'helpers.php',
        'resolver_function' => 'sr_message_report_target',
        'redirect_path_prefix' => '/message',
        'actions' => ['none', 'suspend_reported_account'],
    ],
];
