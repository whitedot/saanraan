<?php

$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
$adminContainerClass = 'admin-page-member-groups admin-ui-scope';
$adminPageTitle = '회원 그룹';
if ($memberGroupsPage === 'group_form') {
    $adminPageTitle = is_array($editGroup) ? '회원 그룹 수정' : '회원 그룹 생성';
} elseif ($memberGroupsPage === 'rules') {
    $adminPageTitle = '회원 그룹 자동 규칙';
} elseif ($memberGroupsPage === 'rule_form') {
    $adminPageTitle = is_array($editRule) ? '회원 그룹 자동 규칙 수정' : '회원 그룹 자동 규칙 생성';
} elseif ($memberGroupsPage === 'evaluations') {
    $adminPageTitle = '회원 그룹 자동 재평가';
} elseif ($memberGroupsPage === 'assignments') {
    $adminPageTitle = '회원 그룹 수동 배정';
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
$groupListFilter = isset($groupListFilter) && is_array($groupListFilter) ? $groupListFilter : ['status' => '', 'field' => 'all', 'keyword' => ''];
$groupStatusCounts = isset($groupStatusCounts) && is_array($groupStatusCounts) ? $groupStatusCounts : [];
$totalGroups = (int) ($groupStatusCounts['total'] ?? count($groups));
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberGroupsPage === 'group_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editGroup) ? '그룹 수정' : '그룹 생성'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="group_id" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['id'] : ''); ?>">

            <?php if (is_array($editGroup)) { ?>
                <p>그룹 key: <?php echo sr_e((string) $editGroup['group_key']); ?></p>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_groups_group_key">그룹 key</label>
                    <div class="admin-form-field">
                        <input id="member_admin_groups_group_key" type="text" name="group_key" maxlength="60" required class="form-input">
                    </div>
                </div>
            <?php } ?>

            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_title">그룹명</label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_title" type="text" name="title" maxlength="120" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['title'] : ''); ?>" class="form-input" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="member_admin_groups_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e(is_array($editGroup) ? (string) ($editGroup['description'] ?? '') : ''); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_status">상태</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_status" name="status" class="form-select">
                                            <?php $currentStatus = is_array($editGroup) ? (string) $editGroup['status'] : 'enabled'; ?>
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_sort_order">정렬 순서</label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['sort_order'] : '0'); ?>" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-soft-default">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'groups') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-soft-default">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총그룹 <strong><?php echo sr_e((string) $totalGroups); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($groupStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=disabled')); ?>" class="admin-summary-meta">미사용 <?php echo sr_e((string) ($groupStatusCounts['disabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups?status=archived')); ?>" class="admin-summary-meta">보관 <?php echo sr_e((string) ($groupStatusCounts['archived'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="admin-filter admin-member-group-filter ui-form-theme">
        <div class="admin-filter-grid admin-member-group-search-grid">
            <div class="admin-filter-field">
                <label for="member-group-status-filter" class="admin-filter-label">상태</label>
                <select name="status" id="member-group-status-filter" class="form-select admin-filter-input">
                    <option value="">전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($groupListFilter['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="member-group-search-field" class="admin-filter-label">검색 조건</label>
                <select name="field" id="member-group-search-field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'key' => '그룹 key', 'title' => '그룹명', 'description' => '설명'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($groupListFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-member-group-filter-keyword">
                <label for="member-group-search-keyword" class="admin-filter-label">검색어</label>
                <input type="text" id="member-group-search-keyword" name="q" value="<?php echo sr_e((string) ($groupListFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="그룹 key, 그룹명, 설명">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">그룹 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups/new')); ?>" class="btn btn-sm btn-soft-default">새 그룹 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-member-group-table">
            <caption class="sr-only">회원 그룹 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>그룹명</th>
                    <th>상태</th>
                    <th>회원 수</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groups === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">회원 그룹이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($groups as $group) { ?>
                    <?php
                    $groupStatus = (string) $group['status'];
                    $statusClass = match ($groupStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td><?php echo sr_e((string) $group['id']); ?></td>
                        <td class="admin-table-nowrap admin-member-group-key-cell"><?php echo sr_e((string) $group['group_key']); ?></td>
                        <td class="admin-member-group-title-cell"><?php echo sr_e((string) $group['title']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-group-number-cell"><?php echo sr_e((string) $group['active_member_count']); ?></td>
                        <td class="admin-table-nowrap admin-member-group-number-cell"><?php echo sr_e((string) $group['sort_order']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/member-groups/edit?id=' . rawurlencode((string) $group['id']))); ?>" class="btn btn-sm btn-soft-default">수정</a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } elseif ($memberGroupsPage === 'rules') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">자동 조건 후보</h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>모듈</th>
                    <th>조건</th>
                    <th>설명</th>
                    <th>파라미터</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ruleDefinitions === []) { ?>
                    <tr>
                        <td colspan="4" class="admin-empty-state">설치된 활성 모듈이 제공하는 회원 그룹 조건 후보가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($ruleDefinitions as $definition) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $definition['source_module_key']); ?></td>
                        <td>
                            <?php echo sr_e((string) $definition['label']); ?><br>
                            <?php echo sr_e((string) $definition['rule_key']); ?>
                        </td>
                        <td><?php echo sr_e((string) $definition['description']); ?></td>
                        <td>
                            <?php if ($definition['params'] === []) { ?>
                                없음
                            <?php } else { ?>
                                <ul>
                                    <?php foreach ($definition['params'] as $param) { ?>
                                        <li>
                                            <?php echo sr_e((string) $param['key']); ?>:
                                            <?php echo sr_e((string) $param['label']); ?>
                                            (<?php echo sr_e(sr_admin_code_label((string) $param['type'], 'setting_type')); ?>)
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">저장된 자동 규칙</h2>
            <a href="<?php echo sr_e(sr_url('/admin/member-group-rules/new')); ?>" class="btn btn-sm btn-soft-default">새 자동 규칙 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>그룹</th>
                    <th>조건</th>
                    <th>정책</th>
                    <th>상태</th>
                    <th>최근 평가</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groupRules === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">자동 규칙이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($groupRules as $rule) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $rule['id']); ?></td>
                        <td><?php echo sr_e((string) $rule['group_title']); ?></td>
                        <td>
                            <?php echo sr_e((string) $rule['source_module_key']); ?><br>
                            <?php echo sr_e((string) $rule['rule_key']); ?>
                        </td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['evaluation_policy'], 'evaluation_policy')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $rule['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) ($rule['last_evaluated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/member-group-rules/edit?id=' . rawurlencode((string) $rule['id']))); ?>" class="btn btn-sm btn-soft-default">수정</a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } elseif ($memberGroupsPage === 'rule_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-rules/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo is_array($editRule) ? '자동 규칙 수정' : '자동 규칙 생성'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="rule_id" value="<?php echo sr_e(is_array($editRule) ? (string) $editRule['id'] : ''); ?>">
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_group_id">대상 그룹</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_group_id" name="group_id" required class="form-select">
                                            <?php foreach ($groups as $group) { ?>
                                                <option value="<?php echo sr_e((string) $group['id']); ?>"<?php echo is_array($editRule) && (int) $editRule['group_id'] === (int) $group['id'] ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_definition_key">조건 후보</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_definition_key" name="definition_key" required data-member-rule-definition class="form-select">
                                            <?php $currentDefinitionKey = is_array($editRule) ? (string) $editRule['source_module_key'] . ':' . (string) $editRule['rule_key'] : ''; ?>
                                            <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                                                <option value="<?php echo sr_e((string) $definitionKey); ?>"<?php echo $currentDefinitionKey === (string) $definitionKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $definition['label']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">조건 설정</span>
                <div class="admin-form-field">
                    <?php
                    $currentRuleParams = [];
                    if (is_array($editRule)) {
                        $decodedRuleParams = json_decode((string) $editRule['rule_params_json'], true);
                        $currentRuleParams = is_array($decodedRuleParams) ? $decodedRuleParams : [];
                    }
                    ?>
                    <div class="member-rule-param-panels" data-member-rule-param-panels>
                        <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                            <?php $panelActive = $currentDefinitionKey === (string) $definitionKey || ($currentDefinitionKey === '' && $definitionKey === array_key_first($ruleDefinitions)); ?>
                            <div class="member-rule-param-panel"<?php echo $panelActive ? '' : ' hidden'; ?> data-rule-param-panel="<?php echo sr_e((string) $definitionKey); ?>">
                                <?php if ((string) ($definition['description'] ?? '') !== '') { ?>
                                    <p><?php echo sr_e((string) $definition['description']); ?></p>
                                <?php } ?>
                                <?php if (($definition['params'] ?? []) === []) { ?>
                                    <p>추가 조건 설정이 필요하지 않습니다.</p>
                                <?php } ?>
                                <?php foreach ((array) ($definition['params'] ?? []) as $param) { ?>
                                    <?php
                                    $paramKey = (string) ($param['key'] ?? '');
                                    $paramType = (string) ($param['type'] ?? 'string');
                                    $paramValue = array_key_exists($paramKey, $currentRuleParams) ? $currentRuleParams[$paramKey] : ($param['default'] ?? '');
                                    $paramFieldId = 'member_rule_param_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $definitionKey . '_' . $paramKey);
                                    ?>
                                    <label class="admin-filter-field" for="<?php echo sr_e($paramFieldId); ?>">
                                        <span class="admin-filter-label"><?php echo sr_e((string) ($param['label'] ?? $paramKey)); ?></span>
                                        <?php if ($paramType === 'bool') { ?>
                                            <select id="<?php echo sr_e($paramFieldId); ?>" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-select">
                                                <option value="1"<?php echo !empty($paramValue) ? ' selected' : ''; ?>>예</option>
                                                <option value="0"<?php echo empty($paramValue) ? ' selected' : ''; ?>>아니오</option>
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
                    <details class="admin-advanced-details">
                        <summary>JSON 직접 입력</summary>
                        <textarea name="rule_params_json" rows="4" cols="70" class="form-textarea"><?php echo sr_e(is_array($editRule) ? (string) $editRule['rule_params_json'] : '{}'); ?></textarea>
                    </details>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_evaluation_policy">평가 정책</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_evaluation_policy" name="evaluation_policy" class="form-select">
                                            <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo is_array($editRule) && (string) $editRule['evaluation_policy'] === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_status_2">상태</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_status_2" name="status" class="form-select">
                                            <?php foreach ($allowedRuleStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo is_array($editRule) && (string) $editRule['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-group-rules')); ?>" class="btn btn-soft-default">목록</a>
            <button type="submit" class="btn btn-solid-primary">자동 규칙 저장</button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'evaluations') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/account')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2>자동 규칙 재평가</h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <span class="form-label">회원 조회</span>
                <div class="admin-form-field">
                    <select name="account_identifier_field" class="form-select" aria-label="회원 조회 조건">
                        <option value="hash">해시 아이디</option>
                        <option value="email">이메일</option>
                        <option value="name">이름</option>
                    </select>
                    <input type="text" name="account_identifier" maxlength="120" required class="form-input" aria-label="회원 조회어">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_source_module_key">모듈 key</label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_source_module_key" type="text" name="source_module_key" maxlength="60" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary">재평가</button>
        </div>
    </form>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/batch')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2>일괄 재평가</h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_source_module_key_2">모듈 key</label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_source_module_key_2" type="text" name="source_module_key" maxlength="60" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_limit">최대 회원 수</label>
                <div class="admin-form-field">
                    <input id="member_admin_groups_limit" type="number" name="limit" min="1" max="200" value="50" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary">일괄 재평가</button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'assignments') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/grant')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2>수동 배정</h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <span class="form-label">회원 조회</span>
                <div class="admin-form-field">
                    <select name="account_identifier_field" class="form-select" aria-label="회원 조회 조건">
                        <option value="hash">해시 아이디</option>
                        <option value="email">이메일</option>
                        <option value="name">이름</option>
                    </select>
                    <input type="text" name="account_identifier" maxlength="120" required class="form-input" aria-label="회원 조회어">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_groups_group_id_2">그룹</label>
                <div class="admin-form-field">
                    <select id="member_admin_groups_group_id_2" name="group_id" required class="form-select">
                                            <?php foreach ($groups as $group) { ?>
                                                <option value="<?php echo sr_e((string) $group['id']); ?>">
                                                    <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary">배정</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">최근 배정</h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>그룹</th>
                    <th>유형</th>
                    <th>상태</th>
                    <th>부여</th>
                    <th class="text-end">회수</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($memberships === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">배정 이력이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($memberships as $membership) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $membership['id']); ?></td>
                        <td>
                            <?php echo sr_e((string) $membership['account_public_hash']); ?><br>
                            <?php echo sr_e(sr_admin_member_display_name_preview($membership)); ?>
                        </td>
                        <td><?php echo sr_e((string) $membership['group_title']); ?></td>
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
                                    <button type="submit" class="btn btn-sm btn-outline-danger">해제</button>
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
            <h2 class="card-title">배정 이력</h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>그룹</th>
                    <th>이벤트</th>
                    <th>메시지</th>
                    <th>시간</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($membershipLogs === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">이력이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($membershipLogs as $log) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $log['id']); ?></td>
                        <td>
                            <?php echo sr_e((string) $log['account_public_hash']); ?><br>
                            <?php echo sr_e(sr_admin_member_display_name_preview($log)); ?>
                        </td>
                        <td><?php echo sr_e((string) $log['group_title']); ?></td>
                        <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                        <td><?php echo sr_e((string) $log['message']); ?></td>
                        <td><?php echo sr_e((string) $log['created_at']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
