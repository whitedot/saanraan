<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/asset-policy-sets', 'view');

$errors = [];
$notice = $_SESSION['sr_content_asset_policy_set_notice'] ?? '';
$sessionErrors = $_SESSION['sr_content_asset_policy_set_errors'] ?? [];
$sessionValues = $_SESSION['sr_content_asset_policy_set_values'] ?? [];
unset($_SESSION['sr_content_asset_policy_set_notice'], $_SESSION['sr_content_asset_policy_set_errors'], $_SESSION['sr_content_asset_policy_set_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}

$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];
$policySetPage = sr_get_string('mode', 20);
if (!in_array($policySetPage, ['new', 'edit'], true)) {
    $policySetPage = 'list';
}
$editPolicySet = null;
if ($policySetPage === 'edit') {
    $setId = (int) sr_get_string('id', 20);
    $editPolicySet = sr_content_asset_policy_set_by_id($pdo, $setId);
    if (!is_array($editPolicySet)) {
        sr_render_error(404, '콘텐츠 회원 그룹 정책을 찾을 수 없습니다.');
    }
}

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/asset-policy-sets', 'edit');
    sr_require_csrf();

    $setId = (int) sr_post_string('set_id', 20);
    $isUpdate = $setId > 0;
    $existing = $isUpdate ? sr_content_asset_policy_set_by_id($pdo, $setId) : null;
    if ($isUpdate && !is_array($existing)) {
        $errors[] = '수정할 콘텐츠 회원 그룹 정책을 찾을 수 없습니다.';
    }

    $policiesJson = sr_content_asset_group_policy_json_from_post('policies');
    $values = [
        'set_key' => strtolower(trim(sr_post_string('set_key', 60))),
        'title' => sr_content_clean_single_line(sr_post_string('title', 120), 120),
        'description' => sr_content_clean_text(sr_post_string('description', 1000), 1000),
        'status' => sr_post_string('status', 30),
        'policies_json' => $policiesJson,
    ];

    if (!sr_content_asset_policy_set_key_is_valid((string) $values['set_key'])) {
        $errors[] = '회원 그룹 정책 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_content_asset_policy_set_key_exists($pdo, (string) $values['set_key'], $setId)) {
        $errors[] = '이미 사용 중인 회원 그룹 정책 key입니다.';
    }
    if ((string) $values['title'] === '') {
        $errors[] = '회원 그룹 정책 이름을 입력하세요.';
    }
    if (!in_array((string) $values['status'], sr_content_asset_policy_set_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($policiesJson), '콘텐츠 회원 그룹 정책'));

    if ($errors !== []) {
        $_SESSION['sr_content_asset_policy_set_errors'] = $errors;
        $_SESSION['sr_content_asset_policy_set_values'] = $values + ['id' => $setId];
        sr_redirect($isUpdate ? '/admin/content/asset-policy-sets?mode=edit&id=' . (string) $setId : '/admin/content/asset-policy-sets?mode=new');
    }

    $savedId = sr_content_save_asset_policy_set($pdo, $values, (int) $account['id'], $setId);
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => $isUpdate ? 'content.asset_policy_set.updated' : 'content.asset_policy_set.created',
        'target_type' => 'content_asset_policy_set',
        'target_id' => (string) $savedId,
        'result' => 'success',
        'message' => 'Content asset policy set saved.',
        'metadata' => $values,
    ]);

    $_SESSION['sr_content_asset_policy_set_notice'] = '콘텐츠 회원 그룹 정책을 저장했습니다.';
    sr_redirect('/admin/content/asset-policy-sets');
}

$policySets = sr_content_asset_policy_sets($pdo);
$values = is_array($sessionValues) && $sessionValues !== []
    ? $sessionValues
    : (is_array($editPolicySet) ? $editPolicySet : [
        'id' => 0,
        'set_key' => '',
        'title' => '',
        'description' => '',
        'status' => 'enabled',
        'policies_json' => '',
    ]);

include SR_ROOT . '/modules/content/views/admin-asset-policy-sets.php';
