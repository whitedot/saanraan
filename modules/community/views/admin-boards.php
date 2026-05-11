<?php

$adminPageTitle = '커뮤니티 게시판';
$sourceLabels = [
    'board' => '개별 설정',
    'group' => '그룹 기본값',
];
$boardSettingSource = static function (array $board, string $key): string {
    $sources = is_array($board['setting_sources'] ?? null) ? $board['setting_sources'] : [];
    return (string) ($sources[$key] ?? 'board');
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

<p><a href="<?php echo toy_e(toy_url('/admin/community/board-groups')); ?>">게시판 그룹 관리로 이동</a></p>

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
    <h2>게시판 생성</h2>
    <form method="post" action="<?php echo toy_e(toy_url('/admin/community/boards')); ?>">
        <?php echo toy_csrf_field(); ?>
        <input type="hidden" name="intent" value="create">
        <p>
            <label>게시판 key<br>
                <input type="text" name="board_key" maxlength="60" required>
            </label>
        </p>
        <p>
            <label>게시판 그룹<br>
                <select name="board_group_id">
                    <option value="0">없음</option>
                    <?php foreach ($boardGroups as $boardGroup) { ?>
                        <option value="<?php echo toy_e((string) $boardGroup['id']); ?>"><?php echo toy_e((string) $boardGroup['title']); ?></option>
                    <?php } ?>
                </select>
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
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo toy_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <?php foreach (toy_community_board_group_setting_keys() as $settingKey) { ?>
            <input type="hidden" name="source_<?php echo toy_e($settingKey); ?>" value="board">
        <?php } ?>
        <p>
            <label>읽기 정책<br>
                <select name="read_policy">
                    <?php foreach ($allowedReadPolicies as $policy) { ?>
                        <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>읽기 그룹 key<br>
                <input type="text" name="read_group_keys" maxlength="1000" placeholder="regular_member, vip">
            </label>
        </p>
        <p>
            <label>쓰기 정책<br>
                <select name="write_policy">
                    <?php foreach ($allowedWritePolicies as $policy) { ?>
                        <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>쓰기 그룹 key<br>
                <input type="text" name="write_group_keys" maxlength="1000" placeholder="regular_member, vip">
            </label>
        </p>
        <p>
            <label>댓글 정책<br>
                <select name="comment_policy">
                    <?php foreach ($allowedCommentPolicies as $policy) { ?>
                        <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>댓글 그룹 key<br>
                <input type="text" name="comment_group_keys" maxlength="1000" placeholder="regular_member, vip">
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="image_uploads_enabled" value="1" checked>
                이미지 첨부 허용
            </label>
        </p>
        <p>
            <label>이미지 최대 용량(bytes)<br>
                <input type="number" name="attachment_max_bytes" min="1024" max="10485760" value="2097152">
            </label>
        </p>
        <p>
            <label>이미지 최대 개수<br>
                <input type="number" name="attachment_max_count" min="0" max="10" value="1">
            </label>
        </p>
        <p>
            <label>정렬 순서<br>
                <input type="number" name="sort_order" min="0" max="1000000" value="0">
            </label>
        </p>
        <button type="submit">생성</button>
    </form>
</section>

<section>
    <h2>게시판 목록</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>key</th>
                <th>이름</th>
                <th>그룹</th>
                <th>상태</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($boards === []) { ?>
                <tr>
                    <td colspan="6">게시판이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($boards as $board) { ?>
                <tr>
                    <td><?php echo toy_e((string) $board['id']); ?></td>
                    <td><?php echo toy_e((string) $board['board_key']); ?></td>
                    <td><?php echo toy_e((string) $board['title']); ?></td>
                    <td><?php echo toy_e((string) ($board['board_group_title'] ?? '')); ?></td>
                    <td><?php echo toy_e((string) $board['status']); ?></td>
                    <td>
                        <a href="<?php echo toy_e(toy_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">바로가기</a>
                    </td>
                </tr>
                <tr>
                    <td colspan="6">
                        <form method="post" action="<?php echo toy_e(toy_url('/admin/community/boards')); ?>">
                            <?php echo toy_csrf_field(); ?>
                            <input type="hidden" name="intent" value="update">
                            <input type="hidden" name="board_id" value="<?php echo toy_e((string) $board['id']); ?>">
                            <p>
                                <label>게시판 그룹<br>
                                    <select name="board_group_id">
                                        <option value="0">없음</option>
                                        <?php foreach ($boardGroups as $boardGroup) { ?>
                                            <option value="<?php echo toy_e((string) $boardGroup['id']); ?>"<?php echo (int) ($board['board_group_id'] ?? 0) === (int) $boardGroup['id'] ? ' selected' : ''; ?>><?php echo toy_e((string) $boardGroup['title']); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </p>
                            <p>
                                <label>이름<br>
                                    <input type="text" name="title" maxlength="120" value="<?php echo toy_e((string) $board['title']); ?>" required>
                                </label>
                            </p>
                            <p>
                                <label>설명<br>
                                    <textarea name="description" rows="3" cols="60"><?php echo toy_e((string) ($board['description'] ?? '')); ?></textarea>
                                </label>
                            </p>
                            <p>
                                <label>상태<br>
                                    <select name="status">
                                        <?php foreach ($allowedStatuses as $status) { ?>
                                            <option value="<?php echo toy_e($status); ?>"<?php echo $status === (string) $board['status'] ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </p>
                            <p>
                                <label>읽기 정책<br>
                                    <select name="read_policy">
                                        <?php foreach ($allowedReadPolicies as $policy) { ?>
                                            <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === (string) $board['read_policy'] ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <select name="source_read_policy">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'read_policy') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo toy_e((string) ($board['effective_read_policy'] ?? $board['read_policy'])); ?></small>
                            </p>
                            <p>
                                <label>읽기 그룹 key<br>
                                    <input type="text" name="read_group_keys" maxlength="1000" value="<?php echo toy_e(implode(', ', is_array($board['read_group_keys'] ?? null) ? $board['read_group_keys'] : [])); ?>">
                                </label>
                                <select name="source_read_group_keys">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'read_group_keys') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                            </p>
                            <p>
                                <label>쓰기 정책<br>
                                    <select name="write_policy">
                                        <?php foreach ($allowedWritePolicies as $policy) { ?>
                                            <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === (string) $board['write_policy'] ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <select name="source_write_policy">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'write_policy') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo toy_e((string) ($board['effective_write_policy'] ?? $board['write_policy'])); ?></small>
                            </p>
                            <p>
                                <label>쓰기 그룹 key<br>
                                    <input type="text" name="write_group_keys" maxlength="1000" value="<?php echo toy_e(implode(', ', is_array($board['write_group_keys'] ?? null) ? $board['write_group_keys'] : [])); ?>">
                                </label>
                                <select name="source_write_group_keys">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'write_group_keys') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                            </p>
                            <p>
                                <label>댓글 정책<br>
                                    <select name="comment_policy">
                                        <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                            <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === (string) $board['comment_policy'] ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <select name="source_comment_policy">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'comment_policy') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo toy_e((string) ($board['effective_comment_policy'] ?? $board['comment_policy'])); ?></small>
                            </p>
                            <p>
                                <label>댓글 그룹 key<br>
                                    <input type="text" name="comment_group_keys" maxlength="1000" value="<?php echo toy_e(implode(', ', is_array($board['comment_group_keys'] ?? null) ? $board['comment_group_keys'] : [])); ?>">
                                </label>
                                <select name="source_comment_group_keys">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'comment_group_keys') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="image_uploads_enabled" value="1"<?php echo (int) $board['image_uploads_enabled'] === 1 ? ' checked' : ''; ?>>
                                    이미지 첨부 허용
                                </label>
                                <select name="source_image_uploads_enabled">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'image_uploads_enabled') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo !empty($board['effective_image_uploads_enabled']) ? '허용' : '차단'; ?></small>
                            </p>
                            <p>
                                <label>이미지 최대 용량(bytes)<br>
                                    <input type="number" name="attachment_max_bytes" min="1024" max="10485760" value="<?php echo toy_e((string) ($board['attachment_max_bytes'] ?? 2097152)); ?>">
                                </label>
                                <select name="source_attachment_max_bytes">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'attachment_max_bytes') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo toy_e((string) ($board['effective_attachment_max_bytes'] ?? $board['attachment_max_bytes'])); ?></small>
                            </p>
                            <p>
                                <label>이미지 최대 개수<br>
                                    <input type="number" name="attachment_max_count" min="0" max="10" value="<?php echo toy_e((string) ($board['attachment_max_count'] ?? 1)); ?>">
                                </label>
                                <select name="source_attachment_max_count">
                                    <?php foreach ($sourceLabels as $source => $label) { ?>
                                        <option value="<?php echo toy_e($source); ?>"<?php echo $boardSettingSource($board, 'attachment_max_count') === $source ? ' selected' : ''; ?>><?php echo toy_e($label); ?></option>
                                    <?php } ?>
                                </select>
                                <small>적용값: <?php echo toy_e((string) ($board['effective_attachment_max_count'] ?? $board['attachment_max_count'])); ?></small>
                            </p>
                            <p>
                                <label>정렬 순서<br>
                                    <input type="number" name="sort_order" min="0" max="1000000" value="<?php echo toy_e((string) $board['sort_order']); ?>">
                                </label>
                            </p>
                            <button type="submit">변경</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
