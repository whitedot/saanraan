<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_community_settings($pdo);
$nextPath = sr_community_safe_next_path(
    sr_request_method() === 'POST' ? sr_post_string('next', 255) : sr_get_string('next', 255)
);
$errors = [];
$notice = '';
$values = [
    'nickname' => '',
];

if (empty($settings['nickname_enabled']) || sr_community_nickname_status_blocks_identity((string) ($account['status'] ?? ''))) {
    sr_redirect($nextPath);
}

if (sr_community_member_nickname($pdo, (int) $account['id']) !== '') {
    sr_redirect($nextPath);
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $nicknameInput = sr_post_string_without_truncation('nickname', 80);
    $values['nickname'] = is_string($nicknameInput) ? trim($nicknameInput) : '';
    $postResult = sr_community_handle_member_nickname_setup_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if ($errors === []) {
        sr_redirect($nextPath);
    }
}

include SR_ROOT . '/modules/community/views/nickname.php';
