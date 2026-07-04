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
$detailId = 0;
if (preg_match('#\A/admin/identity-verifications/([0-9]+)\z#', $path, $matches) === 1) {
    $detailId = (int) $matches[1];
}

$detail = null;
$detailResult = null;
$detailLinks = [];
if ($detailId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $detailId]);
    $detail = $stmt->fetch();
    if (!is_array($detail)) {
        sr_render_error(404, '본인확인 이력을 찾을 수 없습니다.');
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_results WHERE attempt_id = :attempt_id LIMIT 1');
    $stmt->execute(['attempt_id' => $detailId]);
    $result = $stmt->fetch();
    $detailResult = is_array($result) ? $result : null;
    if ($detailResult !== null) {
        $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_links WHERE result_id = :result_id ORDER BY id ASC');
        $stmt->execute(['result_id' => (int) $detailResult['id']]);
        $detailLinks = $stmt->fetchAll();
    }
}

$providers = sr_identity_verification_providers($pdo);
$filters = sr_identity_verification_admin_attempt_filters_from_request($pdo);
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

include SR_ROOT . '/modules/identity_verification/views/admin-verifications.php';
