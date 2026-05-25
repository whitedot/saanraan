<?php

$adminPageTitle = '쿠폰 관리';
$adminPageSubtitle = '쿠폰 정의, 회원 발급, 사용 상태를 관리합니다.';
$targetTypes = sr_coupon_target_types();
$refundablePolicies = sr_coupon_refundable_policies();
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-card card">
    <h2>쿠폰 정의 등록</h2>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create_definition">
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_coupon_key">쿠폰 키 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_coupon_key" type="text" name="coupon_key" class="form-control" maxlength="60" pattern="[a-z0-9_]+" data-admin-key-input required>
                <p class="admin-form-help">소문자, 숫자, 밑줄만 사용합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_title">이름 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_title" type="text" name="title" class="form-control" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_description">설명</label>
            <div class="admin-form-field">
                <textarea id="coupon_admin_description" name="description" class="form-control" rows="3"></textarea>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_target_type">대상 유형 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="coupon_admin_target_type" name="target_type" class="form-select" required>
                    <?php foreach ($targetTypes as $targetType => $targetTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_target_id">대상 ID</label>
            <div class="admin-form-field">
                <input id="coupon_admin_target_id" type="text" name="target_id" class="form-control" maxlength="80">
                <p class="admin-form-help">비워 두면 대상 유형 전체에 사용할 수 있습니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_refundable_policy">환급 정책 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="coupon_admin_refundable_policy" name="refundable_policy" class="form-select" required>
                    <?php foreach ($refundablePolicies as $policy => $policyLabel) { ?>
                        <option value="<?php echo sr_e((string) $policy); ?>"><?php echo sr_e((string) $policyLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_max_uses">사용 가능 횟수 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_max_uses" type="number" name="max_uses_per_issue" class="form-control" min="1" max="1000" value="1" required>
            </div>
        </div>
        <input type="hidden" name="status" value="active">
        <input type="hidden" name="coupon_type" value="access">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">정의 등록</button>
        </div>
    </form>
</section>

<section class="admin-card card">
    <h2>회원 발급</h2>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="issue_coupon">
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_issue_definition">쿠폰 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="coupon_admin_issue_definition" name="coupon_definition_id" class="form-select" required>
                    <?php foreach ($definitions as $definition) { ?>
                        <option value="<?php echo sr_e((string) $definition['id']); ?>"><?php echo sr_e((string) $definition['title']); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_account_identifier">회원 공개 해시 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_account_identifier" type="text" name="account_identifier" class="form-control" maxlength="80" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_issued_reason">발급 사유</label>
            <div class="admin-form-field">
                <input id="coupon_admin_issued_reason" type="text" name="issued_reason" class="form-control" maxlength="255">
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">발급</button>
        </div>
    </form>
</section>

<section class="admin-card card">
    <h2>쿠폰 정의</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>키</th>
                <th>이름</th>
                <th>대상</th>
                <th>상태</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($definitions as $definition) { ?>
                <tr>
                    <td><?php echo sr_e((string) $definition['id']); ?></td>
                    <td><?php echo sr_e((string) $definition['coupon_key']); ?></td>
                    <td><?php echo sr_e((string) $definition['title']); ?></td>
                    <td><?php echo sr_e((string) $definition['target_type']); ?> <?php echo sr_e((string) $definition['target_id']); ?></td>
                    <td><?php echo sr_e((string) $definition['status']); ?></td>
                    <td>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="set_definition_status">
                            <input type="hidden" name="definition_id" value="<?php echo sr_e((string) $definition['id']); ?>">
                            <input type="hidden" name="status" value="<?php echo (string) $definition['status'] === 'active' ? 'disabled' : 'active'; ?>">
                            <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $definition['status'] === 'active' ? '중지' : '사용'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<section class="admin-card card">
    <h2>최근 발급</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>회원 ID</th>
                <th>쿠폰</th>
                <th>상태</th>
                <th>사용</th>
                <th>발급일</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($issues as $issue) { ?>
                <tr>
                    <td><?php echo sr_e((string) $issue['id']); ?></td>
                    <td><?php echo sr_e((string) $issue['account_id']); ?></td>
                    <td><?php echo sr_e((string) $issue['title']); ?></td>
                    <td><?php echo sr_e((string) $issue['status']); ?></td>
                    <td><?php echo sr_e((string) $issue['used_count']); ?></td>
                    <td><?php echo sr_e((string) $issue['issued_at']); ?></td>
                    <td>
                        <?php if ((string) $issue['status'] === 'active') { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="set_issue_status">
                                <input type="hidden" name="issue_id" value="<?php echo sr_e((string) $issue['id']); ?>">
                                <input type="hidden" name="status" value="revoked">
                                <button type="submit" class="btn btn-sm btn-solid-light">회수</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
