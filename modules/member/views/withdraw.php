<?php

$pageTitle = sr_t('member::ui.member.4406c379');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/account/withdraw')); ?>">
            <?php echo sr_csrf_field(); ?>
            <?php if (($withdrawalAssets ?? []) !== []) { ?>
                <section>
                    <h2><?php echo sr_e(sr_t('member::ui.text.8a351546')); ?></h2>
                    <ul>
                        <?php foreach ($withdrawalAssets as $assetKey => $asset) { ?>
                            <li>
                                <?php echo sr_e((string) $asset['label']); ?>
                                <?php echo sr_e(number_format((int) $asset['balance'])); ?>
                                <?php echo $assetKey === 'deposit' ? sr_t('member::ui.text.d3263170') : sr_t('member::ui.text.9f2904c9'); ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if (isset($withdrawalAssets['deposit'])) { ?>
                        <p><?php echo sr_e(sr_t('member::ui.deposit.cc2fa304')); ?></p>
                        <p>
                            <label for="modules_member_withdraw_refund_bank">
                                <span><?php echo sr_e(sr_t('member::ui.text.9630c622')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_member_withdraw_refund_bank" type="text" name="refund_bank" value="<?php echo sr_e((string) ($refundAccount['bank'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_holder">
                                <span><?php echo sr_e(sr_t('member::ui.text.71b9138c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_member_withdraw_refund_account_holder" type="text" name="refund_account_holder" value="<?php echo sr_e((string) ($refundAccount['holder'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_number">
                                <span><?php echo sr_e(sr_t('member::ui.text.e93f44ec')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_member_withdraw_refund_account_number" type="text" name="refund_account_number" value="<?php echo sr_e((string) ($refundAccount['number'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                    <?php } ?>
                </section>
            <?php } ?>
            <p>
                <label for="modules_member_withdraw_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_member_withdraw_password" type="password" name="password" required>
                </label>
            </p>
            <p>
                <label for="modules_member_withdraw_confirm_text">
                    <span><?php echo sr_e(sr_t('member::ui.text.82e63a67')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_member_withdraw_confirm_text" type="text" name="confirm_text" required>
                </label>
                <small><?php echo sr_e(sr_t('member::action.withdraw.confirm_help', ['phrase' => sr_t('member::action.withdraw.confirm_text')])); ?></small>
            </p>
            <button type="submit"><?php echo sr_e(sr_t('member::ui.text.871d2076')); ?></button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('member::ui.text.13b28045')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
