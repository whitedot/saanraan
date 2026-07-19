<?php

$pageTitle = sr_t('notification::ui.notification.12ddd6ca');
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/notifications'),
    'robots' => 'noindex, nofollow',
];
$notificationLayoutContext = [
    'stylesheets' => ['/modules/notification/assets/module.css'],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $notificationLayoutContext);
?>
    <main class="notification-account-page">
        <?php echo sr_public_feedback_toasts('notification', $notice, $errors); ?>

        <header class="notification-account-heading">
            <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
            <p class="type-small notification-account-muted">새 알림을 확인하고 개인 푸시 수신처를 관리합니다.</p>
        </header>

        <section class="card" aria-labelledby="notification-account-summary-title">
            <div class="card-header">
                <h2 id="notification-account-summary-title" class="card-title"><?php echo sr_e(sr_t('notification::ui.text.50f30154')); ?></h2>
            </div>
            <div class="card-body">
                <dl class="notification-account-summary-list">
                    <div>
                        <dt><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></dt>
                        <dd class="type-section-title"><?php echo sr_e((string) $notificationSummary['total']); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo sr_e(sr_t('notification::ui.text.62808119')); ?></dt>
                        <dd class="type-section-title"><?php echo sr_e((string) $notificationSummary['unread']); ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="card" aria-labelledby="notification-account-push-title">
            <div class="card-header">
                <h2 id="notification-account-push-title" class="card-title">외부 푸시</h2>
                <p class="type-small notification-account-muted">연결한 개인 수신처로 새 알림 도착 사실만 받습니다.</p>
            </div>
            <div class="card-body notification-account-card-stack">
                <?php if (!$pushProviderReady) { ?>
                    <div class="alert alert-info">
                        <p>현재 외부 푸시 연결을 사용할 수 없습니다.</p>
                    </div>
                <?php } ?>

                <?php if ($pushEndpoints !== []) { ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>제공자</th>
                                    <th>수신처</th>
                                    <th>상태</th>
                                    <th>연결일</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pushEndpoints as $pushEndpoint) { ?>
                                    <tr>
                                        <td><?php echo sr_e(sr_notification_member_external_channel_label((string) ($pushEndpoint['provider_key'] ?? ''))); ?></td>
                                        <td>
                                            <?php echo sr_e((string) ((string) ($pushEndpoint['recipient_label'] ?? '') !== '' ? $pushEndpoint['recipient_label'] : $pushEndpoint['recipient_masked'])); ?>
                                            <?php if ((string) ($pushEndpoint['recipient_label'] ?? '') !== '') { ?>
                                                <br><span class="type-small notification-account-muted"><?php echo sr_e((string) $pushEndpoint['recipient_masked']); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo (string) $pushEndpoint['status'] === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'; ?> badge-pill">
                                                <?php echo sr_e((string) $pushEndpoint['status'] === 'active' ? '사용 중' : '해제됨'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sr_notification_time_html((string) $pushEndpoint['created_at']); ?></td>
                                        <td>
                                            <?php if ((string) $pushEndpoint['status'] === 'active') { ?>
                                                <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>" class="notification-account-inline-form">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="intent" value="disable_push_endpoint">
                                                    <input type="hidden" name="endpoint_id" value="<?php echo sr_e((string) $pushEndpoint['id']); ?>">
                                                    <label class="sr-only" for="modules_notification_disable_push_password_<?php echo sr_e((string) $pushEndpoint['id']); ?>">현재 비밀번호</label>
                                                    <input id="modules_notification_disable_push_password_<?php echo sr_e((string) $pushEndpoint['id']); ?>" type="password" name="current_password" autocomplete="current-password" required class="form-input" placeholder="현재 비밀번호">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">해제</button>
                                                </form>
                                            <?php } else { ?>
                                                <span class="type-small notification-account-muted">해제됨</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </section>

        <?php if ($pushProviderReady) { ?>
            <div class="notification-account-provider-grid">
                <?php foreach ($pushProviderStates as $pushProviderKey => $pushProviderState) { ?>
                    <?php if (empty($pushProviderState['ready'])) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <?php $pushProviderLabel = (string) ($pushProviderState['label'] ?? sr_notification_member_external_channel_label((string) $pushProviderKey)); ?>
                    <section class="card">
                        <div class="card-header">
                            <h2 class="card-title"><?php echo sr_e($pushProviderLabel); ?> 푸시</h2>
                            <span class="badge badge-soft-info badge-pill"><?php echo sr_e((string) ((int) ($pushProviderState['active_count'] ?? 0))); ?> / 5</span>
                        </div>
                        <div class="card-body notification-account-card-stack">
                            <?php if (!empty($pushProviderState['limit_reached'])) { ?>
                                <div class="alert alert-warning">
                                    <p><?php echo sr_e($pushProviderLabel); ?> 푸시 수신처는 최대 5개까지 연결할 수 있습니다.</p>
                                </div>
                            <?php } else { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>" class="notification-account-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="connect_push_endpoint">
                                    <input type="hidden" name="provider_key" value="<?php echo sr_e((string) $pushProviderKey); ?>">
                                    <?php if ((string) $pushProviderKey === 'telegram_bot') { ?>
                                        <p>
                                            <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_chat_id">
                                                <span>Telegram chat ID <span class="sr-required-label">(필수)</span></span>
                                                <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_chat_id" type="text" name="telegram_chat_id" maxlength="120" required class="form-input">
                                            </label>
                                        </p>
                                    <?php } else { ?>
                                        <p>
                                            <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_endpoint">
                                                <span><?php echo sr_e($pushProviderLabel); ?> 수신 URL <span class="sr-required-label">(필수)</span></span>
                                                <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_endpoint" type="url" name="endpoint" maxlength="255" required class="form-input" autocomplete="off" placeholder="https://">
                                            </label>
                                        </p>
                                    <?php } ?>
                                    <p>
                                        <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_label">
                                            <span>표시 이름</span>
                                            <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_label" type="text" name="recipient_label" maxlength="120" class="form-input">
                                        </label>
                                    </p>
                                    <p>
                                        <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_password">
                                            <span>현재 비밀번호 <span class="sr-required-label">(필수)</span></span>
                                            <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_password" type="password" name="current_password" autocomplete="current-password" required class="form-input">
                                        </label>
                                    </p>
                                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($pushProviderLabel); ?> 푸시 연결</button>
                                </form>
                            <?php } ?>
                        </div>
                    </section>
                <?php } ?>
            </div>
        <?php } ?>

        <form method="get" action="<?php echo sr_e(sr_url('/account/notifications')); ?>" class="filtering filtering-plain notification-account-filter">
            <div class="filtering-fields">
                <div class="filtering-field">
                    <label class="filtering-label" for="modules_notification_account_notifications_status"><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></label>
                    <select id="modules_notification_account_notifications_status" name="status" class="form-select">
                        <?php foreach (['' => sr_t('notification::ui.text.62808119'), 'read' => sr_t('notification::ui.text.3fe5701c')] as $value => $label) { ?>
                            <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['status'] === (string) $value ? ' selected' : ''; ?>>
                                <?php echo sr_e($label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('notification::ui.text.f8d240bf')); ?></button>
            </div>
        </form>

        <section id="notification-list" class="card" aria-labelledby="notification-account-list-title">
            <div class="card-header">
                <h2 id="notification-account-list-title" class="card-title"><?php echo sr_e($pageTitle); ?> 목록</h2>
                <?php if ($notifications !== [] && $filters['status'] !== 'read') { ?>
                    <form method="post" action="<?php echo sr_e(sr_url($notificationListPath)); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="mark_all_read">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo sr_e(sr_t('notification::ui.text.6577bbbb')); ?></button>
                    </form>
                <?php } ?>
            </div>
            <div class="card-body notification-account-card-stack">
                <?php if ($notifications === []) { ?>
                    <p class="notification-account-empty"><?php echo sr_e(sr_t('notification::ui.notification.16d30d47')); ?></p>
                <?php } else { ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo sr_e(sr_t('notification::ui.text.cb0f2404')); ?></th>
                                    <th><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></th>
                                    <th><?php echo sr_e(sr_t('notification::ui.text.5efd3ddd')); ?></th>
                                    <th><?php echo sr_e(sr_t('notification::ui.text.29ae8f30')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification) { ?>
                                    <?php $notificationLinkAttributes = sr_notification_item_link_attributes($notification, (int) ($account['id'] ?? 0), true); ?>
                                    <tr>
                                        <td>
                                            <?php if ($notificationLinkAttributes !== '') { ?>
                                                <a<?php echo $notificationLinkAttributes; ?>><?php echo sr_notification_body_html($notification) !== '' ? sr_notification_body_html($notification) : sr_e('알림 확인'); ?></a>
                                            <?php } else { ?>
                                                <?php echo sr_notification_body_html($notification) !== '' ? sr_notification_body_html($notification) : sr_e('알림'); ?>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo (string) $notification['status'] === 'read' ? 'badge-soft-secondary' : 'badge-soft-info'; ?> badge-pill">
                                                <?php echo sr_e((string) $notification['status'] === 'read' ? sr_t('notification::ui.text.3fe5701c') : sr_t('notification::ui.text.62808119')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sr_notification_time_html((string) $notification['created_at']); ?></td>
                                        <td>
                                            <?php if ($notification['read_at'] === null) { ?>
                                                <form method="post" action="<?php echo sr_e(sr_url($notificationListPath)); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="intent" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?></button>
                                                </form>
                                            <?php } else { ?>
                                                <span class="type-small notification-account-muted"><?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?></span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
            <?php if ((int) ($notificationPagination['total_pages'] ?? 1) > 1) { ?>
                <div class="card-footer">
                    <?php echo sr_public_pagination_html($notificationPagination, $notificationPaginationBasePath, '알림 목록 페이지', 'page', 'notification-list'); ?>
                </div>
            <?php } ?>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
