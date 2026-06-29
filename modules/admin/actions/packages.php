<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/packages', 'view');

$packageThemeCandidates = sr_package_theme_candidates();
$packageSkinCandidatesByModule = [];
foreach (array_keys(sr_package_skin_contracts()) as $packageSkinModuleKey) {
    $packageSkinCandidatesByModule[$packageSkinModuleKey] = sr_package_skin_candidates((string) $packageSkinModuleKey);
}
$packageLayoutHealthWarnings = sr_public_layout_health_warnings($pdo, $site);

include SR_ROOT . '/modules/admin/views/packages.php';
