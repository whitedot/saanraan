<?php

$adminPageTitle = $detail !== null ? '본인확인 상세' : '본인확인 이력';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($detail !== null) { ?>
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">시도 #<?php echo sr_e((string) $detail['id']); ?></h2>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/identity-verifications')); ?>">목록</a>
        </div>
        <table class="table">
            <tbody>
                <?php foreach (['verification_key', 'provider_key', 'purpose', 'account_id', 'status', 'provider_transaction_id', 'provider_reference', 'failure_code', 'failure_message', 'requested_at', 'completed_at', 'failed_at', 'expires_at'] as $field) { ?>
                    <tr>
                        <th><?php echo sr_e($field); ?></th>
                        <td><?php echo sr_e((string) ($detail[$field] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>

    <?php if ($detailResult !== null) { ?>
        <section class="card">
            <h2>검증 결과 요약</h2>
            <table class="table">
                <tbody>
                    <?php foreach (['id', 'provider_key', 'provider_transaction_id', 'birth_date', 'gender', 'nationality', 'age_over_14', 'age_over_19', 'verified_at', 'expires_at'] as $field) { ?>
                        <tr>
                            <th><?php echo sr_e($field); ?></th>
                            <td><?php echo sr_e((string) ($detailResult[$field] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach (['ci_hash' => 'CI', 'di_hash' => 'DI', 'name_hash' => '이름', 'phone_hash' => '휴대폰'] as $field => $label) { ?>
                        <tr>
                            <th><?php echo sr_e($label . ' 식별자'); ?></th>
                            <td><?php echo (string) ($detailResult[$field] ?? '') !== '' ? sr_e('보관됨') : sr_e('없음'); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    <?php } ?>

    <?php if ($detailLinks !== []) { ?>
        <section class="card">
            <h2>계정 연결</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>계정</th>
                        <th>목적</th>
                        <th>연결</th>
                        <th>해제</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailLinks as $link) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $link['id']); ?></td>
                            <td><?php echo sr_e((string) $link['account_id']); ?></td>
                            <td><?php echo sr_e((string) $link['purpose']); ?></td>
                            <td><?php echo sr_relative_time_html((string) $link['linked_at']); ?></td>
                            <td><?php echo sr_relative_time_html((string) ($link['revoked_at'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    <?php } ?>
<?php } ?>

<section class="card">
    <div class="card-header">
        <h2 class="card-title">최근 시도</h2>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>">환경설정</a>
    </div>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/identity-verifications')); ?>" class="admin-filter-form">
        <select name="status" class="form-select">
            <option value="">모든 상태</option>
            <?php foreach (['ready', 'pending', 'verified', 'failed', 'expired', 'canceled'] as $statusOption) { ?>
                <option value="<?php echo sr_e($statusOption); ?>"<?php echo $status === $statusOption ? ' selected' : ''; ?>><?php echo sr_e($statusOption); ?></option>
            <?php } ?>
        </select>
        <select name="provider_key" class="form-select">
            <option value="">모든 제공자</option>
            <?php foreach ($providers as $key => $provider) { ?>
                <option value="<?php echo sr_e((string) $key); ?>"<?php echo $providerKey === (string) $key ? ' selected' : ''; ?>><?php echo sr_e((string) ($provider['display_name'] ?? $key)); ?></option>
            <?php } ?>
        </select>
        <button type="submit" class="btn btn-solid-light">필터</button>
    </form>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>제공자</th>
                <th>목적</th>
                <th>계정</th>
                <th>상태</th>
                <th>요청</th>
                <th>검증</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attempts as $attempt) { ?>
                <tr>
                    <td><a href="<?php echo sr_e(sr_url('/admin/identity-verifications/' . (int) $attempt['id'])); ?>">#<?php echo sr_e((string) $attempt['id']); ?></a></td>
                    <td><?php echo sr_e((string) $attempt['provider_key']); ?></td>
                    <td><?php echo sr_e((string) $attempt['purpose']); ?></td>
                    <td><?php echo sr_e((string) ($attempt['account_id'] ?? '')); ?></td>
                    <td><span class="badge"><?php echo sr_e((string) $attempt['status']); ?></span></td>
                    <td><?php echo sr_relative_time_html((string) $attempt['requested_at']); ?></td>
                    <td><?php echo sr_relative_time_html((string) ($attempt['verified_at'] ?? '')); ?></td>
                </tr>
            <?php } ?>
            <?php if ($attempts === []) { ?>
                <tr><td colspan="7">본인확인 시도가 없습니다.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
