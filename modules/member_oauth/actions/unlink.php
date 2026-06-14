<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

sr_require_csrf();
sr_render_error(501, 'OAuth unlink flow is not enabled yet.');
