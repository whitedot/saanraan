#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$helper = file_get_contents($root . '/modules/coupon/helpers.php');
$coreDecisions = file_get_contents($root . '/docs/core-decisions.md');
if (!is_string($helper)) {
    $errors[] = 'Coupon helper cannot be read.';
} else {
    if (strpos($helper, 'function sr_coupon_key_is_valid') === false
        || strpos($helper, "'/\\A[a-z][a-z0-9_]{1,59}\\z/'") === false
    ) {
        $errors[] = 'Coupon definitions must validate coupon_key with the admin key pattern server-side.';
    }
    if (strpos($helper, "preg_match('/\\A[1-9][0-9]*\\z/', \$maxUsesString)") === false) {
        $errors[] = 'Coupon max_uses_per_issue must reject non-integer POST values server-side.';
    }
    if (strpos($helper, 'function sr_coupon_validity_policies(): array') === false
        || strpos($helper, 'function sr_coupon_definition_validity_payload(array $data, string $now): array') === false
        || strpos($helper, 'function sr_coupon_issue_validity_window(array $definition, string $issuedAt, ?string $expiresAtOverride = null, array $claimContext = []): array') === false
        || strpos($helper, "'fixed_range' => '고정 사용 기간'") === false
        || strpos($helper, "'relative_days' => '발급 후 일수'") === false
        || strpos($helper, 'clamp_starts_at_to_issued_at') === false
        || strpos($helper, '이미 만료된 쿠폰은 지급할 수 없습니다.') === false
        || strpos($helper, 'function sr_coupon_usable_account_issue_count(PDO $pdo, int $accountId): int') === false
        || strpos($helper, 'AND (i.starts_at IS NULL OR i.starts_at <= :starts_now_value)') === false
        || strpos($helper, 'AND (i.expires_at IS NULL OR i.expires_at >= :expires_now_value)') === false
    ) {
        $errors[] = 'Coupon validity policy must validate definition policy, compute issue starts/expires, and exclude future-start issues from usable paths.';
    }
    if (strpos($helper, 'SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1') === false) {
        $errors[] = 'Coupon definitions must reject duplicate coupon_key values before insert.';
    }
    if (strpos($helper, 'function sr_coupon_expire_active_issues(PDO $pdo, ?int $accountId = null): int') === false
        || substr_count($helper, 'sr_coupon_expire_active_issues($pdo') < 4
    ) {
        $errors[] = 'Coupon issue queries and redemption must transition expired active issues.';
    }
    if (strpos($helper, "return ['active', 'issue_stopped', 'disabled'];") === false
        || strpos($helper, 'function sr_coupon_definition_allows_issue(string $status): bool') === false
        || strpos($helper, 'function sr_coupon_definition_allows_redeem(string $status): bool') === false
        || strpos($helper, "d.status IN ('active', 'issue_stopped')") === false
    ) {
        $errors[] = 'Coupon definition status semantics must distinguish active, issue_stopped, and disabled states.';
    }
    if (strpos($helper, 'sr_coupon_redemption_pricing_columns_available($pdo)') === false
        || strpos($helper, 'r.amount, r.currency_code, r.asset_unit, r.policy_summary, r.priced_at') === false
    ) {
        $errors[] = 'Coupon admin redemption queries must include pricing snapshot columns with a legacy fallback.';
    }
    if (strpos($helper, 'function sr_coupon_target_capability_summary(array $capabilities): string') === false
        || strpos($helper, "'capability_label'") === false
        || strpos($helper, "'pricing_label'") === false
        || strpos($helper, "['source' => 'admin_lookup']") === false
    ) {
        $errors[] = 'Coupon target lookup results must expose capability and pricing summaries for admin selection.';
    }
    if (strpos($helper, 'function sr_coupon_assert_refundable_target_contract(PDO $pdo, string $targetType, string $refundablePolicy): void') === false
        || strpos($helper, 'sr_coupon_target_contract_has_capability($target, \'revoke_access\')') === false
        || strpos($helper, '환급 가능 쿠폰은 접근권 회수를 지원하는 사용처에만 연결할 수 있습니다.') === false
    ) {
        $errors[] = 'Coupon definition save must require revoke_access capability for refundable target-specific coupons.';
    }
    if (strpos($helper, 'function sr_coupon_assert_refundable_benefit_model(string $couponType, string $refundablePolicy): void') === false
        || strpos($helper, '정액/정률 할인 쿠폰은 복합 자산 결제 취소 계약이 준비될 때까지 환급 가능으로 설정할 수 없습니다.') === false
        || strpos($helper, 'sr_coupon_assert_refundable_benefit_model($couponType, $refundablePolicy);') === false
        || strpos($helper, '접근권 쿠폰 사용 내역만 수동 환불할 수 있습니다. 할인 쿠폰 복합 결제는 소비 도메인 취소 계약이 필요합니다.') === false
        || strpos($helper, 'd.coupon_key, d.title, d.coupon_type, d.refundable_policy') === false
    ) {
        $errors[] = 'Coupon definition save must reject refundable discount coupons until mixed asset cancellation has a domain contract.';
    }
    if (strpos($helper, 'function sr_coupon_refund_redemption_state_only(PDO $pdo, int $redemptionId, int $adminAccountId, string $refundNote, array $options = []): array') === false
        || strpos($helper, 'notification_payload') === false
        || strpos($helper, 'sr_coupon_refund_redemption_state_only($pdo, $redemptionId, $adminAccountId, $refundNote)') === false
        || strpos($helper, 'sr_coupon_revoke_target_access_or_fail(') === false
        || strpos($helper, 'sr_coupon_notify_issue_event($pdo, (int) $refund[\'coupon_issue_id\']') === false
    ) {
        $errors[] = 'Coupon refund must expose a state-only primitive while keeping the standalone refund wrapper responsible for access revoke and notification.';
    }
    if (strpos($helper, 'function sr_coupon_types(): array') === false
        || strpos($helper, "'fixed_discount' => '정액 할인'") === false
        || strpos($helper, "'percent_discount' => '정률 할인'") === false
        || strpos($helper, 'function sr_coupon_definition_discount_columns_available(PDO $pdo): bool') === false
        || strpos($helper, "function sr_coupon_definition_discount_columns_available(PDO \$pdo): bool\n{\n    try {") === false
        || strpos($helper, 'number_format($amount)') === false
        || strpos($helper, "'원 할인'") === false
        || strpos($helper, '정액 할인 금액은 1 이상 정수로 입력하세요.') === false
        || strpos($helper, '정률 할인율은 1부터 100 사이의 정수로 입력하세요.') === false
    ) {
        $errors[] = 'Coupon definition save must validate fixed and percent discount fields server-side without stale schema caching.';
    }
    if (strpos($helper, 'function sr_coupon_settings(PDO $pdo): array') === false
        || strpos($helper, "'coupon_zone_label' => '쿠폰존'") === false
        || strpos($helper, 'function sr_coupon_zone_label(PDO $pdo): string') === false
        || strpos($helper, 'function sr_coupon_notification_cases(): array') === false
        || strpos($helper, "'issue_refunded' => [") === false
        || strpos($helper, "'event_key' => 'issue.refunded'") === false
        || strpos($helper, "'notification_cases' => sr_coupon_default_notification_case_settings()") === false
        || strpos($helper, 'function sr_coupon_notification_setting_for_event(array $settings, string $eventKey): ?array') === false
        || strpos($helper, 'function sr_coupon_notification_event_uses_email(PDO $pdo, string $eventKey): bool') === false
        || strpos($helper, 'function sr_coupon_admin_notification_email_warnings(PDO $pdo): array') === false
        || strpos($helper, "'disabled_reclaim_notifications_enabled' => true") === false
        || strpos($helper, "'disabled_reclaim_notification_event_key' => 'issue.definition_disabled'") === false
        || strpos($helper, "'disabled_reclaim_notification_channels' => ['site']") === false
        || strpos($helper, 'function sr_coupon_notification_channel_options(PDO $pdo): array') === false
        || strpos($helper, 'function sr_coupon_notify_definition_disabled_unused_issue_reclaims(PDO $pdo, array $definitionIds, ?int $createdByAccountId = null): array') === false
        || strpos($helper, 'sr_coupon_unused_active_issue_ids_for_definition($pdo, $definitionId)') === false
        || strpos($helper, "'reclaim_reason' => 'coupon_definition_disabled'") === false
        || strpos($helper, "if (empty(\$caseSetting['enabled']))") === false
    ) {
        $errors[] = 'Coupon notification flow must expose configurable notification cases and notify disabled definition holders through those settings.';
    }
}

$action = file_get_contents($root . '/modules/coupon/actions/admin-coupons.php');
if (!is_string($action)) {
    $errors[] = 'Coupon admin action cannot be read.';
} elseif (strpos($action, "'max_uses_per_issue' => sr_post_string('max_uses_per_issue', 10)") === false
    || strpos($action, "'max_uses_per_issue' => (int) sr_post_string('max_uses_per_issue', 10)") !== false
) {
    $errors[] = 'Coupon admin action must pass raw max_uses_per_issue input to server-side validation.';
}
if (is_string($action)
    && (
        strpos($action, "'validity_policy' => sr_post_string('validity_policy', 30)") === false
        || strpos($action, "'validity_days' => sr_post_string('validity_days', 10)") === false
        || strpos($action, "'valid_from' => sr_post_string('valid_from', 30)") === false
        || strpos($action, "'valid_until' => sr_post_string('valid_until', 30)") === false
        || strpos($action, "'issue_expires_at' => sr_post_string('issue_expires_at', 30)") === false
        || strpos($action, 'sr_coupon_assert_definition_issueable_now($pdo, $definitionId);') === false
    )
) {
    $errors[] = 'Coupon admin action must pass validity policy inputs and preflight manual issue validity.';
}
if (is_string($action) && strpos($action, "'discount_currency_code' => sr_post_string('discount_currency_code', 3)") !== false) {
    $errors[] = 'Coupon definition form must not expose a standalone discount currency field for fixed discounts.';
}
if (is_string($action)
    && (
        strpos($action, '$disabledNotificationDefinitionIds') === false
        || strpos($action, 'sr_coupon_notify_definition_disabled_unused_issue_reclaims($pdo, $disabledNotificationDefinitionIds') === false
        || strpos($action, 'sr_coupon_notify_definition_disabled_unused_issue_reclaims($pdo, [$definitionId]') === false
        || strpos($action, '사용 전 지급건') === false
        || strpos($action, 'sr_coupon_admin_notification_email_warnings($pdo)') === false
    )
) {
    $errors[] = 'Coupon definition disable actions must send reclaim notifications and expose admin email warning context.';
}
if (is_string($action)
    && (
        strpos($action, 'sr_coupon_issue_to_account(') === false
        || strpos($action, 'sr_coupon_update_issue_status($pdo') === false
        || strpos($action, 'sr_coupon_refund_paid_issue_assets($pdo') === false
        || strpos($action, 'sr_coupon_refund_redemption($pdo') === false
    )
) {
    $errors[] = 'Coupon admin actions must keep notification-backed issue, status, paid refund, and redemption refund flows connected.';
}

$view = file_get_contents($root . '/modules/coupon/views/admin-coupons.php');
if (!is_string($view)) {
    $errors[] = 'Coupon admin view cannot be read.';
} elseif (strpos($view, 'pattern="[a-z][a-z0-9_]{1,59}"') === false
    || strpos($view, 'data-admin-key-input') === false
    || strpos($view, 'inputmode="latin"') === false
    || strpos($view, 'autocapitalize="none"') === false
    || strpos($view, 'spellcheck="false"') === false
) {
    $errors[] = 'Coupon admin view must align coupon_key browser validation with admin key input rules.';
}
if (is_string($view)
    && (
        strpos($view, '<th>가격 스냅샷</th>') === false
        || strpos($view, '$redemptionHasPriceSnapshot') === false
        || strpos($view, "(string) (\$redemption['coupon_type'] ?? 'access') === 'access'") === false
    )
) {
    $errors[] = 'Coupon admin redemption view must expose the stored pricing snapshot.';
}
if (is_string($view)
    && (
        strpos($view, 'data-coupon-claim-campaign-form') === false
        || strpos($view, 'data-coupon-paid-required-input') === false
        || strpos($view, 'data-coupon-paid-asset-checkbox') === false
        || strpos($view, 'function syncClaimCampaignPaidFields(form)') === false
        || strpos($view, 'assetCheckboxes[0].setCustomValidity') === false
    )
) {
    $errors[] = 'Coupon paid claim campaign form must align conditional required UI, browser validation, and paid asset selection.';
}
if (is_string($view)
    && (
        strpos($view, 'data-coupon-type-select') === false
        || strpos($view, 'data-coupon-fixed-required-input') === false
        || strpos($view, 'data-coupon-percent-required-input') === false
        || strpos($view, 'coupon_admin_discount_amount_unit') === false
        || strpos($view, '>원</span>') === false
        || strpos($view, 'coupon_admin_discount_percent_unit') === false
        || strpos($view, '>%</span>') === false
        || strpos($view, 'coupon_admin_discount_currency_code') !== false
        || strpos($view, 'syncCouponBenefitFields') === false
        || strpos($view, 'sr_coupon_definition_benefit_label($definition)') === false
    )
) {
    $errors[] = 'Coupon definition form must expose fixed and percent discount settings with units and conditional validation.';
}
if (is_string($view)
    && (
        strpos($view, 'data-coupon-validity-policy') === false
        || strpos($view, 'data-coupon-validity-fixed-range-field') === false
        || strpos($view, 'data-coupon-validity-fixed-field') === false
        || strpos($view, 'data-coupon-validity-relative-field') === false
        || strpos($view, 'syncCouponValidityFields') === false
        || strpos($view, 'name="issue_expires_at"') === false
        || strpos($view, 'sr_coupon_definition_validity_label($definition)') === false
    )
) {
    $errors[] = 'Coupon admin view must expose validity policy controls, campaign fixed expiry override, and definition validity summary.';
}
if (is_string($view)
    && (
        strpos($view, "value=\"issue_stopped\"") === false
        || strpos($view, '>지급 중지</button>') === false
        || strpos($view, '>사용 중지</button>') === false
    )
) {
    $errors[] = 'Coupon definition admin status controls must expose issue stop and full disable separately.';
}
if (is_string($view)
    && (
        strpos($view, '$couponEmailWarningHtml') === false
        || strpos($view, '$couponEmailWarningAttribute') === false
        || strpos($view, "admin-coupon-email-warning") === false
        || strpos($view, "issue.created") === false
        || strpos($view, "issue.definition_disabled") === false
        || strpos($view, "issue.status_updated") === false
        || strpos($view, "issue.refunded") === false
        || strpos($view, "redemption.refunded") === false
        || strpos($view, "data-coupon-email-warning") === false
    )
) {
    $errors[] = 'Coupon admin action surfaces must warn operators when configured email notification channels can send mail.';
}
if (is_string($view)) {
    $issueStoppedStatusPosition = strpos($view, '<input type="hidden" name="status" value="issue_stopped">');
    $disabledStatusPosition = strpos($view, '<input type="hidden" name="status" value="disabled">');
    $issueStoppedFormStart = is_int($issueStoppedStatusPosition) ? strrpos(substr($view, 0, $issueStoppedStatusPosition), '<form') : false;
    $disabledFormStart = is_int($disabledStatusPosition) ? strrpos(substr($view, 0, $disabledStatusPosition), '<form') : false;
    $issueStoppedFormPrefix = is_int($issueStoppedFormStart) && is_int($issueStoppedStatusPosition) ? substr($view, $issueStoppedFormStart, $issueStoppedStatusPosition - $issueStoppedFormStart) : '';
    $disabledFormPrefix = is_int($disabledFormStart) && is_int($disabledStatusPosition) ? substr($view, $disabledFormStart, $disabledStatusPosition - $disabledFormStart) : '';
    if ($issueStoppedFormPrefix === ''
        || $disabledFormPrefix === ''
        || strpos($issueStoppedFormPrefix, "couponEmailWarningAttribute('issue.definition_disabled')") !== false
        || strpos($disabledFormPrefix, "couponEmailWarningAttribute('issue.definition_disabled')") === false
    ) {
        $errors[] = 'Coupon definition email warning must apply to full disable, not issue stop.';
    }
}
if (!is_string($coreDecisions)
    || strpos($coreDecisions, '콘텐츠와 커뮤니티 유료 열람/다운로드는 `access`, `fixed_discount`, `percent_discount`를 모두 쿠폰 사용 후보로 평가') === false
    || strpos($coreDecisions, '쿠폰 우선 적용은 `access` 쿠폰만 소비') !== false
) {
    $errors[] = 'Core decisions must describe current discount coupon redemption support without the legacy access-only claim.';
}

$settingsAction = file_get_contents($root . '/modules/coupon/actions/admin-coupon-settings.php');
$settingsView = file_get_contents($root . '/modules/coupon/views/admin-settings.php');
$paths = file_get_contents($root . '/modules/coupon/paths.php');
$adminMenu = file_get_contents($root . '/modules/coupon/admin-menu.php');
if (!is_string($settingsAction)
    || strpos($settingsAction, 'sr_admin_require_permission($pdo') === false
    || strpos($settingsAction, "\$permissionPath, 'view'") === false
    || strpos($settingsAction, "\$permissionPath, 'edit'") === false
    || strpos($settingsAction, '대량 발송될 수 있으므로') === false
    || strpos($settingsAction, '$notificationCases = sr_coupon_notification_cases()') === false
    || strpos($settingsAction, "\$couponZoneLabel = sr_coupon_normalize_zone_label(sr_post_string('coupon_zone_label', 40))") === false
    || strpos($settingsAction, '$postedCases = $_POST[\'notification_cases\'] ?? []') === false
    || strpos($settingsAction, '채널을 하나 이상 선택하세요.') === false
    || strpos($settingsAction, "'notification_cases' => \$caseSettings") === false
    || strpos($settingsAction, 'sr_coupon_save_settings($pdo, $postedSettings)') === false
    || strpos($settingsAction, "event_type' => 'coupon.settings.updated'") === false
) {
    $errors[] = 'Coupon settings action must validate permissions, save notification case settings, and audit changes.';
}
if (!is_string($settingsView)
    || strpos($settingsView, '$notificationCases') === false
    || strpos($settingsView, 'name="coupon_zone_label"') === false
    || strpos($settingsView, '쿠폰존 명칭') === false
    || strpos($settingsView, 'notification_cases[') === false
    || strpos($settingsView, 'form-choice-toggle-input sr-only') === false
    || strpos($settingsView, 'data-coupon-notification-case') === false
    || strpos($settingsView, 'data-coupon-notification-channel') === false
    || strpos($settingsView, 'setCustomValidity') === false
    || strpos($settingsView, 'disabled_reclaim_notification_event_key') !== false
    || strpos($settingsView, 'sr_admin_feedback_toasts($notice, $errors)') === false
) {
    $errors[] = 'Coupon settings view must expose notification cases with checkbox toggle channel selection, browser validation, and toast feedback.';
}
if (!is_string($paths)
    || strpos($paths, "'GET /admin/coupons/settings' => 'actions/admin-coupon-settings.php'") === false
    || strpos($paths, "'POST /admin/coupons/settings' => 'actions/admin-coupon-settings.php'") === false
    || !is_string($adminMenu)
    || strpos($adminMenu, "'label' => '쿠폰·이용권'") === false
    || strpos($adminMenu, "'label' => '쿠폰·이용권 관리'") === false
    || strpos($adminMenu, "'path' => '/admin/coupons/settings'") === false
) {
    $errors[] = 'Coupon settings route and admin menu entry must be registered with the restored coupon/pass module label.';
}

$assetAdjustJs = file_get_contents($root . '/modules/admin/assets/asset-adjust.js');
if (!is_string($assetAdjustJs)
    || strpos($assetAdjustJs, 'item.capability_label ||') === false
    || strpos($assetAdjustJs, 'item.pricing_label ||') === false
    || strpos($assetAdjustJs, 'item.policy_summary ||') === false
) {
    $errors[] = 'Admin reference lookup rendering must display coupon capability and pricing metadata when supplied.';
}

$module = file_get_contents($root . '/modules/coupon/module.php');
$update = file_get_contents($root . '/modules/coupon/updates/2026.05.003.sql');
if (!is_string($module)
    || preg_match("/'version'\\s*=>\\s*'([0-9]{4}\\.[0-9]{2}\\.[0-9]{3})'/", $module, $versionMatch) !== 1
    || version_compare($versionMatch[1], '2026.05.003', '<')
) {
    $errors[] = 'Coupon module version must be bumped for the validation behavior change.';
}
if (!is_string($update) || strpos($update, "WHERE module_key = 'coupon'") === false) {
    $errors[] = 'Coupon validation update must include a module version marker update.';
}
$expiryUpdate = file_get_contents($root . '/modules/coupon/updates/2026.05.006.sql');
if (!is_string($expiryUpdate)
    || strpos($expiryUpdate, "SET status = 'expired'") === false
    || strpos($expiryUpdate, "WHERE module_key = 'coupon'") === false
) {
    $errors[] = 'Coupon expiry update must transition existing expired active issues and bump the module version.';
}
$discountUpdate = file_get_contents($root . '/modules/coupon/updates/2026.06.006.sql');
if (!is_string($discountUpdate)
    || strpos($discountUpdate, 'discount_amount') === false
    || strpos($discountUpdate, 'discount_percent') === false
    || strpos($discountUpdate, 'discount_currency_code') === false
    || strpos($discountUpdate, "WHERE module_key = 'coupon'") === false
) {
    $errors[] = 'Coupon discount definition update must add discount columns and bump the module version.';
}
$settingsUpdate = file_get_contents($root . '/modules/coupon/updates/2026.06.007.sql');
$validityUpdate = file_get_contents($root . '/modules/coupon/updates/2026.06.009.sql');
if (!is_string($validityUpdate)
    || strpos($validityUpdate, 'validity_policy') === false
    || strpos($validityUpdate, 'validity_days') === false
    || strpos($validityUpdate, 'starts_at') === false
    || strpos($validityUpdate, "version = '2026.06.009'") === false
) {
    $errors[] = 'Coupon validity update must add definition policy columns, issue starts_at, and bump the module version.';
}
$notificationInstall = file_get_contents($root . '/modules/notification/install.sql');
$notificationUpdate = file_get_contents($root . '/modules/notification/updates/2026.06.011.sql');
if (!is_string($settingsUpdate)
    || strpos($settingsUpdate, "'/admin/coupons/settings'") === false
    || strpos($settingsUpdate, "SET name = '쿠폰·이용권'") === false
    || strpos($settingsUpdate, "version = '2026.06.007'") === false
) {
    $errors[] = 'Coupon settings update must grant settings permissions, restore the coupon/pass module name, and bump the coupon module version.';
}
if (!is_string($notificationInstall)
    || strpos($notificationInstall, "'coupon', 'issue.refunded'") === false
    || strpos($notificationInstall, "'coupon', 'issue.definition_disabled'") === false
    || !is_string($notificationUpdate)
    || strpos($notificationUpdate, '{{SR_TABLE_PREFIX}}notification_event_templates') === false
    || strpos($notificationUpdate, "'coupon', 'issue.refunded'") === false
    || strpos($notificationUpdate, "'coupon', 'issue.definition_disabled'") === false
) {
    $errors[] = 'Notification templates must include coupon issue refund and definition disabled account events.';
}

if ($errors !== []) {
    fwrite(STDERR, "coupon admin validation checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "coupon admin validation checks completed.\n";
