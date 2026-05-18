<?php

$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
$adminPageTitle = '커뮤니티 게시판 그룹';
if ($communityBoardGroupsPage === 'new') {
    $adminPageTitle = '게시판 그룹 생성';
} elseif ($communityBoardGroupsPage === 'edit') {
    $adminPageTitle = '게시판 그룹 수정';
}

$settingLabels = [
    'read_policy' => '읽기 정책',
    'write_policy' => '쓰기 정책',
    'comment_policy' => '댓글 정책',
    'read_group_keys' => '읽기 그룹 key',
    'write_group_keys' => '쓰기 그룹 key',
    'comment_group_keys' => '댓글 그룹 key',
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
$groupKeysSettingValue = static function (array $settings, string $key): string {
    $value = (string) ($settings[$key] ?? '');
    if ($value === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    $rawKeys = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    return implode(', ', sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []));
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

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="btn btn-surface-default-soft">커뮤니티 설정</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-surface-default-soft">게시판 그룹 목록</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>" class="btn btn-surface-default-soft">게시판 그룹 생성</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-surface-default-soft">게시판 관리</a>
    </div>
</div>

<?php if ($communityBoardGroupsPage !== 'list' && $enabledMemberGroups !== []) { ?>
    <section>
        <h2>사용 가능한 회원 그룹 key</h2>
        <ul>
            <?php foreach ($enabledMemberGroups as $memberGroup) { ?>
                <li>
                    <?php echo sr_e((string) $memberGroup['group_key']); ?>
                    - <?php echo sr_e((string) $memberGroup['title']); ?>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php if ($communityBoardGroupsPage === 'list') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">게시판 그룹 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>" class="btn btn-sm btn-surface-default-soft">새 게시판 그룹 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table">
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
                    <tr>
                        <td><?php echo sr_e((string) $boardGroup['id']); ?></td>
                        <td><?php echo sr_e((string) $boardGroup['group_key']); ?></td>
                        <td><?php echo sr_e((string) $boardGroup['title']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $boardGroup['status'], 'content_status')); ?></td>
                        <td>
                            <a href="<?php echo sr_e(sr_url('/admin/community/boards?group_id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-surface-default-soft">
                                <?php echo sr_e((string) ($boardGroup['board_count'] ?? 0)); ?>개 보기
                            </a>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>" class="btn btn-sm btn-surface-default-soft">수정</a>
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
                <p>그룹 key: <?php echo sr_e((string) $formBoardGroup['group_key']); ?></p>
            <?php } else { ?>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">그룹 key</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">그룹 key</span>
                        <input type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" required>
                        </label>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">이름</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">이름</span>
                    <input type="text" name="title" maxlength="120" value="<?php echo sr_e($groupField($formBoardGroup, 'title')); ?>" required>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">설명</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">설명</span>
                    <textarea name="description" rows="3" cols="60"><?php echo sr_e($groupField($formBoardGroup, 'description')); ?></textarea>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">상태</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">상태</span>
                    <select name="status">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
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
                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>">
                    </label>
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2>그룹 기본 설정</h2>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">읽기 정책</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">읽기 정책</span>
                        <select name="group_read_policy">
                            <?php foreach ($allowedReadPolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'read_policy', 'public') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">읽기 그룹 key</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">읽기 그룹 key</span>
                        <input type="text" name="group_read_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'read_group_keys')); ?>" placeholder="regular_member, vip">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">읽기 최소 레벨</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">읽기 최소 레벨</span>
                        <input type="number" name="group_read_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'read_min_level', '0')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">쓰기 정책</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">쓰기 정책</span>
                        <select name="group_write_policy">
                            <?php foreach ($allowedWritePolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'write_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">쓰기 그룹 key</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">쓰기 그룹 key</span>
                        <input type="text" name="group_write_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'write_group_keys')); ?>" placeholder="regular_member, vip">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">쓰기 최소 레벨</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">쓰기 최소 레벨</span>
                        <input type="number" name="group_write_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'write_min_level', '0')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">댓글 정책</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">댓글 정책</span>
                        <select name="group_comment_policy">
                            <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'comment_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">댓글 그룹 key</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">댓글 그룹 key</span>
                        <input type="text" name="group_comment_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'comment_group_keys')); ?>" placeholder="regular_member, vip">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">댓글 최소 레벨</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">댓글 최소 레벨</span>
                        <input type="number" name="group_comment_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'comment_min_level', '0')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">이미지 첨부 허용</span></div>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label">
                            <input type="checkbox" name="group_image_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                            <?php echo sr_admin_choice_label_html('이미지 첨부 허용'); ?>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">이미지 최대 용량(bytes)</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">이미지 최대 용량(bytes)</span>
                        <input type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_bytes', '2097152')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">이미지 최대 개수</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">이미지 최대 개수</span>
                        <input type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_count', '1')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">파일 첨부 허용</span></div>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label">
                            <input type="checkbox" name="group_file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                            <?php echo sr_admin_choice_label_html('파일 첨부 허용'); ?>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">파일 최대 용량(bytes)</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">파일 최대 용량(bytes)</span>
                        <input type="number" name="group_file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_bytes', '5242880')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">파일 최대 개수</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">파일 최대 개수</span>
                        <input type="number" name="group_file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3')); ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">파일 허용 확장자</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">파일 허용 확장자</span>
                        <input type="text" name="group_file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_allowed_extensions', 'pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,hwp')); ?>" placeholder="pdf, txt, zip">
                        </label>
                    </div>
                </div>
        </section>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <section class="admin-card card">
                    <h2>같은 그룹 게시판에 적용</h2>
                    <p>적용할 설정을 선택하세요.</p>
                    <?php foreach ($settingLabels as $settingKey => $settingLabel) { ?>
                        <label class="admin-form-check form-label">
                            <input type="checkbox" name="apply_setting_keys[]" value="<?php echo sr_e($settingKey); ?>" class="form-checkbox">
                            <?php echo sr_e($settingLabel); ?>
                        </label>
                    <?php } ?>
                </section>
            <?php } ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardGroupsPage === 'edit' ? '그룹 변경' : '그룹 생성'; ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
