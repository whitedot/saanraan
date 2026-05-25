<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'view');
$canEditNicknames = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');
$settings = sr_community_settings($pdo);
$nicknameRequired = !empty($settings['nickname_enabled']) && !empty($settings['nickname_required']);

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');

    $postResult = sr_community_handle_nickname_post($pdo, $account, $settings);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if ($errors === []) {
        sr_admin_flash_result(sr_admin_action_result([], $notice));
        sr_redirect('/admin/community/nicknames');
    }
}

$nicknameFilter = sr_community_nickname_filter($pdo, $runtimeConfig);
$nicknameTotal = sr_community_nickname_count($pdo, $nicknameFilter);
$nicknamePagination = sr_admin_pagination_from_total($pdo, $nicknameTotal);
$nicknameRows = sr_admin_member_rows_with_public_hash(
    $runtimeConfig,
    sr_community_nickname_rows($pdo, $nicknameFilter, (int) $nicknamePagination['per_page'], sr_admin_pagination_offset($nicknamePagination))
);

include SR_ROOT . '/modules/community/views/admin-nicknames.php';
