<?php

$adminPageTitle = '기본 통화 변경';
$adminPageTitleUrl = '/admin/settings/currency';
$adminPageTitleActionsHtml = '<a href="' . sr_e(sr_url('/admin/settings')) . '" class="btn btn-ghost-default">'
    . sr_material_icon_html('settings')
    . '<span>사이트 설정</span>'
    . '</a>';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">위험! 기본 통화 변경</h2>
        <div class="card-actions">
            <?php if (!empty($currencyChangeCanSubmit)) { ?>
                <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-currency-change-modal" data-overlay="#admin-currency-change-modal"><?php echo sr_material_icon_html('currency_exchange'); ?>변경</button>
            <?php } else { ?>
                <span class="badge-status is-warning">변경 후보 없음</span>
            <?php } ?>
        </div>
    </div>
    <div class="admin-list-summary-row">
        <p class="admin-list-summary">현재 기본 통화는 <code><?php echo sr_e((string) $currencyChangeCurrentCurrency); ?></code>입니다. 기본 통화는 신규 가격/정책 row와 통화가 빠진 자산 구매력 계약의 fallback 기준입니다. 기존 가격, 거래 로그, 구매력 snapshot은 변환하지 않습니다.</p>
    </div>
    <?php if (!empty($currencyChangeImpactSummary['totals_by_currency'])) { ?>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>대상</th>
                        <th>컬럼</th>
                        <th>통화별 row</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($currencyChangeImpactSummary['rows'] ?? []) as $currencyImpactRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($currencyImpactRow['label'] ?? '')); ?></td>
                            <td><code><?php echo sr_e((string) ($currencyImpactRow['column'] ?? '')); ?></code></td>
                            <td>
                                <?php
                                $currencyDistributionParts = [];
                                foreach ((array) ($currencyImpactRow['distribution'] ?? []) as $currencyCode => $rowCount) {
                                    $currencyDistributionParts[] = (string) $currencyCode . ' ' . number_format((int) $rowCount);
                                }
                                ?>
                                <?php echo sr_e(implode(', ', $currencyDistributionParts)); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="admin-list-summary-row">
            <p class="admin-list-summary">현재 확인 가능한 가격/정산 통화 row가 없습니다. 그래도 이후 새 정책 기본값과 자산 구매력 fallback은 변경된 통화를 사용합니다.</p>
        </div>
    <?php } ?>
    <?php if (!empty($currencyChangeImpactSummary['asset_purchase_power'])) { ?>
        <div class="table-wrapper">
            <table class="table table-list table-sm">
                <thead>
                    <tr>
                        <th>자산</th>
                        <th>구매력</th>
                        <th>정산 통화</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($currencyChangeImpactSummary['asset_purchase_power'] ?? []) as $assetPowerRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($assetPowerRow['label'] ?? $assetPowerRow['module_key'] ?? '')); ?></td>
                            <td><?php echo sr_e(number_format((int) ($assetPowerRow['asset_units'] ?? 1)) . ' 단위 = ' . number_format((int) ($assetPowerRow['settlement_units'] ?? 1))); ?></td>
                            <td><code><?php echo sr_e((string) ($assetPowerRow['settlement_currency'] ?? '')); ?></code></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php if (!empty($currencyChangeCanSubmit)) { ?>
<div id="admin-currency-change-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-currency-change-modal-title" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/settings/currency')); ?>" class="modal-content ui-form-theme" autocomplete="off" data-admin-currency-change-form data-current-currency="<?php echo sr_e((string) $currencyChangeCurrentCurrency); ?>">
            <div class="modal-header">
                <h3 id="admin-currency-change-modal-title" class="modal-title">위험! 기본 통화 변경</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-currency-change-modal">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="currency_change">
                <div class="form-row">
                    <label class="form-label" for="admin_settings_new_default_currency">새 기본 통화 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <select id="admin_settings_new_default_currency" name="new_default_currency" class="form-select" required data-admin-currency-change-target data-overlay-focus>
                            <?php foreach ($currencyChangeCurrencyOptions as $currencyCode) { ?>
                                <option value="<?php echo sr_e((string) $currencyCode); ?>"><?php echo sr_e((string) $currencyCode); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">현재 기본 통화는 <code><?php echo sr_e((string) $currencyChangeCurrentCurrency); ?></code>입니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_reason">변경 사유 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <textarea id="admin_settings_currency_change_reason" name="currency_change_reason" class="form-textarea" rows="3" maxlength="1000" required></textarea>
                        <p class="form-help">설치 초기에 잘못 선택한 통화를 정정하는 경우처럼 운영 맥락을 남깁니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_confirmation">확인 문구 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="admin_settings_currency_change_confirmation" type="text" name="currency_change_confirmation" class="form-input" maxlength="120" required autocomplete="one-time-code" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-admin-currency-change-confirmation>
                        <p class="form-help">아래 문구를 그대로 입력하세요: <code data-admin-currency-change-phrase><?php echo sr_e((string) $currencyChangeConfirmationPhrase); ?></code></p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_password">현재 비밀번호 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="admin_settings_currency_change_password" type="password" name="currency_change_password" class="form-input" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" required>
                        <p class="form-help">변경 직전에 서버가 통화 값, 확인 문구, 사유, 비밀번호를 다시 검증하고 감사 로그를 남깁니다.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-currency-change-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-outline-danger modal-action"><?php echo sr_material_icon_html('currency_exchange'); ?>기본 통화 변경</button>
            </div>
        </form>
    </div>
</div>
<?php } ?>

<script>
(function () {
    var currencyChangeForm = document.querySelector('[data-admin-currency-change-form]');
    if (!currencyChangeForm) {
        return;
    }

    var currencyChangeTarget = currencyChangeForm.querySelector('[data-admin-currency-change-target]');
    var currencyChangePhrase = currencyChangeForm.querySelector('[data-admin-currency-change-phrase]');
    var currencyChangeCurrent = currencyChangeForm.getAttribute('data-current-currency') || '';

    function syncCurrencyChangePhrase() {
        if (!currencyChangeTarget || !currencyChangePhrase) {
            return;
        }
        currencyChangePhrase.textContent = currencyChangeCurrent + '에서 ' + currencyChangeTarget.value + '로 변경';
    }

    syncCurrencyChangePhrase();
    if (currencyChangeTarget) {
        currencyChangeTarget.addEventListener('change', syncCurrencyChangePhrase);
    }
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
