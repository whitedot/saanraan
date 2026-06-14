<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/privacy/helpers.php';

$returnTo = sr_get_string_without_truncation('return_to', 1024);
$cookieConsentReturnTo = sr_member_safe_next_path(is_string($returnTo) ? $returnTo : '/');
$cookieConsentSelectedItems = sr_privacy_cookie_consent_selected_items();

include SR_ROOT . '/modules/privacy/views/cookie-settings.php';
