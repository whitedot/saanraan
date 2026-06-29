<?php

$adminPageTitle = '포인트/금액 환전 기준값';
$adminContainerClass = 'admin-page-asset-exchange admin-ui-scope';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$assets = isset($assets) && is_array($assets) ? $assets : [];
$relativeValues = isset($relativeValues) && is_array($relativeValues) ? $relativeValues : sr_asset_exchange_relative_values_from_settings($settings);
$policyPreviews = isset($policyPreviews) && is_array($policyPreviews) ? $policyPreviews : sr_asset_exchange_canonical_policy_rows_from_settings($settings);
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">자산별 상대 가치</h2>
            <div class="card-actions">
                <a href="<?php echo sr_e(sr_url('/admin/asset-exchange/settings')); ?>" class="btn btn-sm btn-outline-secondary">공통 조건</a>
            </div>
        </div>
        <?php foreach (sr_asset_exchange_relative_value_setting_keys() as $moduleKey => $settingKey) { ?>
            <?php
            $label = sr_asset_exchange_asset_label($assets, (string) $moduleKey);
            $inputId = 'asset_exchange_' . (string) $settingKey;
            ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e($inputId); ?>"><?php echo sr_e($label); ?> 기준값 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e($inputId); ?>" type="number" name="<?php echo sr_e((string) $settingKey); ?>" value="<?php echo sr_e((string) ($relativeValues[$moduleKey] ?? 1)); ?>" class="form-input" min="1" required>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card admin-list-card">
        <div class="card-header">
            <h2 class="card-title">파생 환전표</h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>출금 항목</th>
                        <th>입금 항목</th>
                        <th>환전 비율</th>
                        <th>금액 제한</th>
                        <th>반올림</th>
                        <th>수수료</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($policyPreviews as $policy) { ?>
                        <?php
                        $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
                        $toModuleKey = (string) ($policy['to_module_key'] ?? '');
                        $fromMissing = !isset($assets[$fromModuleKey]);
                        $toMissing = !isset($assets[$toModuleKey]);
                        $status = (string) ($policy['status'] ?? 'disabled');
                        ?>
                        <tr>
                            <td>
                                <?php echo sr_e(sr_asset_exchange_asset_label($assets, $fromModuleKey)); ?>
                                <?php if ($fromMissing) { ?>
                                    <br><small>비활성</small>
                                <?php } ?>
                            </td>
                            <td>
                                <?php echo sr_e(sr_asset_exchange_asset_label($assets, $toModuleKey)); ?>
                                <?php if ($toMissing) { ?>
                                    <br><small>비활성</small>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e('출금 ' . number_format((int) $policy['rate_denominator']) . '당 입금 ' . number_format((int) $policy['rate_numerator'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) $policy['min_amount']) . ' - ' . ($policy['max_amount'] === null ? '제한 없음' : number_format((int) $policy['max_amount']))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($roundingModeLabels[(string) $policy['rounding_mode']] ?? $policy['rounding_mode'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($feeTriggerLabels[(string) $policy['fee_trigger']] ?? $policy['fee_trigger'])); ?></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo $status === 'enabled' ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e((string) ($policyStatusLabels[$status] ?? $status)); ?></span>
                                <?php if ($status === 'enabled' && ($fromMissing || $toMissing)) { ?>
                                    <br><small>실행 불가</small>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="btn btn-solid-light">환전 내역</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
