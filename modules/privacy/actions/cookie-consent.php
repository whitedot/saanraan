<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/privacy/helpers.php';

sr_require_csrf();

$consent = sr_post_string('consent', 40);
if ($consent === 'custom') {
    $postedItems = $_POST['optional_items'] ?? [];
    $postedItems = is_array($postedItems) ? $postedItems : [];
    $consent = sr_privacy_cookie_consent_value_from_items(array_values($postedItems));
} elseif (!in_array($consent, sr_privacy_cookie_consent_values(), true)) {
    $consent = 'essential';
}

sr_privacy_cookie_consent_set($consent);

$returnTo = sr_post_string_without_truncation('return_to', 1024);
sr_redirect(sr_member_safe_next_path(is_string($returnTo) ? $returnTo : '/'));
