<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'edit');

$availableTargets = sr_banner_available_targets($pdo);
$allowedTypes = sr_banner_subject_search_types($pdo, $availableTargets);
$referenceType = sr_get_string('reference_type', 60);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'items' => array_key_exists($referenceType, $allowedTypes)
        ? sr_banner_subject_search($pdo, $referenceType, sr_get_string('q', 120), 20)
        : [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
sr_finish_response();
