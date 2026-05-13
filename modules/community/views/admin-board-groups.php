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
    <a href="<?php echo sr_e(sr_url('/admin/community/settings')); ?>">커뮤니티 설정</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>">게시판 그룹 목록</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>">게시판 그룹 생성</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>">게시판 관리</a>
</p>

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
    <section>
        <h2>게시판 그룹 목록</h2>
        <p><a href="<?php echo sr_e(sr_url('/admin/community/board-groups/new')); ?>">새 게시판 그룹 추가</a></p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>key</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>게시판 수</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($boardGroups === []) { ?>
                    <tr>
                        <td colspan="6">게시판 그룹이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($boardGroups as $boardGroup) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $boardGroup['id']); ?></td>
                        <td><?php echo sr_e((string) $boardGroup['group_key']); ?></td>
                        <td><?php echo sr_e((string) $boardGroup['title']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $boardGroup['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) ($boardGroup['board_count'] ?? 0)); ?></td>
                        <td>
                            <a href="<?php echo sr_e(sr_url('/admin/community/board-groups/edit?id=' . rawurlencode((string) $boardGroup['id']))); ?>">수정</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
<?php } else { ?>
    <section>
        <h2><?php echo $communityBoardGroupsPage === 'edit' ? '게시판 그룹 수정' : '게시판 그룹 생성'; ?></h2>
        <form method="post" action="<?php echo sr_e(sr_url($communityBoardGroupsPage === 'edit' ? '/admin/community/board-groups/update' : '/admin/community/board-groups/create')); ?>">
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <input type="hidden" name="group_id" value="<?php echo sr_e((string) $formBoardGroup['id']); ?>">
                <p>그룹 key: <?php echo sr_e((string) $formBoardGroup['group_key']); ?></p>
            <?php } else { ?>
                <p>
                    <label>그룹 key<br>
                        <input type="text" name="group_key" maxlength="60" value="<?php echo sr_e($groupField($formBoardGroup, 'group_key')); ?>" required>
                    </label>
                </p>
            <?php } ?>
            <p>
                <label>이름<br>
                    <input type="text" name="title" maxlength="120" value="<?php echo sr_e($groupField($formBoardGroup, 'title')); ?>" required>
                </label>
            </p>
            <p>
                <label>설명<br>
                    <textarea name="description" rows="3" cols="60"><?php echo sr_e($groupField($formBoardGroup, 'description')); ?></textarea>
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === $groupField($formBoardGroup, 'status', 'enabled') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>정렬 순서<br>
                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e($groupField($formBoardGroup, 'sort_order', '0')); ?>">
                </label>
            </p>
            <fieldset>
                <legend>그룹 기본 설정</legend>
                <p>
                    <label>읽기 정책<br>
                        <select name="group_read_policy">
                            <?php foreach ($allowedReadPolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'read_policy', 'public') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>읽기 그룹 key<br>
                        <input type="text" name="group_read_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'read_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                </p>
                <p>
                    <label>읽기 최소 레벨<br>
                        <input type="number" name="group_read_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'read_min_level', '0')); ?>">
                    </label>
                </p>
                <p>
                    <label>쓰기 정책<br>
                        <select name="group_write_policy">
                            <?php foreach ($allowedWritePolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'write_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>쓰기 그룹 key<br>
                        <input type="text" name="group_write_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'write_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                </p>
                <p>
                    <label>쓰기 최소 레벨<br>
                        <input type="number" name="group_write_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'write_min_level', '0')); ?>">
                    </label>
                </p>
                <p>
                    <label>댓글 정책<br>
                        <select name="group_comment_policy">
                            <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === $groupSettingValue($formGroupSettings, 'comment_policy', 'member') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($policy, 'policy')); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>댓글 그룹 key<br>
                        <input type="text" name="group_comment_group_keys" maxlength="1000" value="<?php echo sr_e($groupKeysSettingValue($formGroupSettings, 'comment_group_keys')); ?>" placeholder="regular_member, vip">
                    </label>
                </p>
                <p>
                    <label>댓글 최소 레벨<br>
                        <input type="number" name="group_comment_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'comment_min_level', '0')); ?>">
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="group_image_uploads_enabled" value="1"<?php echo in_array($groupSettingValue($formGroupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        이미지 첨부 허용
                    </label>
                </p>
                <p>
                    <label>이미지 최대 용량(bytes)<br>
                        <input type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_bytes', '2097152')); ?>">
                    </label>
                </p>
                <p>
                    <label>이미지 최대 개수<br>
                        <input type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'attachment_max_count', '1')); ?>">
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="group_file_uploads_enabled" value="1"<?php echo in_array($groupSettingValue($formGroupSettings, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        파일 첨부 허용
                    </label>
                </p>
                <p>
                    <label>파일 최대 용량(bytes)<br>
                        <input type="number" name="group_file_attachment_max_bytes" min="1024" max="20971520" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_bytes', '5242880')); ?>">
                    </label>
                </p>
                <p>
                    <label>파일 최대 개수<br>
                        <input type="number" name="group_file_attachment_max_count" min="0" max="5" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_attachment_max_count', '3')); ?>">
                    </label>
                </p>
                <p>
                    <label>파일 허용 확장자<br>
                        <input type="text" name="group_file_allowed_extensions" maxlength="1000" value="<?php echo sr_e($groupSettingValue($formGroupSettings, 'file_allowed_extensions', 'pdf,txt,csv,zip,doc,docx,xls,xlsx,ppt,pptx,hwp')); ?>" placeholder="pdf, txt, zip">
                    </label>
                </p>
            </fieldset>
            <?php if ($communityBoardGroupsPage === 'edit') { ?>
                <fieldset>
                    <legend>같은 그룹 게시판에 적용</legend>
                    <?php foreach ($settingLabels as $settingKey => $settingLabel) { ?>
                        <label>
                            <input type="checkbox" name="apply_setting_keys[]" value="<?php echo sr_e($settingKey); ?>">
                            <?php echo sr_e($settingLabel); ?>
                        </label><br>
                    <?php } ?>
                </fieldset>
            <?php } ?>
            <button type="submit"><?php echo $communityBoardGroupsPage === 'edit' ? '그룹 변경' : '그룹 생성'; ?></button>
        </form>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
