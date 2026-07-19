<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'summary_function' => 'sr_notification_public_header_summary',
    'link_attributes_function' => 'sr_notification_item_link_attributes',
    'clean_text_function' => 'sr_notification_clean_single_line',
    'time_function' => 'sr_notification_time_html',
];
