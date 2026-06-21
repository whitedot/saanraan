<?php

$pageTitle = sr_t('community::ui.text.a8ffa557');
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/message?id=' . (string) $message['id'],
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
    <main class="community-screen">
        <p>
            <a href="<?php echo sr_e(sr_url($messageBox === 'sent' ? '/community/messages?box=sent' : '/community/messages')); ?>">
                <?php echo sr_e($messageBox === 'sent' ? sr_t('community::ui.text.add34931') : sr_t('community::ui.text.1df1e319')); ?>
            </a>
        </p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <dl>
            <dt><?php echo sr_e(sr_t('community::ui.member.2d301cb0')); ?></dt>
            <dd><?php echo sr_e(sr_community_message_account_label(
                is_string($message['sender_display_name'] ?? null) ? $message['sender_display_name'] : null,
                (int) $message['sender_account_id'],
                $canViewMemberIdentifiers,
                $config,
                is_string($message['sender_account_status'] ?? null) ? $message['sender_account_status'] : null,
                is_string($message['sender_nickname'] ?? null) ? $message['sender_nickname'] : null,
                isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
            )); ?></dd>
            <dt><?php echo sr_e(sr_t('community::ui.member.a8116cfc')); ?></dt>
            <dd><?php echo sr_e(sr_community_message_account_label(
                is_string($message['recipient_display_name'] ?? null) ? $message['recipient_display_name'] : null,
                (int) $message['recipient_account_id'],
                $canViewMemberIdentifiers,
                $config,
                is_string($message['recipient_account_status'] ?? null) ? $message['recipient_account_status'] : null,
                is_string($message['recipient_nickname'] ?? null) ? $message['recipient_nickname'] : null,
                isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
            )); ?></dd>
            <dt><?php echo sr_e(sr_t('community::ui.text.4f639f73')); ?></dt>
            <dd><?php echo sr_community_time_html((string) $message['created_at']); ?></dd>
            <dt><?php echo sr_e(sr_t('community::ui.text.e37351b4')); ?></dt>
            <dd><?php echo sr_community_time_html((string) ($message['read_at'] ?? ''), '-'); ?></dd>
        </dl>
        <div>
            <?php echo sr_community_plain_text_html((string) $message['body_text']); ?>
        </div>

        <?php echo sr_public_feedback_toasts('community', $reportNotice, $reportErrors); ?>

        <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="target_type" value="message">
            <input type="hidden" name="target_id" value="<?php echo sr_e((string) $message['id']); ?>">
            <p>
                <label for="modules_community_message_view_reason_key">
                    <span><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <select id="modules_community_message_view_reason_key" name="reason_key" required>
                        <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                            <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label for="modules_community_message_view_memo_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></span>
                    <textarea id="modules_community_message_view_memo_text" name="memo_text" rows="3" cols="60"></textarea>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.text.2a07d26b')); ?></button>
        </form>

        <?php if ($replyAccountHash !== '') { ?>
            <p><a href="<?php echo sr_e(sr_url('/community/message/write?to_account=' . rawurlencode($replyAccountHash))); ?>"><?php echo sr_e(sr_t('community::ui.text.755cd430')); ?></a></p>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/community/message/delete')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="message_id" value="<?php echo sr_e((string) $message['id']); ?>">
            <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
        </form>
    </main>
<?php sr_public_layout_end(); ?>
