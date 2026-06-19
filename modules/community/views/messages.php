<?php

$pageTitle = $box === 'sent' ? sr_t('community::ui.text.add34931') : sr_t('community::ui.text.1df1e319');
$seo = [
    'title' => $pageTitle,
    'canonical' => $box === 'sent' ? '/community/messages?box=sent' : '/community/messages',
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
    <main class="community-screen">
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p>
            <a href="<?php echo sr_e(sr_url('/community/messages')); ?>"><?php echo sr_e(sr_t('community::ui.text.1df1e319')); ?></a>
            /
            <a href="<?php echo sr_e(sr_url('/community/messages?box=sent')); ?>"><?php echo sr_e(sr_t('community::ui.text.add34931')); ?></a>
            /
            <a href="<?php echo sr_e(sr_url('/community/message/write')); ?>"><?php echo sr_e(sr_t('community::ui.text.288b8b7e')); ?></a>
        </p>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <?php if ($messages === []) { ?>
            <p><?php echo sr_e(sr_t('community::ui.text.f3e1cf06')); ?></p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo sr_e($box === 'sent' ? sr_t('community::ui.member.a8116cfc') : sr_t('community::ui.member.2d301cb0')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.ed5e184f')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message) { ?>
                        <tr>
                            <td>
                                <?php echo sr_e(sr_community_message_account_label(
                                    is_string($message['other_display_name'] ?? null) ? $message['other_display_name'] : null,
                                    $box === 'sent' ? (int) $message['recipient_account_id'] : (int) $message['sender_account_id'],
                                    $canViewMemberIdentifiers,
                                    $config,
                                    is_string($message['other_account_status'] ?? null) ? $message['other_account_status'] : null,
                                    is_string($message['other_nickname'] ?? null) ? $message['other_nickname'] : null,
                                    isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                                )); ?>
                            </td>
                            <td><?php echo sr_e($box === 'sent' ? ((string) ($message['read_at'] ?? '') === '' ? sr_t('community::ui.text.62808119') : sr_t('community::ui.text.3fe5701c')) : ((string) ($message['read_at'] ?? '') === '' ? sr_t('community::ui.text.eacc746d') : sr_t('community::ui.text.3fe5701c'))); ?></td>
                            <td><?php echo sr_community_time_html((string) $message['created_at']); ?></td>
                            <td><a href="<?php echo sr_e(sr_url('/community/message?id=' . (string) $message['id'])); ?>"><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></a></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/community/message/delete')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="message_id" value="<?php echo sr_e((string) $message['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
