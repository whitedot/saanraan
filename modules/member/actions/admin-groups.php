<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

if (!sr_member_groups_table_exists($pdo)) {
    sr_render_error(500, '회원 그룹 테이블이 없습니다. 회원 모듈 업데이트를 먼저 적용하세요.');
}

$errors = [];
$notice = '';
$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
if (!in_array($memberGroupsPage, ['groups', 'group_form', 'rules', 'rule_form', 'evaluations', 'assignments'], true)) {
    $memberGroupsPage = 'groups';
}
$allowedStatuses = sr_member_group_statuses();
$allowedRuleStatuses = sr_member_group_rule_statuses();
$allowedEvaluationPolicies = sr_member_group_evaluation_policies();
$ruleDefinitions = sr_member_group_rule_definitions($pdo);
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();

if (sr_request_method() === 'POST') {
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    if ($intent === 'save_group') {
        $groupId = sr_admin_post_positive_int('group_id');
        $groupKey = sr_post_string('group_key', 60);
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);

        if ($groupId < 1 && !sr_member_group_key_is_valid($groupKey)) {
            $errors[] = '그룹 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
        }

        if ($title === '') {
            $errors[] = '그룹 이름을 입력하세요.';
        }

        if ($description === null) {
            $errors[] = '설명은 2000자 이하로 입력하세요.';
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '그룹 상태 값이 올바르지 않습니다.';
        }

        if ($sortOrder === null) {
            $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
            $sortOrder = 0;
        }

        if ($errors === [] && $groupId > 0) {
            $existingGroup = sr_member_group_by_id($pdo, $groupId);
            if (!is_array($existingGroup)) {
                $errors[] = '수정할 그룹을 찾을 수 없습니다.';
            }
        }

        if ($errors === [] && $groupId < 1 && sr_member_group_by_key($pdo, $groupKey) !== null) {
            $errors[] = '이미 사용 중인 그룹 key입니다.';
        }

        if ($errors === []) {
            $savedGroupId = sr_member_group_save($pdo, [
                'id' => $groupId,
                'group_key' => $groupKey,
                'title' => $title,
                'description' => (string) $description,
                'status' => $status,
                'sort_order' => (int) $sortOrder,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $groupId > 0 ? 'member.group.updated' : 'member.group.created',
                'target_type' => 'member_group',
                'target_id' => (string) $savedGroupId,
                'result' => 'success',
                'message' => 'Member group saved.',
                'metadata' => [
                    'status' => $status,
                ],
            ]);

            $notice = $groupId > 0 ? '회원 그룹을 수정했습니다.' : '회원 그룹을 만들었습니다.';
        }
    } elseif ($intent === 'save_rule') {
        $ruleId = sr_admin_post_positive_int('rule_id');
        $groupId = sr_admin_post_positive_int('group_id');
        $definitionKey = sr_post_string('definition_key', 200);
        $ruleParamsJson = sr_post_string_without_truncation('rule_params_json', 5000);
        $ruleParamInputs = $_POST['rule_param'][$definitionKey] ?? null;
        $evaluationPolicy = sr_post_string('evaluation_policy', 30);
        $status = sr_post_string('status', 30);

        if ($groupId < 1 || !is_array(sr_member_group_by_id($pdo, $groupId))) {
            $errors[] = '자동 규칙을 적용할 그룹을 선택하세요.';
        }

        if (!isset($ruleDefinitions[$definitionKey])) {
            $errors[] = '자동 규칙 조건 후보가 올바르지 않습니다.';
        }

        if (is_array($ruleParamInputs) && isset($ruleDefinitions[$definitionKey])) {
            $decodedParams = sr_member_group_rule_params_from_input($ruleDefinitions[$definitionKey], $ruleParamInputs);
            $ruleParamsJson = '{}';
        } elseif ($ruleParamsJson === null) {
            $errors[] = '조건 설정 JSON은 5000자 이하로 입력하세요.';
            $ruleParamsJson = '{}';
            $decodedParams = [];
        } else {
            $decodedParams = json_decode((string) $ruleParamsJson, true);
            if (!is_array($decodedParams)) {
                $errors[] = '조건 설정 JSON 형식이 올바르지 않습니다.';
                $decodedParams = [];
            }
        }

        if (!in_array($evaluationPolicy, $allowedEvaluationPolicies, true)) {
            $errors[] = '평가 정책 값이 올바르지 않습니다.';
        }

        if (!in_array($status, $allowedRuleStatuses, true)) {
            $errors[] = '자동 규칙 상태 값이 올바르지 않습니다.';
        }

        if ($errors === [] && $ruleId > 0 && !is_array(sr_member_group_rule_by_id($pdo, $ruleId))) {
            $errors[] = '수정할 자동 규칙을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $definition = $ruleDefinitions[$definitionKey];
            $normalizedParamsJson = json_encode($decodedParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($normalizedParamsJson)) {
                $normalizedParamsJson = '{}';
            }

            $savedRuleId = sr_member_group_rule_save($pdo, [
                'id' => $ruleId,
                'group_id' => $groupId,
                'source_module_key' => (string) $definition['source_module_key'],
                'rule_key' => (string) $definition['rule_key'],
                'rule_params_json' => $normalizedParamsJson,
                'evaluation_policy' => $evaluationPolicy,
                'status' => $status,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $ruleId > 0 ? 'member.group_rule.updated' : 'member.group_rule.created',
                'target_type' => 'member_group_rule',
                'target_id' => (string) $savedRuleId,
                'result' => 'success',
                'message' => 'Member group rule saved.',
                'metadata' => [
                    'group_id' => $groupId,
                    'source_module_key' => (string) $definition['source_module_key'],
                    'rule_key' => (string) $definition['rule_key'],
                    'evaluation_policy' => $evaluationPolicy,
                    'status' => $status,
                ],
            ]);

            $notice = $ruleId > 0 ? '자동 규칙을 수정했습니다.' : '자동 규칙을 만들었습니다.';
        }
    } elseif ($intent === 'evaluate_account' || $intent === 'evaluate_batch') {
        $targetAccountIdentifier = sr_post_string('account_identifier', 80);
        $targetAccountField = sr_post_string('account_identifier_field', 20);
        if ($targetAccountIdentifier === '') {
            $targetAccountIdentifier = sr_post_string('account_id', 80);
        }
        $targetAccountId = sr_admin_member_account_id_from_lookup($pdo, $runtimeConfig, $targetAccountField, $targetAccountIdentifier);
        $sourceModuleKey = sr_post_string('source_module_key', 60);
        $limit = sr_admin_post_int_in_range('limit', 1, 200);

        if ($intent === 'evaluate_account' && $targetAccountId < 1) {
            $errors[] = '재평가할 회원 공개 해시를 입력하세요.';
        }

        if ($intent === 'evaluate_batch' && $limit === null) {
            $errors[] = 'batch 재평가 수는 1 이상 200 이하의 정수여야 합니다.';
            $limit = 50;
        }

        if ($sourceModuleKey !== '' && !sr_is_safe_module_key($sourceModuleKey)) {
            $errors[] = '모듈 key가 올바르지 않습니다.';
        }

        if ($errors === [] && $intent === 'evaluate_account') {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetAccountId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '회원을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            if ($intent === 'evaluate_account') {
                $summary = sr_member_group_evaluate_account($pdo, $targetAccountId, [
                    'source_module_key' => $sourceModuleKey,
                ]);
                $targetType = 'member_account';
                $targetId = (string) $targetAccountId;
                $eventType = 'member.group_rules.evaluated';
                $notice = '회원 그룹 자동 규칙을 재평가했습니다. 평가 ' . (string) $summary['evaluated'] . '건, 부여 ' . (string) $summary['granted'] . '건, 회수 ' . (string) $summary['revoked'] . '건.';
            } else {
                $summary = sr_member_group_evaluate_accounts($pdo, [
                    'source_module_key' => $sourceModuleKey,
                    'limit' => (int) $limit,
                ]);
                $targetType = 'member_group_rule';
                $targetId = 'batch';
                $eventType = 'member.group_rules.batch_evaluated';
                $notice = '회원 그룹 자동 규칙을 batch 재평가했습니다. 회원 ' . (string) $summary['accounts'] . '명, 평가 ' . (string) $summary['evaluated'] . '건, 부여 ' . (string) $summary['granted'] . '건, 회수 ' . (string) $summary['revoked'] . '건.';
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $eventType,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'result' => 'success',
                'message' => 'Member group rules evaluated.',
                'metadata' => $summary,
            ]);
        }
    } elseif ($intent === 'grant_manual' || $intent === 'revoke_manual') {
        $groupId = sr_admin_post_positive_int('group_id');
        $targetAccountIdentifier = sr_post_string('account_identifier', 80);
        $targetAccountField = sr_post_string('account_identifier_field', 20);
        if ($targetAccountIdentifier === '') {
            $targetAccountIdentifier = sr_post_string('account_id', 80);
        }
        $targetAccountId = sr_admin_member_account_id_from_lookup($pdo, $runtimeConfig, $targetAccountField, $targetAccountIdentifier);

        if ($groupId < 1) {
            $errors[] = '그룹을 선택하세요.';
        }

        if ($targetAccountId < 1) {
            $errors[] = '회원 공개 해시를 입력하세요.';
        }

        if ($errors === []) {
            $group = sr_member_group_by_id($pdo, $groupId);
            if (!is_array($group)) {
                $errors[] = '그룹을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetAccountId]);
            $targetAccount = $stmt->fetch();
            if (!is_array($targetAccount)) {
                $errors[] = '회원을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            if ($intent === 'grant_manual') {
                sr_member_group_grant_manual($pdo, $targetAccountId, $groupId, (int) $account['id']);
                $eventType = 'member.group.manual_granted';
                $notice = '회원에게 그룹을 수동 배정했습니다.';
            } else {
                sr_member_group_revoke_manual($pdo, $targetAccountId, $groupId, (int) $account['id']);
                $eventType = 'member.group.manual_revoked';
                $notice = '회원 그룹 수동 배정을 해제했습니다.';
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $eventType,
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Member group membership changed.',
                'metadata' => [
                    'group_id' => $groupId,
                    'assignment_type' => 'manual',
                ],
            ]);
        }
    } else {
        $errors[] = '회원 그룹 작업 값이 올바르지 않습니다.';
    }
}

$editGroupId = 0;
$editIdValue = sr_get_string('edit_id', 20);
if ($editIdValue !== '' && preg_match('/\A[1-9][0-9]*\z/', $editIdValue) === 1) {
    $editGroupId = (int) $editIdValue;
}

$editRuleId = 0;
$editRuleIdValue = sr_get_string('edit_rule_id', 20);
if ($editRuleIdValue !== '' && preg_match('/\A[1-9][0-9]*\z/', $editRuleIdValue) === 1) {
    $editRuleId = (int) $editRuleIdValue;
}

$editGroup = $editGroupId > 0 ? sr_member_group_by_id($pdo, $editGroupId) : null;
$editRule = $editRuleId > 0 ? sr_member_group_rule_by_id($pdo, $editRuleId) : null;
$groups = sr_member_groups($pdo);
$groupRules = sr_member_group_rules($pdo);
$memberships = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_member_group_memberships($pdo, 100));
$membershipLogs = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_member_group_logs($pdo, 50));

include SR_ROOT . '/modules/member/views/admin-groups.php';
