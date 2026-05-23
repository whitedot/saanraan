<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);

if (!sr_member_groups_table_exists($pdo)) {
    sr_render_error(500, sr_t('member::action.admin_groups.table_missing'));
}

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
if (!in_array($memberGroupsPage, ['groups', 'group_form', 'rules', 'rule_form'], true)) {
    $memberGroupsPage = 'groups';
}
$memberGroupPermissionPath = [
    'groups' => '/admin/member-groups',
    'group_form' => '/admin/member-groups',
    'rules' => '/admin/member-group-rules',
    'rule_form' => '/admin/member-group-rules',
][$memberGroupsPage];
sr_admin_require_permission($pdo, (int) $account['id'], $memberGroupPermissionPath, 'view');
$allowedStatuses = sr_member_group_statuses();
$allowedRuleStatuses = sr_member_group_rule_statuses();
$allowedEvaluationPolicies = sr_member_group_evaluation_policies();
$ruleDefinitions = sr_member_group_rule_definitions($pdo);
$memberRuleSourceOptions = [];
$stmt = $pdo->query("SELECT DISTINCT source_module_key FROM sr_member_group_rules WHERE status = 'enabled' ORDER BY source_module_key ASC");
foreach ($stmt->fetchAll() as $row) {
    $sourceModuleKey = (string) ($row['source_module_key'] ?? '');
    if (!sr_is_safe_module_key($sourceModuleKey)) {
        continue;
    }

    $metadata = sr_module_metadata($sourceModuleKey);
    $moduleName = trim((string) ($metadata['name'] ?? ''));
    $memberRuleSourceOptions[$sourceModuleKey] = [
        'module_key' => $sourceModuleKey,
        'label' => $moduleName !== '' ? sr_admin_module_name_label($moduleName) : $sourceModuleKey,
    ];
}
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], $memberGroupPermissionPath, $intent === 'revoke_manual' ? 'delete' : 'edit');
    if ($intent === 'save_group') {
        $groupId = sr_admin_post_positive_int('group_id');
        $groupKey = strtolower(trim(sr_post_string('group_key', 60)));
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);

        if ($groupId < 1 && !sr_member_group_key_is_valid($groupKey)) {
            $errors[] = sr_t('member::action.admin_groups.group_key_invalid');
        }

        if ($title === '') {
            $errors[] = sr_t('member::action.admin_groups.group_title_required');
        }

        if ($description === null) {
            $errors[] = sr_t('member::action.admin_groups.description_too_long');
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('member::action.admin_groups.group_status_invalid');
        }

        if ($sortOrder === null) {
            $errors[] = sr_t('member::action.admin_groups.sort_order_invalid');
            $sortOrder = 0;
        }

        if ($errors === [] && $groupId > 0) {
            $existingGroup = sr_member_group_by_id($pdo, $groupId);
            if (!is_array($existingGroup)) {
                $errors[] = sr_t('member::action.admin_groups.group_edit_not_found');
            }
        }

        if ($errors === [] && $groupId < 1 && sr_member_group_by_key($pdo, $groupKey) !== null) {
            $errors[] = sr_t('member::action.admin_groups.group_key_duplicate');
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

            $notice = $groupId > 0 ? sr_t('member::action.admin_groups.group_updated') : sr_t('member::action.admin_groups.group_created');
            if ($groupId <= 0) {
                sr_admin_flash_result(sr_admin_action_result([], $notice));
                sr_redirect('/admin/member-groups');
            }
        }
    } elseif ($intent === 'save_rule') {
        $ruleId = sr_admin_post_positive_int('rule_id');
        $groupId = sr_admin_post_positive_int('group_id');
        $definitionKey = sr_post_string('definition_key', 200);
        $ruleParamInputs = $_POST['rule_param'][$definitionKey] ?? null;
        $evaluationPolicy = sr_post_string('evaluation_policy', 30);
        $status = sr_post_string('status', 30);
        $decodedParams = [];

        if ($groupId < 1 || !is_array(sr_member_group_by_id($pdo, $groupId))) {
            $errors[] = sr_t('member::action.admin_groups.rule_group_required');
        }

        if (!isset($ruleDefinitions[$definitionKey])) {
            $errors[] = sr_t('member::action.admin_groups.rule_definition_invalid');
        }

        if (isset($ruleDefinitions[$definitionKey])) {
            $decodedParams = sr_member_group_rule_params_from_input(
                $ruleDefinitions[$definitionKey],
                is_array($ruleParamInputs) ? $ruleParamInputs : [],
                $pdo
            );
        }

        if (!in_array($evaluationPolicy, $allowedEvaluationPolicies, true)) {
            $errors[] = sr_t('member::action.admin_groups.evaluation_policy_invalid');
        }

        if (!in_array($status, $allowedRuleStatuses, true)) {
            $errors[] = sr_t('member::action.admin_groups.rule_status_invalid');
        }

        if ($errors === [] && $ruleId > 0 && !is_array(sr_member_group_rule_by_id($pdo, $ruleId))) {
            $errors[] = sr_t('member::action.admin_groups.rule_edit_not_found');
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

            $notice = $ruleId > 0 ? sr_t('member::action.admin_groups.rule_updated') : sr_t('member::action.admin_groups.rule_created');
            if ($ruleId <= 0) {
                sr_admin_flash_result(sr_admin_action_result([], $notice));
                sr_redirect('/admin/member-group-rules');
            }
        }
    } elseif ($intent === 'evaluate_account') {
        $targetAccountIdentifier = sr_post_string('account_identifier', 80);
        $targetAccountField = sr_post_string('account_identifier_field', 20);
        if ($targetAccountIdentifier === '') {
            $targetAccountIdentifier = sr_post_string('account_id', 80);
        }
        $targetAccountId = sr_admin_member_account_id_from_lookup($pdo, $runtimeConfig, $targetAccountField, $targetAccountIdentifier);
        $sourceModuleKey = strtolower(trim(sr_post_string('source_module_key', 60)));

        if ($targetAccountId < 1) {
            $errors[] = sr_t('member::action.admin_groups.evaluate_member_required');
        }

        if ($sourceModuleKey !== '' && (!sr_is_safe_module_key($sourceModuleKey) || !isset($memberRuleSourceOptions[$sourceModuleKey]))) {
            $errors[] = sr_t('member::action.admin_groups.module_key_invalid');
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetAccountId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = sr_t('member::action.admin.member_not_found');
            }
        }

        if ($errors === []) {
            $summary = sr_member_group_evaluate_account($pdo, $targetAccountId, [
                'source_module_key' => $sourceModuleKey,
            ]);
            $targetType = 'member_account';
            $targetId = (string) $targetAccountId;
            $eventType = 'member.group_rules.evaluated';
            $notice = sr_t('member::action.admin_groups.evaluated', [
                'evaluated' => (string) $summary['evaluated'],
                'granted' => (string) $summary['granted'],
                'revoked' => (string) $summary['revoked'],
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $eventType,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'result' => 'success',
                'message' => 'Member group rules evaluated.',
                'metadata' => array_merge($summary, [
                    'source_module_key' => $sourceModuleKey,
                ]),
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
            $errors[] = sr_t('member::action.admin_groups.group_required');
        }

        if ($targetAccountId < 1) {
            $errors[] = sr_t('member::action.admin_groups.member_hash_required');
        }

        if ($errors === []) {
            $group = sr_member_group_by_id($pdo, $groupId);
            if (!is_array($group)) {
                $errors[] = sr_t('member::action.admin_groups.group_not_found');
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetAccountId]);
            $targetAccount = $stmt->fetch();
            if (!is_array($targetAccount)) {
                $errors[] = sr_t('member::action.admin.member_not_found');
            }
        }

        if ($errors === []) {
            if ($intent === 'grant_manual') {
                sr_member_group_grant_manual($pdo, $targetAccountId, $groupId, (int) $account['id']);
                $eventType = 'member.group.manual_granted';
                $notice = sr_t('member::action.admin_groups.manual_granted');
            } else {
                sr_member_group_revoke_manual($pdo, $targetAccountId, $groupId, (int) $account['id']);
                $eventType = 'member.group.manual_revoked';
                $notice = sr_t('member::action.admin_groups.manual_revoked');
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
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/member-groups');
        }
    } else {
        $errors[] = sr_t('member::action.admin_groups.intent_invalid');
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
$groupListFilter = sr_admin_member_group_list_filter($allowedStatuses);
$allGroups = sr_member_groups($pdo);
$groupStatusCounts = sr_admin_member_group_status_counts($allGroups);
$groups = $memberGroupsPage === 'groups'
    ? sr_admin_member_group_filter_rows($allGroups, $groupListFilter)
    : $allGroups;
$groupRules = sr_member_group_rules($pdo);
$membershipsByGroupId = [];
$membershipLogsByGroupId = [];
if ($memberGroupsPage === 'groups') {
    foreach ($groups as $group) {
        $groupId = (int) ($group['id'] ?? 0);
        if ($groupId < 1) {
            continue;
        }

        $membershipsByGroupId[$groupId] = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_member_group_memberships($pdo, 50, $groupId));
        $membershipLogsByGroupId[$groupId] = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_member_group_logs($pdo, 50, $groupId));
    }
}

include SR_ROOT . '/modules/member/views/admin-groups.php';
