<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$config = sr_runtime_config();
$targetHash = strtolower(trim(sr_post_string('target_account_hash', 40)));
$intent = sr_post_string('intent', 20);
$returnTo = sr_post_string_without_truncation('return_to', 255);
$returnTo = is_string($returnTo) && sr_is_safe_relative_url($returnTo) ? $returnTo : '/';

$target = sr_member_follow_target_from_hash($pdo, $config, $targetHash);
$notice = '';
$errors = [];

if (!is_array($target)) {
    $errors[] = '팔로우할 회원을 찾을 수 없습니다.';
} elseif ((int) $target['id'] === (int) $account['id']) {
    $errors[] = '자기 자신은 팔로우할 수 없습니다.';
} elseif ($intent === 'follow') {
    if (sr_member_follow_account($pdo, (int) $account['id'], (int) $target['id'])) {
        $notice = '팔로우했습니다.';
    } else {
        $errors[] = '팔로우 상태를 저장하지 못했습니다.';
    }
} elseif ($intent === 'unfollow') {
    if (sr_member_unfollow_account($pdo, (int) $account['id'], (int) $target['id'])) {
        $notice = '팔로우를 끊었습니다.';
    } else {
        $notice = '팔로우 상태를 변경했습니다.';
    }
} else {
    $errors[] = '팔로우 요청을 확인해 주세요.';
}

$_SESSION['sr_member_follow_feedback'] = [
    'notice' => $notice,
    'errors' => $errors,
];

sr_redirect($returnTo);
