<?php

$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
$adminPageTitle = '커뮤니티 게시판';
if ($communityBoardsPage === 'new') {
    $adminPageTitle = '게시판 생성';
} elseif ($communityBoardsPage === 'edit') {
    $adminPageTitle = '게시판 수정';
}

$sourceLabels = [
    'board' => '개별 설정',
    'group' => '그룹 기본값',
];
$boardSettingSource = static function (array $board, string $key): string {
    $sources = is_array($board['setting_sources'] ?? null) ? $board['setting_sources'] : [];
    return (string) ($sources[$key] ?? 'board');
};
$boardGroupKeysValue = static function (array $board, string $key): string {
    return implode(', ', is_array($board[$key] ?? null) ? $board[$key] : []);
};
$boardArrayValue = static function (array $board, string $key): string {
    return implode(', ', is_array($board[$key] ?? null) ? $board[$key] : []);
};
$boardField = static function (array $board, string $key, string $default = ''): string {
    return (string) ($board[$key] ?? $default);
};
$selectedBoard = is_array($editBoard ?? null) ? $editBoard : [];
$formBoard = $communityBoardsPage === 'edit' ? $selectedBoard : [
    'board_group_id' => 0,
    'board_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'enabled',
    'read_policy' => (string) ($allowedReadPolicies[0] ?? 'public'),
    'write_policy' => (string) ($allowedWritePolicies[0] ?? 'member'),
    'comment_policy' => (string) ($allowedCommentPolicies[0] ?? 'member'),
    'image_uploads_enabled' => 1,
    'attachment_max_bytes' => 2097152,
    'attachment_max_count' => 1,
    'banner_before_list_id' => 0,
    'banner_after_list_id' => 0,
    'popup_layer_list_id' => 0,
    'banner_before_view_id' => 0,
    'banner_after_view_id' => 0,
    'popup_layer_view_id' => 0,
    'banner_before_form_id' => 0,
    'banner_after_form_id' => 0,
    'popup_layer_form_id' => 0,
    'file_uploads_enabled' => '0',
    'file_attachment_max_bytes' => 5242880,
    'file_attachment_max_count' => 3,
    'file_allowed_extensions' => ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'],
    'sort_order' => 0,
    'read_group_keys' => [],
    'write_group_keys' => [],
    'comment_group_keys' => [],
    'read_min_level' => 0,
    'write_min_level' => 0,
    'comment_min_level' => 0,
    'skin_key' => 'basic',
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="member-summary">
    <div class="member-summary-links">
        <a href="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="btn btn-surface-default-soft">커뮤니티 설정</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-surface-default-soft">게시판 목록</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/boards/new')); ?>" class="btn btn-surface-default-soft">게시판 생성</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>" class="btn btn-surface-default-soft">게시판 그룹 관리</a>
    </div>
</div>

<?php if ($communityBoardsPage !== 'list' && $enabledMemberGroups !== []) { ?>
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

<?php if ($communityBoardsPage === 'list') { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">게시판 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards/new')); ?>" class="btn btn-sm btn-surface-default-soft">새 게시판 추가</a>
        </div>
        <?php if ((int) ($boardGroupFilterId ?? 0) > 0) { ?>
            <div class="admin-filter-form">
                <div class="admin-filter-heading">
                    <strong><?php echo sr_e((string) ($boardGroupTitles[(int) $boardGroupFilterId] ?? '선택한 그룹')); ?> 그룹에 속한 게시판만 표시 중</strong>
                    <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-sm btn-surface-default-soft">전체 보기</a>
                </div>
            </div>
        <?php } ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>이름</th>
                    <th>그룹</th>
                    <th>상태</th>
                    <th>스킨</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boards === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-dashboard-empty">게시판이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($boards as $board) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $board['id']); ?></td>
                        <td><?php echo sr_e((string) $board['board_key']); ?></td>
                        <td><?php echo sr_e((string) $board['title']); ?></td>
                        <td><?php echo sr_e((string) ($board['board_group_title'] ?? '')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $board['status'], 'content_status')); ?></td>
                        <td>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="update_skin">
                                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $board['id']); ?>">
                                <select name="skin_key">
                                    <?php foreach ($communitySkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($board['skin_key'] ?? 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-surface-default-soft">저장</button>
                            </form>
                        </td>
                        <td class="member-cell-manage">
                            <div class="member-manage">
                                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>" class="btn btn-sm btn-surface-default-soft">바로가기</a>
                                <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) $board['id']))); ?>" class="btn btn-sm btn-surface-default-soft">수정</a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardsPage === 'edit' ? '/admin/community/boards/update' : '/admin/community/boards/create')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
        <section class="card">
            <h2><?php echo $communityBoardsPage === 'edit' ? '게시판 수정' : '게시판 생성'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardsPage === 'edit') { ?>
                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                <p>게시판 key: <?php echo sr_e((string) $formBoard['board_key']); ?></p>
            <?php } else { ?>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">게시판 key</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">게시판 key</span>
                        <input type="text" name="board_key" maxlength="60" value="<?php echo sr_e($boardField($formBoard, 'board_key')); ?>" required>
                        </label>
                    </div>
                </div>
            <?php } ?>
            <div class="af-row">
                <div class="af-label"><span class="form-label">게시판 그룹</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">게시판 그룹</span>
                    <select name="board_group_id">
                        <option value="0">없음</option>
                        <?php foreach ($boardGroups as $boardGroup) { ?>
                            <option value="<?php echo sr_e((string) $boardGroup['id']); ?>"<?php echo (int) $boardField($formBoard, 'board_group_id', '0') === (int) $boardGroup['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $boardGroup['title']); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">이름</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">이름</span>
                    <input type="text" name="title" maxlength="120" value="<?php echo sr_e($boardField($formBoard, 'title')); ?>" required>
                    </label>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">설명</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">설명</span>
                    <textarea name="description" rows="3" cols="60"><?php echo sr_e($boardField($formBoard, 'description')); ?></textarea>
                    </label>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">상태</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">상태</span>
                    <select name="status">
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === $boardField($formBoard, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">게시판 스킨</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">게시판 스킨</span>
                    <select name="skin_key">
                        <?php foreach ($communitySkinOptions as $skinKey => $skinOption) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $boardField($formBoard, 'skin_key', 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>

            <?php foreach (sr_community_board_group_setting_keys() as $settingKey) { ?>
                <?php if ($communityBoardsPage === 'new') { ?>
                    <input type="hidden" name="source_<?php echo sr_e($settingKey); ?>" value="board">
                <?php } ?>
            <?php } ?>
        </section>

        <section class="card">
            <h2>접근 정책</h2>
            <div class="af-row">
                <div class="af-label"><span class="form-label">읽기 정책</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">읽기 정책</span>
                    <select name="read_policy">
                        <?php foreach ($allowedReadPolicies as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'read_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_read_policy">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_read_policy'] ?? $formBoard['read_policy']), 'policy')); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">읽기 그룹 key</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">읽기 그룹 key</span>
                    <input type="text" name="read_group_keys" maxlength="1000" value="<?php echo sr_e($boardGroupKeysValue($formBoard, 'read_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_read_group_keys">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">읽기 최소 레벨</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">읽기 최소 레벨</span>
                    <input type="number" name="read_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($boardField($formBoard, 'read_min_level', '0')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_read_min_level">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_read_min_level'] ?? $formBoard['read_min_level'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">쓰기 정책</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">쓰기 정책</span>
                    <select name="write_policy">
                        <?php foreach ($allowedWritePolicies as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'write_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_write_policy">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_write_policy'] ?? $formBoard['write_policy']), 'policy')); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">쓰기 그룹 key</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">쓰기 그룹 key</span>
                    <input type="text" name="write_group_keys" maxlength="1000" value="<?php echo sr_e($boardGroupKeysValue($formBoard, 'write_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_write_group_keys">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">쓰기 최소 레벨</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">쓰기 최소 레벨</span>
                    <input type="number" name="write_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($boardField($formBoard, 'write_min_level', '0')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_write_min_level">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_write_min_level'] ?? $formBoard['write_min_level'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">댓글 정책</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">댓글 정책</span>
                    <select name="comment_policy">
                        <?php foreach ($allowedCommentPolicies as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'comment_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                        <?php } ?>
                    </select>
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_comment_policy">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_comment_policy'] ?? $formBoard['comment_policy']), 'policy')); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">댓글 그룹 key</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">댓글 그룹 key</span>
                    <input type="text" name="comment_group_keys" maxlength="1000" value="<?php echo sr_e($boardGroupKeysValue($formBoard, 'comment_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_comment_group_keys">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">댓글 최소 레벨</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">댓글 최소 레벨</span>
                    <input type="number" name="comment_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($boardField($formBoard, 'comment_min_level', '0')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_comment_min_level">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_comment_min_level'] ?? $formBoard['comment_min_level'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">이미지 첨부 허용</span></div>
                <div class="af-field">
                    <label class="af-check form-label">
                        <input type="checkbox" name="image_uploads_enabled" value="1" class="form-checkbox"<?php echo (int) $boardField($formBoard, 'image_uploads_enabled', '1') === 1 ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('이미지 첨부 허용'); ?>
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_image_uploads_enabled">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'image_uploads_enabled') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo !empty($formBoard['effective_image_uploads_enabled']) ? '허용' : '차단'; ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">이미지 최대 용량(bytes)</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">이미지 최대 용량(bytes)</span>
                    <input type="number" name="attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_bytes', '2097152')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_attachment_max_bytes">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'attachment_max_bytes') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_attachment_max_bytes'] ?? $formBoard['attachment_max_bytes'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">이미지 최대 개수</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">이미지 최대 개수</span>
                    <input type="number" name="attachment_max_count" min="0" max="10" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_count', '1')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_attachment_max_count">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'attachment_max_count') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_attachment_max_count'] ?? $formBoard['attachment_max_count'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">파일 첨부 허용</span></div>
                <div class="af-field">
                    <label class="af-check form-label">
                        <input type="checkbox" name="file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($boardField($formBoard, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('파일 첨부 허용'); ?>
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_file_uploads_enabled">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_uploads_enabled') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo !empty($formBoard['effective_file_uploads_enabled']) ? '허용' : '차단'; ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">파일 최대 용량(bytes)</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">파일 최대 용량(bytes)</span>
                    <input type="number" name="file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_bytes', '5242880')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_file_attachment_max_bytes">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_attachment_max_bytes') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_file_attachment_max_bytes'] ?? $formBoard['file_attachment_max_bytes'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">파일 최대 개수</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">파일 최대 개수</span>
                    <input type="number" name="file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_count', '3')); ?>">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_file_attachment_max_count">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_attachment_max_count') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e((string) ($formBoard['effective_file_attachment_max_count'] ?? $formBoard['file_attachment_max_count'])); ?></small>
                <?php } ?>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">파일 허용 확장자</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">파일 허용 확장자</span>
                    <input type="text" name="file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($boardArrayValue($formBoard, 'file_allowed_extensions')); ?>" placeholder="pdf, txt, zip">
                    </label>
                <?php if ($communityBoardsPage === 'edit') { ?>
                    <select name="source_file_allowed_extensions">
                        <?php foreach ($sourceLabels as $source => $label) { ?>
                            <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_allowed_extensions') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                        <?php } ?>
                    </select>
                    <small>적용값: <?php echo sr_e(implode(', ', is_array($formBoard['effective_file_allowed_extensions'] ?? null) ? $formBoard['effective_file_allowed_extensions'] : [])); ?></small>
                <?php } ?>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>배너</h2>
                <?php foreach ($publicBannerSettingLabels as $bannerSettingKey => $bannerSettingLabel) { ?>
                    <div class="af-row">
                        <div class="af-label"><span class="form-label"><?php echo sr_e((string) $bannerSettingLabel); ?></span></div>
                        <div class="af-field">
                            <label>
                                <span class="sr-only"><?php echo sr_e((string) $bannerSettingLabel); ?></span>
                                <select name="<?php echo sr_e((string) $bannerSettingKey); ?>">
                                    <option value="0">사용 안 함</option>
                                    <?php foreach ($publicBanners as $publicBanner) { ?>
                                        <option value="<?php echo sr_e((string) $publicBanner['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $bannerSettingKey, '0') === (int) $publicBanner['id'] ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) $publicBanner['title']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <p><small>배너 관리에서 출력 위치를 공용 배너로 저장한 항목만 선택할 수 있습니다.</small></p>
        </section>

        <section class="card">
            <h2>팝업레이어</h2>
                <?php foreach ($publicPopupLayerSettingLabels as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                    <div class="af-row">
                        <div class="af-label"><span class="form-label"><?php echo sr_e((string) $popupLayerSettingLabel); ?></span></div>
                        <div class="af-field">
                            <label>
                                <span class="sr-only"><?php echo sr_e((string) $popupLayerSettingLabel); ?></span>
                                <select name="<?php echo sr_e((string) $popupLayerSettingKey); ?>">
                                    <option value="0">사용 안 함</option>
                                    <?php foreach ($publicPopupLayers as $publicPopupLayer) { ?>
                                        <option value="<?php echo sr_e((string) $publicPopupLayer['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $popupLayerSettingKey, '0') === (int) $publicPopupLayer['id'] ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) $publicPopupLayer['title']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <p><small>팝업레이어 관리에서 노출 대상을 공용 팝업레이어로 저장한 항목만 선택할 수 있습니다.</small></p>
        </section>

        <section class="card">
            <h2>정렬</h2>
            <div class="af-row">
                <div class="af-label"><span class="form-label">정렬 순서</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">정렬 순서</span>
                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'sort_order', '0')); ?>">
                    </label>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardsPage === 'edit' ? '변경' : '생성'; ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
