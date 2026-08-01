<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/public-summary.php';

return [
    'context_function' => 'sr_message_public_summary_context',
    'enabled_function' => 'sr_message_enabled',
    'unread_count_function' => 'sr_message_unread_count',
];
