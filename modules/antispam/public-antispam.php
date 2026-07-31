<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'mode_function' => 'sr_antispam_mode',
    'mode_options_function' => 'sr_antispam_mode_options',
    'policy_function' => 'sr_antispam_policy',
    'render_function' => 'sr_antispam_challenge_render',
    'verify_function' => 'sr_antispam_verify',
];
