<?php

$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
$adminPageTitle = '커뮤니티 게시판 그룹';
$adminPageSubtitle = '게시판 그룹 상태를 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = 'admin-page-community-board-group-list admin-ui-scope';
if ($communityBoardGroupsPage === 'new') {
    $adminPageTitle = '게시판 그룹 생성';
    $adminPageSubtitle = '게시판 그룹과 그룹 기본 정책을 생성합니다.';
    $adminContainerClass = 'admin-page-community-board-group-form admin-ui-scope';
} elseif ($communityBoardGroupsPage === 'edit') {
    $adminPageTitle = '게시판 그룹 수정';
    $adminPageSubtitle = '게시판 그룹 기본 정보와 공통 정책을 수정합니다.';
    $adminContainerClass = 'admin-page-community-board-group-form admin-ui-scope';
}
$boardGroupListFilters = isset($boardGroupListFilters) && is_array($boardGroupListFilters) ? $boardGroupListFilters : ['status' => '', 'field' => 'all', 'q' => ''];
$boardGroupStatusCounts = isset($boardGroupStatusCounts) && is_array($boardGroupStatusCounts) ? $boardGroupStatusCounts : [];
$totalBoardGroups = (int) ($boardGroupStatusCounts['total'] ?? count($boardGroups ?? []));

$settingLabels = [
    'read_policy' => '읽기 정책',
    'write_policy' => '쓰기 정책',
    'comment_policy' => '댓글 정책',
    'read_group_keys' => '읽기 회원 그룹',
    'write_group_keys' => '쓰기 회원 그룹',
    'comment_group_keys' => '댓글 회원 그룹',
    'read_min_level' => '읽기 최소 레벨',
    'write_min_level' => '쓰기 최소 레벨',
    'comment_min_level' => '댓글 최소 레벨',
    'image_uploads_enabled' => '이미지 첨부 허용',
    'attachment_max_bytes' => '이미지 최대 용량',
    'attachment_max_count' => '이미지 최대 개수',
    'file_uploads_enabled' => '파일 첨부 허용',
    'file_attachment_max_bytes' => '파일 최대 용량',
    'file_attachment_max_count' => '파일 최대 개수',
    'file_allowed_extensions' => '파일 허용 확장자',
];
$groupSettingValue = static function (array $settings, string $key, string $default): string {
    return (string) ($settings[$key] ?? $default);
};
$groupKeysSettingValue = static function (array $settings, string $key): array {
    $value = (string) ($settings[$key] ?? '');
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
};
$groupField = static function (array $group, string $key, string $default = ''): string {
    return (string) ($group[$key] ?? $default);
};
$selectedBoardGroup = is_array($editBoardGroup ?? null) ? $editBoardGroup : [];
$formBoardGroup = $communityBoardGroupsPage === 'edit' ? $selectedBoardGroup : [
    'group_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'enabled',
    'sort_order' => 0,
];
$formGroupSettings = [];
if ($communityBoardGroupsPage === 'edit' && isset($formBoardGroup['id'])) {
    $formGroupSettings = is_array($boardGroupSettings[(int) $formBoardGroup['id']] ?? null) ? $boardGroupSettings[(int) $formBoardGroup['id']] : [];
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityBoardGroupsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총그룹 <strong><?php echo sr_e((string) $totalBoardGroups); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($boardGroupStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=disabled')); ?>" class="admin-summary-meta">중지 <?php echo sr_e((string) ($boardGroupStatusCounts['disabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups?status=archived')); ?>" class="admin-summary-meta">보관 <?php echo sr_e((string) ($boardGroupStatusCounts['archived'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="admin-filter admin-community-board-group-filter ui-form-theme">
        <div class="admin-filter-grid admin-community-board-group-search-grid">
            <div class="admin-filter-field admin-community-board-group-filter-status">
                <label for="community_admin_board_groups_status_filter" class="admin-filter-label">상태</label>
                <select id="community_admin_board_groups_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($boardGroupListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedGroupStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($boardGroupListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-group-filter-field">
                <label for="community_admin_board_groups_field" class="admin-filter-label">검색 조건</label>
                <select id="community_admin_board_groups_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'key' => 'key', 'title' => '이름'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardGroupListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-group-filter-keyword">
                <label for="community_admin_board_groups_q" class="admin-filter-label">검색어</label>
                <input id="community_admin_board_groups_q" type="search" name="q" value="<?php echo sr_e((string) ($boardGroupListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="key, 이름">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">게시판 그룹 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>" class="btn btn-sm btn-solid-light">새 게시판 그룹 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-community-board-group-table">
            <caption class="sr-only">커뮤니티 게시판 그룹 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>게시판 수</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boardGroups === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">게시판 그룹이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($boardGroups as $boardGroup) { ?>
                    <?php
                    $boardGroupStatus = (string) $boardGroup['status'];
                    $statusClass = match ($boardGroupStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $boardGroup['id']); ?></td>
                        <td class="admin-table-nowrap admin-community-board-group-key-cell"><?php echo sr_e((string) $boardGroup['group_key']); ?></td>
                        <td class="admin-table-break admin-community-board-group-title-cell"><?php echo sr_e((string) $boardGroup['title']); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($boardGroupStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap">
                            <a href="<?php echo sr_e(sr_url('/admin/community/boards?group_id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-solid-light">
                                <?php echo sr_e((string) ($boardGroup['board_count'] ?? 0)); ?>개 보기
                            </a>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardGroupsPage === 'edit' ? '/admin/community/board-groups/update' : '/admin/community/board-groups/create')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $communityBoardGroupsPage === 'edit' ? '게시판 그룹 수정' : '게시판 그룹 생성'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $formBoardGroup['id']); ?>">
                <div class="admin-form-row">
                    <span class="form-label">그룹 key</span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e((string) $formBoardGroup['group_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_key">그룹 key</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_key" type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" class="form-input" required>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_title">이름</label>
                <div class="admin-form-field">
                    <input id="community_admin_board_groups_title" type="text" name="title" maxlength="120" value="<?php echo sr_e($groupField($formBoardGroup, 'title')); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="community_admin_board_groups_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e($groupField($formBoardGroup, 'description')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_status">상태</label>
                <div class="admin-form-field">
                    <select id="community_admin_board_groups_status" name="status" class="form-select">
                                            <?php foreach ($allowedGroupStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_board_groups_sort_order">정렬 순서</label>
                <div class="admin-form-field">
                    <input id="community_admin_board_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>" class="form-input">
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2>그룹 기본 설정</h2>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_read_policy">읽기 정책</label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_read_policy" name="group_read_policy" class="form-select">
                                                    <?php foreach ($allowedReadPolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'read_policy', 'public') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_read_group_keys">읽기 회원 그룹</label>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_read_group_keys', 'group_read_group_keys', $groupKeysSettingValue($formGroupSettings, 'read_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_read_min_level">읽기 최소 레벨</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_read_min_level" type="number" name="group_read_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" class="form-input" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'read_min_level', '0')); ?>">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_write_policy">쓰기 정책</label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_write_policy" name="group_write_policy" class="form-select">
                                                    <?php foreach ($allowedWritePolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'write_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_write_group_keys">쓰기 회원 그룹</label>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_write_group_keys', 'group_write_group_keys', $groupKeysSettingValue($formGroupSettings, 'write_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_write_min_level">쓰기 최소 레벨</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_write_min_level" type="number" name="group_write_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" class="form-input" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'write_min_level', '0')); ?>">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_comment_policy">댓글 정책</label>
                    <div class="admin-form-field">
                        <select id="community_admin_board_groups_group_comment_policy" name="group_comment_policy" class="form-select">
                                                    <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'comment_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_comment_group_keys">댓글 회원 그룹</label>
                    <div class="admin-form-field">
                        <?php echo sr_admin_member_group_key_select_html('community_admin_board_groups_group_comment_group_keys', 'group_comment_group_keys', $groupKeysSettingValue($formGroupSettings, 'comment_group_keys'), $enabledMemberGroups); ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_comment_min_level">댓글 최소 레벨</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_comment_min_level" type="number" name="group_comment_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" class="form-input" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'comment_min_level', '0')); ?>">
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label">이미지 첨부 허용</span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_image_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_image_uploads_enabled" type="checkbox" name="group_image_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html('이미지 첨부 허용'); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_attachment_max_bytes">이미지 최대 용량(bytes)</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_attachment_max_bytes" type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_bytes', '2097152')); ?>" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_attachment_max_count">이미지 최대 개수</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_attachment_max_count" type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_count', '1')); ?>" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label">파일 첨부 허용</span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_group_file_uploads_enabled">
                                                    <input id="modules_community_admin_board_groups_group_file_uploads_enabled" type="checkbox" name="group_file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html('파일 첨부 허용'); ?>
                                                </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_file_attachment_max_bytes">파일 최대 용량(bytes)</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_attachment_max_bytes" type="number" name="group_file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_bytes', '5242880')); ?>" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_file_attachment_max_count">파일 최대 개수</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_attachment_max_count" type="number" name="group_file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3')); ?>" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_board_groups_group_file_allowed_extensions">파일 허용 확장자</label>
                    <div class="admin-form-field">
                        <input id="community_admin_board_groups_group_file_allowed_extensions" type="text" name="group_file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_allowed_extensions', 'pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,hwp')); ?>" class="form-input form-control-full" placeholder="pdf, txt, zip">
                    </div>
                </div>
        </section>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <section class="admin-card card">
                    <h2>같은 그룹 게시판에 적용</h2>
                    <p>적용할 설정을 선택하세요.</p>
                    <?php foreach ($settingLabels as $settingKey => $settingLabel) { ?>
                        <label class="admin-form-check form-label" for="modules_community_admin_board_groups_apply_setting_keys">
                            <input id="modules_community_admin_board_groups_apply_setting_keys" type="checkbox" name="apply_setting_keys[]" value="<?php echo sr_e($settingKey); ?>" class="form-checkbox">
                            <?php echo sr_e($settingLabel); ?>
                        </label>
                    <?php } ?>
                </section>
            <?php } ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardGroupsPage === 'edit' ? '그룹 변경' : '그룹 생성'; ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
