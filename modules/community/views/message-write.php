<?php

$pageTitle = sr_t('community::ui.text.288b8b7e');
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/message/write',
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
    <main class="community-screen">
        <p>
            <a href="<?php echo sr_e(sr_url('/community/messages')); ?>"><?php echo sr_e(sr_t('community::ui.text.b546791f')); ?></a>
        </p>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php echo sr_public_feedback_toasts('community', $recipientPresetNotice, $errors); ?>

        <form method="post" action="<?php echo sr_e(sr_url('/community/message/write')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="recipient_account_hash" value="">
            <p>
                <label for="modules_community_message_write_recipient_identifier">
                    <span><?php echo sr_e(sr_t('community::ui.member.a8116cfc')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <span class="community-recipient-picker" data-sr-recipient-picker>
                        <span class="community-recipient-selected" data-sr-recipient-picker-selected>
                            <?php foreach (is_array($recipientPickerItems ?? null) ? $recipientPickerItems : [] as $recipientPickerItem) { ?>
                                <?php $recipientPickerHash = (string) ($recipientPickerItem['hash'] ?? ''); ?>
                                <?php $recipientPickerLabel = (string) ($recipientPickerItem['label'] ?? ''); ?>
                                <?php if ($recipientPickerHash !== '' && $recipientPickerLabel !== '') { ?>
                                    <span class="community-recipient-chip" data-recipient-hash="<?php echo sr_e($recipientPickerHash); ?>">
                                        <span><?php echo sr_e($recipientPickerLabel); ?></span>
                                        <button type="button" class="community-recipient-chip-remove" aria-label="<?php echo sr_e($recipientPickerLabel . ' 제거'); ?>">×</button>
                                        <input type="hidden" name="recipient_account_hashes[]" value="<?php echo sr_e($recipientPickerHash); ?>">
                                    </span>
                                <?php } ?>
                            <?php } ?>
                        </span>
                        <input id="modules_community_message_write_recipient_identifier" type="text" name="recipient_identifier" value="<?php echo sr_e(is_string($values['recipient_identifier']) ? $values['recipient_identifier'] : ''); ?>" maxlength="255" required data-sr-recipient-picker-input data-sr-recipient-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>" autocomplete="off">
                    </span>
                </label>
            </p>
            <p>
                <label for="modules_community_message_write_body_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.cb0f2404')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <textarea id="modules_community_message_write_body_text" name="body_text" rows="10" cols="80" required><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.text.9aee63bd')); ?></button>
        </form>
    </main>
<?php sr_public_layout_end(); ?>
