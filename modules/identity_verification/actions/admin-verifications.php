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

$status = sr_get_string('status', 30);
$providerKey = sr_identity_verification_provider_key(sr_get_string('provider_key', 60));
$where = [];
$params = [];
if ($status !== '' && in_array($status, ['ready', 'pending', 'verified', 'failed', 'expired', 'canceled'], true)) {
    $where[] = 'a.status = :status';
    $params['status'] = $status;
}
if ($providerKey !== '') {
    $where[] = 'a.provider_key = :provider_key';
    $params['provider_key'] = $providerKey;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare(
    'SELECT a.*, r.id AS result_id, r.verified_at, r.expires_at AS result_expires_at
     FROM sr_identity_verification_attempts a
     LEFT JOIN sr_identity_verification_results r ON r.attempt_id = a.id
     ' . $whereSql . '
     ORDER BY a.id DESC
     LIMIT 100'
);
$stmt->execute($params);
$attempts = $stmt->fetchAll();
$providers = sr_identity_verification_providers($pdo);

include SR_ROOT . '/modules/identity_verification/views/admin-verifications.php';
