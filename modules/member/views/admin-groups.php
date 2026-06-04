<?php

$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
$groupSort = isset($groupSort) && is_array($groupSort) ? $groupSort : sr_admin_member_group_default_sort();
$groupRuleSort = isset($groupRuleSort) && is_array($groupRuleSort) ? $groupRuleSort : sr_member_group_rule_default_sort();
$adminContainerClass = 'admin-page-member-groups admin-ui-scope';
$adminPageTitle = sr_t('member::ui.member.7482bebf');
if ($memberGroupsPage === 'group_form') {
    $adminPageTitle = is_array($editGroup) ? sr_t('member::ui.member.edit.c267c25d') : sr_t('member::ui.member.c879c4be');
} elseif ($memberGroupsPage === 'rules') {
    $adminPageTitle = sr_t('member::ui.member.bc3daeb8');
} elseif ($memberGroupsPage === 'rule_form') {
    $adminPageTitle = is_array($editRule) ? sr_t('member::ui.member.edit.8fa5d9e5') : sr_t('member::ui.member.ac78ee3c');
}

$memberGroupHelpOpenLabel = sr_t('member::help.open');
$memberGroupHelp = [
    'group_key' => [
        'id' => 'member-groups-help-group-key-modal',
        'title' => sr_t('member::help.groups.group_key.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.group_key.body.1',
            'member::help.groups.group_key.body.2',
        ]),
    ],
    'group_status' => [
        'id' => 'member-groups-help-group-status-modal',
        'title' => sr_t('member::help.groups.group_status.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.group_status.body.1',
            'member::help.groups.group_status.body.2',
        ]),
    ],
    'sort_order' => [
        'id' => 'member-groups-help-sort-order-modal',
        'title' => sr_t('member::help.groups.sort_order.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.sort_order.body.1',
            'member::help.groups.sort_order.body.2',
        ]),
    ],
    'member_hash' => [
        'id' => 'member-groups-help-member-hash-modal',
        'title' => sr_t('member::help.groups.member_hash.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.member_hash.body.1',
            'member::help.groups.member_hash.body.2',
        ]),
    ],
    'rule_scope' => [
        'id' => 'member-groups-help-rule-scope-modal',
        'title' => sr_t('member::help.groups.rule_scope.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.rule_scope.body.1',
            'member::help.groups.rule_scope.body.2',
        ]),
    ],
    'rule_group' => [
        'id' => 'member-groups-help-rule-group-modal',
        'title' => sr_t('member::help.groups.rule_group.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.rule_group.body.1',
            'member::help.groups.rule_group.body.2',
            'member::ui.member.group_rule_evaluate_help.1e458510',
        ]),
    ],
    'rule_definition' => [
        'id' => 'member-groups-help-rule-definition-modal',
        'title' => sr_t('member::help.groups.rule_definition.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.rule_definition.body.1',
            'member::help.groups.rule_definition.body.2',
        ]),
    ],
    'rule_params' => [
        'id' => 'member-groups-help-rule-params-modal',
        'title' => sr_t('member::help.groups.rule_params.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.rule_params.body.1',
            'member::help.groups.rule_params.body.2',
        ]),
    ],
    'evaluation_policy' => [
        'id' => 'member-groups-help-evaluation-policy-modal',
        'title' => sr_t('member::help.groups.evaluation_policy.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.evaluation_policy.body.1',
            'member::help.groups.evaluation_policy.body.2',
            'member::help.groups.evaluation_policy.body.3',
        ]),
    ],
    'rule_status' => [
        'id' => 'member-groups-help-rule-status-modal',
        'title' => sr_t('member::help.groups.rule_status.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.groups.rule_status.body.1',
            'member::help.groups.rule_status.body.2',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
$groupListFilter = isset($groupListFilter) && is_array($groupListFilter) ? $groupListFilter : ['status' => '', 'field' => 'all', 'keyword' => ''];
$groupRuleFilter = isset($groupRuleFilter) && is_array($groupRuleFilter) ? $groupRuleFilter : ['status' => [], 'evaluation_policy' => [], 'group_id' => [], 'source_module_key' => [], 'field' => 'all', 'keyword' => ''];
$groupStatusCounts = isset($groupStatusCounts) && is_array($groupStatusCounts) ? $groupStatusCounts : [];
$membershipsByGroupId = isset($membershipsByGroupId) && is_array($membershipsByGroupId) ? $membershipsByGroupId : [];
$membershipLogsByGroupId = isset($membershipLogsByGroupId) && is_array($membershipLogsByGroupId) ? $membershipLogsByGroupId : [];
$ruleDefinitions = isset($ruleDefinitions) && is_array($ruleDefinitions) ? $ruleDefinitions : [];
$memberRuleSourceOptions = isset($memberRuleSourceOptions) && is_array($memberRuleSourceOptions) ? $memberRuleSourceOptions : [];
$totalGroups = (int) ($groupStatusCounts['total'] ?? count($groups));
$canEditMemberGroups = !empty($canEditMemberGroups);
$createGroupForm = isset($createGroupForm) && is_array($createGroupForm) ? $createGroupForm : null;
$editGroupFormById = isset($editGroupFormById) && is_array($editGroupFormById) ? $editGroupFormById : [];
$openEditGroupModalId = isset($openEditGroupModalId) ? (int) $openEditGroupModalId : 0;
$openCreateGroupModal = !empty($openCreateGroupModal) && $canEditMemberGroups;
$openEditGroupModalId = $canEditMemberGroups ? $openEditGroupModalId : 0;
$memberGroupFormFields = static function (?array $formGroup, string $fieldPrefix, bool $focusFirst = false) use ($allowedStatuses, $memberGroupHelp, $memberGroupHelpOpenLabel): void {
    $isEdit = is_array($formGroup) && (int) ($formGroup['id'] ?? 0) > 0;
    $groupKeyId = $fieldPrefix . '_group_key';
    $titleId = $fieldPrefix . '_title';
    $descriptionId = $fieldPrefix . '_description';
    $statusId = $fieldPrefix . '_status';
    $sortOrderId = $fieldPrefix . '_sort_order';
    $focusAttr = $focusFirst ? ' data-overlay-focus' : '';
    $titleFocusAttr = $isEdit && $focusFirst ? ' data-overlay-focus' : '';
    ?>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="group_id" value="<?php echo sr_e($isEdit ? (string) $formGroup['id'] : ''); ?>">

    <?php if ($isEdit) { ?>
        <div class="admin-form-row">
            <span class="form-label admin-form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.key.1057ecca'), $memberGroupHelp['group_key']['id'], $memberGroupHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.key.1057ecca')); ?></span></span>
            <div class="admin-form-field">
                <code><?php echo sr_e((string) $formGroup['group_key']); ?></code>
            </div>
        </div>
    <?php } else { ?>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html($groupKeyId, sr_t('member::ui.key.1057ecca'), $memberGroupHelp['group_key']['id'], $memberGroupHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <input id="<?php echo sr_e($groupKeyId); ?>" type="text" name="group_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" value="<?php echo sr_e(is_array($formGroup) ? (string) ($formGroup['group_key'] ?? '') : ''); ?>" required class="form-input"<?php echo $focusAttr; ?> data-admin-key-input>
            </div>
        </div>
    <?php } ?>

    <div class="admin-form-row">
        <label class="form-label" for="<?php echo sr_e($titleId); ?>"><?php echo sr_e(sr_t('member::ui.text.97e73d18')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
        <div class="admin-form-field">
            <input id="<?php echo sr_e($titleId); ?>" type="text" name="title" maxlength="120" value="<?php echo sr_e(is_array($formGroup) ? (string) ($formGroup['title'] ?? '') : ''); ?>" class="form-input form-control-full" required<?php echo $titleFocusAttr; ?>>
        </div>
    </div>
    <div class="admin-form-row">
        <label class="form-label" for="<?php echo sr_e($descriptionId); ?>"><?php echo sr_e(sr_t('member::ui.text.8c3f651d')); ?></label>
        <div class="admin-form-field">
            <textarea id="<?php echo sr_e($descriptionId); ?>" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e(is_array($formGroup) ? (string) ($formGroup['description'] ?? '') : ''); ?></textarea>
        </div>
    </div>
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($statusId, sr_t('member::ui.status.e10195a1'), $memberGroupHelp['group_status']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <select id="<?php echo sr_e($statusId); ?>" name="status" class="form-select">
                <?php $currentStatus = is_array($formGroup) ? (string) ($formGroup['status'] ?? 'enabled') : 'enabled'; ?>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($sortOrderId, sr_t('member::ui.text.7d2dc215'), $memberGroupHelp['sort_order']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <input id="<?php echo sr_e($sortOrderId); ?>" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e(is_array($formGroup) ? (string) ($formGroup['sort_order'] ?? '0') : '0'); ?>" required class="form-input">
        </div>
    </div>
    <?php
};
$memberRuleFormFields = static function (?array $formRule, string $fieldPrefix, bool $focusFirst = false) use ($allowedEvaluationPolicies, $allowedRuleStatuses, $groups, $memberGroupHelp, $memberGroupHelpOpenLabel, $pdo, $ruleDefinitions): void {
    $currentDefinitionKey = is_array($formRule) ? (string) $formRule['source_module_key'] . ':' . (string) $formRule['rule_key'] : '';
    $currentRuleParams = [];
    if (is_array($formRule)) {
        $decodedRuleParams = json_decode((string) $formRule['rule_params_json'], true);
        $currentRuleParams = is_array($decodedRuleParams) ? $decodedRuleParams : [];
    }
    $groupFieldId = $fieldPrefix . '_group_id';
    $definitionFieldId = $fieldPrefix . '_definition_key';
    $evaluationPolicyFieldId = $fieldPrefix . '_evaluation_policy';
    $statusFieldId = $fieldPrefix . '_status';
    $focusAttr = $focusFirst ? ' data-overlay-focus' : '';
    ?>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="rule_id" value="<?php echo sr_e(is_array($formRule) ? (string) $formRule['id'] : ''); ?>">
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($groupFieldId, sr_t('member::ui.text.5034bb32'), $memberGroupHelp['rule_group']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <select id="<?php echo sr_e($groupFieldId); ?>" name="group_id" required class="form-select"<?php echo $focusAttr; ?>>
                <?php foreach ($groups as $group) { ?>
                    <option value="<?php echo sr_e((string) $group['id']); ?>"<?php echo is_array($formRule) && (int) $formRule['group_id'] === (int) $group['id'] ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($definitionFieldId, sr_t('member::ui.text.7a1e6434'), $memberGroupHelp['rule_definition']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <select id="<?php echo sr_e($definitionFieldId); ?>" name="definition_key" required data-member-rule-definition class="form-select">
                <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                    <option value="<?php echo sr_e((string) $definitionKey); ?>"<?php echo $currentDefinitionKey === (string) $definitionKey ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) $definition['label']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="admin-form-row">
        <span class="form-label admin-form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.settings.7d7902a7'), $memberGroupHelp['rule_params']['id'], $memberGroupHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.settings.7d7902a7')); ?></span></span>
        <div class="admin-form-field">
            <div class="member-rule-param-panels" data-member-rule-param-panels>
                <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                    <?php $panelActive = $currentDefinitionKey === (string) $definitionKey || ($currentDefinitionKey === '' && $definitionKey === array_key_first($ruleDefinitions)); ?>
                    <div class="member-rule-param-panel"<?php echo $panelActive ? '' : ' hidden'; ?> data-rule-param-panel="<?php echo sr_e((string) $definitionKey); ?>">
                        <?php if ((string) ($definition['description'] ?? '') !== '') { ?>
                            <p class="type-small"><?php echo sr_e((string) $definition['description']); ?></p>
                        <?php } ?>
                        <?php if (($definition['params'] ?? []) === []) { ?>
                            <p class="type-small"><?php echo sr_e(sr_t('member::ui.settings.1ca7d0dd')); ?></p>
                        <?php } ?>
                        <?php foreach ((array) ($definition['params'] ?? []) as $param) { ?>
                            <?php
                            $paramKey = (string) ($param['key'] ?? '');
                            $paramType = (string) ($param['type'] ?? 'string');
                            $paramValue = array_key_exists($paramKey, $currentRuleParams) ? $currentRuleParams[$paramKey] : ($param['default'] ?? '');
                            $paramFieldId = $fieldPrefix . '_param_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $definitionKey . '_' . $paramKey);
                            $paramOptions = sr_member_group_rule_param_options($pdo, $param);
                            ?>
                            <label class="filtering-field" for="<?php echo sr_e($paramFieldId); ?>">
                                <span class="filtering-label"><?php echo sr_e((string) ($param['label'] ?? $paramKey)); ?></span>
                                <?php if ($paramType === 'bool') { ?>
                                    <select id="<?php echo sr_e($paramFieldId); ?>" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-select">
                                        <option value="1"<?php echo !empty($paramValue) ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.text.2eb73fba')); ?></option>
                                        <option value="0"<?php echo empty($paramValue) ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.text.4c490f1c')); ?></option>
                                    </select>
                                <?php } elseif ($paramOptions !== []) { ?>
                                    <select id="<?php echo sr_e($paramFieldId); ?>" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-select">
                                        <?php foreach ($paramOptions as $option) { ?>
                                            <option value="<?php echo sr_e((string) $option['value']); ?>"<?php echo (string) $paramValue === (string) $option['value'] ? ' selected' : ''; ?>>
                                                <?php echo sr_e((string) $option['label']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                <?php } elseif ($paramType === 'int' || $paramType === 'subject') { ?>
                                    <input id="<?php echo sr_e($paramFieldId); ?>" type="number" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo isset($param['min']) ? ' min="' . sr_e((string) $param['min']) . '"' : ''; ?><?php echo isset($param['max']) ? ' max="' . sr_e((string) $param['max']) . '"' : ''; ?><?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
                                <?php } else { ?>
                                    <input id="<?php echo sr_e($paramFieldId); ?>" type="text" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
                                <?php } ?>
                            </label>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($evaluationPolicyFieldId, sr_t('member::ui.text.c3054578'), $memberGroupHelp['evaluation_policy']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <select id="<?php echo sr_e($evaluationPolicyFieldId); ?>" name="evaluation_policy" class="form-select">
                <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                    <option value="<?php echo sr_e($policy); ?>"<?php echo is_array($formRule) && (string) $formRule['evaluation_policy'] === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="admin-form-row">
        <?php echo sr_admin_form_label_help_html($statusFieldId, sr_t('member::ui.status.e10195a1'), $memberGroupHelp['rule_status']['id'], $memberGroupHelpOpenLabel, true); ?>
        <div class="admin-form-field">
            <select id="<?php echo sr_e($statusFieldId); ?>" name="status" class="form-select">
                <?php foreach ($allowedRuleStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo is_array($formRule) && (string) $formRule['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <?php
};
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberGroupsPage === 'group_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editGroup) ? sr_t('member::ui.edit.5784f889') : sr_t('member::ui.text.22129319'); ?></h2>
            <?php $memberGroupFormFields(is_array($editGroup) ? $editGroup : null, 'member_admin_groups'); ?>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'groups') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.ca286213')); ?> <strong><?php echo sr_e((string) $totalGroups); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($groupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.active.f54a7542')); ?> <?php echo sr_e((string) ($groupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($groupStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php $selectedMemberGroupStatuses = is_array($groupListFilter['status'] ?? null) ? $groupListFilter['status'] : []; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="filtering-form filtering filtering-plain admin-member-group-filter ui-form-theme">
        <div class="filtering-fields admin-member-group-search-grid">
                    <div class="filtering-field">
                        <span class="filtering-label"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('member-group-status-filter', 'status', sr_admin_code_label_options($allowedStatuses, 'content_status'), $selectedMemberGroupStatuses, sr_t('member::ui.all.a4b69faf')); ?>
                    </div>
                    <div class="filtering-field">
                        <label for="member-group-search-field" class="filtering-label">검색조건</label>
                        <select name="field" id="member-group-search-field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('member::ui.all.a4b69faf'), 'key' => sr_t('member::ui.key.1057ecca'), 'title' => sr_t('member::ui.text.97e73d18'), 'description' => sr_t('member::ui.text.8c3f651d')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($groupListFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field admin-member-group-filter-keyword">
                        <label for="member-group-search-keyword" class="filtering-label"><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></label>
                        <input type="text" id="member-group-search-keyword" name="q" value="<?php echo sr_e((string) ($groupListFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" placeholder="<?php echo sr_e(sr_t('member::ui.key.60df9e41')); ?>">
                    </div>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.list.c78d8209')); ?></h2>
            <?php if ($canEditMemberGroups) { ?>
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $openCreateGroupModal ? 'true' : 'false'; ?>" aria-controls="member-group-create-modal" data-overlay="#member-group-create-modal"><?php echo sr_e(sr_t('member::ui.text.6de46476')); ?></button>
                </div>
            <?php } ?>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($groupSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_member_group_sort_options(), sr_admin_member_group_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="회원 그룹 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($groupPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-member-group-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('member::ui.member.list.7b664c16')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('group_key', $groupSort); ?>><?php echo sr_admin_sort_header_html('key', 'group_key', $groupSort, sr_admin_member_group_sort_options(), sr_admin_member_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $groupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.97e73d18'), 'title', $groupSort, sr_admin_member_group_sort_options(), sr_admin_member_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $groupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.status.e10195a1'), 'status', $groupSort, sr_admin_member_group_sort_options(), sr_admin_member_group_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('active_member_count', $groupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.member.984c7e2b'), 'active_member_count', $groupSort, sr_admin_member_group_sort_options(), sr_admin_member_group_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groups === []) { ?>
                    <tr>
                        <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.4ef35a24')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($groups as $group) { ?>
                    <?php
                    $groupId = (int) $group['id'];
                    $groupStatus = (string) $group['status'];
                    $statusClass = match ($groupStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    $editGroupModalId = 'member-group-edit-modal-' . $groupId;
                    $manualAssignModalId = 'member-group-manual-assign-modal-' . $groupId;
                    $assignmentHistoryModalId = 'member-group-assignment-history-modal-' . $groupId;
                    ?>
                    <tr>
                        <td class="admin-table-nowrap admin-member-group-key-cell"><?php echo sr_e((string) $group['group_key']); ?></td>
                        <td class="admin-member-group-title-cell"><?php echo sr_e((string) $group['title']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-group-number-cell"><?php echo sr_e((string) $group['active_member_count']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($manualAssignModalId); ?>" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>"><?php echo sr_e(sr_t('member::ui.text.94e3ebac')); ?></button>
                                <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($assignmentHistoryModalId); ?>" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>"><?php echo sr_e(sr_t('member::ui.text.fb4e329c')); ?></button>
                                <?php if ($canEditMemberGroups) { ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>" aria-haspopup="dialog" aria-expanded="<?php echo $openEditGroupModalId === $groupId ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($editGroupModalId); ?>" data-overlay="#<?php echo sr_e($editGroupModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>

    <?php echo sr_admin_pagination_html($groupPagination, '회원 그룹 목록 페이지'); ?>

    <?php if ($canEditMemberGroups) { ?>
    <div id="member-group-create-modal" class="modal-overlay modal-overlay-fade overlay<?php echo $openCreateGroupModal ? ' overlay-open open' : ' hidden pointer-events-none opacity-0'; ?>" role="dialog" tabindex="-1" aria-labelledby="member_group_create_modal_title" aria-hidden="<?php echo $openCreateGroupModal ? 'false' : 'true'; ?>"<?php echo $openCreateGroupModal ? '' : ' inert'; ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="member_group_create_modal_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.22129319')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#member-group-create-modal">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php $memberGroupFormFields($createGroupForm, 'member_group_create_modal', true); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#member-group-create-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>

    <?php foreach ($groups as $group) { ?>
        <?php
        $groupId = (int) $group['id'];
        $editGroupModalId = 'member-group-edit-modal-' . $groupId;
        $manualAssignModalId = 'member-group-manual-assign-modal-' . $groupId;
        $assignmentHistoryModalId = 'member-group-assignment-history-modal-' . $groupId;
        $manualAssignFieldPrefix = 'member_group_manual_assign_' . $groupId;
        $manualAssignAccountInputId = $manualAssignFieldPrefix . '_account_identifier';
        $manualAssignMemberLookupModalId = $manualAssignFieldPrefix . '_member_lookup_modal';
        $editGroupFieldPrefix = 'member_group_edit_' . $groupId;
        $editGroupForm = isset($editGroupFormById[$groupId]) && is_array($editGroupFormById[$groupId]) ? $editGroupFormById[$groupId] : $group;
        $openEditGroupModal = $canEditMemberGroups && $openEditGroupModalId === $groupId;
        $groupMemberships = isset($membershipsByGroupId[$groupId]) && is_array($membershipsByGroupId[$groupId]) ? $membershipsByGroupId[$groupId] : [];
        $groupMembershipLogs = isset($membershipLogsByGroupId[$groupId]) && is_array($membershipLogsByGroupId[$groupId]) ? $membershipLogsByGroupId[$groupId] : [];
        ?>
        <?php if ($canEditMemberGroups) { ?>
        <div id="<?php echo sr_e($editGroupModalId); ?>" class="modal-overlay modal-overlay-fade overlay<?php echo $openEditGroupModal ? ' overlay-open open' : ' hidden pointer-events-none opacity-0'; ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($editGroupFieldPrefix); ?>_title" aria-hidden="<?php echo $openEditGroupModal ? 'false' : 'true'; ?>"<?php echo $openEditGroupModal ? '' : ' inert'; ?>>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($editGroupFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.edit.5784f889')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($editGroupModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php $memberGroupFormFields($editGroupForm, $editGroupFieldPrefix, true); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($editGroupModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

        <div id="<?php echo sr_e($manualAssignModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($manualAssignFieldPrefix); ?>_title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/grant')); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($manualAssignFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.94e3ebac')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="group_id" value="<?php echo sr_e((string) $groupId); ?>">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.7482bebf')); ?> <strong><?php echo sr_e((string) $group['title']); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e((string) $group['group_key']); ?></span>
                        </div>
                        <div class="admin-form-row">
                            <?php echo sr_admin_form_label_help_html($manualAssignAccountInputId, sr_t('member::ui.member.hash.5a5dbe2b'), $memberGroupHelp['member_hash']['id'], $memberGroupHelpOpenLabel, true); ?>
                            <div class="admin-form-field">
                                <div class="admin-lookup-control">
                                    <input id="<?php echo sr_e($manualAssignAccountInputId); ?>" type="text" name="account_identifier" class="form-input" maxlength="80" required data-overlay-focus>
                                    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($manualAssignMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($manualAssignMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($manualAssignAccountInputId); ?>"><?php echo sr_e(sr_t('admin::ui.member.search.f7a330b0')); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($manualAssignModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.text.41172d90')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $assetAdjustLookup = [
            'field_prefix' => $manualAssignFieldPrefix,
            'member_input_id' => $manualAssignAccountInputId,
            'return_overlay_id' => $manualAssignModalId,
            'return_label' => sr_t('member::ui.text.94e3ebac'),
            'member_search_url' => sr_url('/admin/members/search'),
        ];
        include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
        ?>

        <div id="<?php echo sr_e($assignmentHistoryModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($manualAssignFieldPrefix); ?>_history_title" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($manualAssignFieldPrefix); ?>_history_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.2680da81')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>">
                            <?php echo sr_material_icon_html('close'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="admin-summary-stats">
                            <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.7482bebf')); ?> <strong><?php echo sr_e((string) $group['title']); ?></strong></span>
                            <span class="admin-summary-meta"><?php echo sr_e((string) $group['group_key']); ?></span>
                        </div>
                        <section class="admin-card admin-list-card card admin-list-form">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('member::ui.text.561bac1a')); ?></h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead class="ui-table-head">
                                        <tr>
                                            <th><?php echo sr_e(sr_t('member::ui.member.e335b899')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.5cf2792b')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.095ffbfb')); ?></th>
                                            <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.8b179161')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($groupMemberships === []) { ?>
                                            <tr>
                                                <td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.6bfe04ee')); ?></td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($groupMemberships as $membership) { ?>
                                            <tr>
                                                <td>
                                                    <?php echo sr_e((string) $membership['account_public_hash']); ?><br>
                                                    <?php echo sr_e(sr_admin_member_display_name_preview($membership)); ?>
                                                </td>
                                                <td><?php echo sr_e(sr_admin_code_label((string) $membership['assignment_type'], 'assignment_type')); ?></td>
                                                <td><?php echo sr_e(sr_admin_code_label((string) $membership['status'], 'membership_status')); ?></td>
                                                <td><?php echo sr_e((string) ($membership['granted_at'] ?? '')); ?></td>
                                                <td class="admin-table-actions-cell">
                                                    <div class="admin-row-actions">
                                                        <?php if ((string) $membership['assignment_type'] === 'manual' && (string) $membership['status'] === 'active') { ?>
                                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/revoke')); ?>">
                                                                <?php echo sr_csrf_field(); ?>
                                                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $membership['account_id']); ?>">
                                                                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $membership['group_id']); ?>">
                                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('member::ui.text.293182ec')); ?>" title="<?php echo sr_e(sr_t('member::ui.text.293182ec')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                                            </form>
                                                        <?php } else { ?>
                                                            <?php echo sr_e((string) ($membership['revoked_at'] ?? '')); ?>
                                                        <?php } ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-card admin-list-card card admin-list-form">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('member::ui.text.2680da81')); ?></h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="table">
                                    <thead class="ui-table-head">
                                        <tr>
                                            <th><?php echo sr_e(sr_t('member::ui.member.e335b899')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.46b289bb')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.4cd44bae')); ?></th>
                                            <th><?php echo sr_e(sr_t('member::ui.text.4692cef5')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($groupMembershipLogs === []) { ?>
                                            <tr>
                                                <td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.537aa44f')); ?></td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($groupMembershipLogs as $log) { ?>
                                            <tr>
                                                <td>
                                                    <?php echo sr_e((string) $log['account_public_hash']); ?><br>
                                                    <?php echo sr_e(sr_admin_member_display_name_preview($log)); ?>
                                                </td>
                                                <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                                                <td><?php echo sr_e((string) $log['message']); ?></td>
                                                <td><?php echo sr_e((string) $log['created_at']); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assignmentHistoryModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } elseif ($memberGroupsPage === 'rules') { ?>
    <?php
    $registeredGroupCount = count($groups);
    $enabledRuleTargetGroups = [];
    $selectableEvaluateExcludeGroups = [];
    foreach ($groups as $group) {
        if ((string) ($group['status'] ?? '') === 'enabled') {
            $enabledRuleTargetGroups[] = $group;
        }
        if ((string) ($group['status'] ?? '') !== 'archived') {
            $selectableEvaluateExcludeGroups[] = $group;
        }
    }
    $canEvaluateGroupRules = $registeredGroupCount > 0 && $enabledRuleTargetGroups !== [];
    $canSelectEvaluateExcludeGroups = count($selectableEvaluateExcludeGroups) > 1;
    $selectedGroupRuleStatuses = is_array($groupRuleFilter['status'] ?? null) ? $groupRuleFilter['status'] : [];
    $selectedGroupRuleEvaluationPolicies = is_array($groupRuleFilter['evaluation_policy'] ?? null) ? $groupRuleFilter['evaluation_policy'] : [];
    $selectedGroupRuleGroupIds = is_array($groupRuleFilter['group_id'] ?? null) ? $groupRuleFilter['group_id'] : [];
    $selectedGroupRuleSourceModuleKeys = is_array($groupRuleFilter['source_module_key'] ?? null) ? $groupRuleFilter['source_module_key'] : [];
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/member-group-rules')); ?>" class="filtering-form filtering filtering-plain admin-member-group-rule-filter ui-form-theme">
        <div class="filtering-fields admin-member-group-rule-search-grid">
            <div class="filtering-field">
                <span class="filtering-label"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('member-group-rule-status-filter', 'status', sr_admin_code_label_options($allowedRuleStatuses, 'content_status'), $selectedGroupRuleStatuses, '전체'); ?>
            </div>
            <div class="filtering-field">
                <label for="member-group-rule-policy-filter" class="filtering-label"><?php echo sr_e(sr_t('member::ui.text.ff41d4a4')); ?></label>
                <select id="member-group-rule-policy-filter" name="evaluation_policy" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                        <option value="<?php echo sr_e($policy); ?>"<?php echo in_array($policy, $selectedGroupRuleEvaluationPolicies, true) ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field">
                <label for="member_group_rule_filter_group" class="filtering-label"><?php echo sr_e(sr_t('member::ui.text.5d908ddd')); ?></label>
                <select id="member_group_rule_filter_group" name="group_id" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($groups as $group) { ?>
                        <?php $groupId = (string) (int) ($group['id'] ?? 0); ?>
                        <option value="<?php echo sr_e($groupId); ?>"<?php echo in_array($groupId, $selectedGroupRuleGroupIds, true) ? ' selected' : ''; ?>><?php echo sr_e((string) ($group['title'] ?? '')); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field">
                <label for="member_group_rule_filter_source" class="filtering-label"><?php echo sr_e(sr_t('member::ui.text.291ac971')); ?></label>
                <select id="member_group_rule_filter_source" name="source_module_key" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($memberRuleSourceOptions as $sourceModuleKey => $sourceOption) { ?>
                        <option value="<?php echo sr_e((string) $sourceModuleKey); ?>"<?php echo in_array((string) $sourceModuleKey, $selectedGroupRuleSourceModuleKeys, true) ? ' selected' : ''; ?>><?php echo sr_e((string) ($sourceOption['label'] ?? $sourceModuleKey)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field">
                <label for="member_group_rule_filter_field" class="filtering-label">검색조건</label>
                <select id="member_group_rule_filter_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => '전체', 'group' => sr_t('member::ui.text.5d908ddd'), 'source' => sr_t('member::ui.text.291ac971'), 'rule' => '규칙 key'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($groupRuleFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field admin-member-group-rule-filter-keyword">
                <label for="member_group_rule_filter_q" class="filtering-label"><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></label>
                <input id="member_group_rule_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($groupRuleFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="그룹, 모듈, 규칙 key">
            </div>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.save.617f3ca3')); ?></h2>
            <div class="admin-row-actions">
                <?php if ($canEvaluateGroupRules) { ?>
                    <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="member-group-rule-evaluate-modal" data-overlay="#member-group-rule-evaluate-modal"><?php echo sr_e(sr_t('member::ui.text.3d1d323a')); ?></button>
                <?php } ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="member-group-rule-create-modal" data-overlay="#member-group-rule-create-modal"><?php echo sr_e(sr_t('member::ui.text.b5b997ea')); ?></button>
            </div>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($groupRuleSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="회원 그룹 규칙 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($groupRulePagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('group_title', $groupRuleSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.5d908ddd'), 'group_title', $groupRuleSort, sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('source_module_key', $groupRuleSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.291ac971'), 'source_module_key', $groupRuleSort, sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('evaluation_policy', $groupRuleSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.ff41d4a4'), 'evaluation_policy', $groupRuleSort, sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $groupRuleSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.status.e10195a1'), 'status', $groupRuleSort, sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('last_evaluated_at', $groupRuleSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.4c544b45'), 'last_evaluated_at', $groupRuleSort, sr_member_group_rule_sort_options(), sr_member_group_rule_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groupRules === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.text.1998c6cf')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($groupRules as $rule) { ?>
                    <?php
                    $ruleSourceModuleKey = (string) $rule['source_module_key'];
                    $ruleKey = (string) $rule['rule_key'];
                    $ruleDefinitionKey = $ruleSourceModuleKey . ':' . $ruleKey;
                    $ruleDefinition = isset($ruleDefinitions[$ruleDefinitionKey]) && is_array($ruleDefinitions[$ruleDefinitionKey]) ? $ruleDefinitions[$ruleDefinitionKey] : null;
                    $ruleModuleLabel = (string) ($memberRuleSourceOptions[$ruleSourceModuleKey]['label'] ?? '');
                    if ($ruleModuleLabel === '') {
                        $moduleMetadata = sr_module_metadata($ruleSourceModuleKey);
                        $moduleName = trim((string) ($moduleMetadata['name'] ?? ''));
                        $ruleModuleLabel = $moduleName !== '' ? sr_admin_module_name_label($moduleName) : sr_t('member::ui.member.rule_source_unknown.6b4675ab');
                    }
                    $ruleLabel = is_array($ruleDefinition) ? trim((string) ($ruleDefinition['label'] ?? '')) : '';
                    $ruleDescription = is_array($ruleDefinition) ? trim((string) ($ruleDefinition['description'] ?? '')) : '';
                    if ($ruleLabel === '') {
                        $ruleLabel = sr_t('member::ui.member.rule_definition_unknown.9b814086');
                    }
                    ?>
                    <tr>
                        <td><?php echo sr_e((string) $rule['group_title']); ?></td>
                        <td title="<?php echo sr_e($ruleSourceModuleKey . ' / ' . $ruleKey); ?>">
                            <strong><?php echo sr_e($ruleLabel); ?></strong><br>
                            <span class="admin-table-subtext"><?php echo sr_e($ruleModuleLabel); ?><?php echo $ruleDescription !== '' ? ' · ' . sr_e($ruleDescription) : ''; ?></span>
                        </td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['evaluation_policy'], 'evaluation_policy')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) ($rule['last_evaluated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/member-group-rules/edit?id=' . rawurlencode((string) $rule['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>

    <?php echo sr_admin_pagination_html($groupRulePagination, '회원 그룹 규칙 목록 페이지'); ?>

    <?php if ($canEvaluateGroupRules) { ?>
    <div id="member-group-rule-evaluate-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="member_group_rule_evaluate_modal_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/group')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="member_group_rule_evaluate_modal_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.member.group_rule_evaluate.55daa57d')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#member-group-rule-evaluate-modal">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="evaluate_group">
                    <div class="admin-form-row">
                        <?php echo sr_admin_form_label_help_html('member_group_rule_evaluate_group_id', sr_t('member::ui.text.5034bb32'), $memberGroupHelp['rule_group']['id'], $memberGroupHelpOpenLabel, true); ?>
                        <div class="admin-form-field">
                            <select id="member_group_rule_evaluate_group_id" name="group_id" class="form-select" required data-overlay-focus>
                                <option value=""><?php echo sr_e(sr_t('member::ui.text.72ea3d64')); ?></option>
                                <?php foreach ($enabledRuleTargetGroups as $group) { ?>
                                    <option value="<?php echo sr_e((string) $group['id']); ?>"><?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($canSelectEvaluateExcludeGroups) { ?>
                    <div class="admin-form-row">
                        <label class="form-label" for="member_group_rule_evaluate_exclude_group_ids_select"><?php echo sr_e(sr_t('member::ui.member.exclude_group.9ad7aa30')); ?></label>
                        <div class="admin-form-field">
                            <?php
                            $excludeGroupOptions = [];
                            foreach ($selectableEvaluateExcludeGroups as $group) {
                                $excludeGroupId = (string) ($group['id'] ?? '');
                                if ($excludeGroupId === '') {
                                    continue;
                                }

                                $excludeGroupOptions[$excludeGroupId] = (string) ($group['title'] ?? '') . ' (' . (string) ($group['group_key'] ?? '') . ')';
                            }
                            echo sr_admin_select_badge_list_html(
                                'member_group_rule_evaluate_exclude_group_ids',
                                'exclude_group_ids',
                                $excludeGroupOptions,
                                [],
                                sr_t('member::ui.member.exclude_group_empty.563ab565'),
                                sr_t('member::ui.member.exclude_group_select.b923ff94')
                            );
                            ?>
                            <p class="admin-form-help"><?php echo sr_e(sr_t('member::ui.member.exclude_group_help.8a94a7f3')); ?></p>
                        </div>
                    </div>
                    <?php } else { ?>
                    <div class="admin-form-row">
                        <span class="form-label"><?php echo sr_e(sr_t('member::ui.member.exclude_group.9ad7aa30')); ?></span>
                        <div class="admin-form-field">
                            <p class="admin-form-help"><?php echo sr_e(sr_t('member::ui.member.exclude_group_single_group_help.19517db8')); ?></p>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#member-group-rule-evaluate-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.text.3d1d323a')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php if ($canSelectEvaluateExcludeGroups) { ?>
        <script>
        (function () {
            'use strict';

            function selectedValues(root) {
                var values = {};
                if (!root) {
                    return values;
                }
                root.querySelectorAll('[data-admin-select-badge-value]').forEach(function (input) {
                    if (input.value) {
                        values[input.value] = true;
                    }
                });
                return values;
            }

            function targetOptionValues(target) {
                var values = [];
                if (!target) {
                    return values;
                }
                Array.prototype.forEach.call(target.options, function (option) {
                    if (option.value) {
                        values.push(option.value);
                    }
                });
                return values;
            }

            function removeExcludedValue(root, value) {
                if (!root || !value) {
                    return;
                }
                root.querySelectorAll('[data-admin-select-badge-item]').forEach(function (item) {
                    var input = item.querySelector('[data-admin-select-badge-value]');
                    if (input && input.value === value) {
                        item.remove();
                    }
                });
            }

            function lastExcludedTargetValue(root, targetValues) {
                var targetMap = {};
                targetValues.forEach(function (value) {
                    targetMap[value] = true;
                });
                var items = root ? Array.prototype.slice.call(root.querySelectorAll('[data-admin-select-badge-item]')) : [];
                for (var index = items.length - 1; index >= 0; index -= 1) {
                    var input = items[index].querySelector('[data-admin-select-badge-value]');
                    if (input && targetMap[input.value]) {
                        return input.value;
                    }
                }
                return '';
            }

            function syncExcludeGroups() {
                var target = document.getElementById('member_group_rule_evaluate_group_id');
                var root = document.getElementById('member_group_rule_evaluate_exclude_group_ids');
                var select = root ? root.querySelector('[data-admin-select-badge-list-select]') : null;
                if (!target || !root || !select) {
                    return;
                }

                var targetValue = target.value || '';
                var targetValues = targetOptionValues(target);
                var values = selectedValues(root);
                var hasExcludedValues = Object.keys(values).length > 0;
                if (!targetValue && hasExcludedValues && targetValues.length > 0) {
                    var remainingTargetValues = targetValues.filter(function (value) {
                        return !values[value];
                    });
                    if (remainingTargetValues.length === 1) {
                        target.value = remainingTargetValues[0];
                        targetValue = target.value || '';
                    } else if (remainingTargetValues.length === 0) {
                        target.value = lastExcludedTargetValue(root, targetValues);
                        targetValue = target.value || '';
                    }
                }

                if (targetValue) {
                    removeExcludedValue(root, targetValue);
                }
                values = selectedValues(root);
                Array.prototype.forEach.call(select.options, function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        option.style.display = '';
                        return;
                    }
                    var blocked = !!values[option.value] || (targetValue !== '' && option.value === targetValue);
                    option.hidden = blocked;
                    option.disabled = blocked;
                    option.style.display = blocked ? 'none' : '';
                });
                select.value = '';
            }

            function scheduleSyncExcludeGroups() {
                syncExcludeGroups();
                window.setTimeout(syncExcludeGroups, 0);
                window.setTimeout(syncExcludeGroups, 50);
                if (window.requestAnimationFrame) {
                    window.requestAnimationFrame(syncExcludeGroups);
                }
            }

            document.addEventListener('change', function (event) {
                if (
                    event.target
                    && (
                        event.target.id === 'member_group_rule_evaluate_group_id'
                        || (event.target.closest && event.target.closest('#member_group_rule_evaluate_exclude_group_ids'))
                    )
                ) {
                    scheduleSyncExcludeGroups();
                }
            });
            document.addEventListener('click', function (event) {
                if (event.target && event.target.closest && event.target.closest('#member_group_rule_evaluate_exclude_group_ids [data-admin-select-badge-remove]')) {
                    scheduleSyncExcludeGroups();
                }
            });
            document.addEventListener('DOMContentLoaded', function () {
                var root = document.getElementById('member_group_rule_evaluate_exclude_group_ids');
                var items = root ? root.querySelector('[data-admin-select-badge-list-items]') : null;
                if (items && window.MutationObserver) {
                    new MutationObserver(scheduleSyncExcludeGroups).observe(items, {childList: true});
                }
                scheduleSyncExcludeGroups();
            });
            scheduleSyncExcludeGroups();
        }());
        </script>
    <?php } ?>
    <?php } ?>

    <div id="member-group-rule-create-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="member_group_rule_create_modal_title" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-rules/save')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="member_group_rule_create_modal_title" class="modal-title"><?php echo sr_e(sr_t('member::ui.text.eee300ae')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#member-group-rule-create-modal">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php $memberRuleFormFields(null, 'member_group_rule_create_modal', true); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#member-group-rule-create-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('member::ui.save.95d3fea1')); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php } elseif ($memberGroupsPage === 'rule_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-rules/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editRule) ? sr_t('member::ui.edit.6e308f62') : sr_t('member::ui.text.eee300ae'); ?></h2>
            <?php $memberRuleFormFields(is_array($editRule) ? $editRule : null, 'member_admin_groups_rule_form'); ?>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-group-rules')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.95d3fea1')); ?></button>
        </div>
    </form>
<?php } ?>

<?php foreach ($memberGroupHelp as $memberGroupHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberGroupHelpModal['id'], (string) $memberGroupHelpModal['title'], (string) $memberGroupHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
