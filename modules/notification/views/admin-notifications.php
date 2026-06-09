<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = sr_t('notification::ui.notification.list.7475cac1');
$adminContainerClass = 'admin-page-notification-list admin-ui-scope';
if ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = sr_t('notification::ui.notification.56c30db0');
    $adminContainerClass = 'admin-page-notification-delivery-list admin-ui-scope';
}
$notificationListFilters = isset($notificationListFilters) && is_array($notificationListFilters) ? $notificationListFilters : ['audience' => [], 'status' => [], 'field' => 'all', 'q' => ''];
$deliveryListFilters = isset($deliveryListFilters) && is_array($deliveryListFilters) ? $deliveryListFilters : ['delivery_channel' => [], 'delivery_status' => [], 'field' => 'all', 'q' => ''];
$notificationStatusCounts = isset($notificationStatusCounts) && is_array($notificationStatusCounts) ? $notificationStatusCounts : [];
$deliveryStatusCounts = isset($deliveryStatusCounts) && is_array($deliveryStatusCounts) ? $deliveryStatusCounts : [];
$notificationSort = isset($notificationSort) && is_array($notificationSort) ? $notificationSort : sr_notification_admin_notification_default_sort();
$deliverySortOptions = isset($deliverySortOptions) && is_array($deliverySortOptions) ? $deliverySortOptions : [
    'notification' => ['columns' => ['n.title', 'd.id']],
    'channel' => ['columns' => ['d.channel', 'd.id']],
    'recipient' => ['columns' => ['d.recipient', 'd.id']],
    'status' => ['columns' => ['d.status', 'd.id']],
    'updated_at' => ['columns' => ['d.updated_at', 'd.id']],
];
$deliveryDefaultSort = isset($deliveryDefaultSort) && is_array($deliveryDefaultSort) ? $deliveryDefaultSort : sr_admin_sort_default('updated_at', 'desc');
$deliverySort = isset($deliverySort) && is_array($deliverySort) ? $deliverySort : $deliveryDefaultSort;
$allowedNotificationStatuses = isset($allowedNotificationStatuses) && is_array($allowedNotificationStatuses) ? $allowedNotificationStatuses : [];
$allowedDeliveryChannels = isset($allowedDeliveryChannels) && is_array($allowedDeliveryChannels) ? $allowedDeliveryChannels : ['email'];
$totalNotifications = (int) ($notificationStatusCounts['total'] ?? count($notifications ?? []));
$totalDeliveries = (int) ($deliveryStatusCounts['total'] ?? count($deliveries ?? []));
$notificationCreateModalId = 'notification-create-modal';
$notificationCreateAccountInputId = 'notification_admin_notifications_account_identifier';
$notificationCreateMemberLookupPrefix = 'notification_create';
$notificationCreateMemberLookupModalId = $notificationCreateMemberLookupPrefix . '_member_lookup_modal';
$notificationCreateModalOpen = !empty($notificationCreateModalOpen);
$notificationCreateValues = isset($notificationCreateValues) && is_array($notificationCreateValues) ? $notificationCreateValues : [
    'audience' => (string) ($allowedAudiences[0] ?? 'account'),
    'account_identifier' => '',
    'title' => '',
    'body_text' => '',
    'link_url' => '',
    'channels' => ['site'],
];
$notificationCreateChannels = is_array($notificationCreateValues['channels'] ?? null) ? $notificationCreateValues['channels'] : ['site'];
$notificationCreateAudience = (string) ($notificationCreateValues['audience'] ?? 'account');
$selectedNotificationAudiences = is_array($notificationListFilters['audience'] ?? null) ? $notificationListFilters['audience'] : [];
$selectedNotificationStatuses = is_array($notificationListFilters['status'] ?? null) ? $notificationListFilters['status'] : [];
$selectedDeliveryChannels = is_array($deliveryListFilters['delivery_channel'] ?? null) ? $deliveryListFilters['delivery_channel'] : [];
$selectedDeliveryStatuses = is_array($deliveryListFilters['delivery_status'] ?? null) ? $deliveryListFilters['delivery_status'] : [];
$notificationAdminEditorKey = $pdo instanceof PDO ? sr_admin_editor_key($pdo) : 'textarea';
$notificationEditorAttributes = $pdo instanceof PDO ? sr_editor_textarea_attributes($pdo, $notificationAdminEditorKey, 'admin_basic') : '';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($notificationAdminPage === 'deliveries') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('notification::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('notification::ui.text.34c94df2')); ?> <strong><?php echo sr_e((string) $totalDeliveries); ?><?php echo sr_e(sr_t('notification::ui.text.a57ab057')); ?></strong></span>
            <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries?delivery_status=' . rawurlencode((string) $status))); ?>" class="admin-summary-meta">
                    <?php echo sr_e(sr_admin_code_label((string) $status, 'delivery_status')); ?> <?php echo sr_e((string) ($deliveryStatusCounts[$status] ?? 0)); ?><?php echo sr_e(sr_t('notification::ui.text.a57ab057')); ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <?php $deliveryDetailFilterOpen = $selectedDeliveryChannels !== [] || $selectedDeliveryStatuses !== []; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="filtering-form admin-notification-delivery-filter ui-form-theme">
        <div class="filtering filtering-card<?php echo $deliveryDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields admin-notification-delivery-search-grid">
                <div class="filtering-field admin-notification-delivery-filter-field">
                <label for="notification_admin_delivery_search_field" class="filtering-label">검색조건</label>
                <select name="field" id="notification_admin_delivery_search_field" class="form-select filtering-input">
                    <?php foreach (['all' => sr_t('notification::ui.all.a4b69faf'), 'id' => sr_t('notification::ui.id.14dddbba'), 'notification' => sr_t('notification::ui.notification.id.ccc3eb79'), 'title' => sr_t('notification::ui.notification.b99c9635'), 'recipient' => sr_t('notification::ui.text.fb3853ea')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($deliveryListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                </div>
                <div class="filtering-field filtering-field-fill admin-notification-delivery-filter-keyword">
                <label for="notification_admin_delivery_search_keyword" class="filtering-label"><?php echo sr_e(sr_t('notification::ui.search.bda397fc')); ?></label>
                <input type="text" id="notification_admin_delivery_search_keyword" name="q" value="<?php echo sr_e((string) ($deliveryListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('notification::ui.id.notification.id.f7f271dc')); ?>">
                </div>
            </div>
            <div id="notification_delivery_detail_filters" class="filtering-body" data-filtering-body<?php echo $deliveryDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field admin-notification-delivery-filter-channel">
                    <label for="notification_admin_delivery_channel_filter" class="filtering-label"><?php echo sr_e(sr_t('notification::ui.text.3f2758e3')); ?></label>
                    <select id="notification_admin_delivery_channel_filter" name="delivery_channel" class="form-select filtering-input">
                        <option value=""><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></option>
                        <?php foreach ($allowedDeliveryChannels as $channel) { ?>
                            <option value="<?php echo sr_e($channel); ?>"<?php echo in_array($channel, $selectedDeliveryChannels, true) ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field admin-notification-delivery-filter-status">
                    <span class="filtering-label"><?php echo sr_e(sr_t('notification::ui.status.3d8c9ee7')); ?></span>
                    <?php echo sr_admin_filter_toggle_group_html('notification_admin_delivery_status_filter', 'delivery_status', sr_admin_code_label_options($allowedDeliveryStatuses, 'delivery_status'), $selectedDeliveryStatuses, sr_t('notification::ui.all.a4b69faf')); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $deliveryDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="notification_delivery_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('notification::ui.search.4b8d541e')); ?></button>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('notification::ui.text.077631f5')); ?></h2>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($deliverySort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($deliverySortOptions, $deliveryDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="알림 발송 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($deliveryPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-notification-delivery-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('notification::ui.notification.56c30db0')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('notification', $deliverySort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.notification.12ddd6ca'), 'notification', $deliverySort, $deliverySortOptions, $deliveryDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('channel', $deliverySort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.text.a391a59a'), 'channel', $deliverySort, $deliverySortOptions, $deliveryDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('recipient', $deliverySort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.text.fb3853ea'), 'recipient', $deliverySort, $deliverySortOptions, $deliveryDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $deliverySort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.status.e10195a1'), 'status', $deliverySort, $deliverySortOptions, $deliveryDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $deliverySort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.edit.d3a98476'), 'updated_at', $deliverySort, $deliverySortOptions, $deliveryDefaultSort); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('notification::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($deliveries === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('notification::ui.text.4ecdd323')); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($deliveries as $delivery) { ?>
                        <?php
                        $deliveryStatus = (string) $delivery['status'];
                        $deliveryStatusClass = match ($deliveryStatus) {
                            'sent' => 'is-normal',
                            'failed', 'canceled' => 'is-left',
                            default => 'is-blocked',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-break admin-notification-delivery-title-cell">
                                <?php echo sr_e((string) ($delivery['notification_title'] ?? '')); ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_code_label((string) $delivery['channel'], 'notification_channel')); ?></td>
                            <td class="admin-table-break admin-notification-delivery-recipient-cell"><?php echo sr_e((string) (($delivery['recipient'] ?? '') !== '' ? $delivery['recipient'] : '-')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($deliveryStatusClass); ?>"><?php echo sr_e(sr_admin_code_label($deliveryStatus, 'delivery_status')); ?></span></td>
                            <td class="admin-table-nowrap admin-notification-delivery-date-cell"><?php echo sr_notification_time_html((string) $delivery['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/notification-deliveries/status')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="delivery_id" value="<?php echo sr_e((string) $delivery['id']); ?>">
                                    <label class="sr-only" for="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>"><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                                    <select name="status" id="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>" class="form-select" required>
                                                <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) $delivery['status'] === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                                                    </option>
                                                <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('notification::ui.save.5fb92622')); ?></button>
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
    <?php echo sr_admin_pagination_html($deliveryPagination, '이메일 발송 작업 목록 페이지'); ?>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('notification::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('notification::ui.notification.bf2c2182')); ?> <strong><?php echo sr_e((string) $totalNotifications); ?><?php echo sr_e(sr_t('notification::ui.text.a57ab057')); ?></strong></span>
            <?php foreach ($allowedNotificationStatuses as $status) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/notifications?status=' . rawurlencode((string) $status))); ?>" class="admin-summary-meta">
                    <?php echo sr_e(sr_admin_code_label((string) $status, 'notification_status')); ?> <?php echo sr_e((string) ($notificationStatusCounts[$status] ?? 0)); ?><?php echo sr_e(sr_t('notification::ui.text.a57ab057')); ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <?php $notificationDetailFilterOpen = $selectedNotificationAudiences !== [] || $selectedNotificationStatuses !== []; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="filtering-form admin-notification-filter ui-form-theme">
        <div class="filtering filtering-card<?php echo $notificationDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields admin-notification-search-grid">
                <div class="filtering-field admin-notification-filter-field">
                <label for="notification_admin_search_field" class="filtering-label">검색조건</label>
                <select name="field" id="notification_admin_search_field" class="form-select filtering-input">
                    <?php foreach (['all' => sr_t('notification::ui.all.a4b69faf'), 'title' => sr_t('notification::ui.text.08b17e43'), 'body' => sr_t('notification::ui.text.cb0f2404'), 'link' => sr_t('notification::ui.text.3d54da9c'), 'account' => sr_t('notification::ui.member.id.07083483'), 'id' => sr_t('notification::ui.notification.id.ccc3eb79')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($notificationListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                </div>
                <div class="filtering-field filtering-field-fill admin-notification-filter-keyword">
                <label for="notification_admin_search_keyword" class="filtering-label"><?php echo sr_e(sr_t('notification::ui.search.bda397fc')); ?></label>
                <input type="text" id="notification_admin_search_keyword" name="q" value="<?php echo sr_e((string) ($notificationListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('notification::ui.id.c7b74a34')); ?>">
                </div>
            </div>
            <div id="notification_detail_filters" class="filtering-body" data-filtering-body<?php echo $notificationDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field admin-notification-filter-audience">
                    <span class="filtering-label"><?php echo sr_e(sr_t('notification::ui.text.8c609deb')); ?></span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('notification_admin_audience_filter', 'audience', sr_admin_code_label_options($allowedAudiences, 'notification_audience'), $selectedNotificationAudiences, sr_t('notification::ui.all.a4b69faf')); ?>
                </div>
                <div class="filtering-field admin-notification-filter-status">
                    <span class="filtering-label"><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('notification_admin_status_filter', 'status', sr_admin_code_label_options($allowedNotificationStatuses, 'notification_status'), $selectedNotificationStatuses, sr_t('notification::ui.all.a4b69faf')); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $notificationDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="notification_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('notification::ui.search.4b8d541e')); ?></button>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('notification::ui.notification.list.7475cac1')); ?></h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $notificationCreateModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($notificationCreateModalId); ?>" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>"><?php echo sr_e(sr_t('notification::ui.notification.create.fda77a84')); ?></button>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($notificationSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="알림 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($notificationPagination); ?>
        </div>
        <form id="notification-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="notification-bulk-form" data-notification-bulk-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="batch_status">
            <input type="hidden" name="operation_key" value="notification.set_status">
            <div class="admin-list-actions notification-bulk-actions" hidden data-notification-bulk-bar>
                <div class="notification-bulk-controls">
                    <select name="target_status" class="form-select" aria-label="변경할 알림 상태">
                        <?php foreach ($allowedNotificationStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"><?php echo sr_e(sr_admin_code_label($status, 'notification_status')); ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="btn btn-solid-primary" data-notification-bulk-submit disabled>상태 변경</button>
                    <button type="button" class="btn btn-solid-light" data-notification-bulk-clear>선택 해제</button>
                </div>
                <div class="notification-bulk-summary" aria-live="polite">
                    <strong data-notification-selected-count>0</strong>개 선택됨
                </div>
            </div>
        </form>
        <div class="table-wrapper">
        <table class="table admin-notification-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('notification::ui.notification.list.7475cac1')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th class="notification-select-cell">
                        <label class="sr-only" for="notification_bulk_select_all">현재 페이지 알림 전체 선택</label>
                        <input id="notification_bulk_select_all" type="checkbox" class="form-checkbox" data-notification-select-all<?php echo $notifications === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('title', $notificationSort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.text.08b17e43'), 'title', $notificationSort, sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('audience', $notificationSort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.text.8c609deb'), 'audience', $notificationSort, sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $notificationSort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.status.e10195a1'), 'status', $notificationSort, sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $notificationSort); ?>><?php echo sr_admin_sort_header_html(sr_t('notification::ui.text.5efd3ddd'), 'created_at', $notificationSort, sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('notification::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notifications === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('notification::ui.create.notification.f92f6fb2')); ?></td></tr>
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
                            <td class="notification-select-cell">
                                <label class="sr-only" for="notification_bulk_select_<?php echo sr_e((string) (int) $notification['id']); ?>"><?php echo sr_e((string) ($notification['title'] ?? '')); ?> 선택</label>
                                <input id="notification_bulk_select_<?php echo sr_e((string) (int) $notification['id']); ?>" type="checkbox" name="selected_notification_ids[]" value="<?php echo sr_e((string) (int) $notification['id']); ?>" class="form-checkbox" form="notification-bulk-status-form" data-notification-row-select>
                            </td>
                            <td class="admin-table-break admin-notification-title-cell"><?php echo sr_e((string) ($notification['title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap admin-notification-audience-cell"><?php echo sr_e(sr_admin_code_label((string) $notification['audience'], 'notification_audience')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($notificationStatus, 'notification_status')); ?></span></td>
                            <td class="admin-table-nowrap admin-notification-date-cell"><?php echo sr_notification_time_html((string) $notification['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('notification::ui.delete.6139b6c3')); ?>" title="<?php echo sr_e(sr_t('notification::ui.delete.6139b6c3')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
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
    <?php echo sr_admin_pagination_html($notificationPagination, '알림 목록 페이지'); ?>

    <script>
    (function () {
        var bulkForm = document.querySelector('[data-notification-bulk-form]');
        if (!bulkForm) {
            return;
        }

        var bar = document.querySelector('[data-notification-bulk-bar]');
        var countNode = document.querySelector('[data-notification-selected-count]');
        var submit = document.querySelector('[data-notification-bulk-submit]');
        var clear = document.querySelector('[data-notification-bulk-clear]');
        var selectAll = document.querySelector('[data-notification-select-all]');
        var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-notification-row-select]'));

        var checkedRows = function () {
            return rowChecks.filter(function (input) {
                return input.checked && !input.disabled;
            });
        };

        var syncBulkState = function () {
            var selectedCount = checkedRows().length;
            if (countNode) {
                countNode.textContent = String(selectedCount);
            }
            if (bar) {
                bar.hidden = selectedCount < 1;
            }
            if (submit) {
                submit.disabled = selectedCount < 1;
            }
            if (selectAll) {
                selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
            }
        };

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(function (input) {
                    if (!input.disabled) {
                        input.checked = selectAll.checked;
                    }
                });
                syncBulkState();
            });
        }
        rowChecks.forEach(function (input) {
            input.addEventListener('change', syncBulkState);
        });
        if (clear) {
            clear.addEventListener('click', function () {
                rowChecks.forEach(function (input) {
                    input.checked = false;
                });
                syncBulkState();
            });
        }
        bulkForm.addEventListener('submit', function (event) {
            var selectedCount = checkedRows().length;
            if (selectedCount < 1) {
                event.preventDefault();
                syncBulkState();
                return;
            }
            var status = bulkForm.querySelector('select[name="target_status"]');
            var statusLabel = status && status.options[status.selectedIndex] ? status.options[status.selectedIndex].text : '선택한 상태';
            if (!window.confirm('선택한 알림 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
                event.preventDefault();
            }
        });
        syncBulkState();
    })();
    </script>

    <div id="<?php echo sr_e($notificationCreateModalId); ?>" class="modal-overlay modal-overlay-fade overlay<?php echo $notificationCreateModalOpen ? ' overlay-open open' : ' hidden pointer-events-none opacity-0'; ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($notificationCreateModalId); ?>_title" aria-hidden="<?php echo $notificationCreateModalOpen ? 'false' : 'true'; ?>"<?php echo $notificationCreateModalOpen ? '' : ' inert'; ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/create')); ?>" class="modal-content ui-form-theme" data-notification-create-form>
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($notificationCreateModalId); ?>_title" class="modal-title"><?php echo sr_e(sr_t('notification::ui.notification.create.079d0758')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('notification::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="create">
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_audience"><?php echo sr_e(sr_t('notification::ui.text.8c609deb')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="notification_admin_notifications_audience" name="audience" class="form-select" required data-notification-audience data-overlay-focus>
                                <?php foreach ($allowedAudiences as $audience) { ?>
                                    <option value="<?php echo sr_e($audience); ?>"<?php echo (string) ($notificationCreateValues['audience'] ?? '') === $audience ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row" data-notification-account-row<?php echo $notificationCreateAudience === 'account' ? '' : ' hidden'; ?>>
                        <label class="form-label" for="<?php echo sr_e($notificationCreateAccountInputId); ?>"><?php echo sr_e(sr_t('notification::ui.member.900e04a5')); ?> <span class="sr-required-label" data-notification-account-required<?php echo (string) ($notificationCreateValues['audience'] ?? '') === 'account' ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <div class="admin-lookup-control">
                                <input id="<?php echo sr_e($notificationCreateAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e((string) ($notificationCreateValues['account_identifier'] ?? '')); ?>" maxlength="80" class="form-input" data-notification-account-identifier<?php echo (string) ($notificationCreateValues['audience'] ?? '') === 'account' ? ' required' : ''; ?>>
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($notificationCreateMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($notificationCreateMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($notificationCreateAccountInputId); ?>"><?php echo sr_e(sr_t('notification::ui.member.search.f7a330b0')); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_title"><?php echo sr_e(sr_t('notification::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_title" type="text" name="title" value="<?php echo sr_e((string) ($notificationCreateValues['title'] ?? '')); ?>" maxlength="160" required class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_body_text"><?php echo sr_e(sr_t('notification::ui.text.cb0f2404')); ?></label>
                        <div class="admin-form-field">
                            <textarea id="notification_admin_notifications_body_text" name="body_text" maxlength="5000" class="form-textarea"<?php echo $notificationEditorAttributes; ?>><?php echo sr_e((string) ($notificationCreateValues['body_text'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_link_url"><?php echo sr_e(sr_t('notification::ui.url.f7ca9b13')); ?></label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_link_url" type="text" name="link_url" value="<?php echo sr_e((string) ($notificationCreateValues['link_url'] ?? '')); ?>" maxlength="255" class="form-input form-control-full" placeholder="<?php echo sr_e(sr_t('notification::ui.path.https.example.com.a67f0fa1')); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label"><?php echo sr_e(sr_t('notification::ui.text.a391a59a')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></span>
                        <div class="admin-form-field">
                            <div class="admin-check-list">
                                <?php foreach ($allowedCreateChannels as $channel) { ?>
                                    <?php $channelInputId = 'notification_admin_notifications_channel_' . (string) $channel; ?>
                                    <label class="admin-form-check form-label" for="<?php echo sr_e($channelInputId); ?>">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-checkbox" data-notification-channel<?php echo in_array($channel, $notificationCreateChannels, true) ? ' checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?>
                                    </label>
                                <?php } ?>
                            </div>
                            <small class="admin-form-help"><?php echo sr_e(sr_t('notification::ui.notification.create.notification.email.active.d3635deb')); ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>"><?php echo sr_e(sr_t('notification::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('notification::ui.notification.create.079d0758')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
    $assetAdjustLookup = [
        'field_prefix' => $notificationCreateMemberLookupPrefix,
        'member_input_id' => $notificationCreateAccountInputId,
        'return_overlay_id' => $notificationCreateModalId,
        'return_label' => sr_t('notification::ui.notification.create.f8cc68db'),
        'member_search_url' => sr_url('/admin/members/search'),
    ];
    include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
    ?>
    <script>
    (function () {
        var form = document.querySelector('[data-notification-create-form]');
        if (!form) {
            return;
        }

        var audience = form.querySelector('[data-notification-audience]');
        var accountRow = form.querySelector('[data-notification-account-row]');
        var accountInput = form.querySelector('[data-notification-account-identifier]');
        var accountRequired = form.querySelector('[data-notification-account-required]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-notification-channel]'));

        function syncRequiredState() {
            var accountNeeded = audience && audience.value === 'account';
            var channelSelected = channels.some(function (channel) {
                return channel.checked;
            });
            if (accountRow) {
                accountRow.hidden = !accountNeeded;
            }
            if (accountRequired) {
                accountRequired.hidden = !accountNeeded;
            }
            if (accountInput) {
                accountInput.required = accountNeeded;
            }
            if (channels[0] && typeof channels[0].setCustomValidity === 'function') {
                channels[0].setCustomValidity(channelSelected ? '' : '발송 채널을 하나 이상 선택하세요.');
            }
        }

        form.addEventListener('change', function (event) {
            if (event.target === audience || channels.indexOf(event.target) !== -1) {
                syncRequiredState();
            }
        });

        form.addEventListener('submit', function (event) {
            syncRequiredState();
            if (channels[0] && !channels[0].validity.valid) {
                event.preventDefault();
                channels[0].reportValidity();
            }
        });

        syncRequiredState();
    })();
    </script>
    <?php echo $pdo instanceof PDO ? sr_editor_assets_html($pdo, $notificationAdminEditorKey, 'admin_basic') : ''; ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
