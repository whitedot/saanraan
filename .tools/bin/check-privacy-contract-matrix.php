#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_privacy_matrix_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_privacy_matrix_module_metadata(string $moduleKey): array
{
    $file = 'modules/' . $moduleKey . '/module.php';
    if (!is_file($file)) {
        sr_privacy_matrix_error('module.php is missing: ' . $moduleKey);
        return [];
    }

    $metadata = include $file;
    if (!is_array($metadata)) {
        sr_privacy_matrix_error('module.php must return an array: ' . $moduleKey);
        return [];
    }

    return $metadata;
}

function sr_privacy_matrix_contracts(array $metadata, string $key): array
{
    $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
    $values = is_array($contracts[$key] ?? null) ? $contracts[$key] : [];
    return array_values(array_filter($values, 'is_string'));
}

function sr_privacy_matrix_account_reference_pattern(): string
{
    return '/\b(?:[a-z0-9_]*account_id|email|recipient|phone|birth_date|ip_hash|user_agent_hash|provider_subject_hash|consent_snapshot_json|answer_snapshot_json|metadata_snapshot_json)\b/i';
}

function sr_privacy_matrix_sql_files(string $moduleDir): array
{
    $files = [];
    foreach ([$moduleDir . '/install.sql', $moduleDir . '/updates/*.sql'] as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }
    }

    sort($files, SORT_STRING);
    return $files;
}

function sr_privacy_matrix_sql_account_reference_files(string $moduleDir): array
{
    $files = [];
    foreach (sr_privacy_matrix_sql_files($moduleDir) as $file) {
        $sql = file_get_contents($file);
        if (!is_string($sql)) {
            sr_privacy_matrix_error('SQL file cannot be read for privacy matrix scan: ' . $file);
            continue;
        }

        if (preg_match(sr_privacy_matrix_account_reference_pattern(), $sql) === 1) {
            $files[] = $file;
        }
    }

    return $files;
}

function sr_privacy_matrix_check_callable_signature(string $moduleKey, string $contractFile, callable $callable, int $minimumParams): void
{
    try {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], (string) $callable[1]);
        } else {
            $reflection = new ReflectionFunction(Closure::fromCallable($callable));
        }
    } catch (Throwable $exception) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' callable signature cannot be inspected.');
        return;
    }

    if ($reflection->getNumberOfParameters() < $minimumParams) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' callable must accept at least ' . $minimumParams . ' parameters.');
        return;
    }

    $parameters = $reflection->getParameters();
    $firstType = $parameters[0]->getType();
    $secondType = $parameters[1]->getType();
    if (!$firstType instanceof ReflectionNamedType || $firstType->getName() !== 'PDO') {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' first callable parameter must be PDO.');
    }
    if (!$secondType instanceof ReflectionNamedType || $secondType->getName() !== 'int') {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' second callable parameter must be int account id.');
    }

    $returnType = $reflection->getReturnType();
    if ($contractFile === 'privacy-cleanup.php' && (!$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'array')) {
        sr_privacy_matrix_error($moduleKey . ' privacy-cleanup.php callable must declare an array return type.');
    }
}

function sr_privacy_matrix_check_contract_return(string $moduleKey, string $contractFile): void
{
    $path = 'modules/' . $moduleKey . '/' . $contractFile;
    if (!is_file($path)) {
        return;
    }

    try {
        $contract = include $path;
    } catch (Throwable $exception) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' cannot be included: ' . $exception->getMessage());
        return;
    }

    if ($contractFile === 'privacy-export.php') {
        if (!is_callable($contract) && !is_array($contract)) {
            sr_privacy_matrix_error($moduleKey . ' privacy-export.php must return callable or array.');
            return;
        }

        if (is_callable($contract)) {
            sr_privacy_matrix_check_callable_signature($moduleKey, $contractFile, $contract, 2);
        }
        return;
    }

    if ($contractFile === 'privacy-cleanup.php') {
        if (!is_callable($contract)) {
            sr_privacy_matrix_error($moduleKey . ' privacy-cleanup.php must return callable.');
            return;
        }

        sr_privacy_matrix_check_callable_signature($moduleKey, $contractFile, $contract, 2);
    }
}

function sr_privacy_matrix_check_pending_policy_values(string $file, string $contents): void
{
    $lines = preg_split('/\R/u', $contents);
    if (!is_array($lines)) {
        $lines = [$contents];
    }

    foreach ($lines as $lineNumber => $line) {
        if (strpos($line, '정책 값에 `pending`, `TODO`, `TBD`, `미정`, `미확정`을 남기면 실패한다') !== false) {
            continue;
        }

        foreach ([
            '/(export_policy|cleanup_policy|lawful_basis).{0,80}(pending|todo|tbd|미정|미확정)/iu',
            '/(pending|todo|tbd|미정|미확정).{0,80}(export_policy|cleanup_policy|lawful_basis)/iu',
        ] as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                sr_privacy_matrix_error('privacy policy value must not be left pending in ' . $file . ':' . ((int) $lineNumber + 1));
                return;
            }
        }
    }
}

$matrixFile = 'docs/privacy-contract-matrix.md';
if (!is_file($matrixFile)) {
    sr_privacy_matrix_error('privacy contract matrix document is missing.');
    $matrix = '';
} else {
    $matrix = (string) file_get_contents($matrixFile);
}

$processingRecordsFile = 'docs/privacy-processing-records.md';
if (!is_file($processingRecordsFile)) {
    sr_privacy_matrix_error('privacy processing records document is missing.');
    $processingRecords = '';
} else {
    $processingRecords = (string) file_get_contents($processingRecordsFile);
}

foreach ([
    $matrixFile => $matrix,
    $processingRecordsFile => $processingRecords,
] as $policyFile => $policyContents) {
    if ($policyContents !== '') {
        sr_privacy_matrix_check_pending_policy_values((string) $policyFile, (string) $policyContents);
    }
}

$expected = [
    'admin' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'antispam' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'antispam_captcha_providers' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'asset_exchange' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'asset_ledger' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'banner' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'ckeditor' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'community' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'content' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'coupon' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'deposit' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'embed_manager' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'logo_manager' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'member' => ['status' => 'export_owner', 'export' => true, 'cleanup' => false, 'consumes_cleanup' => true],
    'member_oauth' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'notification' => ['status' => 'export_retained', 'export' => true, 'cleanup' => true],
    'point' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'policy_documents' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'popup_layer' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'privacy' => ['status' => 'coordinator_direct', 'export' => false, 'cleanup' => false, 'consumes_export' => true],
    'quiz' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'reaction' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'reward' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'seo' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'site_menu' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'survey' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
];

$operationalRetainedModules = ['admin', 'embed_manager', 'logo_manager'];

foreach ($expected as $moduleKey => $policy) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | `' . $policy['status'] . '` |') === false) {
        sr_privacy_matrix_error('privacy matrix row is missing or status changed without checker update: ' . $moduleKey);
    }

    $metadata = sr_privacy_matrix_module_metadata($moduleKey);
    $moduleFile = 'modules/' . $moduleKey . '/module.php';
    $moduleContents = is_file($moduleFile) ? file_get_contents($moduleFile) : false;
    if (is_string($moduleContents)) {
        sr_privacy_matrix_check_pending_policy_values($moduleFile, $moduleContents);
    }

    $provides = sr_privacy_matrix_contracts($metadata, 'provides');
    $consumes = sr_privacy_matrix_contracts($metadata, 'consumes');

    foreach (['privacy-export.php' => 'export', 'privacy-cleanup.php' => 'cleanup'] as $contractFile => $policyKey) {
        $required = (bool) ($policy[$policyKey] ?? false);
        $hasContract = in_array($contractFile, $provides, true);
        $hasFile = is_file('modules/' . $moduleKey . '/' . $contractFile);

        if ($required && (!$hasContract || !$hasFile)) {
            sr_privacy_matrix_error($moduleKey . ' must provide ' . $contractFile . ' according to privacy matrix.');
        }

        if (!$required && $moduleKey !== 'member' && $moduleKey !== 'privacy' && ($hasContract || $hasFile)) {
            sr_privacy_matrix_error($moduleKey . ' has unexpected ' . $contractFile . ' for current privacy matrix status.');
        }

        if ($hasFile) {
            $contractContents = file_get_contents('modules/' . $moduleKey . '/' . $contractFile);
            if (is_string($contractContents)) {
                sr_privacy_matrix_check_pending_policy_values('modules/' . $moduleKey . '/' . $contractFile, $contractContents);
            }

            sr_privacy_matrix_check_contract_return($moduleKey, $contractFile);
        }
    }

    if ((bool) ($policy['consumes_cleanup'] ?? false) && !in_array('privacy-cleanup.php', $consumes, true)) {
        sr_privacy_matrix_error($moduleKey . ' must consume privacy-cleanup.php.');
    }

    if ((bool) ($policy['consumes_export'] ?? false) && !in_array('privacy-export.php', $consumes, true)) {
        sr_privacy_matrix_error($moduleKey . ' must consume privacy-export.php.');
    }
}

$moduleDirs = glob('modules/*', GLOB_ONLYDIR);
if (is_array($moduleDirs)) {
    sort($moduleDirs);
    foreach ($moduleDirs as $moduleDir) {
        $moduleKey = basename($moduleDir);
        if (!is_file($moduleDir . '/module.php')) {
            continue;
        }

        if (!isset($expected[$moduleKey])) {
            sr_privacy_matrix_error('module is missing from privacy contract matrix: ' . $moduleKey);
            continue;
        }

        $accountReferenceFiles = sr_privacy_matrix_sql_account_reference_files($moduleDir);
        $hasAccountReference = $accountReferenceFiles !== [];
        if ($hasAccountReference && $matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` |') === false) {
            sr_privacy_matrix_error('SQL schema files have account or personal identifier columns but matrix row is missing: ' . $moduleKey);
        }

        $status = (string) ($expected[$moduleKey]['status'] ?? '');
        if ($status === 'no_member_personal_data' && $hasAccountReference) {
            sr_privacy_matrix_error($moduleKey . ' is marked no_member_personal_data but SQL schema files contain account or personal identifier columns: ' . implode(', ', $accountReferenceFiles));
        }

        if ($hasAccountReference && in_array($status, ['export_cleanup', 'export_retained', 'export_owner', 'coordinator_direct'], true)) {
            continue;
        }

        if ($hasAccountReference && $status === 'operational_retained' && !in_array($moduleKey, $operationalRetainedModules, true)) {
            sr_privacy_matrix_error($moduleKey . ' has account references and operational_retained status but no detailed retention policy.');
        }
    }
}

foreach ([
    ['needle' => 'sr_member_run_privacy_cleanup_contracts', 'file' => 'modules/member/helpers/privacy.php'],
    ['needle' => 'sr_privacy_export_data', 'file' => 'modules/privacy/helpers/requests.php'],
    ['needle' => 'sr_privacy_export_runtime_check_content', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'sr_privacy_export_runtime_check_community', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'sr_privacy_export_runtime_check_retained_modules', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'PRAGMA table_info(sr_content_comments)', 'file' => 'modules/content/helpers/comments.php'],
    ['needle' => 'PRAGMA table_info(sr_community_comments)', 'file' => 'modules/community/helpers/posts.php'],
    ['needle' => 'PRAGMA table_info(', 'file' => 'modules/content/privacy-cleanup.php'],
    ['needle' => 'PRAGMA table_info(', 'file' => 'modules/community/privacy-cleanup.php'],
    ['needle' => 'sr_privacy_cleanup_runtime_check_content', 'file' => '.tools/bin/check-privacy-cleanup-runtime.php'],
    ['needle' => 'sr_privacy_cleanup_runtime_check_community', 'file' => '.tools/bin/check-privacy-cleanup-runtime.php'],
    ['needle' => '.tools/bin/check-retention-targets.php', 'file' => '.tools/bin/check.php'],
    ['needle' => '.tools/bin/check-privacy-contract-matrix.php', 'file' => '.tools/bin/check.php'],
    ['needle' => '.tools/bin/check-privacy-export-runtime.php', 'file' => '.tools/bin/check.php'],
    ['needle' => '.tools/bin/check-privacy-cleanup-runtime.php', 'file' => '.tools/bin/check.php'],
    ['needle' => '.tools/bin/check-doc-links.php', 'file' => '.tools/bin/check.php'],
] as $runtimeMarker) {
    $needle = (string) $runtimeMarker['needle'];
    $file = (string) $runtimeMarker['file'];
    $contents = is_file($file) ? (string) file_get_contents($file) : '';
    if ($contents === '' || strpos($contents, $needle) === false) {
        sr_privacy_matrix_error('privacy runtime function marker is missing: ' . $needle);
    }
}

foreach ([
    'export_cleanup',
    'export_retained',
    'export_owner',
    'coordinator_direct',
    'operational_retained',
    'no_member_personal_data',
    '설치 SQL 또는 update SQL 기준',
    '.tools/bin/check-privacy-contract-matrix.php',
] as $needle) {
    if ($matrix !== '' && strpos($matrix, $needle) === false) {
        sr_privacy_matrix_error('privacy matrix document is missing marker: ' . $needle);
    }
}

foreach ([
    '## 마일스톤 12 재기준화 기준',
    '현재 번들 모듈은 26개',
    'antispam_captcha_providers',
    'member_oauth',
    'policy_documents',
    'quiz',
    'reaction',
    'survey',
    '쿠키와 브라우저 저장소',
    '계정 원천과 인증',
    '정책 문서와 동의',
    '사용자 제출과 활동',
    '금액성 원장과 권리',
    '알림과 외부 발송',
    '운영 보존과 감사',
    '### 이슈별 추가 모듈 영향',
    '#151 쿠키 동의 관리',
    '마케팅/분석 script를 추가하려면 사전 동의 gate',
    '#152 배너 클릭 hash',
    '기본 보관일은 180일',
    '#153 전역 감사 로그',
    '본인 export 기본 범위에서 제외',
    '#154 알림 delivery recipient',
    'push endpoint ciphertext',
    '#155 커뮤니티 쪽지 상대방 식별자',
    'raw `sender_account_id`/`recipient_account_id`',
    '#156 자산 로그 account_id 보존',
    '환불·정산·분쟁 대응',
    '#157 관리자 메모 redaction',
    '`admin_note`는 입력 가이드',
    '#158 권리 요청 전파',
    '#159 특별범주/연령/고유식별자성',
    '#160 ROPA 확장',
    '#161 conformance 자동화',
    'smoke readiness를 한 경로에서 실패',
] as $needle) {
    if ($matrix !== '' && strpos($matrix, $needle) === false) {
        sr_privacy_matrix_error('privacy matrix document is missing milestone 12 recut marker: ' . $needle);
    }
}

foreach ([
    '# 개인정보 처리활동 기록 기준',
    'ROPA',
    'activity_key',
    'module_key',
    'data_subjects',
    'data_categories',
    'processing_purpose',
    'lawful_basis',
    'special_category_policy',
    'retention_basis',
    'retention_period',
    'processors',
    '재수탁사',
    'international_transfer',
    'storage_location',
    'access_scope',
    'verification',
    '## 현재 번들 모듈 처리활동 씨앗',
    '## 외부 처리자와 국외이전 후보',
    '이메일 발송 provider',
    'CAPTCHA provider',
    'OAuth/OIDC provider',
    '결제/본인확인 provider',
    'S3 호환 storage',
    '외부 알림 채널',
    '## 특별범주·연령·고유식별자성 데이터 기준',
    '기본 번들 모듈은 특별범주 개인정보',
    'provider 원문 응답',
    '## 마일스톤 12 conformance 자동화 기준',
    '`php .tools/bin/check.php`',
    'check-retention-targets.php',
    'check-privacy-contract-matrix.php',
    'check-privacy-export-runtime.php',
    'check-privacy-cleanup-runtime.php',
    'check-doc-links.php',
    '설치 DB smoke',
    '정책 값에 `pending`, `TODO`, `TBD`, `미정`, `미확정`을 남기면 실패한다',
    '주민등록번호',
    'CI/DI 원문',
    'HMAC hash 또는 최소 결과 snapshot',
    '회원 선택 프로필',
    '`birth_date`',
    'OAuth/OIDC profile',
    'member`의 `birth_date`',
    '퀴즈/설문 답변',
    '관리자 메모/감사 metadata',
    'special_category_policy',
    '관리자 원문 노출 금지',
    '## 쿠키와 브라우저 저장소 inventory',
    'PHP session cookie',
    '`sr_popup_layer_{id}_dismissed`',
    'CAPTCHA provider script',
    '`sr_cookie_consent`',
    '`items:popup_dismissal`',
    "sr_privacy_cookie_consent_allows('popup_dismissal')",
    '항목별 허용',
    '/account/privacy-requests',
    '기능성 저장소 동의 철회',
    '`verify_remote_ip_enabled = false`',
    '`community_privacy_consent_accepted`',
    'localStorage',
    '동의 상태를 확인',
    '## 권리 요청 전파 기준',
    '`access`, `rectification`, `erasure`, `restriction`, `portability`, `objection`, `withdrawal`',
    '요청자 확인, 처리 자료 또는 처리 결과 확인, 처리 내용 메모',
    '`rectification` 정정',
    '`restriction` 처리 제한',
    '`withdrawal` 동의 철회',
    '회원 마케팅 수신 동의와 쿠키/추적 동의',
    '/account/privacy-requests`의 기능성 쿠키 철회',
    '커뮤니티/설문 제출 동의',
    '자동 일괄 변경보다 모듈 소유 정책',
    '배너 클릭 hash 보관일',
    '## 감사 로그 개인정보 기준',
    'operational_retained',
    'sr_audit_metadata_sanitize()',
    'sr_admin_audit_log_display_message()',
    'sr_admin_audit_log_display_metadata()',
    'privacy-export.php` 기본 수집 대상에서 제외',
    'audit_logs_days',
    'metadata 입력 기준',
    '## 알림 delivery recipient 기준',
    'site delivery export',
    'email delivery export',
    'push endpoint export',
    'recipient_masked',
    'external admin channel',
    'policy_documents` cleanup은 안내메일 delivery의 `account_id` 연결을 제거',
    'notifications_days',
    '/admin/notification-deliveries',
    '## 커뮤니티 쪽지 상대방 식별자 기준',
    '대상 회원이 보낸 쪽지와 받은 쪽지',
    '`message_direction`',
    '`counterparty_role`',
    '`masked_sender` 또는 `masked_recipient`',
    '`sender_account_id`와 `recipient_account_id`',
    'raw account id 제외',
    '## 자산 로그 account_id 보존 기준',
    '`export_retained` 데이터',
    '`sr_content_asset_access_logs`',
    '`sr_content_asset_action_logs`',
    '`sr_content_author_reward_logs`',
    '`sr_content_access_entitlements`와 `sr_content_file_download_logs`',
    '`sr_community_asset_logs`',
    '`sr_community_publisher_reward_logs`',
    'downloader/publisher account id',
    'cleanup runtime은 콘텐츠/커뮤니티 자산 로그 account id',
    '## 관리자 메모 redaction 기준',
    'sr_admin_privacy_request_admin_note_sanitize()',
    'privacy_requests.admin_note',
    'secret류 문자열, 이메일, 휴대폰 번호, 주민등록번호',
    'provider 원문 응답',
] as $needle) {
    if ($processingRecords !== '' && strpos($processingRecords, $needle) === false) {
        sr_privacy_matrix_error('privacy processing records document is missing marker: ' . $needle);
    }
}

foreach (array_keys($expected) as $moduleKey) {
    if ($processingRecords !== '' && strpos($processingRecords, '| `' . $moduleKey . '` |') === false) {
        sr_privacy_matrix_error('privacy processing records module row is missing: ' . $moduleKey);
    }
}

foreach ([
    'core/helpers/runtime.php' => [
        "ini_set('session.cookie_httponly', '1')",
        "ini_set('session.cookie_samesite', 'Lax')",
        'session_set_cookie_params',
    ],
    'modules/popup_layer/assets/saanraan-popup-layer.js' => [
        'function functionalCookieAllowed()',
        'sr_cookie_consent=items:',
        'popup_dismissal',
        "sr_cookie_consent=functional",
        "document.cookie = 'sr_popup_layer_' + popupId + '_dismissed=1;",
        'SameSite=Lax',
    ],
    'modules/privacy/helpers.php' => [
        'function sr_privacy_cookie_consent_cookie_name(): string',
        "'sr_cookie_consent'",
        'function sr_privacy_cookie_consent_essential_items(): array',
        "'cookie_preferences'",
        "'session_security'",
        'function sr_privacy_cookie_consent_optional_items(): array',
        "'popup_dismissal'",
        'function sr_privacy_cookie_consent_selected_items(): array',
        'function sr_privacy_cookie_consent_allows(string $category): bool',
        'function sr_privacy_cookie_consent_value_from_items(array $items): string',
        'function sr_privacy_cookie_consent_items_fields_html(array $selectedItems): string',
        'function sr_privacy_cookie_consent_essential_fields_html(): string',
        'function sr_privacy_cookie_settings_path(string $returnTo): string',
        "'/privacy/cookie-settings?return_to='",
        'function sr_privacy_cookie_consent_public_html(?PDO $pdo = null): string',
        'sr_privacy_cookie_settings_path($returnTo)',
        "sr_url('/privacy/cookie-consent')",
    ],
    'modules/privacy/actions/cookie-settings.php' => [
        'sr_get_string_without_truncation',
        'sr_member_safe_next_path',
        'sr_privacy_cookie_consent_selected_items()',
        "views/cookie-settings.php",
    ],
    'modules/privacy/actions/cookie-consent.php' => [
        'sr_require_csrf();',
        "\$consent === 'all'",
        "\$consent === 'selected'",
        "\$consent === 'reject'",
        'optional_items',
        'sr_privacy_cookie_consent_value_from_items',
        'sr_privacy_cookie_consent_set($consent);',
        'sr_member_safe_next_path',
    ],
    'modules/privacy/views/account-privacy-requests.php' => [
        "sr_privacy_cookie_consent_selected_items()",
        'sr_privacy_cookie_settings_path($cookieConsentReturnTo)',
        "sr_url('/privacy/cookie-consent')",
        'name="consent" value="reject"',
        'name="consent" value="all"',
        "sr_t('privacy::cookie.manage.body')",
    ],
    'modules/privacy/views/cookie-settings.php' => [
        "sr_t('privacy::cookie.manage.title')",
        "sr_t('privacy::cookie.essential.group')",
        "sr_t('privacy::cookie.optional.group')",
        'sr_privacy_cookie_consent_essential_fields_html()',
        "sr_url('/privacy/cookie-consent')",
        'name="consent" value="selected"',
        'name="consent" value="reject"',
        'name="consent" value="all"',
        'sr_privacy_cookie_consent_items_fields_html($cookieConsentSelectedItems)',
    ],
    'modules/privacy/paths.php' => [
        "'GET /privacy/cookie-settings' => 'actions/cookie-settings.php'",
        "'POST /privacy/cookie-consent' => 'actions/cookie-consent.php'",
    ],
    'layouts/public/basic/layout.php' => [
        '/modules/privacy/assets/cookie-consent.css',
        'sr_privacy_cookie_consent_public_html($layoutPdo)',
    ],
    'modules/quiz/layouts/basic/layout.php' => [
        '/modules/privacy/assets/cookie-consent.css',
        'sr_privacy_cookie_consent_public_html($layoutPdo)',
    ],
    'modules/content/layouts/basic/layout.php' => [
        '/modules/privacy/assets/cookie-consent.css',
        'sr_privacy_cookie_consent_public_html($layoutPdo)',
    ],
    'modules/community/themes/basic/layout.php' => [
        '/modules/privacy/assets/cookie-consent.css',
        'sr_privacy_cookie_consent_public_html($layoutPdo)',
    ],
    'modules/popup_layer/helpers.php' => [
        'function sr_popup_layer_cookie_name(int $popupId): string',
        'dismiss_cookie_days',
    ],
    'modules/antispam/module.php' => [
        "'verify_remote_ip_enabled' => false",
    ],
    'modules/antispam/helpers.php' => [
        "\$payload['remoteip'] = sr_client_ip();",
        "'<script src=\"' . sr_e((string) \$provider['script_url'])",
    ],
    'modules/antispam_captcha_providers/antispam-providers.php' => [
        'https://js.hcaptcha.com/1/api.js',
        'https://www.google.com/recaptcha/api.js',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
    ],
    'modules/community/helpers/privacy-consents.php' => [
        "function sr_community_privacy_consent_accepted_from_post(): bool",
        "community_privacy_consent_accepted",
    ],
] as $file => $markers) {
    $contents = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($contents)) {
        sr_privacy_matrix_error('browser storage inventory source cannot be read: ' . $file);
        continue;
    }

    foreach ($markers as $marker) {
        if (strpos($contents, $marker) === false) {
            sr_privacy_matrix_error('browser storage inventory source marker is missing in ' . $file . ': ' . $marker);
        }
    }
}

foreach ([
    'admin' => '관리자 권한',
    'embed_manager' => '본문 참조',
    'logo_manager' => '로고 변경',
] as $moduleKey => $retentionMarker) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | ' . $retentionMarker) === false) {
        sr_privacy_matrix_error('operational retained module is missing detailed retention row: ' . $moduleKey);
    }
}

foreach ([
    'asset_exchange' => ['환전 실행 증빙', '환전 묶음 ID'],
    'coupon' => ['쿠폰 지급/사용/환불 권리 증빙', 'refunded_by_account_id'],
    'deposit' => ['현금성 예치금 원장', '은행명/계좌번호/예금주'],
    'notification' => ['회원 알림 제공 이력', 'delivery destination/metadata'],
    'point' => ['포인트 원장', '만료 source/consume transaction 연결'],
    'reward' => ['적립금 원장', '은행명/계좌번호/예금주'],
] as $moduleKey => $markers) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | ' . $markers[0]) === false) {
        sr_privacy_matrix_error('export retained module is missing detailed retention row: ' . $moduleKey);
        continue;
    }

    foreach ($markers as $marker) {
        if ($matrix !== '' && strpos($matrix, $marker) === false) {
            sr_privacy_matrix_error('export retained module detail is missing marker for ' . $moduleKey . ': ' . $marker);
        }
    }
}

foreach ([
    '## 운영 보존 세부 기준',
    '보존 사유',
    '운영자 접근 범위',
    '1.0 전 검토 항목',
    '탈퇴 계정 표시명',
    'orphan ref',
    '감사 로그로 충분히 대체',
    '재식별성이 높은 필드',
    '보존기간 정책',
    '고위험 필드/연결',
    '계좌정보 마스킹',
    '주소 마스킹',
] as $needle) {
    if ($matrix !== '' && strpos($matrix, $needle) === false) {
        sr_privacy_matrix_error('privacy matrix document is missing retention policy marker: ' . $needle);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "privacy contract matrix check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy contract matrix check completed.\n";
