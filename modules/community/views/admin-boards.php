<?php

$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
$adminPageTitle = '커뮤니티 게시판';
$adminPageSubtitle = '게시판 상태와 그룹을 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = 'admin-page-community-board-list admin-ui-scope';
if ($communityBoardsPage === 'new') {
    $adminPageTitle = '게시판 생성';
    $adminPageSubtitle = '커뮤니티에서 사용할 게시판과 접근 정책을 생성합니다.';
    $adminContainerClass = 'admin-page-community-board-form admin-ui-scope';
} elseif ($communityBoardsPage === 'edit') {
    $adminPageTitle = '게시판 수정';
    $adminPageSubtitle = '게시판 기본 정보와 접근, 표시, 자산 정책을 수정합니다.';
    $adminContainerClass = 'admin-page-community-board-form admin-ui-scope';
}
$boardListFilters = isset($boardListFilters) && is_array($boardListFilters) ? $boardListFilters : ['status' => '', 'group_id' => 0, 'field' => 'all', 'q' => ''];
$boardStatusCounts = isset($boardStatusCounts) && is_array($boardStatusCounts) ? $boardStatusCounts : [];
$totalBoards = (int) ($boardStatusCounts['total'] ?? count($boards ?? []));

$sourceLabels = [
    'board' => '개별 설정',
    'group' => '그룹 기본값',
];
$boardSettingSource = static function (array $board, string $key): string {
    $sources = is_array($board['setting_sources'] ?? null) ? $board['setting_sources'] : [];
    return (string) ($sources[$key] ?? 'board');
};
$boardArrayValue = static function (array $board, string $key): string {
    return implode(', ', is_array($board[$key] ?? null) ? $board[$key] : []);
};
$boardField = static function (array $board, string $key, string $default = ''): string {
    return (string) ($board[$key] ?? $default);
};
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$communityLevelSelectHtml = static function (string $id, string $name, int $selectedLevel): string {
    $selectedLevel = sr_community_normalize_level_value($selectedLevel);
    $html = '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select">';
    for ($levelValue = 0; $levelValue <= sr_community_max_level_value(); $levelValue++) {
        $html .= '<option value="' . sr_e((string) $levelValue) . '"' . ($selectedLevel === $levelValue ? ' selected' : '') . '>';
        $html .= sr_e((string) $levelValue);
        $html .= '</option>';
    }

    return $html . '</select>';
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
    'asset_policy_source' => 'global',
    'post_reward_enabled' => !empty($settings['post_reward_enabled']) ? '1' : '0',
    'post_reward_asset_module' => (string) ($settings['post_reward_asset_module'] ?? 'point'),
    'post_reward_amount' => (string) ($settings['post_reward_amount'] ?? 0),
    'comment_reward_enabled' => !empty($settings['comment_reward_enabled']) ? '1' : '0',
    'comment_reward_asset_module' => (string) ($settings['comment_reward_asset_module'] ?? 'point'),
    'comment_reward_amount' => (string) ($settings['comment_reward_amount'] ?? 0),
    'write_charge_enabled' => !empty($settings['write_charge_enabled']) ? '1' : '0',
    'write_charge_asset_module' => (string) ($settings['write_charge_asset_module'] ?? 'point'),
    'write_charge_amount' => (string) ($settings['write_charge_amount'] ?? 0),
    'comment_charge_enabled' => !empty($settings['comment_charge_enabled']) ? '1' : '0',
    'comment_charge_asset_module' => (string) ($settings['comment_charge_asset_module'] ?? 'point'),
    'comment_charge_amount' => (string) ($settings['comment_charge_amount'] ?? 0),
    'paid_read_enabled' => !empty($settings['paid_read_enabled']) ? '1' : '0',
    'paid_read_asset_module' => (string) ($settings['paid_read_asset_module'] ?? 'point'),
    'paid_read_amount' => (string) ($settings['paid_read_amount'] ?? 0),
    'paid_read_charge_policy' => (string) ($settings['paid_read_charge_policy'] ?? 'once'),
    'paid_attachment_download_enabled' => !empty($settings['paid_attachment_download_enabled']) ? '1' : '0',
    'paid_attachment_download_asset_module' => (string) ($settings['paid_attachment_download_asset_module'] ?? 'point'),
    'paid_attachment_download_amount' => (string) ($settings['paid_attachment_download_amount'] ?? 0),
    'paid_attachment_download_charge_policy' => (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once'),
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityBoardsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총게시판 <strong><?php echo sr_e((string) $totalBoards); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($boardStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=disabled')); ?>" class="admin-summary-meta">중지 <?php echo sr_e((string) ($boardStatusCounts['disabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards?status=archived')); ?>" class="admin-summary-meta">보관 <?php echo sr_e((string) ($boardStatusCounts['archived'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="admin-filter admin-community-board-filter ui-form-theme">
        <div class="admin-filter-grid admin-community-board-search-grid">
            <div class="admin-filter-field admin-community-board-filter-status">
                <label for="community_admin_boards_status_filter" class="admin-filter-label">상태</label>
                <select id="community_admin_boards_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($boardListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($boardListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-group">
                <label for="community_admin_boards_group_filter" class="admin-filter-label">게시판 그룹</label>
                <select id="community_admin_boards_group_filter" name="group_id" class="form-select admin-filter-input">
                    <option value="0"<?php echo (int) ($boardListFilters['group_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($boardGroups as $boardGroup) { ?>
                        <option value="<?php echo sr_e((string) $boardGroup['id']); ?>"<?php echo (int) ($boardListFilters['group_id'] ?? 0) === (int) $boardGroup['id'] ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $boardGroup['title']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-field">
                <label for="community_admin_boards_field" class="admin-filter-label">검색 조건</label>
                <select id="community_admin_boards_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'key' => 'key', 'title' => '이름', 'group' => '그룹'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($boardListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-board-filter-keyword">
                <label for="community_admin_boards_q" class="admin-filter-label">검색어</label>
                <input id="community_admin_boards_q" type="search" name="q" value="<?php echo sr_e((string) ($boardListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="key, 이름, 그룹">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">게시판 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/community/boards/new')); ?>" class="btn btn-sm btn-solid-light">새 게시판 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-community-board-table">
            <caption class="sr-only">커뮤니티 게시판 목록</caption>
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
                        <td colspan="7" class="admin-empty-state">게시판이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($boards as $board) { ?>
                    <?php
                    $boardStatus = (string) $board['status'];
                    $statusClass = match ($boardStatus) {
                        'enabled' => 'is-normal',
                        'disabled' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $board['id']); ?></td>
                        <td class="admin-table-nowrap admin-community-board-key-cell"><?php echo sr_e((string) $board['board_key']); ?></td>
                        <td class="admin-table-break admin-community-board-title-cell"><?php echo sr_e((string) $board['title']); ?></td>
                        <td class="admin-table-break admin-community-board-group-cell"><?php echo sr_e((string) ($board['board_group_title'] ?? '')); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($boardStatus, 'content_status')); ?></span></td>
                        <td class="admin-table-nowrap">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="update_skin">
                                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $board['id']); ?>">
                                <select name="skin_key" class="form-select">
                                    <?php foreach ($communitySkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($board['skin_key'] ?? 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-solid-light">저장</button>
                            </form>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>" class="btn btn-sm btn-solid-light">바로가기</a>
                                <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) $board['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url($communityBoardsPage === 'edit' ? '/admin/community/boards/update' : '/admin/community/boards/create')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $communityBoardsPage === 'edit' ? '게시판 수정' : '게시판 생성'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardsPage === 'edit') { ?>
                <input type="hidden" name="board_id" value="<?php echo sr_e((string) $formBoard['id']); ?>">
                <div class="admin-form-row">
                    <span class="form-label">게시판 key</span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e((string) $formBoard['board_key']); ?></code>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="community_admin_boards_board_key">게시판 key</label>
                    <div class="admin-form-field">
                        <input id="community_admin_boards_board_key" type="text" name="board_key" maxlength="60" value="<?php echo sr_e($boardField($formBoard, 'board_key')); ?>" class="form-input" required>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_board_group_id">게시판 그룹</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_board_group_id" name="board_group_id" class="form-select">
                                            <option value="0">없음</option>
                                            <?php foreach ($boardGroups as $boardGroup) { ?>
                                                <option value="<?php echo sr_e((string) $boardGroup['id']); ?>"<?php echo (int) $boardField($formBoard, 'board_group_id', '0') === (int) $boardGroup['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $boardGroup['title']); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_title">이름</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_title" type="text" name="title" maxlength="120" value="<?php echo sr_e($boardField($formBoard, 'title')); ?>" class="form-input form-control-full" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="community_admin_boards_description" name="description" rows="3" cols="60" class="form-textarea"><?php echo sr_e($boardField($formBoard, 'description')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_status">상태</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === $boardField($formBoard, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_skin_key">게시판 스킨</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_skin_key" name="skin_key" class="form-select">
                                            <?php foreach ($communitySkinOptions as $skinKey => $skinOption) { ?>
                                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $boardField($formBoard, 'skin_key', 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>

            <?php foreach (sr_community_board_group_setting_keys() as $settingKey) { ?>
                <?php if ($communityBoardsPage === 'new') { ?>
                    <input type="hidden" name="source_<?php echo sr_e($settingKey); ?>" value="board">
                <?php } ?>
            <?php } ?>
        </section>

        <section class="admin-card card">
            <h2>접근 정책</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_read_policy">읽기 정책</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_read_policy" name="read_policy" class="form-select">
                                            <?php foreach ($allowedReadPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'read_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                                        </select>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_read_policy" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_read_policy'] ?? $formBoard['read_policy']), 'policy')); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_read_group_keys">읽기 회원 그룹</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_read_group_keys', 'read_group_keys', is_array($formBoard['read_group_keys'] ?? null) ? $formBoard['read_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_read_group_keys" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_read_min_level">읽기 최소 레벨</label>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_read_min_level', 'read_min_level', (int) $boardField($formBoard, 'read_min_level', '0')); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_read_min_level" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'read_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_read_min_level'] ?? $formBoard['read_min_level'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_write_policy">쓰기 정책</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_write_policy" name="write_policy" class="form-select">
                                            <?php foreach ($allowedWritePolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'write_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                                        </select>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_write_policy" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_write_policy'] ?? $formBoard['write_policy']), 'policy')); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_write_group_keys">쓰기 회원 그룹</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_write_group_keys', 'write_group_keys', is_array($formBoard['write_group_keys'] ?? null) ? $formBoard['write_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_write_group_keys" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_write_min_level">쓰기 최소 레벨</label>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_write_min_level', 'write_min_level', (int) $boardField($formBoard, 'write_min_level', '0')); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_write_min_level" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'write_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_write_min_level'] ?? $formBoard['write_min_level'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_comment_policy">댓글 정책</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_comment_policy" name="comment_policy" class="form-select">
                                            <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $boardField($formBoard, 'comment_policy') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                                            <?php } ?>
                                        </select>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_comment_policy" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_policy') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e(sr_admin_code_label((string) ($formBoard['effective_comment_policy'] ?? $formBoard['comment_policy']), 'policy')); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_comment_group_keys">댓글 회원 그룹</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('community_admin_boards_comment_group_keys', 'comment_group_keys', is_array($formBoard['comment_group_keys'] ?? null) ? $formBoard['comment_group_keys'] : [], $enabledMemberGroups); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_comment_group_keys" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_group_keys') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_comment_min_level">댓글 최소 레벨</label>
                <div class="admin-form-field">
                    <?php echo $communityLevelSelectHtml('community_admin_boards_comment_min_level', 'comment_min_level', (int) $boardField($formBoard, 'comment_min_level', '0')); ?>
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_comment_min_level" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'comment_min_level') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_comment_min_level'] ?? $formBoard['comment_min_level'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">이미지 첨부 허용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_boards_image_uploads_enabled">
                                            <input id="modules_community_admin_boards_image_uploads_enabled" type="checkbox" name="image_uploads_enabled" value="1" class="form-checkbox"<?php echo (int) $boardField($formBoard, 'image_uploads_enabled', '1') === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('이미지 첨부 허용'); ?>
                                        </label>
                                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_image_uploads_enabled" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'image_uploads_enabled') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo !empty($formBoard['effective_image_uploads_enabled']) ? '허용' : '차단'; ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_attachment_max_bytes">이미지 최대 용량(bytes)</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_attachment_max_bytes" type="number" name="attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_bytes', '2097152')); ?>" class="form-input">
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_attachment_max_bytes" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'attachment_max_bytes') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_attachment_max_bytes'] ?? $formBoard['attachment_max_bytes'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_attachment_max_count">이미지 최대 개수</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_attachment_max_count" type="number" name="attachment_max_count" min="0" max="10" value="<?php echo sr_e($boardField($formBoard, 'attachment_max_count', '1')); ?>" class="form-input">
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_attachment_max_count" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'attachment_max_count') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_attachment_max_count'] ?? $formBoard['attachment_max_count'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">파일 첨부 허용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_boards_file_uploads_enabled">
                                            <input id="modules_community_admin_boards_file_uploads_enabled" type="checkbox" name="file_uploads_enabled" value="1" class="form-checkbox"<?php echo in_array($boardField($formBoard, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('파일 첨부 허용'); ?>
                                        </label>
                                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_file_uploads_enabled" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_uploads_enabled') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo !empty($formBoard['effective_file_uploads_enabled']) ? '허용' : '차단'; ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_file_attachment_max_bytes">파일 최대 용량(bytes)</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_file_attachment_max_bytes" type="number" name="file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_bytes', '5242880')); ?>" class="form-input">
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_file_attachment_max_bytes" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_attachment_max_bytes') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_file_attachment_max_bytes'] ?? $formBoard['file_attachment_max_bytes'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_file_attachment_max_count">파일 최대 개수</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_file_attachment_max_count" type="number" name="file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($boardField($formBoard, 'file_attachment_max_count', '3')); ?>" class="form-input">
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_file_attachment_max_count" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_attachment_max_count') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e((string) ($formBoard['effective_file_attachment_max_count'] ?? $formBoard['file_attachment_max_count'])); ?></small>
                                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_file_allowed_extensions">파일 허용 확장자</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_file_allowed_extensions" type="text" name="file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($boardArrayValue($formBoard, 'file_allowed_extensions')); ?>" class="form-input form-control-full" placeholder="pdf, txt, zip">
                    <?php if ($communityBoardsPage === 'edit') { ?>
                                        <select name="source_file_allowed_extensions" class="form-select">
                                            <?php foreach ($sourceLabels as $source => $label) { ?>
                                                <option value="<?php echo sr_e($source); ?>"<?php echo $boardSettingSource($formBoard, 'file_allowed_extensions') === $source ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                            <?php } ?>
                                        </select>
                                        <small>적용값: <?php echo sr_e(implode(', ', is_array($formBoard['effective_file_allowed_extensions'] ?? null) ? $formBoard['effective_file_allowed_extensions'] : [])); ?></small>
                                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2>
                <span>배너</span>
                <?php if (sr_module_enabled($pdo, 'banner')) { ?>
                    <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-sm btn-solid-light">배너 관리</a>
                <?php } ?>
            </h2>
                <p>배너 관리에서 출력 위치를 공용 배너로 저장한 항목만 선택할 수 있습니다.</p>
                <?php foreach ($publicBannerSettingLabels as $bannerSettingKey => $bannerSettingLabel) { ?>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('community_board_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e('community_board_' . (string) $bannerSettingKey); ?>" name="<?php echo sr_e((string) $bannerSettingKey); ?>" class="form-select form-control-full">
                                                                <option value="0">사용 안 함</option>
                                                                <?php foreach ($publicBanners as $publicBanner) { ?>
                                                                    <option value="<?php echo sr_e((string) $publicBanner['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $bannerSettingKey, '0') === (int) $publicBanner['id'] ? ' selected' : ''; ?>>
                                                                        <?php echo sr_e((string) $publicBanner['title']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                        </div>
                    </div>
                <?php } ?>
        </section>

        <section class="admin-card card">
            <h2>
                <span>팝업레이어</span>
                <?php if (sr_module_enabled($pdo, 'popup_layer')) { ?>
                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-sm btn-solid-light">팝업레이어 관리</a>
                <?php } ?>
            </h2>
                <p>팝업레이어 관리에서 노출 대상을 공용 팝업레이어로 저장한 항목만 선택할 수 있습니다.</p>
                <?php foreach ($publicPopupLayerSettingLabels as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('community_board_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e('community_board_' . (string) $popupLayerSettingKey); ?>" name="<?php echo sr_e((string) $popupLayerSettingKey); ?>" class="form-select form-control-full">
                                                                <option value="0">사용 안 함</option>
                                                                <?php foreach ($publicPopupLayers as $publicPopupLayer) { ?>
                                                                    <option value="<?php echo sr_e((string) $publicPopupLayer['id']); ?>"<?php echo (int) $boardField($formBoard, (string) $popupLayerSettingKey, '0') === (int) $publicPopupLayer['id'] ? ' selected' : ''; ?>>
                                                                        <?php echo sr_e((string) $publicPopupLayer['title']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                        </div>
                    </div>
                <?php } ?>
        </section>

        <section class="admin-card card">
            <h2>회원 자산</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_asset_policy_source">적용 방식</label>
                <div class="admin-form-field">
                    <select id="community_admin_boards_asset_policy_source" name="asset_policy_source" class="form-select">
                        <option value="global"<?php echo $boardField($formBoard, 'asset_policy_source', 'global') === 'global' ? ' selected' : ''; ?>>커뮤니티 전역 설정</option>
                        <option value="board"<?php echo $boardField($formBoard, 'asset_policy_source', 'global') === 'board' ? ' selected' : ''; ?>>게시판 개별 설정</option>
                    </select>
                </div>
            </div>
            <div class="admin-form-grid">
                <?php foreach ([
                    'post_reward' => '게시글 적립',
                    'comment_reward' => '댓글 적립',
                    'write_charge' => '글쓰기 차감',
                    'comment_charge' => '댓글 차감',
                    'paid_read' => '유료 열람',
                    'paid_attachment_download' => '첨부 다운로드 차감',
                ] as $assetPrefix => $assetLabel) { ?>
                    <?php $assetEnabledId = 'community_board_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $assetPrefix) . '_enabled'; ?>
                    <?php $usesCompositeAsset = sr_community_asset_prefix_uses_composite((string) $assetPrefix); ?>
                    <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($boardField($formBoard, $assetPrefix . '_asset_module', 'point')); ?>
                    <div class="admin-form-row">
                        <span class="form-label"><?php echo sr_e($assetLabel); ?></span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                                            <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e($assetPrefix); ?>_enabled" value="1" class="form-checkbox"<?php echo in_array($boardField($formBoard, $assetPrefix . '_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                                                            <?php echo sr_admin_choice_label_html($assetLabel . ' 사용'); ?>
                                                        </label>
                                                        <?php if ($usesCompositeAsset) { ?>
                                                            <?php echo sr_admin_checkbox_list_html('community_board_' . (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_asset_module', $assetModuleChoiceOptions, $selectedAssetModules, '활성 자산 모듈 없음'); ?>
                                                        <?php } else { ?>
                                                            <select name="<?php echo sr_e($assetPrefix); ?>_asset_module" class="form-select">
                                                                <?php if ($assetModuleOptions === []) { ?>
                                                                    <option value="">활성 자산 모듈 없음</option>
                                                                <?php } ?>
                                                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                                    <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo $boardField($formBoard, $assetPrefix . '_asset_module', 'point') === (string) $assetModule ? ' selected' : ''; ?>>
                                                                        <?php echo sr_e((string) $assetOption['label']); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        <?php } ?>
                                                        <?php if ($usesCompositeAsset) { ?>
                                                            <p class="admin-form-help">여러 자산을 선택하면 포인트, 적립금, 예치금 순서로 차감합니다.</p>
                                                        <?php } ?>
                                                        <input type="number" name="<?php echo sr_e($assetPrefix); ?>_amount" min="0" max="999999999" value="<?php echo sr_e($boardField($formBoard, $assetPrefix . '_amount', '0')); ?>" class="form-input">
                                                        <?php if ($assetPrefix === 'paid_read') { ?>
                                                            <select name="paid_read_charge_policy" class="form-select">
                                                                <option value="once"<?php echo $boardField($formBoard, 'paid_read_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>>최초 1회</option>
                                                                <option value="every_view"<?php echo $boardField($formBoard, 'paid_read_charge_policy', 'once') === 'every_view' ? ' selected' : ''; ?>>매 열람</option>
                                                            </select>
                                                        <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                                                            <select name="paid_attachment_download_charge_policy" class="form-select">
                                                                <option value="once"<?php echo $boardField($formBoard, 'paid_attachment_download_charge_policy', 'once') === 'once' ? ' selected' : ''; ?>>최초 1회</option>
                                                                <option value="every_download"<?php echo $boardField($formBoard, 'paid_attachment_download_charge_policy', 'once') === 'every_download' ? ' selected' : ''; ?>>매 다운로드</option>
                                                            </select>
                                                        <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section class="admin-card card">
            <h2>정렬</h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_admin_boards_sort_order">정렬 순서</label>
                <div class="admin-form-field">
                    <input id="community_admin_boards_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($boardField($formBoard, 'sort_order', '0')); ?>" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary"><?php echo $communityBoardsPage === 'edit' ? '변경' : '생성'; ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
