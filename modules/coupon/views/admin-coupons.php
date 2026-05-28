<?php

$couponAdminPage = isset($couponAdminPage) ? (string) $couponAdminPage : 'list';
if (!in_array($couponAdminPage, ['list', 'create', 'issue'], true)) {
    $couponAdminPage = 'list';
}
$adminPageTitle = '쿠폰·이용권 관리';
$adminPageSubtitle = '지급 내역과 쿠폰 종류를 확인합니다.';
if ($couponAdminPage === 'create') {
    $adminPageSubtitle = '회원에게 지급할 쿠폰 종류를 만듭니다.';
} elseif ($couponAdminPage === 'issue') {
    $adminPageSubtitle = '만들어 둔 쿠폰 종류를 회원에게 지급합니다.';
}
$targetTypes = sr_coupon_target_types($pdo);
$refundablePolicies = sr_coupon_refundable_policies();
$definitionStatusLabels = [
    'active' => '사용 중',
    'disabled' => '중지',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($couponAdminPage === 'list') { ?>
<section class="admin-card card">
    <div class="admin-list-summary">
        <div>
            <h2>목록</h2>
            <p class="admin-form-help">지급 내역을 확인하거나 쿠폰 종류의 사용 상태를 바꿉니다.</p>
        </div>
        <div class="admin-form-actions">
            <a href="<?php echo sr_e(sr_url('/admin/coupons/issue')); ?>" class="btn btn-solid-primary">회원에게 지급</a>
            <a href="<?php echo sr_e(sr_url('/admin/coupons/new')); ?>" class="btn btn-solid-light">쿠폰 종류 만들기</a>
        </div>
    </div>
</section>
<?php } ?>

<?php if ($couponAdminPage === 'create') { ?>
<section class="admin-card card">
    <h2>쿠폰 종류 만들기</h2>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/new')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create_definition">
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_coupon_key">관리용 키 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_coupon_key" type="text" name="coupon_key" class="form-control" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" data-admin-key-input required>
                <p class="admin-form-help">관리자가 구분하기 위한 고유값입니다. 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_title">쿠폰 이름 <span class="sr-required-label">(필수)</span></label>
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
            <label class="form-label" for="coupon_admin_target_type">사용처 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="coupon_admin_target_type" name="target_type" class="form-select" required>
                    <?php foreach ($targetTypes as $targetType => $targetTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="coupon_admin_target_id">대상 번호</label>
            <div class="admin-form-field">
                <input id="coupon_admin_target_id" type="text" name="target_id" class="form-control" maxlength="80">
                <p class="admin-form-help">특정 콘텐츠나 게시글에만 쓰게 할 때 해당 번호를 입력합니다. 비워 두면 선택한 사용처 전체에 사용할 수 있습니다.</p>
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
            <label class="form-label" for="coupon_admin_max_uses">사용 횟수 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="coupon_admin_max_uses" type="number" name="max_uses_per_issue" class="form-control" min="1" max="1000" value="1" required>
                <p class="admin-form-help">회원에게 지급된 쿠폰 1장이 몇 번까지 사용할 수 있는지 정합니다.</p>
            </div>
        </div>
        <input type="hidden" name="status" value="active">
        <input type="hidden" name="coupon_type" value="access">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">쿠폰 종류 만들기</button>
            <a href="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="btn btn-solid-light">목록으로 돌아가기</a>
        </div>
    </form>
</section>
<?php } ?>

<?php if ($couponAdminPage === 'issue') { ?>
<section class="admin-card card">
    <h2>회원에게 지급</h2>
    <?php if ($definitions === []) { ?>
        <p class="admin-empty-state">지급할 수 있는 쿠폰 종류가 없습니다.</p>
        <p><a href="<?php echo sr_e(sr_url('/admin/coupons/new')); ?>" class="btn btn-solid-primary">쿠폰 종류 만들기</a></p>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/issue')); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="issue_coupon">
            <div class="admin-form-row">
                <label class="form-label" for="coupon_admin_issue_definition">쿠폰 종류 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="coupon_admin_issue_definition" name="coupon_definition_id" class="form-select" required>
                        <?php foreach ($definitions as $definition) { ?>
                            <option value="<?php echo sr_e((string) $definition['id']); ?>"><?php echo sr_e((string) $definition['title']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="coupon_admin_account_identifier">회원 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="coupon_admin_account_identifier" type="text" name="account_identifier" class="form-control" maxlength="80" required>
                    <p class="admin-form-help">회원 관리 화면의 공개 해시 또는 회원 ID를 입력합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="coupon_admin_issued_reason">지급 사유</label>
                <div class="admin-form-field">
                    <input id="coupon_admin_issued_reason" type="text" name="issued_reason" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-solid-primary">쿠폰 지급</button>
                <a href="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="btn btn-solid-light">목록으로 돌아가기</a>
            </div>
        </form>
    <?php } ?>
</section>
<?php } ?>

<?php if ($couponAdminPage === 'list') { ?>
<section class="admin-card card">
    <h2>쿠폰 종류</h2>
    <table class="table">
        <thead>
            <tr>
                <th>관리용 키</th>
                <th>쿠폰 이름</th>
                <th>사용처</th>
                <th>상태</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($definitions === []) { ?>
                <tr>
                    <td colspan="5" class="admin-empty-state">등록된 쿠폰 종류가 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($definitions as $definition) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $definition['coupon_key']); ?></td>
                        <td><?php echo sr_e((string) $definition['title']); ?></td>
                        <td><?php echo sr_e((string) ($targetTypes[(string) $definition['target_type']] ?? $definition['target_type'])); ?> <?php echo sr_e((string) $definition['target_id']); ?></td>
                        <td><?php echo sr_e((string) ($definitionStatusLabels[(string) $definition['status']] ?? $definition['status'])); ?></td>
                        <td>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="set_definition_status">
                                <input type="hidden" name="definition_id" value="<?php echo sr_e((string) $definition['id']); ?>">
                                <input type="hidden" name="status" value="<?php echo (string) $definition['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $definition['status'] === 'active' ? '사용 중지' : '다시 사용'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</section>

<section class="admin-card card">
    <h2>최근 지급 내역</h2>
    <table class="table">
        <thead>
            <tr>
                <th>회원</th>
                <th>쿠폰</th>
                <th>상태</th>
                <th>사용 횟수</th>
                <th>지급일</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($issues === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state">최근 지급 내역이 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($issues as $issue) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $issue['account_public_hash']); ?></td>
                        <td><?php echo sr_e((string) $issue['title']); ?></td>
                        <td><?php echo sr_e(sr_coupon_issue_status_label((string) $issue['status'])); ?></td>
                        <td><?php echo sr_e((string) $issue['used_count']); ?></td>
                        <td><?php echo sr_e((string) $issue['issued_at']); ?></td>
                        <td>
                            <?php if ((string) $issue['status'] === 'active') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="set_issue_status">
                                    <input type="hidden" name="issue_id" value="<?php echo sr_e((string) $issue['id']); ?>">
                                    <input type="hidden" name="status" value="revoked">
                                    <button type="submit" class="btn btn-sm btn-solid-light">지급 취소</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
