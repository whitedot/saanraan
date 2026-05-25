<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = sr_t('notification::ui.notification.12ddd6ca');
$adminContainerClass = 'admin-page-notification-list admin-ui-scope';
if ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = sr_t('notification::ui.notification.56c30db0');
    $adminContainerClass = 'admin-page-notification-delivery-list admin-ui-scope';
}
$notificationListFilters = isset($notificationListFilters) && is_array($notificationListFilters) ? $notificationListFilters : ['audience' => '', 'status' => '', 'field' => 'all', 'q' => ''];
$deliveryListFilters = isset($deliveryListFilters) && is_array($deliveryListFilters) ? $deliveryListFilters : ['delivery_channel' => '', 'delivery_status' => '', 'field' => 'all', 'q' => ''];
$notificationStatusCounts = isset($notificationStatusCounts) && is_array($notificationStatusCounts) ? $notificationStatusCounts : [];
$deliveryStatusCounts = isset($deliveryStatusCounts) && is_array($deliveryStatusCounts) ? $deliveryStatusCounts : [];
$allowedNotificationStatuses = isset($allowedNotificationStatuses) && is_array($allowedNotificationStatuses) ? $allowedNotificationStatuses : [];
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
    'recipient' => '',
    'channels' => ['site'],
];
$notificationCreateChannels = is_array($notificationCreateValues['channels'] ?? null) ? $notificationCreateValues['channels'] : ['site'];
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

    <form method="get" action="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="admin-filter admin-notification-delivery-filter ui-form-theme">
        <div class="admin-filter-grid admin-notification-delivery-search-grid">
            <div class="admin-filter-field admin-notification-delivery-filter-channel">
                <label for="notification_admin_delivery_channel_filter" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.text.3f2758e3')); ?></label>
                <select name="delivery_channel" id="notification_admin_delivery_channel_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedChannels as $channel) { ?>
                        <option value="<?php echo sr_e($channel); ?>"<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === $channel ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-status">
                <label for="notification_admin_delivery_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.status.3d8c9ee7')); ?></label>
                <select name="delivery_status" id="notification_admin_delivery_status_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-field">
                <label for="notification_admin_delivery_search_field" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.search.b79bc9c8')); ?></label>
                <select name="field" id="notification_admin_delivery_search_field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('notification::ui.all.a4b69faf'), 'id' => sr_t('notification::ui.id.14dddbba'), 'notification' => sr_t('notification::ui.notification.id.ccc3eb79'), 'title' => sr_t('notification::ui.notification.b99c9635'), 'recipient' => sr_t('notification::ui.text.fb3853ea')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($deliveryListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-keyword">
                <label for="notification_admin_delivery_search_keyword" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.search.bda397fc')); ?></label>
                <input type="search" id="notification_admin_delivery_search_keyword" name="q" value="<?php echo sr_e((string) ($deliveryListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('notification::ui.id.notification.id.f7f271dc')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('notification::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('notification::ui.text.077631f5')); ?></h2>
        </div>
        <?php echo sr_admin_pagination_summary_html($deliveryPagination); ?>
        <div class="table-wrapper">
        <table class="table admin-notification-delivery-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('notification::ui.notification.56c30db0')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('notification::ui.notification.12ddd6ca')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.text.a391a59a')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.text.fb3853ea')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.edit.d3a98476')); ?></th>
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
                            'ready', 'sent' => 'is-normal',
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
                            <td class="admin-table-nowrap admin-notification-delivery-date-cell"><?php echo sr_e((string) $delivery['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/notification-deliveries/status')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="delivery_id" value="<?php echo sr_e((string) $delivery['id']); ?>">
                                    <label class="sr-only" for="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>"><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                                    <select name="status" id="delivery_status_<?php echo sr_e((string) $delivery['id']); ?>" class="form-select">
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
    <?php echo sr_admin_pagination_html($deliveryPagination, '알림 발송 목록 페이지'); ?>
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

    <form method="get" action="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="admin-filter admin-notification-filter ui-form-theme">
        <div class="admin-filter-grid admin-notification-search-grid">
            <div class="admin-filter-field admin-notification-filter-audience">
                <label for="notification_admin_audience_filter" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.text.8c609deb')); ?></label>
                <select name="audience" id="notification_admin_audience_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($notificationListFilters['audience'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedAudiences as $audience) { ?>
                        <option value="<?php echo sr_e($audience); ?>"<?php echo (string) ($notificationListFilters['audience'] ?? '') === $audience ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-status">
                <label for="notification_admin_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></label>
                <select name="status" id="notification_admin_status_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($notificationListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedNotificationStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($notificationListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'notification_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-field">
                <label for="notification_admin_search_field" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.search.b79bc9c8')); ?></label>
                <select name="field" id="notification_admin_search_field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('notification::ui.all.a4b69faf'), 'title' => sr_t('notification::ui.text.08b17e43'), 'body' => sr_t('notification::ui.text.cb0f2404'), 'link' => sr_t('notification::ui.text.3d54da9c'), 'account' => sr_t('notification::ui.member.id.07083483'), 'id' => sr_t('notification::ui.notification.id.ccc3eb79')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($notificationListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-filter-keyword">
                <label for="notification_admin_search_keyword" class="admin-filter-label"><?php echo sr_e(sr_t('notification::ui.search.bda397fc')); ?></label>
                <input type="search" id="notification_admin_search_keyword" name="q" value="<?php echo sr_e((string) ($notificationListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('notification::ui.id.c7b74a34')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('notification::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('notification::ui.notification.list.7475cac1')); ?></h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $notificationCreateModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($notificationCreateModalId); ?>" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>"><?php echo sr_e(sr_t('notification::ui.notification.create.fda77a84')); ?></button>
        </div>
        <?php echo sr_admin_pagination_summary_html($notificationPagination); ?>
        <div class="table-wrapper">
        <table class="table admin-notification-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('notification::ui.notification.list.7475cac1')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('notification::ui.text.08b17e43')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.text.8c609deb')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('notification::ui.text.5efd3ddd')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('notification::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notifications === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('notification::ui.create.notification.f92f6fb2')); ?></td></tr>
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
                            <td class="admin-table-break admin-notification-title-cell"><?php echo sr_e((string) ($notification['title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap admin-notification-audience-cell"><?php echo sr_e(sr_admin_code_label((string) $notification['audience'], 'notification_audience')); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($notificationStatus, 'notification_status')); ?></span></td>
                            <td class="admin-table-nowrap admin-notification-date-cell"><?php echo sr_e((string) $notification['created_at']); ?></td>
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
                            <select id="notification_admin_notifications_audience" name="audience" class="form-select" data-notification-audience data-overlay-focus>
                                <?php foreach ($allowedAudiences as $audience) { ?>
                                    <option value="<?php echo sr_e($audience); ?>"<?php echo (string) ($notificationCreateValues['audience'] ?? '') === $audience ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
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
                            <textarea id="notification_admin_notifications_body_text" name="body_text" maxlength="5000" class="form-textarea"><?php echo sr_e((string) ($notificationCreateValues['body_text'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_link_url"><?php echo sr_e(sr_t('notification::ui.url.f7ca9b13')); ?></label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_link_url" type="text" name="link_url" value="<?php echo sr_e((string) ($notificationCreateValues['link_url'] ?? '')); ?>" maxlength="255" class="form-input form-control-full" placeholder="<?php echo sr_e(sr_t('notification::ui.path.https.example.com.a67f0fa1')); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_recipient"><?php echo sr_e(sr_t('notification::ui.text.2ab1c735')); ?> <span class="sr-required-label" data-notification-recipient-required<?php echo in_array('email', $notificationCreateChannels, true) ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('notification::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_recipient" type="text" name="recipient" value="<?php echo sr_e((string) ($notificationCreateValues['recipient'] ?? '')); ?>" maxlength="255" class="form-input form-control-full" data-notification-recipient<?php echo in_array('email', $notificationCreateChannels, true) ? ' required' : ''; ?>>
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
        var accountInput = form.querySelector('[data-notification-account-identifier]');
        var accountRequired = form.querySelector('[data-notification-account-required]');
        var recipientInput = form.querySelector('[data-notification-recipient]');
        var recipientRequired = form.querySelector('[data-notification-recipient-required]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-notification-channel]'));

        function hasEmailChannel() {
            return channels.some(function (channel) {
                return channel.checked && channel.value === 'email';
            });
        }

        function syncRequiredState() {
            var accountNeeded = audience && audience.value === 'account';
            var recipientNeeded = hasEmailChannel();
            var channelSelected = channels.some(function (channel) {
                return channel.checked;
            });
            if (accountRequired) {
                accountRequired.hidden = !accountNeeded;
            }
            if (accountInput) {
                accountInput.required = accountNeeded;
            }
            if (recipientRequired) {
                recipientRequired.hidden = !recipientNeeded;
            }
            if (recipientInput) {
                recipientInput.required = recipientNeeded;
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
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
