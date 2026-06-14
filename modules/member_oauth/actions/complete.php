<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

sr_render_error(501, 'OAuth completion flow is not enabled yet.');
