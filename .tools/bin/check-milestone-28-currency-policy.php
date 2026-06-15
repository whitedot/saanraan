#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_milestone_28_check_contains(string $file, array $needles): void
{
    global $errors;

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read milestone 28 policy document: ' . $file;
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = $file . ' must document milestone 28 currency policy marker: ' . $needle;
        }
    }
}

$record = 'docs/records/milestone-28-currency-settlement-policy-2026-06-11.md';

sr_milestone_28_check_contains($record, [
    '#117은 reference/open 상태로 둘 수 있으며',
    '`site.default_currency`는 신규 가격/정책 row의 기본값일 뿐 기존 가격, 거래, 구매력 snapshot을 변환하거나 재해석하는 전역 스위치가 아니다',
    '추가 통화나 가격/정책 row별 currency 입력은 #115 settlement snapshot field contract와 #315 registry freeze 뒤에 별도 구현 이슈로 연다',
    '환율은 초기 범위에서 실제 차감 기준으로 쓰지 않고 통계/표시 보조값으로만 검토한다',
    '환율 미설정, 만료, 조회 실패는 실제 구매 차감을 실패시키지 않는다',
    '`exchange_rate_policy_version`은 환율 환산 표시를 열 때만 쓰는 환율 출처/만료/환산 rounding 정책 version이며 실제 차감 기준 version이 아니다',
    '`settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나로 시작한다',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함한다',
    '`legacy_unknown` row의 환불/정정은 자동 금액 환불 대상이 아니라 관리자 검토 대상으로 둔다',
    '구매력 변경과 가격/정책 통화 일괄 변환 batch apply는 같은 자산/통화/도메인 scope에서 동시에 실행하지 않는다',
    '#319 dry-run/apply는 dry-run 시점의 purchase power version, 가격 row version, currency registry, `rounding_policy_version`을 저장하고 apply 직전에 같은 기준인지 재검증한다',
    'reversal snapshot 후보의 정규 필드는 `original_settlement_log_id`, `reversal_amount`, `reversal_currency`, `reversal_asset_amount`, `reversal_reason`, `rounding_policy_version`, `snapshot_schema_version`, `created_at`이다',
    '통계 합계는 기본적으로 `settlement_currency`별로 분리하고, 환산 합계는 환율 정책이 실제 구현으로 분리되기 전까지 제공하지 않는다',
    'export/cache key는 `settlement_currency`, `snapshot_schema_version`, `rounding_policy_version`, `settlement_kind`, reversal 포함 여부를 명시적으로 구분한다',
    'CSV/admin/privacy export는 raw JSON만 내보내지 않고 저장 기준인 minor unit, settlement currency, asset amount, `snapshot_schema_version`, `rounding_policy_version`과 사람이 읽을 수 있는 settlement 요약을 함께 제공한다',
    '외부 환율 API secret/API key는 새 저장소를 만들지 않고 기존 관리자 secret 설정/마스킹 primitive와 운영 로그 sanitize 기준을 따른다',
]);

sr_milestone_28_check_contains('docs/core-decisions.md', [
    '마일스톤 28의 통화 확장 범위는 `docs/records/milestone-28-currency-settlement-policy-2026-06-11.md`를 따른다',
    '환율은 초기 범위에서 통계/표시 보조값이고, 환율 미설정/만료/조회 실패는 실제 구매 차감을 실패시키지 않습니다',
    '외부 환율 API secret은 기존 관리자 secret 설정/마스킹 primitive와 운영 로그 sanitize 기준을 사용합니다',
    '구매력 변경과 가격/정책 통화 일괄 변환 batch apply는 같은 자산/통화/도메인 scope에서 동시에 실행하지 않고',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고',
    '통계/export/cache key는 `settlement_currency`, `snapshot_schema_version`, `rounding_policy_version`, `settlement_kind`, reversal 포함 여부를 구분하며',
]);

sr_milestone_28_check_contains('docs/README.md', [
    '마일스톤 28 통화·정산 정책 기록 - 2026-06-11',
]);

sr_milestone_28_check_contains('core/views/install.php', [
    'name="default_currency"',
    'array_keys(sr_known_currency_min_units())',
    'data-summary-source="default_currency"',
    'data-summary-target="default_currency"',
    '기존 가격과 로그는 이 값으로 변환되지 않습니다',
]);

sr_milestone_28_check_contains('core/actions/install.php', [
    '$values[\'default_currency\'] = sr_normalize_currency_code($values[\'default_currency\']);',
    'if (!sr_currency_is_known($values[\'default_currency\']))',
    '\'site.default_currency\' => [\'value\' => $values[\'default_currency\'], \'type\' => \'string\']',
    '\'content\' => [' . "\n" . '        \'name\' => \'콘텐츠\',' . "\n" . '        \'version\' => \'2026.06.021\'',
    '\'community\' => [' . "\n" . '        \'name\' => \'커뮤니티\',' . "\n" . '        \'version\' => \'2026.06.026\'',
]);

sr_milestone_28_check_contains('modules/content/helpers.php', [
    '$defaultSettlementCurrency = sr_site_default_currency($pdo);',
    'asset_access_settlement_currency',
    'asset_action_settlement_currency',
]);

sr_milestone_28_check_contains('modules/content/helpers/files.php', [
    'asset_download_settlement_currency',
    'sr_site_default_currency($pdo)',
]);

sr_milestone_28_check_contains('modules/community/actions/admin-settings.php', [
    '$defaultSettlementCurrency = sr_site_default_currency($pdo);',
    'write_charge_settlement_currency',
    'paid_attachment_download_settlement_currency',
]);

sr_milestone_28_check_contains('modules/community/helpers/levels.php', [
    'return sr_community_normalize_settings(sr_module_settings($pdo, \'community\'), null, $pdo);',
    '$assetPrefix . \'_settlement_currency\'',
    'sr_community_asset_settlement_currency($pdo',
]);

sr_milestone_28_check_contains('modules/community/helpers/assets.php', [
    '$value = $settings[$key] ?? $default;',
]);

sr_milestone_28_check_contains('modules/community/updates/2026.06.021.sql', [
    "c.setting_key = 'site.default_currency'",
    "'write_charge_settlement_currency'",
    "SET version = '2026.06.021'",
]);

foreach (['modules/point/member-assets.php', 'modules/reward/member-assets.php', 'modules/deposit/member-assets.php'] as $memberAssetFile) {
    $memberAssetContract = file_get_contents($memberAssetFile);
    if (!is_string($memberAssetContract)) {
        $errors[] = 'cannot read member asset contract: ' . $memberAssetFile;
    } elseif (str_contains($memberAssetContract, "'settlement_currency' => 'KRW'")) {
        $errors[] = $memberAssetFile . ' must let default purchase power currency fall back to site.default_currency';
    }
}

sr_milestone_28_check_contains('modules/admin/views/settings.php', [
    '<span class="form-label">기본 통화</span>',
    '일반 설정 저장으로 변경되지 않습니다',
    '기존 가격, 로그, 구매력 snapshot은 변환하지 않습니다',
]);

$adminSettingsHelper = file_get_contents('modules/admin/helpers/settings.php');
if (!is_string($adminSettingsHelper)) {
    $errors[] = 'cannot read modules/admin/helpers/settings.php for default currency lock check';
} elseif (str_contains($adminSettingsHelper, "'site.default_currency' => ['value' =>")) {
    $errors[] = 'admin settings save must not write site.default_currency after install';
}

if ($errors !== []) {
    fwrite(STDERR, "milestone 28 currency policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "milestone 28 currency policy checks completed.\n";
