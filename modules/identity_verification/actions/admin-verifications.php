<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/identity_verification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/identity-verifications', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

$path = sr_request_path();
$openDetailId = 0;
if (preg_match('#\A/admin/identity-verifications/([0-9]+)\z#', $path, $matches) === 1) {
    $openDetailId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $openDetailId]);
    if (!is_array($stmt->fetch())) {
        sr_render_error(404, '본인확인 이력을 찾을 수 없습니다.');
    }
}

$providers = sr_identity_verification_providers($pdo);
$filters = sr_identity_verification_admin_attempt_filters_from_request($pdo);
if ($openDetailId > 0) {
    $filters['id'] = $openDetailId;
}
$attemptSortOptions = sr_identity_verification_admin_attempt_sort_options();
$attemptDefaultSort = sr_identity_verification_admin_attempt_default_sort();
$attemptSort = sr_admin_sort_from_request($attemptSortOptions, $attemptDefaultSort);
$attemptPagination = sr_admin_pagination_from_total($pdo, sr_identity_verification_admin_attempt_count($pdo, $filters));
$attempts = sr_identity_verification_admin_attempts(
    $pdo,
    $filters,
    (int) $attemptPagination['per_page'],
    sr_admin_pagination_offset($attemptPagination),
    $attemptSort
);
$attemptDetailsById = [];
$attemptIds = [];
foreach ($attempts as $attempt) {
    $attemptId = (int) ($attempt['id'] ?? 0);
    if ($attemptId > 0) {
        $attemptIds[] = $attemptId;
        $attemptDetailsById[$attemptId] = [
            'result' => null,
            'links' => [],
        ];
    }
}

if ($attemptIds !== []) {
    $placeholders = implode(', ', array_fill(0, count($attemptIds), '?'));
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_results WHERE attempt_id IN (' . $placeholders . ') ORDER BY id ASC');
    $stmt->execute($attemptIds);
    $resultIds = [];
    foreach ($stmt->fetchAll() as $result) {
        if (!is_array($result)) {
            continue;
        }
        $attemptId = (int) ($result['attempt_id'] ?? 0);
        $resultId = (int) ($result['id'] ?? 0);
        if ($attemptId > 0 && isset($attemptDetailsById[$attemptId])) {
            $attemptDetailsById[$attemptId]['result'] = $result;
        }
        if ($resultId > 0) {
            $resultIds[$resultId] = $attemptId;
        }
    }

    if ($resultIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($resultIds), '?'));
        $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_links WHERE result_id IN (' . $placeholders . ') ORDER BY result_id ASC, id ASC');
        $stmt->execute(array_keys($resultIds));
        foreach ($stmt->fetchAll() as $link) {
            if (!is_array($link)) {
                continue;
            }
            $resultId = (int) ($link['result_id'] ?? 0);
            $attemptId = (int) ($resultIds[$resultId] ?? 0);
            if ($attemptId > 0 && isset($attemptDetailsById[$attemptId])) {
                $attemptDetailsById[$attemptId]['links'][] = $link;
            }
        }
    }
}

include SR_ROOT . '/modules/identity_verification/views/admin-verifications.php';
