<?php

$pageTitle = sr_t('notification::ui.notification.12ddd6ca');
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/notifications'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <h1 class="type-page-title"><?php echo sr_e(sr_t('notification::ui.notification.12ddd6ca')); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/mypage')); ?>"><?php echo sr_e(sr_t('notification::ui.text.bf751bf5')); ?></a></p>

        <?php echo sr_public_feedback_toasts('notification', $notice, $errors); ?>

        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title"><?php echo sr_e(sr_t('notification::ui.text.50f30154')); ?></h2>
            <dl>
                <dt><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></dt>
                <dd><?php echo sr_e((string) $notificationSummary['total']); ?></dd>
                <dt><?php echo sr_e(sr_t('notification::ui.text.62808119')); ?></dt>
                <dd><?php echo sr_e((string) $notificationSummary['unread']); ?></dd>
            </dl>
        </div></section>

        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">외부 푸시</h2>
            <p>연결한 개인 수신처로 새 알림 도착 사실만 받습니다.</p>

            <?php if (!$pushProviderReady) { ?>
                <p>현재 외부 푸시 연결을 사용할 수 없습니다.</p>
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
                            <th>작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pushEndpoints as $pushEndpoint) { ?>
                            <tr>
                                <td><?php echo sr_e(sr_notification_member_external_channel_label((string) ($pushEndpoint['provider_key'] ?? ''))); ?></td>
                                <td>
                                    <?php echo sr_e((string) ((string) ($pushEndpoint['recipient_label'] ?? '') !== '' ? $pushEndpoint['recipient_label'] : $pushEndpoint['recipient_masked'])); ?>
                                    <?php if ((string) ($pushEndpoint['recipient_label'] ?? '') !== '') { ?>
                                        <br><?php echo sr_e((string) $pushEndpoint['recipient_masked']); ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo sr_e((string) $pushEndpoint['status'] === 'active' ? '사용 중' : '해제됨'); ?></td>
                                <td><?php echo sr_notification_time_html((string) $pushEndpoint['created_at']); ?></td>
                                <td>
                                    <?php if ((string) $pushEndpoint['status'] === 'active') { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="disable_push_endpoint">
                                            <input type="hidden" name="endpoint_id" value="<?php echo sr_e((string) $pushEndpoint['id']); ?>">
                                            <label for="modules_notification_disable_push_password_<?php echo sr_e((string) $pushEndpoint['id']); ?>">
                                                <span>현재 비밀번호</span>
                                                <input id="modules_notification_disable_push_password_<?php echo sr_e((string) $pushEndpoint['id']); ?>" type="password" name="current_password" autocomplete="current-password" required class="form-input">
                                            </label>
                                            <button type="submit" class="btn btn-solid-primary">해제</button>
                                        </form>
                                    <?php } else { ?>
                                        해제됨
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                    </div>
            <?php } ?>

            <?php if ($pushProviderReady) { ?>
                <?php foreach ($pushProviderStates as $pushProviderKey => $pushProviderState) { ?>
                    <?php if (empty($pushProviderState['ready'])) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <?php $pushProviderLabel = (string) ($pushProviderState['label'] ?? sr_notification_member_external_channel_label((string) $pushProviderKey)); ?>
                    <?php if (!empty($pushProviderState['limit_reached'])) { ?>
                        <p><?php echo sr_e($pushProviderLabel); ?> 푸시 수신처는 최대 5개까지 연결할 수 있습니다.</p>
                        <?php continue; ?>
                    <?php } ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="connect_push_endpoint">
                        <input type="hidden" name="provider_key" value="<?php echo sr_e((string) $pushProviderKey); ?>">
                        <?php if ((string) $pushProviderKey === 'telegram_bot') { ?>
                            <p>
                                <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_chat_id">
                                    <span>Telegram chat ID</span>
                                    <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_chat_id" type="text" name="telegram_chat_id" maxlength="120" required class="form-input">
                                </label>
                            </p>
                        <?php } else { ?>
                            <p>
                                <label for="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_endpoint">
                                    <span><?php echo sr_e($pushProviderLabel); ?> 수신 URL</span>
                                    <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_endpoint" type="url" name="endpoint" maxlength="255" required class="form-input form-control-full" autocomplete="off" placeholder="https://">
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
                                <span>현재 비밀번호</span>
                                <input id="modules_notification_<?php echo sr_e((string) $pushProviderKey); ?>_password" type="password" name="current_password" autocomplete="current-password" required class="form-input">
                            </label>
                        </p>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($pushProviderLabel); ?> 푸시 연결</button>
                    </form>
                <?php } ?>
            <?php } ?>
        </div></section>

        <form method="get" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
            <p>
                <label for="modules_notification_account_notifications_status">
                    <span><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></span>
                    <select id="modules_notification_account_notifications_status" name="status" class="form-select">
                        <?php foreach (['' => sr_t('notification::ui.text.62808119'), 'read' => sr_t('notification::ui.text.3fe5701c')] as $value => $label) { ?>
                            <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['status'] === (string) $value ? ' selected' : ''; ?>>
                                <?php echo sr_e($label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('notification::ui.text.f8d240bf')); ?></button>
        </form>

        <?php if ($notifications === []) { ?>
            <p><?php echo sr_e(sr_t('notification::ui.notification.16d30d47')); ?></p>
        <?php } else { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="mark_all_read">
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('notification::ui.text.6577bbbb')); ?></button>
            </form>
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
                            <td><?php echo sr_e((string) $notification['status'] === 'read' ? sr_t('notification::ui.text.3fe5701c') : sr_t('notification::ui.text.62808119')); ?></td>
                            <td><?php echo sr_notification_time_html((string) $notification['created_at']); ?></td>
                            <td>
                                <?php if ($notification['read_at'] === null) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?></button>
                                    </form>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
                    </div>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
