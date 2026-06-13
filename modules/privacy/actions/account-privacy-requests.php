<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_render_error(405, sr_t('privacy::action.error.method_not_allowed'));
}

include SR_ROOT . '/modules/privacy/views/account-privacy-requests.php';
