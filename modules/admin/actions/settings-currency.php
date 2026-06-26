<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'view');
sr_admin_require_owner($pdo, (int) $account['id']);

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'currency_change') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
    sr_require_csrf();

    try {
        $postResult = sr_admin_handle_currency_change_post($pdo, $account, $site ?? null);
        $errors = $postResult['errors'];
        $notice = (string) $postResult['notice'];
        $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'admin_currency_change_failed');
        $errors[] = '기본 통화 변경 중 오류가 발생했습니다. 오류 로그를 확인해 주세요.';
    }
} elseif (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
}

if (sr_request_method() === 'POST') {
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/settings/currency');
}

$currencyChangeCurrentCurrency = sr_site_default_currency($pdo);
$currencyChangeImpactSummary = sr_admin_currency_change_impact_summary($pdo);
$currencyChangeCurrencyOptions = array_values(array_filter(
    sr_admin_currency_change_known_currency_options(),
    static fn (string $currencyCode): bool => $currencyCode !== $currencyChangeCurrentCurrency
));
$currencyChangeCanSubmit = $currencyChangeCurrencyOptions !== [];
$currencyChangeDefaultTargetCurrency = $currencyChangeCurrencyOptions[0] ?? $currencyChangeCurrentCurrency;
$currencyChangeConfirmationPhrase = sr_admin_currency_change_confirmation_phrase($currencyChangeCurrentCurrency, $currencyChangeDefaultTargetCurrency);

include SR_ROOT . '/modules/admin/views/settings-currency.php';
