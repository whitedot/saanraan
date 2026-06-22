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
$limitInput = (int) sr_get_string('limit', 5);
$limit = $limitInput > 0 ? max(1, min(30, $limitInput)) : 20;
$options = [
    'cursor' => sr_get_string('cursor', 40),
    'board_id' => sr_get_string('board_id', 40),
    'status' => sr_get_string('status', 30),
    'response' => 'cursor',
];
$result = array_key_exists($referenceType, $allowedTypes)
    ? sr_popup_layer_subject_search($pdo, $referenceType, sr_get_string('q', 120), $limit, $options)
    : [];

if (!isset($result['items']) || !is_array($result['items'])) {
    $result = [
        'items' => is_array($result) ? $result : [],
        'next_cursor' => null,
        'has_more' => false,
        'limit' => $limit,
        'notice' => '',
    ];
}

sr_json_response($result);
