<?php

$pageTitle = '회원 탈퇴';
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
                    <h2>남은 자산 처리</h2>
                    <ul>
                        <?php foreach ($withdrawalAssets as $assetKey => $asset) { ?>
                            <li>
                                <?php echo sr_e((string) $asset['label']); ?>
                                <?php echo sr_e(number_format((int) $asset['balance'])); ?>
                                <?php echo $assetKey === 'deposit' ? '환불 요청' : '소멸 처리'; ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if (isset($withdrawalAssets['deposit'])) { ?>
                        <p>예치금 환불을 위해 계좌 정보를 입력하세요.</p>
                        <p>
                            <label for="modules_member_withdraw_refund_bank">
                                <span>은행 <span class="sr-required-label">(필수)</span></span>
                                <input id="modules_member_withdraw_refund_bank" type="text" name="refund_bank" value="<?php echo sr_e((string) ($refundAccount['bank'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_holder">
                                <span>예금주 <span class="sr-required-label">(필수)</span></span>
                                <input id="modules_member_withdraw_refund_account_holder" type="text" name="refund_account_holder" value="<?php echo sr_e((string) ($refundAccount['holder'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_member_withdraw_refund_account_number">
                                <span>계좌번호 <span class="sr-required-label">(필수)</span></span>
                                <input id="modules_member_withdraw_refund_account_number" type="text" name="refund_account_number" value="<?php echo sr_e((string) ($refundAccount['number'] ?? '')); ?>" maxlength="80" required>
                            </label>
                        </p>
                    <?php } ?>
                </section>
            <?php } ?>
            <p>
                <label for="modules_member_withdraw_password">
                    <span>비밀번호 <span class="sr-required-label">(필수)</span></span>
                    <input id="modules_member_withdraw_password" type="password" name="password" required>
                </label>
            </p>
            <p>
                <label for="modules_member_withdraw_confirm_text">
                    <span>확인 문구 <span class="sr-required-label">(필수)</span></span>
                    <input id="modules_member_withdraw_confirm_text" type="text" name="confirm_text" required>
                </label>
            </p>
            <button type="submit">탈퇴</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">내 계정</a></p>
    </main>
<?php sr_public_layout_end(); ?>
