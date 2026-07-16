<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/survey/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/groups', 'view');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 30);
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/groups', $intent === 'delete' ? 'delete' : 'edit');
    $groupId = (int) sr_post_string('group_id', 20);
    if ($intent === 'delete') {
        $result = sr_survey_delete_group($pdo, $groupId)
            ? sr_admin_action_result([], '설문 그룹을 삭제하고 소속 설문을 단독으로 전환했습니다.')
            : sr_admin_action_result(['삭제할 설문 그룹을 찾을 수 없습니다.'], '');
        sr_admin_redirect_with_result($result, '/admin/surveys/groups');
    }
    if ($intent === 'save') {
        $groupKey = sr_survey_clean_key(sr_post_string('group_key', 64), 64);
        $values = [
            'group_key' => $groupKey,
            'title' => sr_survey_clean_single_line(sr_post_string('title', 120), 120),
            'description' => sr_survey_clean_text(sr_post_string('description', 2000), 2000),
            'status' => sr_post_string('status', 20),
            'sort_order' => max(0, min(1000000, (int) sr_post_string('sort_order', 20))),
        ];
        $errors = [];
        if ($groupId > 0 && !is_array(sr_survey_group_by_id($pdo, $groupId))) {
            $errors[] = '수정할 설문 그룹을 찾을 수 없습니다.';
        }
        if ($groupId < 1 && !sr_survey_group_key_is_valid($groupKey)) {
            $errors[] = '그룹 Key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
        }
        if ((string) $values['title'] === '') {
            $errors[] = '그룹명을 입력하세요.';
        }
        if (!in_array((string) $values['status'], sr_survey_group_statuses(), true)) {
            $errors[] = '상태 값이 올바르지 않습니다.';
        }
        if ($groupId < 1 && sr_survey_group_key_exists($pdo, $groupKey)) {
            $errors[] = '이미 사용 중인 그룹 Key입니다.';
        }
        if ($errors !== []) {
            $_SESSION['sr_survey_group_form_values'] = array_merge($values, ['id' => $groupId]);
            sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $groupId > 0 ? '/admin/surveys/groups?mode=edit&id=' . (string) $groupId : '/admin/surveys/groups?mode=new');
        }
        $savedId = sr_survey_save_group($pdo, $values, $groupId);
        sr_admin_redirect_with_result(sr_admin_action_result([], $groupId > 0 ? '설문 그룹을 수정했습니다.' : '설문 그룹을 등록했습니다.'), '/admin/surveys/groups?mode=edit&id=' . (string) $savedId);
    }
    sr_admin_redirect_with_result(sr_admin_action_result(['지원하지 않는 요청입니다.'], ''), '/admin/surveys/groups');
}

$flashResult = sr_admin_pop_flash_result();
$mode = sr_get_string('mode', 20);
$mode = in_array($mode, ['new', 'edit'], true) ? $mode : 'list';
$formValues = $_SESSION['sr_survey_group_form_values'] ?? null;
unset($_SESSION['sr_survey_group_form_values']);
if (!is_array($formValues)) {
    $formValues = $mode === 'edit' ? sr_survey_group_by_id($pdo, (int) sr_get_string('id', 20)) : ['id' => 0, 'group_key' => '', 'title' => '', 'description' => '', 'status' => 'enabled', 'sort_order' => 0];
}
if ($mode === 'edit' && !is_array($formValues)) {
    sr_render_error(404, '설문 그룹을 찾을 수 없습니다.');
}
$groupSortOptions = sr_survey_admin_group_sort_options();
$groupDefaultSort = sr_survey_admin_group_default_sort();
$groupSort = sr_admin_sort_from_request($groupSortOptions, $groupDefaultSort);
$groupPagination = $mode === 'list'
    ? sr_admin_pagination_from_total($pdo, sr_survey_admin_group_count($pdo))
    : sr_admin_pagination_meta(0, 1, 1);
$groups = $mode === 'list'
    ? sr_survey_admin_groups($pdo, (int) $groupPagination['per_page'], sr_admin_pagination_offset($groupPagination), $groupSort)
    : [];
$adminPageTitle = $mode === 'list' ? '설문 그룹 관리' : ($mode === 'edit' ? '설문 그룹 수정' : '설문 그룹 등록');
$adminPageTitleUrl = sr_admin_page_title_reset_url($mode === 'list', '/admin/surveys/groups');
include SR_ROOT . '/modules/admin/views/layout-header.php';
echo sr_admin_feedback_toasts((string) ($flashResult['notice'] ?? ''), (array) ($flashResult['errors'] ?? []));
?>
<?php if ($mode === 'list') { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">설문 그룹 목록</h2>
            <div class="card-actions">
                <a href="<?php echo sr_e(sr_url('/admin/surveys/groups?mode=new')); ?>" class="btn btn-sm btn-outline-secondary">그룹 등록</a>
            </div>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($groupSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($groupSortOptions, $groupDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="설문 그룹 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($groupPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-survey-group-table">
                <caption class="sr-only">설문 그룹 목록</caption>
                <thead>
                    <tr>
                        <th<?php echo sr_admin_sort_aria('group_key', $groupSort); ?>><?php echo sr_admin_sort_header_html('식별값', 'group_key', $groupSort, $groupSortOptions, $groupDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('title', $groupSort); ?>><?php echo sr_admin_sort_header_html('그룹명', 'title', $groupSort, $groupSortOptions, $groupDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $groupSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $groupSort, $groupSortOptions, $groupDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('item_count', $groupSort); ?>><?php echo sr_admin_sort_header_html('설문 수', 'item_count', $groupSort, $groupSortOptions, $groupDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('sort_order', $groupSort); ?>><?php echo sr_admin_sort_header_html('순서', 'sort_order', $groupSort, $groupSortOptions, $groupDefaultSort); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($groups === []) { ?>
                        <tr>
                            <td colspan="6" class="admin-empty-state">등록된 설문 그룹이 없습니다.</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($groups as $group) { ?>
                        <?php
                        $groupStatus = (string) ($group['status'] ?? '');
                        $groupStatusClass = match ($groupStatus) {
                            'enabled' => 'is-success',
                            'disabled' => 'is-warning',
                            default => 'is-danger',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($group['group_key'] ?? '')); ?></code></td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($group['title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($groupStatusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($group['item_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($group['sort_order'] ?? 0))); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/surveys/groups?mode=edit&id=' . rawurlencode((string) (int) ($group['id'] ?? 0)))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="설문 그룹 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
        </div>
        <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(sr_survey_group_statuses(), 'content_status'), [
            'enabled' => '현재 운영하는 그룹으로 구분합니다.',
            'disabled' => '그룹 운영을 잠시 중지한 상태로 구분하며 소속 설문의 상태는 바꾸지 않습니다.',
            'archived' => '더 이상 운영하지 않는 그룹을 삭제하지 않고 기록으로 보관합니다.',
        ], '설문 그룹 상태 설명'); ?>
    </section>
    <?php echo sr_admin_pagination_html($groupPagination, '설문 그룹 목록 페이지'); ?>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/groups')); ?>" class="admin-form ui-form-theme"><?php echo sr_csrf_field(); ?><input type="hidden" name="intent" value="save"><input type="hidden" name="group_id" value="<?php echo sr_e((string) (int) ($formValues['id'] ?? 0)); ?>">
        <section class="card"><div class="card-header"><h2 class="card-title">기본 정보</h2></div><div class="form-grid">
            <div class="form-row"><label class="form-label" for="survey_group_key">그룹 Key <span class="sr-required-label">(필수)</span></label><div class="form-field"><input id="survey_group_key" type="text" name="group_key" value="<?php echo sr_e((string) ($formValues['group_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input<?php echo (int) ($formValues['id'] ?? 0) > 0 ? ' readonly' : ''; ?>></div></div>
            <div class="form-row"><label class="form-label" for="survey_group_title">그룹명 <span class="sr-required-label">(필수)</span></label><div class="form-field"><input id="survey_group_title" type="text" name="title" value="<?php echo sr_e((string) ($formValues['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required></div></div>
            <div class="form-row"><label class="form-label" for="survey_group_description">설명</label><div class="form-field"><textarea id="survey_group_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($formValues['description'] ?? '')); ?></textarea></div></div>
            <div class="form-row"><label class="form-label" for="survey_group_status">상태</label><div class="form-field"><select id="survey_group_status" name="status" class="form-select"><?php foreach (sr_survey_group_statuses() as $status) { ?><option value="<?php echo sr_e($status); ?>"<?php echo (string) ($formValues['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e($status); ?></option><?php } ?></select></div></div>
            <div class="form-row"><label class="form-label" for="survey_group_sort_order">정렬 순서</label><div class="form-field"><input id="survey_group_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) (int) ($formValues['sort_order'] ?? 0)); ?>" class="form-input" min="0" max="1000000"></div></div>
        </div></section><div class="form-actions"><a href="<?php echo sr_e(sr_url('/admin/surveys/groups')); ?>" class="btn btn-solid-light">목록</a><button type="submit" class="btn btn-primary">저장</button></div></form>
    <?php if ((int) ($formValues['id'] ?? 0) > 0) { ?><form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/groups')); ?>" class="admin-form ui-form-theme"><?php echo sr_csrf_field(); ?><input type="hidden" name="intent" value="delete"><input type="hidden" name="group_id" value="<?php echo sr_e((string) (int) $formValues['id']); ?>"><div class="form-actions"><button type="submit" class="btn btn-outline-danger" data-confirm="그룹을 삭제하면 소속 설문은 단독으로 전환됩니다. 삭제할까요?">그룹 삭제</button></div></form><?php } ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
