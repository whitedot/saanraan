<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'view');
$canResetNicknames = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');
$nicknameNotificationAvailable = sr_community_notification_available($pdo);

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');

    $postResult = sr_community_handle_nickname_reset_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if ($errors === []) {
        sr_admin_flash_result(sr_admin_action_result([], $notice));
        $returnPathInput = sr_post_string_without_truncation('return_path', 1000);
        $returnPath = is_string($returnPathInput) ? $returnPathInput : '';
        $returnPathInfo = parse_url($returnPath);
        $returnPathValue = is_array($returnPathInfo)
            && !isset($returnPathInfo['scheme'])
            && !isset($returnPathInfo['host'])
            && (string) ($returnPathInfo['path'] ?? '') === '/admin/community/nicknames'
            && !str_contains($returnPath, "\r")
            && !str_contains($returnPath, "\n")
            ? $returnPath
            : '/admin/community/nicknames';
        sr_redirect($returnPathValue);
    }
}

$nicknameFilter = sr_community_nickname_filter($pdo, $runtimeConfig);
$nicknameSearchSubmitted = trim((string) ($nicknameFilter['keyword'] ?? '')) !== '';
$nicknameTotal = $nicknameSearchSubmitted ? sr_community_nickname_count($pdo, $nicknameFilter) : 0;
$nicknamePagination = $nicknameSearchSubmitted
    ? sr_admin_pagination_from_total($pdo, $nicknameTotal)
    : sr_admin_pagination_meta(0, 50, 1);
$nicknameRows = $nicknameSearchSubmitted
    ? sr_admin_member_rows_with_public_hash(
        $runtimeConfig,
        sr_community_nickname_rows($pdo, $nicknameFilter, (int) $nicknamePagination['per_page'], sr_admin_pagination_offset($nicknamePagination))
    )
    : [];

include SR_ROOT . '/modules/community/views/admin-nicknames.php';
