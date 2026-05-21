<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = '알림';
$adminContainerClass = 'admin-page-notification-list admin-ui-scope';
if ($notificationAdminPage === 'new') {
    $adminPageTitle = '알림 등록';
    $adminContainerClass = 'admin-page-notification-form admin-ui-scope';
} elseif ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = '알림 발송 대기열';
    $adminContainerClass = 'admin-page-notification-delivery-list admin-ui-scope';
}
$notificationListFilters = isset($notificationListFilters) && is_array($notificationListFilters) ? $notificationListFilters : ['audience' => '', 'status' => '', 'field' => 'all', 'q' => ''];
$deliveryListFilters = isset($deliveryListFilters) && is_array($deliveryListFilters) ? $deliveryListFilters : ['delivery_channel' => '', 'delivery_status' => ''];
$notificationStatusCounts = isset($notificationStatusCounts) && is_array($notificationStatusCounts) ? $notificationStatusCounts : [];
$allowedNotificationStatuses = isset($allowedNotificationStatuses) && is_array($allowedNotificationStatuses) ? $allowedNotificationStatuses : [];
$totalNotifications = (int) ($notificationStatusCounts['total'] ?? count($notifications ?? []));
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
                    <input id="notification_admin_notifications_title" type="text" name="title" value="" maxlength="160" required class="form-input form-control-full">
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
                    <input id="notification_admin_notifications_link_url" type="text" name="link_url" value="" maxlength="255" class="form-input form-control-full">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="notification_admin_notifications_recipient">외부 수신자</label>
                <div class="admin-form-field">
                    <input id="notification_admin_notifications_recipient" type="text" name="recipient" value="" maxlength="255" class="form-input form-control-full">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">채널</span>
                <div class="admin-form-field">
                    <div class="admin-check-list">
                                            <?php foreach ($allowedCreateChannels as $channel) { ?>
                                                <?php $channelInputId = 'notification_admin_notifications_channel_' . (string) $channel; ?>
                                                <label class="admin-form-check form-label" for="<?php echo sr_e($channelInputId); ?>">
                                                    <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-checkbox"<?php echo $channel === 'site' ? ' checked' : ''; ?>>
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
                        <option value=""<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedChannels as $channel) { ?>
                            <option value="<?php echo sr_e($channel); ?>"<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === $channel ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="admin-filter-field">
                    <label for="delivery_status" class="admin-filter-label">발송 상태</label>
                    <select name="delivery_status" id="delivery_status" class="form-select admin-filter-input">
                        <option value=""<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
            </div>
        </form>
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
                <?php if ($deliveries === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">발송 대기열이 비어 있습니다.</td></tr>
                <?php } else { ?>
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
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총알림 <strong><?php echo sr_e((string) $totalNotifications); ?>개</strong></span>
            <?php foreach ($allowedNotificationStatuses as $status) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/notifications?status=' . rawurlencode((string) $status))); ?>" class="admin-summary-meta">
                    <?php echo sr_e(sr_admin_code_label((string) $status, 'notification_status')); ?> <?php echo sr_e((string) ($notificationStatusCounts[$status] ?? 0)); ?>개
                </a>
            <?php } ?>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="admin-filter admin-notification-filter ui-form-theme">
        <div class="admin-filter-grid admin-notification-search-grid">
            <div class="admin-filter-field admin-notification-filter-audience">
                <label for="notification_admin_audience_filter" class="admin-filter-label">대상</label>
                <select name="audience" id="notification_admin_audience_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($notificationListFilters['audience'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedAudiences as $audience) { ?>
                        <option value="<?php echo sr_e($audience); ?>"<?php echo (string) ($notificationListFilters['audience'] ?? '') === $audience ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-status">
                <label for="notification_admin_status_filter" class="admin-filter-label">상태</label>
                <select name="status" id="notification_admin_status_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($notificationListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedNotificationStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($notificationListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'notification_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-field">
                <label for="notification_admin_search_field" class="admin-filter-label">검색 조건</label>
                <select name="field" id="notification_admin_search_field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'body' => '내용', 'link' => '링크', 'account' => '회원 ID', 'id' => '알림 ID'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($notificationListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-keyword">
                <label for="notification_admin_search_keyword" class="admin-filter-label">검색어</label>
                <input type="search" id="notification_admin_search_keyword" name="q" value="<?php echo sr_e((string) ($notificationListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, 내용, 링크, ID">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">알림 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/notifications/new')); ?>" class="btn btn-sm btn-solid-light">새 알림 등록</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-notification-table">
            <caption class="sr-only">알림 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>제목</th>
                    <th>대상</th>
                    <th>상태</th>
                    <th>생성일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notifications === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">등록된 알림이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($notifications as $notification) { ?>
                        <?php
                        $notificationStatus = (string) $notification['status'];
                        $statusClass = match ($notificationStatus) {
                            'active' => 'is-normal',
                            'deleted' => 'is-left',
                            default => 'is-blocked',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-nowrap notification-id"><?php echo sr_e((string) $notification['id']); ?></td>
                            <td class="admin-table-break admin-notification-title-cell"><?php echo sr_e((string) ($notification['title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap admin-notification-audience-cell"><?php echo sr_e(sr_admin_code_label((string) $notification['audience'], 'notification_audience')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($notificationStatus, 'notification_status')); ?></span></td>
                            <td class="admin-table-nowrap admin-notification-date-cell"><?php echo sr_e((string) $notification['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
