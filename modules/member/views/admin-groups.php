<?php

$memberGroupsPage = isset($memberGroupsPage) ? (string) $memberGroupsPage : 'groups';
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
                    <div class="admin-form-label"><span class="form-label">그룹 key</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">그룹 key</span>
                        <input type="text" name="group_key" maxlength="60" required class="form-input">
                        </label>
                    </div>
                </div>
            <?php } ?>

            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">이름</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">이름</span>
                    <input type="text" name="title" maxlength="120" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['title'] : ''); ?>" class="form-input" required>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">설명</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">설명</span>
                    <textarea name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e(is_array($editGroup) ? (string) ($editGroup['description'] ?? '') : ''); ?></textarea>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">상태</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">상태</span>
                    <select name="status" class="form-select">
                        <?php $currentStatus = is_array($editGroup) ? (string) $editGroup['status'] : 'enabled'; ?>
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">정렬 순서</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">정렬 순서</span>
                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['sort_order'] : '0'); ?>" class="form-input">
                    </label>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>" class="btn btn-soft-default">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } elseif ($memberGroupsPage === 'groups') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">그룹 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/member-groups/new')); ?>" class="btn btn-sm btn-soft-default">새 그룹 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>이름</th>
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
                    <tr>
                        <td><?php echo sr_e((string) $group['id']); ?></td>
                        <td><?php echo sr_e((string) $group['group_key']); ?></td>
                        <td><?php echo sr_e((string) $group['title']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $group['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) $group['active_member_count']); ?></td>
                        <td><?php echo sr_e((string) $group['sort_order']); ?></td>
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
                <div class="admin-form-label"><span class="form-label">대상 그룹</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">대상 그룹</span>
                    <select name="group_id" required class="form-select">
                        <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo sr_e((string) $group['id']); ?>"<?php echo is_array($editRule) && (int) $editRule['group_id'] === (int) $group['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">조건 후보</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">조건 후보</span>
                    <select name="definition_key" required data-member-rule-definition class="form-select">
                        <?php $currentDefinitionKey = is_array($editRule) ? (string) $editRule['source_module_key'] . ':' . (string) $editRule['rule_key'] : ''; ?>
                        <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                            <option value="<?php echo sr_e((string) $definitionKey); ?>"<?php echo $currentDefinitionKey === (string) $definitionKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">조건 설정</span></div>
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
                                    ?>
                                    <label class="admin-filter-field">
                                        <span class="admin-filter-label"><?php echo sr_e((string) ($param['label'] ?? $paramKey)); ?></span>
                                        <?php if ($paramType === 'bool') { ?>
                                            <select name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-select">
                                                <option value="1"<?php echo !empty($paramValue) ? ' selected' : ''; ?>>예</option>
                                                <option value="0"<?php echo empty($paramValue) ? ' selected' : ''; ?>>아니오</option>
                                            </select>
                                        <?php } elseif ($paramType === 'int' || $paramType === 'subject') { ?>
                                            <input type="number" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo isset($param['min']) ? ' min="' . sr_e((string) $param['min']) . '"' : ''; ?><?php echo isset($param['max']) ? ' max="' . sr_e((string) $param['max']) . '"' : ''; ?><?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
                                        <?php } else { ?>
                                            <input type="text" name="rule_param[<?php echo sr_e((string) $definitionKey); ?>][<?php echo sr_e($paramKey); ?>]" value="<?php echo sr_e((string) $paramValue); ?>"<?php echo $panelActive ? '' : ' disabled'; ?> class="form-input">
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
                <div class="admin-form-label"><span class="form-label">평가 정책</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">평가 정책</span>
                    <select name="evaluation_policy" class="form-select">
                        <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo is_array($editRule) && (string) $editRule['evaluation_policy'] === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">상태</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">상태</span>
                    <select name="status" class="form-select">
                        <?php foreach ($allowedRuleStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo is_array($editRule) && (string) $editRule['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
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
                <div class="admin-form-label"><span class="form-label">회원 조회</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">회원 조회 조건</span>
                    <select name="account_identifier_field" class="form-select">
                        <option value="hash">해시 아이디</option>
                        <option value="email">이메일</option>
                        <option value="name">이름</option>
                    </select>
                    </label>
                    <label>
                        <span class="sr-only">회원 조회어</span>
                    <input type="text" name="account_identifier" maxlength="120" required class="form-input">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">모듈 key</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">모듈 key</span>
                    <input type="text" name="source_module_key" maxlength="60" class="form-input">
                    </label>
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
                <div class="admin-form-label"><span class="form-label">모듈 key</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">모듈 key</span>
                    <input type="text" name="source_module_key" maxlength="60" class="form-input">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">최대 회원 수</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">최대 회원 수</span>
                    <input type="number" name="limit" min="1" max="200" value="50" class="form-input">
                    </label>
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
                <div class="admin-form-label"><span class="form-label">회원 조회</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">회원 조회 조건</span>
                    <select name="account_identifier_field" class="form-select">
                        <option value="hash">해시 아이디</option>
                        <option value="email">이메일</option>
                        <option value="name">이름</option>
                    </select>
                    </label>
                    <label>
                        <span class="sr-only">회원 조회어</span>
                    <input type="text" name="account_identifier" maxlength="120" required class="form-input">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">그룹</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">그룹</span>
                    <select name="group_id" required class="form-select">
                        <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo sr_e((string) $group['id']); ?>">
                                <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                    </label>
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
