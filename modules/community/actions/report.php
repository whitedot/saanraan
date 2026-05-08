<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_require_csrf();

$targetType = toy_post_string('target_type', 30);
$targetIdValue = toy_post_string('target_id', 20);
$targetId = preg_match('/\A[1-9][0-9]*\z/', $targetIdValue) === 1 ? (int) $targetIdValue : 0;
$reasonKey = toy_post_string('reason_key', 40);
$memoText = toy_post_string_without_truncation('memo_text', 1000);
$target = toy_community_report_target($pdo, $targetType, $targetId, (int) $account['id']);
if (!is_array($target)) {
    toy_render_error(404, '신고 대상을 찾을 수 없습니다.');
}

$redirectPath = (string) $target['redirect_path'];
$errors = [];
if (!in_array($reasonKey, toy_community_report_reason_keys(), true)) {
    $errors[] = '신고 사유를 선택해 주세요.';
}

if ($memoText === null) {
    $errors[] = '신고 메모는 1000자 이하로 입력해 주세요.';
    $memoText = '';
}

if ((int) $target['reported_account_id'] === (int) $account['id']) {
    $errors[] = '본인이 작성한 내용은 신고할 수 없습니다.';
}

$settings = toy_module_settings($pdo, 'community');
if ($errors === [] && toy_community_report_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = '짧은 시간에 신고를 너무 많이 접수했습니다. 잠시 후 다시 시도해 주세요.';
}

if ($errors === [] && toy_community_report_exists($pdo, (int) $account['id'], (string) $target['target_type'], (int) $target['target_id'])) {
    $errors[] = '이미 신고한 대상입니다.';
}

if ($errors !== []) {
    $_SESSION['toy_community_report_errors'] = $errors;
    toy_redirect($redirectPath);
}

toy_community_create_report($pdo, [
    'target_type' => (string) $target['target_type'],
    'target_id' => (int) $target['target_id'],
    'reporter_account_id' => (int) $account['id'],
    'reported_account_id' => (int) $target['reported_account_id'],
    'reason_key' => $reasonKey,
    'memo_text' => (string) $memoText,
]);
toy_community_record_report_rate_limit($pdo, (int) $account['id'], $settings);
$_SESSION['toy_community_report_notice'] = '신고를 접수했습니다.';

toy_redirect($redirectPath);
