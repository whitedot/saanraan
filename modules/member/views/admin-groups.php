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

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<p>
    <a href="<?php echo sr_e(sr_url('/admin/member-groups')); ?>">그룹 목록</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/member-groups/new')); ?>">그룹 생성</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/member-group-rules')); ?>">자동 규칙</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/member-group-evaluations')); ?>">재평가</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/member-group-assignments')); ?>">수동 배정</a>
</p>

<?php if ($memberGroupsPage === 'group_form') { ?>
    <section>
        <h2><?php echo is_array($editGroup) ? '그룹 수정' : '그룹 생성'; ?></h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/member-groups/save')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="group_id" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['id'] : ''); ?>">

            <?php if (is_array($editGroup)) { ?>
                <p>그룹 key: <?php echo sr_e((string) $editGroup['group_key']); ?></p>
            <?php } else { ?>
                <p>
                    <label>그룹 key<br>
                        <input type="text" name="group_key" maxlength="60" required>
                    </label>
                </p>
            <?php } ?>

            <p>
                <label>이름<br>
                    <input type="text" name="title" maxlength="120" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['title'] : ''); ?>" required>
                </label>
            </p>
            <p>
                <label>설명<br>
                    <textarea name="description" rows="3" cols="60"><?php echo sr_e(is_array($editGroup) ? (string) ($editGroup['description'] ?? '') : ''); ?></textarea>
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php $currentStatus = is_array($editGroup) ? (string) $editGroup['status'] : 'enabled'; ?>
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>정렬 순서<br>
                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e(is_array($editGroup) ? (string) $editGroup['sort_order'] : '0'); ?>">
                </label>
            </p>
            <button type="submit">저장</button>
        </form>
    </section>
<?php } elseif ($memberGroupsPage === 'groups') { ?>
    <section>
        <h2>그룹 목록</h2>
        <p><a href="<?php echo sr_e(sr_url('/admin/member-groups/new')); ?>">새 그룹 추가</a></p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>회원 수</th>
                    <th>정렬</th>
                    <th>수정</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groups === []) { ?>
                    <tr>
                        <td colspan="7">회원 그룹이 없습니다.</td>
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
                        <td><a href="<?php echo sr_e(sr_url('/admin/member-groups/edit?id=' . rawurlencode((string) $group['id']))); ?>">수정</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
<?php } elseif ($memberGroupsPage === 'rules') { ?>
    <section>
        <h2>자동 조건 후보</h2>
        <table>
            <thead>
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
                        <td colspan="4">설치된 활성 모듈이 제공하는 회원 그룹 조건 후보가 없습니다.</td>
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
    </section>

    <section>
        <h2>저장된 자동 규칙</h2>
        <p><a href="<?php echo sr_e(sr_url('/admin/member-group-rules/new')); ?>">새 자동 규칙 추가</a></p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>그룹</th>
                    <th>조건</th>
                    <th>정책</th>
                    <th>상태</th>
                    <th>최근 평가</th>
                    <th>수정</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($groupRules === []) { ?>
                    <tr>
                        <td colspan="7">자동 규칙이 없습니다.</td>
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
                        <td><a href="<?php echo sr_e(sr_url('/admin/member-group-rules/edit?id=' . rawurlencode((string) $rule['id']))); ?>">수정</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
<?php } elseif ($memberGroupsPage === 'rule_form') { ?>
    <section>
        <h2><?php echo is_array($editRule) ? '자동 규칙 수정' : '자동 규칙 생성'; ?></h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-rules/save')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="rule_id" value="<?php echo sr_e(is_array($editRule) ? (string) $editRule['id'] : ''); ?>">
            <p>
                <label>대상 그룹<br>
                    <select name="group_id" required>
                        <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo sr_e((string) $group['id']); ?>"<?php echo is_array($editRule) && (int) $editRule['group_id'] === (int) $group['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>조건 후보<br>
                    <select name="definition_key" required>
                        <?php $currentDefinitionKey = is_array($editRule) ? (string) $editRule['source_module_key'] . ':' . (string) $editRule['rule_key'] : ''; ?>
                        <?php foreach ($ruleDefinitions as $definitionKey => $definition) { ?>
                            <option value="<?php echo sr_e((string) $definitionKey); ?>"<?php echo $currentDefinitionKey === (string) $definitionKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $definition['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>조건 설정 JSON<br>
                    <textarea name="rule_params_json" rows="4" cols="70"><?php echo sr_e(is_array($editRule) ? (string) $editRule['rule_params_json'] : '{}'); ?></textarea>
                </label>
            </p>
            <p>
                <label>평가 정책<br>
                    <select name="evaluation_policy">
                        <?php foreach ($allowedEvaluationPolicies as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo is_array($editRule) && (string) $editRule['evaluation_policy'] === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'evaluation_policy')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php foreach ($allowedRuleStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo is_array($editRule) && (string) $editRule['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">자동 규칙 저장</button>
        </form>
    </section>
<?php } elseif ($memberGroupsPage === 'evaluations') { ?>
    <section>
        <h2>자동 규칙 재평가</h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/account')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>회원 공개 해시<br>
                    <input type="text" name="account_identifier" maxlength="80" required>
                </label>
            </p>
            <p>
                <label>모듈 key<br>
                    <input type="text" name="source_module_key" maxlength="60">
                </label>
            </p>
            <button type="submit">재평가</button>
        </form>

        <h3>일괄 재평가</h3>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-evaluations/batch')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>모듈 key<br>
                    <input type="text" name="source_module_key" maxlength="60">
                </label>
            </p>
            <p>
                <label>최대 회원 수<br>
                    <input type="number" name="limit" min="1" max="200" value="50">
                </label>
            </p>
            <button type="submit">일괄 재평가</button>
        </form>
    </section>
<?php } elseif ($memberGroupsPage === 'assignments') { ?>
    <section>
        <h2>수동 배정</h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/grant')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>회원 공개 해시<br>
                    <input type="text" name="account_identifier" maxlength="80" required>
                </label>
            </p>
            <p>
                <label>그룹<br>
                    <select name="group_id" required>
                        <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo sr_e((string) $group['id']); ?>">
                                <?php echo sr_e((string) $group['title']); ?> (<?php echo sr_e((string) $group['group_key']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">배정</button>
        </form>
    </section>

    <section>
        <h2>최근 배정</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>그룹</th>
                    <th>유형</th>
                    <th>상태</th>
                    <th>부여</th>
                    <th>회수</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($memberships === []) { ?>
                    <tr>
                        <td colspan="7">배정 이력이 없습니다.</td>
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
                        <td>
                            <?php if ((string) $membership['assignment_type'] === 'manual' && (string) $membership['status'] === 'active') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/member-group-assignments/revoke')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $membership['account_id']); ?>">
                                    <input type="hidden" name="group_id" value="<?php echo sr_e((string) $membership['group_id']); ?>">
                                    <button type="submit">해제</button>
                                </form>
                            <?php } else { ?>
                                <?php echo sr_e((string) ($membership['revoked_at'] ?? '')); ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>배정 이력</h2>
        <table>
            <thead>
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
                        <td colspan="6">이력이 없습니다.</td>
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
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
