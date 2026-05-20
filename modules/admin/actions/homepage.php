<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = '';
$values = sr_admin_homepage_settings($pdo, $site ?? null);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $postResult = sr_admin_handle_homepage_post($pdo, $account, $site ?? null);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    $values = $postResult['values'];
    $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);
}

$homepageCandidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
$currentHomepageAvailable = sr_site_home_path_is_available($pdo, (string) ($values['home_path'] ?? '/'));

include SR_ROOT . '/modules/admin/views/homepage.php';
