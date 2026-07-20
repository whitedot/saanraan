<?php

$pageTitle = sr_t('member::ui.member.4406c379');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <?php echo sr_member_feedback_toasts('', $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">

        <form method="post" action="<?php echo sr_e(sr_url('/account/withdraw')); ?>" class="member-skin-basic-form" data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <?php if (!empty($withdrawIdentityPolicy['required'])) { ?>
                <div class="alert <?php echo !empty($withdrawIdentityPolicy['satisfied']) ? 'alert-success' : 'alert-warning'; ?>">
                    <p><?php echo !empty($withdrawIdentityPolicy['satisfied']) ? sr_e('회원탈퇴 본인확인이 완료되었습니다.') : sr_e('회원탈퇴 전 본인확인이 필요합니다.'); ?></p>
                    <?php if (empty($withdrawIdentityPolicy['satisfied']) && !empty($withdrawIdentityPolicy['start_url'])) { ?>
                        <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $withdrawIdentityPolicy['start_url']); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if (($withdrawalAssets ?? []) !== []) { ?>
                <section>
                    <h2><?php echo sr_e(sr_t('member::ui.text.8a351546')); ?></h2>
                    <ul>
                        <?php foreach ($withdrawalAssets as $assetKey => $asset) { ?>
                            <li>
                                <?php echo sr_e((string) $asset['label']); ?>
                                <?php echo sr_e(number_format((int) $asset['balance'])); ?>
                                <?php if ($assetKey === 'coupon') { ?>
                                    <?php echo sr_e((string) ($asset['unit_label'] ?? '개')); ?> <?php echo sr_e((string) $asset['process_label']); ?>
                                <?php } else { ?>
                                    <?php echo sr_e($assetKey === 'deposit' ? sr_t('member::ui.text.d3263170') : sr_t('member::ui.text.9f2904c9')); ?>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if (isset($withdrawalAssets['deposit'])) { ?>
                        <p><?php echo sr_e(sr_t('member::ui.deposit.cc2fa304')); ?></p>
                        <p>
                            <label for="modules_member_withdraw_refund_bank">
                                <span><?php echo sr_e(sr_t('member::ui.text.9630c622')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input class="form-input" id="modules_member_withdraw_refund_bank" type="text" name="refund_bank" value="<?php echo sr_e((string) ($refundAccount['bank'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_holder">
                                <span><?php echo sr_e(sr_t('member::ui.text.71b9138c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input class="form-input" id="modules_member_withdraw_refund_account_holder" type="text" name="refund_account_holder" value="<?php echo sr_e((string) ($refundAccount['holder'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_number">
                                <span><?php echo sr_e(sr_t('member::ui.text.e93f44ec')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                                <input class="form-input" id="modules_member_withdraw_refund_account_number" type="text" name="refund_account_number" value="<?php echo sr_e((string) ($refundAccount['number'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                    <?php } ?>
                </section>
            <?php } ?>
            <p>
                <label for="modules_member_withdraw_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input class="form-input" id="modules_member_withdraw_password" type="password" name="password" required>
                </label>
            </p>
            <p>
                <label for="modules_member_withdraw_confirm_text">
                    <span><?php echo sr_e(sr_t('member::ui.text.82e63a67')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                    <input class="form-input" id="modules_member_withdraw_confirm_text" type="text" name="confirm_text" required>
                </label>
                <small class="ui-kit-hint"><?php echo sr_e(sr_t('member::action.withdraw.confirm_help', ['phrase' => sr_t('member::action.withdraw.confirm_text')])); ?></small>
            </p>
            <button class="btn btn-solid-primary btn-block" type="submit"><?php echo sr_e(sr_t('member::ui.text.871d2076')); ?></button>
        </form>
                <div class="member-skin-basic-actions">
                    <a class="btn btn-outline-default btn-block" href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('member::ui.text.13b28045')); ?></a>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
