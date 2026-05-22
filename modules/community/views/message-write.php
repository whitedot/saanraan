<?php

$pageTitle = sr_t('community::ui.text.288b8b7e');
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/message/write',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <p>
            <a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a>
            /
            <a href="<?php echo sr_e(sr_url('/community/messages')); ?>"><?php echo sr_e(sr_t('community::ui.text.b546791f')); ?></a>
        </p>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if ($recipientPresetNotice !== '') { ?>
            <p><?php echo sr_e($recipientPresetNotice); ?></p>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/community/message/write')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <?php if (is_string($values['recipient_account_hash'] ?? null) && $values['recipient_account_hash'] !== '') { ?>
                    <input type="hidden" name="recipient_account_hash" value="<?php echo sr_e((string) $values['recipient_account_hash']); ?>">
                    <?php echo sr_e(sr_t('community::ui.member.a8116cfc')); ?><br>
                    <?php echo sr_e($recipientLabel); ?>
                <?php } else { ?>
                    <label for="modules_community_message_write_recipient_identifier">
                    <span><?php echo sr_e(sr_t('community::ui.member.email.bca68450')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_community_message_write_recipient_identifier" type="text" name="recipient_identifier" value="<?php echo sr_e(is_string($values['recipient_identifier']) ? $values['recipient_identifier'] : ''); ?>" maxlength="255" required>
                    </label>
                <?php } ?>
            </p>
            <p>
                <label for="modules_community_message_write_body_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.cb0f2404')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <textarea id="modules_community_message_write_body_text" name="body_text" rows="10" cols="80" required><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <button type="submit"><?php echo sr_e(sr_t('community::ui.text.9aee63bd')); ?></button>
        </form>
    </main>
<?php sr_public_layout_end(); ?>
