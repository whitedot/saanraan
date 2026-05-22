<?php

$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
$adminPageTitle = '알림';
$adminContainerClass = 'admin-page-notification-list admin-ui-scope';
if ($notificationAdminPage === 'deliveries') {
    $adminPageTitle = '알림 발송 대기열';
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
            <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총발송 <strong><?php echo sr_e((string) $totalDeliveries); ?>개</strong></span>
            <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/notification-deliveries?delivery_status=' . rawurlencode((string) $status))); ?>" class="admin-summary-meta">
                    <?php echo sr_e(sr_admin_code_label((string) $status, 'delivery_status')); ?> <?php echo sr_e((string) ($deliveryStatusCounts[$status] ?? 0)); ?>개
                </a>
            <?php } ?>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/notification-deliveries')); ?>" class="admin-filter admin-notification-delivery-filter ui-form-theme">
        <div class="admin-filter-grid admin-notification-delivery-search-grid">
            <div class="admin-filter-field admin-notification-delivery-filter-channel">
                <label for="notification_admin_delivery_channel_filter" class="admin-filter-label">발송 채널</label>
                <select name="delivery_channel" id="notification_admin_delivery_channel_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedChannels as $channel) { ?>
                        <option value="<?php echo sr_e($channel); ?>"<?php echo (string) ($deliveryListFilters['delivery_channel'] ?? '') === $channel ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($channel, 'notification_channel')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-status">
                <label for="notification_admin_delivery_status_filter" class="admin-filter-label">발송 상태</label>
                <select name="delivery_status" id="notification_admin_delivery_status_filter" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedDeliveryStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($deliveryListFilters['delivery_status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'delivery_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-field">
                <label for="notification_admin_delivery_search_field" class="admin-filter-label">검색 조건</label>
                <select name="field" id="notification_admin_delivery_search_field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'id' => '발송 ID', 'notification' => '알림 ID', 'title' => '알림 제목', 'recipient' => '수신자'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($deliveryListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-notification-delivery-filter-keyword">
                <label for="notification_admin_delivery_search_keyword" class="admin-filter-label">검색어</label>
                <input type="search" id="notification_admin_delivery_search_keyword" name="q" value="<?php echo sr_e((string) ($deliveryListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="발송 ID, 알림 ID, 제목, 수신자">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">발송 대기열</h2>
        </div>
        <div class="table-wrapper">
        <table class="table admin-notification-delivery-table">
            <caption class="sr-only">알림 발송 대기열</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>알림</th>
                    <th>채널</th>
                    <th>수신자</th>
                    <th>상태</th>
                    <th>수정일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($deliveries === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">발송 대기열이 비어 있습니다.</td></tr>
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
                            <td class="admin-table-nowrap notification-id"><?php echo sr_e((string) $delivery['id']); ?></td>
                            <td class="admin-table-break admin-notification-delivery-title-cell">
                                <?php echo sr_e((string) ($delivery['notification_title'] ?? '')); ?><br>
                                <span class="admin-table-subtext">#<?php echo sr_e((string) $delivery['notification_id']); ?></span>
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
            <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="<?php echo $notificationCreateModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($notificationCreateModalId); ?>" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>">새 알림 등록</button>
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

    <div id="<?php echo sr_e($notificationCreateModalId); ?>" class="modal-overlay modal-overlay-fade overlay<?php echo $notificationCreateModalOpen ? ' overlay-open open' : ' hidden pointer-events-none opacity-0'; ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($notificationCreateModalId); ?>_title" aria-hidden="<?php echo $notificationCreateModalOpen ? 'false' : 'true'; ?>"<?php echo $notificationCreateModalOpen ? '' : ' inert'; ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/create')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($notificationCreateModalId); ?>_title" class="modal-title">알림 등록</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="create">
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_audience">대상</label>
                        <div class="admin-form-field">
                            <select id="notification_admin_notifications_audience" name="audience" class="form-select" data-overlay-focus>
                                <?php foreach ($allowedAudiences as $audience) { ?>
                                    <option value="<?php echo sr_e($audience); ?>"<?php echo (string) ($notificationCreateValues['audience'] ?? '') === $audience ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($audience, 'notification_audience')); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($notificationCreateAccountInputId); ?>">회원 공개 해시</label>
                        <div class="admin-form-field">
                            <div class="admin-lookup-control">
                                <input id="<?php echo sr_e($notificationCreateAccountInputId); ?>" type="text" name="account_identifier" value="<?php echo sr_e((string) ($notificationCreateValues['account_identifier'] ?? '')); ?>" maxlength="80" class="form-input">
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($notificationCreateMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($notificationCreateMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($notificationCreateAccountInputId); ?>">회원 검색</button>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_title">제목 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_title" type="text" name="title" value="<?php echo sr_e((string) ($notificationCreateValues['title'] ?? '')); ?>" maxlength="160" required class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_body_text">내용</label>
                        <div class="admin-form-field">
                            <textarea id="notification_admin_notifications_body_text" name="body_text" maxlength="5000" class="form-textarea"><?php echo sr_e((string) ($notificationCreateValues['body_text'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_link_url">링크 URL</label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_link_url" type="text" name="link_url" value="<?php echo sr_e((string) ($notificationCreateValues['link_url'] ?? '')); ?>" maxlength="255" class="form-input form-control-full" placeholder="/path 또는 https://example.com">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="notification_admin_notifications_recipient">외부 수신자</label>
                        <div class="admin-form-field">
                            <input id="notification_admin_notifications_recipient" type="text" name="recipient" value="<?php echo sr_e((string) ($notificationCreateValues['recipient'] ?? '')); ?>" maxlength="255" class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label">채널</span>
                        <div class="admin-form-field">
                            <div class="admin-check-list">
                                <?php foreach ($allowedCreateChannels as $channel) { ?>
                                    <?php $channelInputId = 'notification_admin_notifications_channel_' . (string) $channel; ?>
                                    <label class="admin-form-check form-label" for="<?php echo sr_e($channelInputId); ?>">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-checkbox"<?php echo in_array($channel, $notificationCreateChannels, true) ? ' checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?>
                                    </label>
                                <?php } ?>
                            </div>
                            <small class="admin-form-help">알림 등록 채널은 사이트 알림과 이메일만 사용합니다.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($notificationCreateModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">알림 등록</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    $assetAdjustLookup = [
        'field_prefix' => $notificationCreateMemberLookupPrefix,
        'member_input_id' => $notificationCreateAccountInputId,
        'return_overlay_id' => $notificationCreateModalId,
        'return_label' => '알림 등록으로 돌아가기',
        'member_search_url' => sr_url('/admin/members/search'),
    ];
    include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
    ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
