<?php

$depositAdminPage = isset($depositAdminPage) ? (string) $depositAdminPage : 'balances';
$adminPageTitle = '예치금 관리';
if ($depositAdminPage === 'adjust') {
    $adminPageTitle = '예치금 조정';
} elseif ($depositAdminPage === 'transactions') {
    $adminPageTitle = '예치금 거래 내역';
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

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

<div class="member-summary">
    <div class="member-summary-links">
        <a href="<?php echo sr_e(sr_url('/admin/deposits/balances')); ?>" class="btn btn-surface-default-soft">잔액</a>
        <a href="<?php echo sr_e(sr_url('/admin/deposits/adjust')); ?>" class="btn btn-surface-default-soft">조정</a>
        <a href="<?php echo sr_e(sr_url('/admin/deposits/transactions')); ?>" class="btn btn-surface-default-soft">거래 내역</a>
    </div>
</div>

<section>
    <h2>회원 조회</h2>
    <form method="get" action="<?php echo sr_e(sr_url($depositAdminPage === 'transactions' ? '/admin/deposits/transactions' : ($depositAdminPage === 'adjust' ? '/admin/deposits/adjust' : '/admin/deposits/balances'))); ?>">
        <label>회원 공개 해시<br>
            <input type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" maxlength="80">
        </label>
        <button type="submit">조회</button>
    </form>

    <?php if (is_array($selectedAccount)) { ?>
        <p>
            <?php echo sr_e((string) $selectedAccount['display_name']); ?>
            (<?php echo sr_e((string) $selectedAccount['email']); ?>)
            공개 해시: <?php echo sr_e((string) $selectedAccount['account_public_hash']); ?>
            잔액: <?php echo sr_e(number_format((int) $selectedBalance)); ?> 원
        </p>
    <?php } elseif ($accountIdentifierFilter !== '') { ?>
        <p>회원을 찾을 수 없습니다.</p>
    <?php } ?>
</section>

<?php if ($depositAdminPage === 'adjust') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/adjust' . ($accountIdentifierFilter !== '' ? '?account_identifier=' . rawurlencode($accountIdentifierFilter) : ''))); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
        <section class="card">
            <h2>예치금 조정</h2>
            <?php echo sr_csrf_field(); ?>
            <p>
                <label>회원 공개 해시<br>
                    <input type="text" name="account_identifier" value="<?php echo sr_e($accountIdentifierFilter); ?>" maxlength="80" required>
                </label>
            </p>
            <p>
                <label>거래 유형<br>
                    <select name="transaction_type">
                        <?php foreach ($allowedTransactionTypes as $type) { ?>
                            <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'transaction_type')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>금액<br>
                    <input type="number" name="amount" step="1" required>
                </label>
                <br>
                예치/환불은 양수, 사용/출금은 음수, 조정은 양수 또는 음수로 입력합니다.
            </p>
            <p>
                <label>사유<br>
                    <input type="text" name="reason" maxlength="255" required>
                </label>
            </p>
            <p>
                <label>참조 유형<br>
                    <input type="text" name="reference_type" maxlength="60">
                </label>
            </p>
            <p>
                <label>참조 ID<br>
                    <input type="text" name="reference_id" maxlength="120">
                </label>
            </p>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/deposits/balances')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } elseif ($depositAdminPage === 'transactions') { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header"><h2 class="card-title">최근 거래</h2></div>
        <?php if ($transactions === []) { ?>
            <p>예치금 거래가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>ID</th>
                        <th>회원</th>
                        <th>유형</th>
                        <th>금액</th>
                        <th>거래 후 잔액</th>
                        <th>사유</th>
                        <th>참조</th>
                        <th>생성일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $transaction['id']); ?></td>
                            <td>
                                <?php echo sr_e((string) $transaction['display_name']); ?><br>
                                <?php echo sr_e((string) $transaction['email']); ?><br>
                                <?php echo sr_e((string) $transaction['account_public_hash']); ?>
                            </td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $transaction['transaction_type'], 'transaction_type')); ?></td>
                            <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?> 원</td>
                            <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?> 원</td>
                            <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            <td><?php echo sr_e((string) $transaction['reference_type'] . ((string) $transaction['reference_id'] !== '' ? ':' . (string) $transaction['reference_id'] : '')); ?></td>
                            <td><?php echo sr_e((string) $transaction['created_at']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } else { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header"><h2 class="card-title">최근 잔액</h2></div>
        <?php if ($balances === []) { ?>
            <p>예치금 잔액이 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>회원 공개 해시</th>
                        <th>회원</th>
                        <th>상태</th>
                        <th>잔액</th>
                        <th>수정일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($balances as $balance) { ?>
                        <tr>
                            <td><a href="<?php echo sr_e(sr_url('/admin/deposits/transactions?account_identifier=' . rawurlencode((string) $balance['account_public_hash']))); ?>"><?php echo sr_e((string) $balance['account_public_hash']); ?></a></td>
                            <td><?php echo sr_e((string) $balance['display_name']); ?><br><?php echo sr_e((string) $balance['email']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $balance['status'], 'member_status')); ?></td>
                            <td><?php echo sr_e(number_format((int) $balance['balance'])); ?> 원</td>
                            <td><?php echo sr_e((string) $balance['updated_at']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
