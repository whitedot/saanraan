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

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light">알림 목록</a>
        <a href="<?php echo sr_e(sr_url('/admin/notifications/new')); ?>" class="btn btn-solid-light">알림 등록</a>
        <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="btn btn-solid-light">발송 대기열</a>
    </div>
</div>

<?php if ($notificationAdminPage === 'new') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/create')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2>알림 등록</h2>
            <?php echo sr_csrf_field(); ?>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_audience">대상</label>
                <div class="admin-form-field">
                    <select id="notification_admin_notifications_audience" name="audience" class="form-select">
                                            <?php foreach ($allowedAudiences as $audience) { ?>
                                                <option value="<?php echo sr_e($audience); ?>"><?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?></option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_account_identifier">회원 공개 해시</label>
                <div class="admin-form-field">
                    <input id="notification_admin_notifications_account_identifier" type="text" name="account_identifier" value="" maxlength="80" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_title">제목</label>
                <div class="admin-form-field">
                    <input id="notification_admin_notifications_title" type="text" name="title" value="" maxlength="160" required class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_body_text">내용</label>
                <div class="admin-form-field">
                    <textarea id="notification_admin_notifications_body_text" name="body_text" maxlength="5000" class="form-textarea"></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_link_url">링크 URL (/로 시작하는 내부 URL 또는 http/https URL)</label>
                <div class="admin-form-field">
                    <input id="notification_admin_notifications_link_url" type="text" name="link_url" value="" maxlength="255" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_recipient">외부 수신자</label>
                <div class="admin-form-field">
                    <input id="notification_admin_notifications_recipient" type="text" name="recipient" value="" maxlength="255" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">채널</span>
                <div class="admin-form-field">
                    <div class="admin-check-list">
                                            <?php foreach ($allowedCreateChannels as $channel) { ?>
                                                <label class="admin-form-check form-label" for="modules_notification_admin_notifications_channels">
                                                    <input id="modules_notification_admin_notifications_channels" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-checkbox"<?php echo $channel === 'site' ? ' checked' : ''; ?>>
                                                    <?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?>
                                                </label>
                                            <?php } ?>
                                        </div>
                                        <small>알림 등록 채널은 사이트 알림과 이메일만 사용합니다.</small>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">알림 등록</button>
        </div>
    </form>
<?php } elseif ($notificationAdminPage === 'deliveries') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">발송 대기열</h2>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="admin-filter ui-form-theme">
            <div class="admin-filter-grid admin-account-search-grid admin-filter-grid-compact">
                <div class="admin-filter-field">
                    <label for="delivery_channel" class="admin-filter-label">발송 채널</label>
                    <select name="delivery_channel" id="delivery_channel" class="form-select admin-filter-input">
                        <option value=""<?php echo $filters['delivery_channel'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedChannels as $channel) { ?>
                            <option value="<?php echo sr_e($channel); ?>"<?php echo $filters['delivery_channel'] === $channel ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="admin-filter-field">
                    <label for="delivery_status" class="admin-filter-label">발송 상태</label>
                    <select name="delivery_status" id="delivery_status" class="form-select admin-filter-input">
                        <option value=""<?php echo $filters['delivery_status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $filters['delivery_status'] === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
            </div>
        </form>
        <?php if ($deliveries === []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <tbody>
                    <tr><td class="admin-empty-state">발송 대기열이 비어 있습니다.</td></tr>
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
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
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
                                    <button type="submit" class="btn btn-sm btn-solid-light">저장</button>
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
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">알림 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/notifications/new')); ?>" class="btn btn-sm btn-solid-light">새 알림 등록</a>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="admin-filter ui-form-theme">
            <div class="admin-filter-grid admin-account-search-grid">
                <div class="admin-filter-field">
                    <label for="audience" class="admin-filter-label">대상</label>
                    <select name="audience" id="audience" class="form-select admin-filter-input">
                        <option value=""<?php echo $filters['audience'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedAudiences as $audience) { ?>
                            <option value="<?php echo sr_e($audience); ?>"<?php echo $filters['audience'] === $audience ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
            </div>
        </form>
        <?php if ($notifications === []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <tbody>
                    <tr><td class="admin-empty-state">등록된 알림이 없습니다.</td></tr>
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
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
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
