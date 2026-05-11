<?php

$adminPageTitle = '커뮤니티 게시판 그룹';
$settingLabels = [
    'read_policy' => '읽기 정책',
    'write_policy' => '쓰기 정책',
    'comment_policy' => '댓글 정책',
    'read_group_keys' => '읽기 그룹 key',
    'write_group_keys' => '쓰기 그룹 key',
    'comment_group_keys' => '댓글 그룹 key',
    'image_uploads_enabled' => '이미지 첨부 허용',
    'attachment_max_bytes' => '이미지 최대 용량',
    'attachment_max_count' => '이미지 최대 개수',
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
    return implode(', ', toy_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []));
};

include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<p><a href="<?php echo toy_e(toy_url('/admin/community/boards')); ?>">게시판 관리로 이동</a></p>

<?php if ($enabledMemberGroups !== []) { ?>
    <section>
        <h2>사용 가능한 회원 그룹 key</h2>
        <ul>
            <?php foreach ($enabledMemberGroups as $memberGroup) { ?>
                <li>
                    <?php echo toy_e((string) $memberGroup['group_key']); ?>
                    - <?php echo toy_e((string) $memberGroup['title']); ?>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<section>
    <h2>게시판 그룹 생성</h2>
    <form method="post" action="<?php echo toy_e(toy_url('/admin/community/board-groups')); ?>">
        <?php echo toy_csrf_field(); ?>
        <input type="hidden" name="intent" value="create_group">
        <p>
            <label>그룹 key<br>
                <input type="text" name="group_key" maxlength="60" required>
            </label>
        </p>
        <p>
            <label>이름<br>
                <input type="text" name="title" maxlength="120" required>
            </label>
        </p>
        <p>
            <label>설명<br>
                <textarea name="description" rows="3" cols="60"></textarea>
            </label>
        </p>
        <p>
            <label>상태<br>
                <select name="status">
                    <?php foreach ($allowedGroupStatuses as $status) { ?>
                        <option value="<?php echo toy_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>정렬 순서<br>
                <input type="number" name="sort_order" min="0" max="1000000" value="0">
            </label>
        </p>
        <fieldset>
            <legend>그룹 기본 설정</legend>
            <p>
                <label>읽기 정책<br>
                    <select name="group_read_policy">
                        <?php foreach ($allowedReadPolicies as $policy) { ?>
                            <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>읽기 그룹 key<br>
                    <input type="text" name="group_read_group_keys" maxlength="1000" placeholder="regular_member, vip">
                </label>
            </p>
            <p>
                <label>쓰기 정책<br>
                    <select name="group_write_policy">
                        <?php foreach ($allowedWritePolicies as $policy) { ?>
                            <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>쓰기 그룹 key<br>
                    <input type="text" name="group_write_group_keys" maxlength="1000" placeholder="regular_member, vip">
                </label>
            </p>
            <p>
                <label>댓글 정책<br>
                    <select name="group_comment_policy">
                        <?php foreach ($allowedCommentPolicies as $policy) { ?>
                            <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>댓글 그룹 key<br>
                    <input type="text" name="group_comment_group_keys" maxlength="1000" placeholder="regular_member, vip">
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="group_image_uploads_enabled" value="1" checked>
                    이미지 첨부 허용
                </label>
            </p>
            <p>
                <label>이미지 최대 용량(bytes)<br>
                    <input type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="2097152">
                </label>
            </p>
            <p>
                <label>이미지 최대 개수<br>
                    <input type="number" name="group_attachment_max_count" min="0" max="10" value="1">
                </label>
            </p>
        </fieldset>
        <button type="submit">그룹 생성</button>
    </form>
</section>

<section>
    <h2>게시판 그룹 목록</h2>
    <?php if ($boardGroups === []) { ?>
        <p>게시판 그룹이 없습니다.</p>
    <?php } else { ?>
        <?php foreach ($boardGroups as $boardGroup) { ?>
            <?php $groupSettings = is_array($boardGroupSettings[(int) $boardGroup['id']] ?? null) ? $boardGroupSettings[(int) $boardGroup['id']] : []; ?>
            <form method="post" action="<?php echo toy_e(toy_url('/admin/community/board-groups')); ?>">
                <?php echo toy_csrf_field(); ?>
                <input type="hidden" name="intent" value="update_group">
                <input type="hidden" name="group_id" value="<?php echo toy_e((string) $boardGroup['id']); ?>">
                <h3><?php echo toy_e((string) $boardGroup['title']); ?> (<?php echo toy_e((string) $boardGroup['group_key']); ?>)</h3>
                <p>게시판 수: <?php echo toy_e((string) ($boardGroup['board_count'] ?? 0)); ?></p>
                <p>
                    <label>이름<br>
                        <input type="text" name="title" maxlength="120" value="<?php echo toy_e((string) $boardGroup['title']); ?>" required>
                    </label>
                </p>
                <p>
                    <label>설명<br>
                        <textarea name="description" rows="3" cols="60"><?php echo toy_e((string) ($boardGroup['description'] ?? '')); ?></textarea>
                    </label>
                </p>
                <p>
                    <label>상태<br>
                        <select name="status">
                            <?php foreach ($allowedGroupStatuses as $status) { ?>
                                <option value="<?php echo toy_e($status); ?>"<?php echo $status === (string) $boardGroup['status'] ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>정렬 순서<br>
                        <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo toy_e((string) $boardGroup['sort_order']); ?>">
                    </label>
                </p>
                <fieldset>
                    <legend>그룹 기본 설정</legend>
                    <p>
                        <label>읽기 정책<br>
                            <select name="group_read_policy">
                                <?php foreach ($allowedReadPolicies as $policy) { ?>
                                    <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === $groupSettingValue($groupSettings, 'read_policy', 'public') ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label>읽기 그룹 key<br>
                            <input type="text" name="group_read_group_keys" maxlength="1000" value="<?php echo toy_e($groupKeysSettingValue($groupSettings, 'read_group_keys')); ?>">
                        </label>
                    </p>
                    <p>
                        <label>쓰기 정책<br>
                            <select name="group_write_policy">
                                <?php foreach ($allowedWritePolicies as $policy) { ?>
                                    <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === $groupSettingValue($groupSettings, 'write_policy', 'member') ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label>쓰기 그룹 key<br>
                            <input type="text" name="group_write_group_keys" maxlength="1000" value="<?php echo toy_e($groupKeysSettingValue($groupSettings, 'write_group_keys')); ?>">
                        </label>
                    </p>
                    <p>
                        <label>댓글 정책<br>
                            <select name="group_comment_policy">
                                <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                    <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === $groupSettingValue($groupSettings, 'comment_policy', 'member') ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label>댓글 그룹 key<br>
                            <input type="text" name="group_comment_group_keys" maxlength="1000" value="<?php echo toy_e($groupKeysSettingValue($groupSettings, 'comment_group_keys')); ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="group_image_uploads_enabled" value="1"<?php echo in_array($groupSettingValue($groupSettings, 'image_uploads_enabled', '1'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                            이미지 첨부 허용
                        </label>
                    </p>
                    <p>
                        <label>이미지 최대 용량(bytes)<br>
                            <input type="number" name="group_attachment_max_bytes" min="1024" max="10485760" value="<?php echo toy_e($groupSettingValue($groupSettings, 'attachment_max_bytes', '2097152')); ?>">
                        </label>
                    </p>
                    <p>
                        <label>이미지 최대 개수<br>
                            <input type="number" name="group_attachment_max_count" min="0" max="10" value="<?php echo toy_e($groupSettingValue($groupSettings, 'attachment_max_count', '1')); ?>">
                        </label>
                    </p>
                </fieldset>
                <fieldset>
                    <legend>같은 그룹 게시판에 적용</legend>
                    <?php foreach ($settingLabels as $settingKey => $settingLabel) { ?>
                        <label>
                            <input type="checkbox" name="apply_setting_keys[]" value="<?php echo toy_e($settingKey); ?>">
                            <?php echo toy_e($settingLabel); ?>
                        </label><br>
                    <?php } ?>
                </fieldset>
                <button type="submit">그룹 변경</button>
            </form>
        <?php } ?>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
