<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/members', 'view');

$accountIdValue = sr_get_string('id', 20);
$accountId = preg_match('/\A[1-9][0-9]*\z/', $accountIdValue) === 1 ? (int) $accountIdValue : 0;
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$member = sr_admin_member_by_id($pdo, $accountId);

if (!is_array($member)) {
    sr_json_response([
        'ok' => false,
        'message' => '회원 정보를 찾을 수 없습니다.',
    ], 404);
}

sr_json_response([
    'ok' => true,
    'member' => [
        'id' => (int) $member['id'],
        'account_public_hash' => sr_admin_member_public_hash($runtimeConfig, (int) $member['id']),
        'display_name' => sr_admin_member_display_name_preview($member),
        'email' => sr_admin_member_email_display($member),
        'locale' => (string) ($member['locale'] ?? ''),
        'status' => (string) ($member['status'] ?? ''),
        'status_label' => sr_admin_code_label((string) ($member['status'] ?? ''), 'member_status'),
        'email_verified' => (string) ($member['email_verified_at'] ?? '') !== '',
        'email_verified_label' => (string) ($member['email_verified_at'] ?? '') !== '' ? '인증됨' : '미인증',
        'last_login_at' => (string) ($member['last_login_at'] ?? ''),
        'created_at' => (string) ($member['created_at'] ?? ''),
        'updated_at' => (string) ($member['updated_at'] ?? ''),
        'edit_url' => sr_url('/admin/members/edit?id=' . (string) (int) $member['id']),
    ],
]);
