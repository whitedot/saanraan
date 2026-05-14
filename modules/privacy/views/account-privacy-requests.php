<?php

$pageTitle = '개인정보 처리 요청';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

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

        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-requests')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>
                    <span>요청 유형</span>
                    <select name="request_type">
                        <?php foreach ($allowedTypes as $requestType) { ?>
                            <option value="<?php echo sr_e($requestType); ?>"<?php echo $values['request_type'] === $requestType ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>
                    <span>요청 내용</span>
                    <textarea name="request_message" rows="5" cols="60"><?php echo sr_e($values['request_message']); ?></textarea>
                </label>
            </p>
            <button type="submit">처리 요청 접수</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>유형</th>
                    <th>상태</th>
                    <th>요청일</th>
                    <th>처리일</th>
                    <th>관리자 메모</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []) { ?>
                    <tr>
                        <td colspan="6">요청이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($requests as $request) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $request['id']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $request['status'], 'privacy_request_status')); ?></td>
                        <td><?php echo sr_e((string) $request['created_at']); ?></td>
                        <td><?php echo sr_e((string) ($request['handled_at'] ?? '')); ?></td>
                        <td><?php echo sr_e(sr_admin_privacy_request_list_preview($request['admin_note'] ?? null)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">내 계정</a></p>
    </main>
<?php sr_public_layout_end(); ?>
