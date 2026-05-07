<?php

$adminPageTitle = '포인트 관리';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<section>
    <h2>회원 조회</h2>
    <form method="get" action="<?php echo toy_e(toy_url('/admin/points')); ?>">
        <label>회원 ID<br>
            <input type="number" name="account_id" value="<?php echo $accountIdFilter > 0 ? toy_e((string) $accountIdFilter) : ''; ?>" min="1">
        </label>
        <button type="submit">조회</button>
    </form>

    <?php if (is_array($selectedAccount)) { ?>
        <p>
            <?php echo toy_e((string) $selectedAccount['display_name']); ?>
            (<?php echo toy_e((string) $selectedAccount['email']); ?>)
            잔액: <?php echo toy_e(number_format((int) $selectedBalance)); ?> P
        </p>
    <?php } elseif ($accountIdFilter > 0) { ?>
        <p>회원을 찾을 수 없습니다.</p>
    <?php } ?>
</section>

<section>
    <h2>포인트 조정</h2>
    <form method="post" action="<?php echo toy_e(toy_url('/admin/points' . ($accountIdFilter > 0 ? '?account_id=' . (string) $accountIdFilter : ''))); ?>">
        <?php echo toy_csrf_field(); ?>
        <p>
            <label>회원 ID<br>
                <input type="number" name="account_id" value="<?php echo $accountIdFilter > 0 ? toy_e((string) $accountIdFilter) : ''; ?>" min="1" required>
            </label>
        </p>
        <p>
            <label>거래 유형<br>
                <select name="transaction_type">
                    <?php foreach ($allowedTransactionTypes as $type) { ?>
                        <option value="<?php echo toy_e($type); ?>"><?php echo toy_e($type); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>수량<br>
                <input type="number" name="amount" step="1" required>
            </label>
            <br>
            지급/환불은 양수, 사용/만료는 음수, 조정은 양수 또는 음수로 입력합니다.
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
        <button type="submit">저장</button>
    </form>
</section>

<section>
    <h2>최근 잔액</h2>
    <?php if ($balances === []) { ?>
        <p>포인트 잔액이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>회원 ID</th>
                    <th>회원</th>
                    <th>상태</th>
                    <th>잔액</th>
                    <th>수정일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($balances as $balance) { ?>
                    <tr>
                        <td><a href="<?php echo toy_e(toy_url('/admin/points?account_id=' . (string) $balance['account_id'])); ?>"><?php echo toy_e((string) $balance['account_id']); ?></a></td>
                        <td><?php echo toy_e((string) $balance['display_name']); ?><br><?php echo toy_e((string) $balance['email']); ?></td>
                        <td><?php echo toy_e((string) $balance['status']); ?></td>
                        <td><?php echo toy_e(number_format((int) $balance['balance'])); ?> P</td>
                        <td><?php echo toy_e((string) $balance['updated_at']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<section>
    <h2>최근 거래</h2>
    <?php if ($transactions === []) { ?>
        <p>포인트 거래가 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>유형</th>
                    <th>수량</th>
                    <th>거래 후 잔액</th>
                    <th>사유</th>
                    <th>참조</th>
                    <th>생성일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $transaction['id']); ?></td>
                        <td><?php echo toy_e((string) $transaction['display_name']); ?><br><?php echo toy_e((string) $transaction['email']); ?></td>
                        <td><?php echo toy_e((string) $transaction['transaction_type']); ?></td>
                        <td><?php echo toy_e(number_format((int) $transaction['amount'])); ?> P</td>
                        <td><?php echo toy_e(number_format((int) $transaction['balance_after'])); ?> P</td>
                        <td><?php echo toy_e((string) $transaction['reason']); ?></td>
                        <td><?php echo toy_e((string) $transaction['reference_type'] . ((string) $transaction['reference_id'] !== '' ? ':' . (string) $transaction['reference_id'] : '')); ?></td>
                        <td><?php echo toy_e((string) $transaction['created_at']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
