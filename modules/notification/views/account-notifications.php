<?php

$pageTitle = sr_t('notification::ui.notification.12ddd6ca');
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/notifications'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main>
        <h1><?php echo sr_e(sr_t('notification::ui.notification.12ddd6ca')); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('notification::ui.text.bf751bf5')); ?></a></p>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <section>
            <h2><?php echo sr_e(sr_t('notification::ui.text.50f30154')); ?></h2>
            <dl>
                <dt><?php echo sr_e(sr_t('notification::ui.all.a4b69faf')); ?></dt>
                <dd><?php echo sr_e((string) $notificationSummary['total']); ?></dd>
                <dt><?php echo sr_e(sr_t('notification::ui.text.62808119')); ?></dt>
                <dd><?php echo sr_e((string) $notificationSummary['unread']); ?></dd>
            </dl>
        </section>

        <form method="get" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
            <p>
                <label for="modules_notification_account_notifications_status">
                    <span><?php echo sr_e(sr_t('notification::ui.status.e10195a1')); ?></span>
                    <select id="modules_notification_account_notifications_status" name="status">
                        <?php foreach (['' => sr_t('notification::ui.text.62808119'), 'read' => sr_t('notification::ui.text.3fe5701c')] as $value => $label) { ?>
                            <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['status'] === (string) $value ? ' selected' : ''; ?>>
                                <?php echo sr_e($label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit"><?php echo sr_e(sr_t('notification::ui.text.f8d240bf')); ?></button>
        </form>

        <?php if ($notifications === []) { ?>
            <p><?php echo sr_e(sr_t('notification::ui.notification.16d30d47')); ?></p>
        <?php } else { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="mark_all_read">
                <button type="submit"><?php echo sr_e(sr_t('notification::ui.text.6577bbbb')); ?></button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('notification::ui.text.08b17e43')); ?></th>
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
                                    <a<?php echo $notificationLinkAttributes; ?>><?php echo sr_e((string) $notification['title']); ?></a>
                                <?php } else { ?>
                                    <?php echo sr_e((string) $notification['title']); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_notification_body_html($notification); ?></td>
                            <td><?php echo sr_e((string) $notification['status'] === 'read' ? sr_t('notification::ui.text.3fe5701c') : sr_t('notification::ui.text.62808119')); ?></td>
                            <td><?php echo sr_notification_time_html((string) $notification['created_at']); ?></td>
                            <td>
                                <?php if ($notification['read_at'] === null) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                        <button type="submit"><?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?></button>
                                    </form>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('notification::ui.text.3fe5701c')); ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
