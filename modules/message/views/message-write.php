<?php

$pageTitle = '쪽지 쓰기';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/message/write',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'consumer_domain' => 'message',
    'module_home_url' => sr_url('/messages'),
    'module_label' => '쪽지',
    'scripts' => ['/assets/member-recipient-picker.js'],
]);
?>
    <main class="ui-page message-screen">
        <header class="ui-page-header">
            <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
            <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/messages')); ?>">쪽지함</a>
        </header>

        <?php echo sr_public_feedback_toasts('message-write', $recipientPresetNotice, $errors); ?>

        <form method="post" action="<?php echo sr_e(sr_url('/message/write')); ?>" class="card">
            <div class="card-body ui-card-body-stack">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="recipient_account_hash" value="">
            <p>
                <label class="ui-field" for="modules_message_write_recipient_identifier">
                    <span>수신자 <span class="sr-required-label">(필수)</span></span>
                    <span class="message-recipient-picker" data-sr-recipient-picker>
                        <span class="message-recipient-selected" data-sr-recipient-picker-selected>
                            <?php foreach (is_array($recipientPickerItems ?? null) ? $recipientPickerItems : [] as $recipientPickerItem) { ?>
                                <?php $recipientPickerHash = (string) ($recipientPickerItem['hash'] ?? ''); ?>
                                <?php $recipientPickerLabel = (string) ($recipientPickerItem['label'] ?? ''); ?>
                                <?php if ($recipientPickerHash !== '' && $recipientPickerLabel !== '') { ?>
                                    <span class="message-recipient-chip" data-recipient-hash="<?php echo sr_e($recipientPickerHash); ?>">
                                        <span><?php echo sr_e($recipientPickerLabel); ?></span>
                                        <button type="button" class="message-recipient-chip-remove" aria-label="<?php echo sr_e($recipientPickerLabel . ' 제거'); ?>">x</button>
                                        <input type="hidden" name="recipient_account_hashes[]" value="<?php echo sr_e($recipientPickerHash); ?>">
                                    </span>
                                <?php } ?>
                            <?php } ?>
                        </span>
                        <input id="modules_message_write_recipient_identifier" type="text" name="recipient_identifier" value="<?php echo sr_e(is_string($values['recipient_identifier']) ? $values['recipient_identifier'] : ''); ?>" maxlength="255" required class="form-input form-control-medium" data-sr-recipient-picker-input data-sr-recipient-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>" autocomplete="off">
                    </span>
                </label>
            </p>
            <p>
                <label class="ui-field" for="modules_message_write_body_text">
                    <span>내용 <span class="sr-required-label">(필수)</span></span>
                    <textarea id="modules_message_write_body_text" name="body_text" rows="10" cols="80" required class="form-textarea form-control-wide"><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary">보내기</button>
            </div>
        </form>
    </main>
<?php sr_public_layout_end(); ?>
