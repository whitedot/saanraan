<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'edit');

$availableTargets = sr_popup_layer_available_targets($pdo);
$allowedTypes = sr_popup_layer_subject_search_types($pdo, $availableTargets);
$referenceType = sr_get_string('reference_type', 60);

sr_json_response([
    'items' => array_key_exists($referenceType, $allowedTypes)
        ? sr_popup_layer_subject_search($pdo, $referenceType, sr_get_string('q', 120), 20)
        : [],
]);
