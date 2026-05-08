<?php

$adminPageTitle = '커뮤니티 게시판';
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
            <label>쓰기 정책<br>
                <select name="write_policy">
                    <?php foreach ($allowedWritePolicies as $policy) { ?>
                        <option value="<?php echo toy_e($policy); ?>"><?php echo toy_e($policy); ?></option>
                    <?php } ?>
                </select>
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
            <label>
                <input type="checkbox" name="image_uploads_enabled" value="1" checked>
                이미지 첨부 허용
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
                <th>상태</th>
                <th>읽기</th>
                <th>쓰기</th>
                <th>댓글</th>
                <th>정렬</th>
                <th>수정</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($boards === []) { ?>
                <tr>
                    <td colspan="9">게시판이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($boards as $board) { ?>
                <tr>
                    <td><?php echo toy_e((string) $board['id']); ?></td>
                    <td><?php echo toy_e((string) $board['board_key']); ?></td>
                    <td colspan="7">
                        <form method="post" action="<?php echo toy_e(toy_url('/admin/community/boards')); ?>">
                            <?php echo toy_csrf_field(); ?>
                            <input type="hidden" name="intent" value="update">
                            <input type="hidden" name="board_id" value="<?php echo toy_e((string) $board['id']); ?>">
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
                            </p>
                            <p>
                                <label>쓰기 정책<br>
                                    <select name="write_policy">
                                        <?php foreach ($allowedWritePolicies as $policy) { ?>
                                            <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === (string) $board['write_policy'] ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </p>
                            <p>
                                <label>댓글 정책<br>
                                    <select name="comment_policy">
                                        <?php foreach ($allowedCommentPolicies as $policy) { ?>
                                            <option value="<?php echo toy_e($policy); ?>"<?php echo $policy === (string) $board['comment_policy'] ? ' selected' : ''; ?>><?php echo toy_e($policy); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="image_uploads_enabled" value="1"<?php echo (int) $board['image_uploads_enabled'] === 1 ? ' checked' : ''; ?>>
                                    이미지 첨부 허용
                                </label>
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
