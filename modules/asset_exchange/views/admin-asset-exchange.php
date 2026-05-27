<?php

$adminPageTitle = '자산 환전 정책';
$editPolicy = isset($editPolicy) && is_array($editPolicy) ? $editPolicy : [];
$policyForm = array_merge([
    'id' => '',
    'from_module_key' => '',
    'to_module_key' => '',
    'status' => 'disabled',
    'rate_numerator' => 1,
    'rate_denominator' => 1,
    'min_amount' => 1,
    'max_amount' => '',
    'rounding_mode' => 'floor',
    'fee_trigger' => 'none',
    'fee_basis' => 'to_amount',
    'fee_rate_numerator' => 0,
    'fee_rate_denominator' => 1,
    'fee_fixed_amount' => 0,
    'fee_min_amount' => '',
    'fee_max_amount' => '',
    'sort_order' => 0,
], $editPolicy);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo sr_e((string) $policyForm['id']); ?>">
    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title"><?php echo (int) ($policyForm['id'] ?? 0) > 0 ? '환전 정책 수정' : '환전 정책 등록'; ?></h2>
        </div>
        <div class="admin-form-grid">
            <label class="admin-form-field" for="asset_exchange_from_module_key">
                <span class="admin-form-label">출금 자산 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_from_module_key" name="from_module_key" class="form-select" required>
                    <option value="">선택</option>
                    <?php foreach ($assets as $asset) { ?>
                        <option value="<?php echo sr_e((string) $asset['module_key']); ?>"<?php echo (string) $policyForm['from_module_key'] === (string) $asset['module_key'] ? ' selected' : ''; ?>><?php echo sr_e((string) $asset['label']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_to_module_key">
                <span class="admin-form-label">입금 자산 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_to_module_key" name="to_module_key" class="form-select" required>
                    <option value="">선택</option>
                    <?php foreach ($assets as $asset) { ?>
                        <option value="<?php echo sr_e((string) $asset['module_key']); ?>"<?php echo (string) $policyForm['to_module_key'] === (string) $asset['module_key'] ? ' selected' : ''; ?>><?php echo sr_e((string) $asset['label']); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_status">
                <span class="admin-form-label">상태 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_status" name="status" class="form-select" required>
                    <?php foreach (['enabled' => '사용', 'disabled' => '중지'] as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $policyForm['status'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_rounding_mode">
                <span class="admin-form-label">반올림 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_rounding_mode" name="rounding_mode" class="form-select" required>
                    <?php foreach (['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'] as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $policyForm['rounding_mode'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_rate_numerator">
                <span class="admin-form-label">비율 분자 <span class="admin-required">(필수)</span></span>
                <input id="asset_exchange_rate_numerator" type="number" name="rate_numerator" value="<?php echo sr_e((string) $policyForm['rate_numerator']); ?>" class="form-input" min="1" required>
                <span class="admin-form-help">출금 자산 1단위를 입금 자산 N단위로 계산할 때의 분자입니다.</span>
            </label>
            <label class="admin-form-field" for="asset_exchange_rate_denominator">
                <span class="admin-form-label">비율 분모 <span class="admin-required">(필수)</span></span>
                <input id="asset_exchange_rate_denominator" type="number" name="rate_denominator" value="<?php echo sr_e((string) $policyForm['rate_denominator']); ?>" class="form-input" min="1" required>
            </label>
            <label class="admin-form-field" for="asset_exchange_min_amount">
                <span class="admin-form-label">최소 환전량 <span class="admin-required">(필수)</span></span>
                <input id="asset_exchange_min_amount" type="number" name="min_amount" value="<?php echo sr_e((string) $policyForm['min_amount']); ?>" class="form-input" min="1" required>
            </label>
            <label class="admin-form-field" for="asset_exchange_max_amount">
                <span class="admin-form-label">최대 환전량</span>
                <input id="asset_exchange_max_amount" type="number" name="max_amount" value="<?php echo sr_e((string) $policyForm['max_amount']); ?>" class="form-input" min="0">
                <span class="admin-form-help">비워두면 1회 최대 금액을 제한하지 않습니다.</span>
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_trigger">
                <span class="admin-form-label">수수료 적용 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_fee_trigger" name="fee_trigger" class="form-select" required>
                    <?php foreach (['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 자산 재환전만 적용'] as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $policyForm['fee_trigger'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_basis">
                <span class="admin-form-label">수수료 기준 <span class="admin-required">(필수)</span></span>
                <select id="asset_exchange_fee_basis" name="fee_basis" class="form-select" required>
                    <?php foreach (['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'] as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $policyForm['fee_basis'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_rate_numerator">
                <span class="admin-form-label">수수료율 분자</span>
                <input id="asset_exchange_fee_rate_numerator" type="number" name="fee_rate_numerator" value="<?php echo sr_e((string) $policyForm['fee_rate_numerator']); ?>" class="form-input" min="0">
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_rate_denominator">
                <span class="admin-form-label">수수료율 분모</span>
                <input id="asset_exchange_fee_rate_denominator" type="number" name="fee_rate_denominator" value="<?php echo sr_e((string) $policyForm['fee_rate_denominator']); ?>" class="form-input" min="1">
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_fixed_amount">
                <span class="admin-form-label">정액 수수료</span>
                <input id="asset_exchange_fee_fixed_amount" type="number" name="fee_fixed_amount" value="<?php echo sr_e((string) $policyForm['fee_fixed_amount']); ?>" class="form-input" min="0">
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_min_amount">
                <span class="admin-form-label">최소 수수료</span>
                <input id="asset_exchange_fee_min_amount" type="number" name="fee_min_amount" value="<?php echo sr_e((string) $policyForm['fee_min_amount']); ?>" class="form-input" min="0">
            </label>
            <label class="admin-form-field" for="asset_exchange_fee_max_amount">
                <span class="admin-form-label">최대 수수료</span>
                <input id="asset_exchange_fee_max_amount" type="number" name="fee_max_amount" value="<?php echo sr_e((string) $policyForm['fee_max_amount']); ?>" class="form-input" min="0">
            </label>
            <label class="admin-form-field" for="asset_exchange_sort_order">
                <span class="admin-form-label">정렬순서</span>
                <input id="asset_exchange_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) $policyForm['sort_order']); ?>" class="form-input">
            </label>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">저장</button>
            <?php if ((int) ($policyForm['id'] ?? 0) > 0) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="btn btn-solid-light">새 정책</a>
            <?php } ?>
        </div>
    </section>
</form>

<section class="admin-card admin-list-card card">
    <div class="card-header"><h2 class="card-title">정책 목록</h2></div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>출금 자산</th>
                    <th>입금 자산</th>
                    <th>비율</th>
                    <th>금액 제한</th>
                    <th>수수료</th>
                    <th>상태</th>
                    <th>수정일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($policies === []) { ?>
                    <tr><td colspan="8" class="admin-empty-state">등록된 환전 정책이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($policies as $policy) { ?>
                    <?php
                    $fromMissing = !isset($assets[(string) $policy['from_module_key']]);
                    $toMissing = !isset($assets[(string) $policy['to_module_key']]);
                    ?>
                    <tr>
                        <td><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['from_module_key'])); ?><?php echo $fromMissing ? ' (비활성)' : ''; ?></td>
                        <td><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['to_module_key'])); ?><?php echo $toMissing ? ' (비활성)' : ''; ?></td>
                        <td><?php echo sr_e(number_format((int) $policy['rate_numerator']) . ' / ' . number_format((int) $policy['rate_denominator'])); ?></td>
                        <td><?php echo sr_e(number_format((int) $policy['min_amount']) . ' - ' . ($policy['max_amount'] === null ? '제한 없음' : number_format((int) $policy['max_amount']))); ?></td>
                        <td><?php echo sr_e((string) $policy['fee_trigger']); ?></td>
                        <td><?php echo sr_e((string) $policy['status']); ?><?php echo $fromMissing || $toMissing ? ' / 실행 불가' : ''; ?></td>
                        <td><?php echo sr_e((string) $policy['updated_at']); ?></td>
                        <td class="text-end"><a href="<?php echo sr_e(sr_url('/admin/asset-exchange?edit=' . (int) $policy['id'])); ?>" class="btn btn-sm btn-solid-light">수정</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
