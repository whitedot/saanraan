<?php

$depositDisplayName = isset($depositDisplayName) && $depositDisplayName !== '' ? (string) $depositDisplayName : '예치금';
$depositUnitLabel = isset($depositUnitLabel) && $depositUnitLabel !== '' ? (string) $depositUnitLabel : '원';
$depositAmountLabel = static function (int $amount) use ($depositUnitLabel): string {
    return number_format($amount) . $depositUnitLabel;
};
$pageTitle = $depositDisplayName . ' 거래 내역';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/deposits'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>
        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>
        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">현재 잔액</h2>
            <p><?php echo sr_e($depositAmountLabel((int) $balance)); ?></p>
            <p>대기 중 환불 신청액: <?php echo sr_e($depositAmountLabel((int) $pendingRefundAmount)); ?></p>
            <p>환불 신청 가능액: <?php echo sr_e($depositAmountLabel((int) $availableRefundAmount)); ?></p>
        </div></section>
        <section id="deposit-refund-request" class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">환불 신청</h2>
            <?php if (empty($refundRequestsEnabled)) { ?>
                <p>현재 <?php echo sr_e($depositDisplayName); ?> 환불 신청을 받지 않습니다.</p>
            <?php } elseif (empty($canRequestRefund)) { ?>
                <p>현재 <?php echo sr_e($depositDisplayName); ?> 환불 신청 대상이 아닙니다.</p>
            <?php } elseif ((int) $availableRefundAmount < sr_deposit_refund_min_amount()) { ?>
                <p>환불 신청 가능액이 최소 신청 금액보다 적습니다.</p>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/account/deposits')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="refund_request">
                    <p>
                        <label for="deposit_refund_amount">
                            <span>신청 금액 <span class="sr-required-label">(필수)</span></span>
                            <input id="deposit_refund_amount" type="number" name="amount" min="<?php echo sr_e((string) sr_deposit_refund_min_amount()); ?>" max="<?php echo sr_e((string) min(sr_deposit_refund_max_amount(), (int) $availableRefundAmount)); ?>" step="1" required class="form-input">
                        </label>
                        <small>최소 <?php echo sr_e($depositAmountLabel(sr_deposit_refund_min_amount())); ?>, 최대 <?php echo sr_e($depositAmountLabel(sr_deposit_refund_max_amount())); ?>까지 신청할 수 있습니다.</small>
                    </p>
                <p>
                    <label for="deposit_refund_bank_name">
                        <span>은행명 <span class="sr-required-label">(필수)</span></span>
                        <input id="deposit_refund_bank_name" type="text" name="bank_name" maxlength="80" required class="form-input">
                    </label>
                </p>
                <p>
                    <label for="deposit_refund_bank_account_number">
                        <span>계좌번호 <span class="sr-required-label">(필수)</span></span>
                        <input id="deposit_refund_bank_account_number" type="text" name="bank_account_number" maxlength="80" required class="form-input">
                    </label>
                </p>
                <p>
                    <label for="deposit_refund_bank_account_holder">
                        <span>예금주 <span class="sr-required-label">(필수)</span></span>
                        <input id="deposit_refund_bank_account_holder" type="text" name="bank_account_holder" maxlength="80" required class="form-input">
                    </label>
                </p>
                <p>
                    <label for="deposit_refund_requester_note">
                        <span>요청 메모</span>
                        <input id="deposit_refund_requester_note" type="text" name="requester_note" maxlength="255" class="form-input">
                    </label>
                    <small>관리자가 환불 입금 확인에 참고할 내용을 적을 수 있습니다.</small>
                </p>
                    <button type="submit" class="btn btn-solid-primary">환불 신청</button>
                </form>
            <?php } ?>
        </div></section>
        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">환불 신청 내역</h2>
            <?php if ($refundRequests === []) { ?>
                <p>환불 신청 내역이 없습니다.</p>
            <?php } else { ?>
                <div class="table-wrapper">
                    <table class="table">
                    <thead>
                        <tr>
                            <th>신청일</th>
                            <th>금액</th>
                            <th>입금 계좌</th>
                            <th>상태</th>
                            <th>처리 메모</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($refundRequests as $request) { ?>
                            <tr>
                                <td><?php echo sr_deposit_time_html((string) $request['requested_at']); ?></td>
                                <td><?php echo sr_e($depositAmountLabel((int) $request['amount'])); ?></td>
                                <td><?php echo sr_e((string) $request['bank_name']); ?> <?php echo sr_e((string) $request['bank_account_number']); ?> <?php echo sr_e((string) $request['bank_account_holder']); ?></td>
                                <td><?php echo sr_e(sr_deposit_request_status_label((string) $request['status'])); ?></td>
                                <td><?php echo sr_e((string) $request['admin_note']); ?></td>
                                <td>
                                    <?php if ((string) $request['status'] === 'pending') { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/account/deposits')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="cancel_refund_request">
                                            <input type="hidden" name="request_id" value="<?php echo sr_e((string) $request['id']); ?>">
                                            <button type="submit" class="btn btn-solid-primary">취소</button>
                                        </form>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                    </div>
            <?php } ?>
        </div></section>
        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">거래 내역</h2>
            <?php if ($transactions === []) { ?>
                <p>거래 내역이 없습니다.</p>
            <?php } else { ?>
                <div class="table-wrapper">
                    <table class="table">
                    <thead>
                        <tr>
                            <th>일시</th>
                            <th>유형</th>
                            <th>변동</th>
                            <th>잔액</th>
                            <th>사유</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) { ?>
                            <tr>
                                <td><?php echo sr_deposit_time_html((string) $transaction['created_at']); ?></td>
                                <td><?php echo sr_e(sr_deposit_transaction_type_label((string) $transaction['transaction_type'])); ?></td>
                                <td><?php echo sr_e($depositAmountLabel((int) $transaction['amount'])); ?></td>
                                <td><?php echo sr_e($depositAmountLabel((int) $transaction['balance_after'])); ?></td>
                                <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                    </div>
            <?php } ?>
        </div></section>
    </main>
<?php sr_public_layout_end(); ?>
