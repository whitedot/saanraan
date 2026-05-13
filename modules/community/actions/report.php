<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$targetType = sr_post_string('target_type', 30);
$targetIdValue = sr_post_string('target_id', 20);
$targetId = preg_match('/\A[1-9][0-9]*\z/', $targetIdValue) === 1 ? (int) $targetIdValue : 0;
$reasonKey = sr_post_string('reason_key', 40);
$memoText = sr_post_string_without_truncation('memo_text', 1000);
$target = sr_community_report_target($pdo, $targetType, $targetId, (int) $account['id']);
if (!is_array($target)) {
    sr_render_error(404, '신고 대상을 찾을 수 없습니다.');
}

$redirectPath = (string) $target['redirect_path'];
$errors = [];
if (!in_array($reasonKey, sr_community_report_reason_keys(), true)) {
    $errors[] = '신고 사유를 선택해 주세요.';
}

if ($memoText === null) {
    $errors[] = '신고 메모는 1000자 이하로 입력해 주세요.';
    $memoText = '';
}

if ((int) $target['reported_account_id'] === (int) $account['id']) {
    $errors[] = '본인이 작성한 대상은 신고할 수 없습니다.';
}

$settings = sr_community_settings($pdo);
if ($errors === [] && sr_community_report_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = '짧은 시간에 신고를 너무 많이 접수했습니다. 잠시 후 다시 시도해 주세요.';
}

if ($errors === [] && sr_community_report_exists($pdo, (int) $account['id'], (string) $target['target_type'], (int) $target['target_id'])) {
    $errors[] = '이미 신고한 대상입니다.';
}

if ($errors !== []) {
    $_SESSION['sr_community_report_errors'] = $errors;
    sr_redirect($redirectPath);
}

$reportId = sr_community_create_report($pdo, [
    'target_type' => (string) $target['target_type'],
    'target_id' => (int) $target['target_id'],
    'reporter_account_id' => (int) $account['id'],
    'reported_account_id' => (int) $target['reported_account_id'],
    'reason_key' => $reasonKey,
    'memo_text' => (string) $memoText,
]);
sr_community_record_report_rate_limit($pdo, (int) $account['id'], $settings);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.report.created',
    'target_type' => 'community_report',
    'target_id' => (string) $reportId,
    'result' => 'success',
    'message' => 'Community report created.',
    'metadata' => [
        'reported_target_type' => (string) $target['target_type'],
        'reported_target_id' => (int) $target['target_id'],
        'reported_account_id' => (int) $target['reported_account_id'],
        'reason_key' => $reasonKey,
    ],
]);
sr_community_create_admin_report_notifications(
    $pdo,
    $reportId,
    (string) $target['target_type'],
    (int) $target['target_id'],
    $reasonKey,
    (int) $account['id']
);
$_SESSION['sr_community_report_notice'] = '신고를 접수했습니다.';

sr_redirect($redirectPath);
