<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = '알림';
if ($notificationAdminPage === 'new') {
    $adminPageTitle = '알림 등록';
} elseif ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = '알림 발송 대기열';
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

<div class="member-summary">
    <div class="member-summary-links">
        <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-surface-default-soft">알림 목록</a>
        <a href="<?php echo sr_e(sr_url('/admin/notifications/new')); ?>" class="btn btn-surface-default-soft">알림 등록</a>
        <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="btn btn-surface-default-soft">발송 대기열</a>
    </div>
</div>

<?php if ($notificationAdminPage === 'new') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/create')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
        <section class="card">
            <h2>알림 등록</h2>
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>대상<br>
                    <select name="audience">
                        <?php foreach ($allowedAudiences as $audience) { ?>
                            <option value="<?php echo sr_e($audience); ?>"><?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?></option>
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
                    <input type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>"<?php echo $channel === 'site' ? ' checked' : ''; ?>>
                    <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                </label><br>
            <?php } ?>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary">알림 등록</button>
        </div>
    </form>
<?php } elseif ($notificationAdminPage === 'deliveries') { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">발송 대기열</h2>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>">
            <div class="member-search-card">
                <div class="member-search-fields community-search-fields-compact">
                    <div class="member-field">
                        <label for="delivery_channel" class="member-field-label">발송 채널</label>
                        <select name="delivery_channel" id="delivery_channel" class="form-select member-field-input">
                        <option value=""<?php echo $filters['delivery_channel'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedChannels as $channel) { ?>
                            <option value="<?php echo sr_e($channel); ?>"<?php echo $filters['delivery_channel'] === $channel ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>
                    <div class="member-field">
                        <label for="delivery_status" class="member-field-label">발송 상태</label>
                        <select name="delivery_status" id="delivery_status" class="form-select member-field-input">
                        <option value=""<?php echo $filters['delivery_status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $filters['delivery_status'] === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-solid-primary member-search-submit">조회</button>
                </div>
            </div>
        </form>
        <?php if ($deliveries === []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <tbody>
                    <tr><td class="admin-dashboard-empty">발송 대기열이 비어 있습니다.</td></tr>
                </tbody>
            </table>
            </div>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>ID</th>
                        <th>알림</th>
                        <th>채널</th>
                        <th>상태</th>
                        <th>수정일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $delivery['id']); ?></td>
                            <td><?php echo sr_e((string) $delivery['notification_id']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $delivery['channel'], 'notification_channel')); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $delivery['status'], 'delivery_status')); ?></td>
                            <td><?php echo sr_e((string) $delivery['updated_at']); ?></td>
                            <td class="member-cell-manage">
                                <div class="member-manage">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/notification-deliveries/status')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="delivery_id" value="<?php echo sr_e((string) $delivery['id']); ?>">
                                    <label class="sr-only" for="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>">상태</label>
                                    <select name="status" id="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>" class="form-select">
                                                <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) $delivery['status'] === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                                                    </option>
                                                <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-surface-default-soft">저장</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } else { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">알림 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/notifications/new')); ?>" class="btn btn-sm btn-surface-default-soft">새 알림 등록</a>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>">
            <div class="member-search-card">
                <div class="member-search-fields">
                    <div class="member-field">
                        <label for="audience" class="member-field-label">대상</label>
                        <select name="audience" id="audience" class="form-select member-field-input">
                        <option value=""<?php echo $filters['audience'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedAudiences as $audience) { ?>
                            <option value="<?php echo sr_e($audience); ?>"<?php echo $filters['audience'] === $audience ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-solid-primary member-search-submit">조회</button>
                </div>
            </div>
        </form>
        <?php if ($notifications === []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <tbody>
                    <tr><td class="admin-dashboard-empty">등록된 알림이 없습니다.</td></tr>
                </tbody>
            </table>
            </div>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>ID</th>
                        <th>대상</th>
                        <th>상태</th>
                        <th>생성일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $notification['id']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $notification['audience'], 'notification_audience')); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $notification['status'], 'notification_status')); ?></td>
                            <td><?php echo sr_e((string) $notification['created_at']); ?></td>
                            <td class="member-cell-manage">
                                <div class="member-manage">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/delete')); ?>" style="display:inline">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
