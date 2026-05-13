<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = '알림';
if ($notificationAdminPage === 'new') {
    $adminPageTitle = '알림 등록';
} elseif ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = '알림 발송 대기열';
}
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

<p>
    <a href="<?php echo toy_e(toy_url('/admin/notifications')); ?>">알림 목록</a>
    |
    <a href="<?php echo toy_e(toy_url('/admin/notifications/new')); ?>">알림 등록</a>
    |
    <a href="<?php echo toy_e(toy_url('/admin/notification-deliveries')); ?>">발송 대기열</a>
</p>

<?php if ($notificationAdminPage === 'new') { ?>
    <section>
        <h2>알림 등록</h2>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/notifications/create')); ?>">
            <?php echo toy_csrf_field(); ?>
            <p>
                <label>대상<br>
                    <select name="audience">
                        <?php foreach ($allowedAudiences as $audience) { ?>
                            <option value="<?php echo toy_e($audience); ?>"><?php echo toy_e(toy_admin_code_label($audience, 'notification_audience')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>회원 공개 해시<br>
                    <input type="text" name="account_identifier" value="" maxlength="80">
                </label>
            </p>
            <p>
                <label>제목<br>
                    <input type="text" name="title" value="" maxlength="160" required>
                </label>
            </p>
            <p>
                <label>내용<br>
                    <textarea name="body_text" maxlength="5000"></textarea>
                </label>
            </p>
            <p>
                <label>링크 URL (/로 시작하는 내부 URL 또는 http/https URL)<br>
                    <input type="text" name="link_url" value="" maxlength="255">
                </label>
            </p>
            <p>
                <label>외부 수신자<br>
                    <input type="text" name="recipient" value="" maxlength="255">
                </label>
            </p>
            <p>채널</p>
            <?php foreach ($allowedChannels as $channel) { ?>
                <label>
                    <input type="checkbox" name="channels[]" value="<?php echo toy_e($channel); ?>"<?php echo $channel === 'site' ? ' checked' : ''; ?>>
                    <?php echo toy_e(toy_admin_code_label($channel, 'notification_channel')); ?>
                </label><br>
            <?php } ?>
            <p><button type="submit">알림 등록</button></p>
        </form>
    </section>
<?php } elseif ($notificationAdminPage === 'deliveries') { ?>
    <section>
        <h2>발송 대기열</h2>
        <form method="get" action="<?php echo toy_e(toy_url('/admin/notification-deliveries')); ?>">
            <p>
                <label>발송 채널<br>
                    <select name="delivery_channel">
                        <option value=""<?php echo $filters['delivery_channel'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedChannels as $channel) { ?>
                            <option value="<?php echo toy_e($channel); ?>"<?php echo $filters['delivery_channel'] === $channel ? ' selected' : ''; ?>>
                                <?php echo toy_e(toy_admin_code_label($channel, 'notification_channel')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>발송 상태<br>
                    <select name="delivery_status">
                        <option value=""<?php echo $filters['delivery_status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                            <option value="<?php echo toy_e($status); ?>"<?php echo $filters['delivery_status'] === $status ? ' selected' : ''; ?>>
                                <?php echo toy_e(toy_admin_code_label($status, 'delivery_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">조회</button>
        </form>
        <?php if ($deliveries === []) { ?>
            <p>발송 대기열이 비어 있습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>알림</th>
                        <th>채널</th>
                        <th>상태</th>
                        <th>수정일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery) { ?>
                        <tr>
                            <td><?php echo toy_e((string) $delivery['id']); ?></td>
                            <td><?php echo toy_e((string) $delivery['notification_id']); ?></td>
                            <td><?php echo toy_e(toy_admin_code_label((string) $delivery['channel'], 'notification_channel')); ?></td>
                            <td><?php echo toy_e(toy_admin_code_label((string) $delivery['status'], 'delivery_status')); ?></td>
                            <td><?php echo toy_e((string) $delivery['updated_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo toy_e(toy_url('/admin/notification-deliveries/status')); ?>">
                                    <?php echo toy_csrf_field(); ?>
                                    <input type="hidden" name="delivery_id" value="<?php echo toy_e((string) $delivery['id']); ?>">
                                    <p>
                                        <label>상태<br>
                                            <select name="status">
                                                <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                                                    <option value="<?php echo toy_e($status); ?>"<?php echo (string) $delivery['status'] === $status ? ' selected' : ''; ?>>
                                                        <?php echo toy_e(toy_admin_code_label($status, 'delivery_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                    </p>
                                    <button type="submit">저장</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </section>
<?php } else { ?>
    <section>
        <h2>알림 목록</h2>
        <p><a href="<?php echo toy_e(toy_url('/admin/notifications/new')); ?>">새 알림 등록</a></p>
        <form method="get" action="<?php echo toy_e(toy_url('/admin/notifications')); ?>">
            <p>
                <label>대상<br>
                    <select name="audience">
                        <option value=""<?php echo $filters['audience'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedAudiences as $audience) { ?>
                            <option value="<?php echo toy_e($audience); ?>"<?php echo $filters['audience'] === $audience ? ' selected' : ''; ?>>
                                <?php echo toy_e(toy_admin_code_label($audience, 'notification_audience')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">조회</button>
        </form>
        <?php if ($notifications === []) { ?>
            <p>등록된 알림이 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>대상</th>
                        <th>상태</th>
                        <th>생성일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification) { ?>
                        <tr>
                            <td><?php echo toy_e((string) $notification['id']); ?></td>
                            <td><?php echo toy_e(toy_admin_code_label((string) $notification['audience'], 'notification_audience')); ?></td>
                            <td><?php echo toy_e(toy_admin_code_label((string) $notification['status'], 'notification_status')); ?></td>
                            <td><?php echo toy_e((string) $notification['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo toy_e(toy_url('/admin/notifications/delete')); ?>" style="display:inline">
                                    <?php echo toy_csrf_field(); ?>
                                    <input type="hidden" name="notification_id" value="<?php echo toy_e((string) $notification['id']); ?>">
                                    <button type="submit">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </section>
<?php } ?>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
