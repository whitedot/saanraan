<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/public-report.php';

return [
    'context_function' => 'sr_community_public_report_context',
    'feedback_function' => 'sr_community_public_report_pop_feedback',
    'render_function' => 'sr_community_public_report_form_html',
];
