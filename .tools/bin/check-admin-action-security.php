#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);
chdir($root);

$errors = [];

function toy_admin_action_security_module_dirs(string $root): array
{
    $dirs = [];
    if (!is_dir($root . '/modules')) {
        return [];
    }

    foreach (new DirectoryIterator($root . '/modules') as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }

        $dirs[] = $entry->getPathname();
    }

    sort($dirs, SORT_STRING);
    return $dirs;
}

function toy_admin_action_security_path_is_safe(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

foreach (toy_admin_action_security_module_dirs($root) as $moduleDir) {
    $pathsFile = $moduleDir . '/paths.php';
    if (!is_file($pathsFile)) {
        continue;
    }

    $paths = include $pathsFile;
    if (!is_array($paths)) {
        $errors[] = 'Module paths.php must return an array: ' . $pathsFile;
        continue;
    }

    foreach ($paths as $route => $actionRelativePath) {
        $route = (string) $route;
        $actionRelativePath = (string) $actionRelativePath;
        if (preg_match('/\A(GET|POST) (\/.*)\z/', $route, $matches) !== 1) {
            $errors[] = 'Route key format is invalid: ' . $pathsFile . ' ' . $route;
            continue;
        }

        if (!toy_admin_action_security_path_is_safe($actionRelativePath)) {
            $errors[] = 'Action path is unsafe: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath;
            continue;
        }

        $method = (string) $matches[1];
        $path = (string) $matches[2];
        $actionFile = $moduleDir . '/' . $actionRelativePath;
        if (!is_file($actionFile)) {
            $errors[] = 'Action file is missing: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath;
            continue;
        }

        $content = file_get_contents($actionFile);
        if (!is_string($content)) {
            $errors[] = 'Action file cannot be read: ' . $actionFile;
            continue;
        }

        if ($method === 'POST' && strpos($content, 'toy_require_csrf(') === false) {
            $errors[] = 'POST action must require CSRF: ' . $route . ' -> ' . $actionFile;
        }

        if (str_starts_with($path, '/admin')) {
            if (strpos($content, 'toy_member_require_login(') === false) {
                $errors[] = 'Admin action must require login: ' . $route . ' -> ' . $actionFile;
            }

            if (strpos($content, 'toy_admin_require_role(') === false) {
                $errors[] = 'Admin action must require an admin role: ' . $route . ' -> ' . $actionFile;
            }
        }
    }
}

$adminRolesHelper = file_get_contents($root . '/modules/admin/helpers/roles.php');
if (!is_string($adminRolesHelper) || strpos($adminRolesHelper, 'function toy_admin_active_owner_count') === false) {
    $errors[] = 'Admin role helper must expose an active owner count guard.';
} elseif (
    strpos($adminRolesHelper, 'toy_admin_active_owner_count($pdo) <= 1') === false
    || strpos($adminRolesHelper, '마지막 active owner 권한은 회수할 수 없습니다.') === false
) {
    $errors[] = 'Admin role helper must prevent revoking the last active owner role.';
}

$adminInputHelper = file_get_contents($root . '/modules/admin/helpers/input.php');
if (!is_string($adminInputHelper)) {
    $errors[] = 'Admin input helper cannot be read.';
} elseif (
    strpos($adminInputHelper, 'function toy_admin_post_positive_int') === false
    || strpos($adminInputHelper, 'function toy_admin_post_int_in_range') === false
    || strpos($adminInputHelper, "\$value = \$_POST[\$key] ?? '';") === false
    || strpos($adminInputHelper, 'is_array($value)') === false
    || strpos($adminInputHelper, 'strlen($value) > $maxLength') === false
    || strpos($adminInputHelper, "preg_match('/\\A[1-9][0-9]*\\z/', \$value)") === false
    || strpos($adminInputHelper, "preg_match('/\\A\\d+\\z/', \$value)") === false
    || strpos($adminInputHelper, '$integerValue < $min || $integerValue > $max') === false
    || strpos($adminInputHelper, 'return (int) $value;') === false
    || strpos($adminRolesHelper, "toy_admin_post_positive_int('account_id')") === false
) {
    $errors[] = 'Admin POST numeric inputs must be accepted only as strict integer strings.';
}

$adminMembersHelper = file_get_contents($root . '/modules/admin/helpers/members.php');
if (!is_string($adminMembersHelper)) {
    $errors[] = 'Admin members helper cannot be read.';
} else {
    if (
        strpos($adminMembersHelper, "in_array(\$intent, ['status', 'revoke_sessions'], true)") === false
        || strpos($adminMembersHelper, '회원 작업 값이 올바르지 않습니다.') === false
        || strpos($adminMembersHelper, "toy_admin_post_positive_int('account_id')") === false
    ) {
        $errors[] = 'Admin members helper must allowlist member management intents and strict account ids.';
    }

    if (
        strpos($adminMembersHelper, 'toy_admin_current_roles($pdo, $targetAccountId)') === false
        || strpos($adminMembersHelper, 'toy_admin_has_role($pdo, (int) $account[\'id\'], [\'owner\'])') === false
        || strpos($adminMembersHelper, 'owner 계정 상태와 세션은 owner만 변경할 수 있습니다.') === false
    ) {
        $errors[] = 'Admin members helper must prevent non-owner admins from changing owner accounts.';
    }

    if (
        strpos($adminMembersHelper, 'toy_admin_active_owner_count($pdo) <= 1') === false
        || strpos($adminMembersHelper, '마지막 active owner 계정은 비활성화할 수 없습니다.') === false
    ) {
        $errors[] = 'Admin members helper must prevent deactivating the last active owner.';
    }

    if (
        strpos($adminMembersHelper, 'function toy_admin_member_email_display') === false
        || strpos($adminMembersHelper, 'function toy_admin_member_display_name_preview') === false
        || strpos($adminMembersHelper, "return \$prefix . '***@' . \$domain;") === false
        || strpos($adminMembersHelper, "toy_log_line_value((string) (\$member['display_name'] ?? ''), 80)") === false
    ) {
        $errors[] = 'Admin member lists must reduce member email and display name exposure before display.';
    }
}

$adminMembersView = file_get_contents($root . '/modules/admin/views/members.php');
if (!is_string($adminMembersView)) {
    $errors[] = 'Admin members view cannot be read.';
} elseif (
    strpos($adminMembersView, 'toy_admin_member_email_display($member)') === false
    || strpos($adminMembersView, 'toy_admin_member_display_name_preview($member)') === false
) {
    $errors[] = 'Admin members view must render member identity fields through privacy display helpers.';
}

$adminRolesView = file_get_contents($root . '/modules/admin/views/roles.php');
if (!is_string($adminRolesView)) {
    $errors[] = 'Admin roles view cannot be read.';
} elseif (
    strpos($adminRolesView, 'toy_admin_member_email_display($adminAccount)') === false
    || strpos($adminRolesView, 'toy_admin_member_display_name_preview($adminAccount)') === false
) {
    $errors[] = 'Admin roles view must render member identity fields through privacy display helpers.';
}

$adminSettingsHelper = file_get_contents($root . '/modules/admin/helpers/settings.php');
if (!is_string($adminSettingsHelper)) {
    $errors[] = 'Admin settings helper cannot be read.';
} elseif (
    strpos($adminSettingsHelper, "in_array(\$intent, ['site', 'site_setting', 'delete_site_setting'], true)") === false
    || strpos($adminSettingsHelper, '사이트 설정 작업 값이 올바르지 않습니다.') === false
) {
    $errors[] = 'Admin settings helper must allowlist site setting intents.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, 'function toy_admin_sensitive_site_setting_keys') === false
    || strpos($adminSettingsHelper, "'admin.module_sources_enabled' => true") === false
    || strpos($adminSettingsHelper, "'admin.repository_archive_unchecked_enabled' => true") === false
    || strpos($adminSettingsHelper, 'function toy_admin_site_setting_requires_bool') === false
    || strpos($adminSettingsHelper, "toy_admin_site_setting_requires_bool(\$settingKey) && \$valueType !== 'bool'") === false
    || strpos($adminSettingsHelper, '고위험 사이트 설정은 bool 타입으로만 저장할 수 있습니다.') === false
    || substr_count($adminSettingsHelper, 'toy_admin_site_setting_reauth_errors($pdo, $account, $settingKey,') < 2
    || strpos($adminSettingsHelper, 'site_setting_reauth') === false
)) {
    $errors[] = 'Admin settings helper must require reauthentication for sensitive site setting changes.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, 'function toy_admin_setting_value_is_secret') === false
    || strpos($adminSettingsHelper, 'function toy_admin_setting_display_value') === false
    || strpos($adminSettingsHelper, 'function toy_admin_setting_value_type_errors') === false
    || strpos($adminSettingsHelper, 'function toy_admin_normalize_setting_value') === false
    || strpos($adminSettingsHelper, 'function toy_admin_site_setting_value_is_secret') === false
    || strpos($adminSettingsHelper, 'function toy_admin_site_setting_display_value') === false
    || strpos($adminSettingsHelper, 'function toy_admin_module_setting_display_value') === false
    || strpos($adminSettingsHelper, 'password|token|secret|credential|bearer') === false
    || strpos($adminSettingsHelper, "'[masked]'") === false
)) {
    $errors[] = 'Admin settings helper must mask secret-like setting values before display.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, "preg_match('/\\A-?\\d+\\z/', \$settingValue)") === false
    || strpos($adminSettingsHelper, 'bool 설정값은 1/0, true/false, yes/no, on/off 중 하나여야 합니다.') === false
    || strpos($adminSettingsHelper, "return in_array(strtolower(\$settingValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';") === false
    || strpos($adminSettingsHelper, 'toy_admin_setting_value_type_errors($settingValue, $valueType)') === false
    || strpos($adminSettingsHelper, 'toy_admin_normalize_setting_value($settingValue, $valueType)') === false
)) {
    $errors[] = 'Admin settings helper must validate and normalize int/bool/json setting values before storage.';
}

if (!isset($adminModuleActionsHelper)) {
    $adminModuleActionsHelper = file_get_contents($root . '/modules/admin/helpers/module-actions.php');
}
if (is_string($adminModuleActionsHelper) && (
    strpos($adminModuleActionsHelper, 'function toy_admin_module_setting_reauth_errors') === false
    || substr_count($adminModuleActionsHelper, 'toy_admin_module_setting_reauth_errors($pdo, $account, $moduleKey, $settingKey,') < 2
    || strpos($adminModuleActionsHelper, 'toy_admin_setting_value_is_secret($settingKey)') === false
    || strpos($adminModuleActionsHelper, 'module_setting_reauth') === false
    || strpos($adminModuleActionsHelper, 'module.setting.reauth_failed') === false
)) {
    $errors[] = 'Admin module settings helper must require reauthentication for secret-like setting changes.';
}
if (is_string($adminModuleActionsHelper) && (
    strpos($adminModuleActionsHelper, 'toy_admin_setting_value_type_errors($settingValue, $valueType)') === false
    || strpos($adminModuleActionsHelper, 'toy_admin_normalize_setting_value($settingValue, $valueType)') === false
)) {
    $errors[] = 'Admin module settings helper must validate and normalize typed setting values before storage.';
}

$adminSettingsView = file_get_contents($root . '/modules/admin/views/settings.php');
if (!is_string($adminSettingsView)) {
    $errors[] = 'Admin settings view cannot be read.';
} elseif (strpos($adminSettingsView, 'toy_admin_site_setting_display_value($setting)') === false) {
    $errors[] = 'Admin settings view must render site setting values through the masking helper.';
}

$adminModulesView = file_get_contents($root . '/modules/admin/views/modules.php');
if (!is_string($adminModulesView)) {
    $errors[] = 'Admin modules view cannot be read.';
} elseif (
    strpos($adminModulesView, 'toy_admin_module_setting_display_value($setting)') === false
    || substr_count($adminModulesView, 'name="owner_password"') < 2
    || strpos($adminModulesView, 'toy_admin_setting_value_is_secret((string) $setting[\'setting_key\'])') === false
) {
    $errors[] = 'Admin modules view must mask module setting values and collect owner reauth for secret-like setting changes.';
}

$adminAuditLogsHelper = file_get_contents($root . '/modules/admin/helpers/audit-logs.php');
if (!is_string($adminAuditLogsHelper)) {
    $errors[] = 'Admin audit logs helper cannot be read.';
} elseif (
    strpos($adminAuditLogsHelper, 'function toy_admin_audit_metadata_redact') === false
    || strpos($adminAuditLogsHelper, 'function toy_admin_audit_log_identifier_filter') === false
    || strpos($adminAuditLogsHelper, 'function toy_admin_audit_log_result_filter') === false
    || strpos($adminAuditLogsHelper, 'function toy_admin_audit_log_date_filter') === false
    || strpos($adminAuditLogsHelper, "preg_match('/\\A[a-z][a-z0-9_.-]*\\z/', \$value)") === false
    || strpos($adminAuditLogsHelper, "in_array(\$value, ['success', 'failure'], true)") === false
    || strpos($adminAuditLogsHelper, "preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/', \$value)") === false
    || strpos($adminAuditLogsHelper, "DateTimeImmutable::createFromFormat('!Y-m-d', \$value)") === false
    || strpos($adminAuditLogsHelper, 'DateTimeImmutable::getLastErrors()') === false
    || strpos($adminAuditLogsHelper, 'function toy_admin_audit_log_display_metadata') === false
    || strpos($adminAuditLogsHelper, 'function toy_admin_audit_log_display_message') === false
    || strpos($adminAuditLogsHelper, 'toy_admin_setting_value_is_secret($key)') === false
    || strpos($adminAuditLogsHelper, 'return toy_log_sensitive_text_sanitize($value);') === false
    || strpos($adminAuditLogsHelper, "toy_log_sensitive_text_sanitize(toy_log_line_value((string) (\$log['message'] ?? ''), 1000))") === false
    || strpos($adminAuditLogsHelper, 'json_decode($metadataJson, true)') === false
    || strpos($adminAuditLogsHelper, "'[invalid metadata]'") === false
) {
    $errors[] = 'Admin audit logs helper must validate filters and redact secret-like metadata before display.';
}

$adminAuditLogsView = file_get_contents($root . '/modules/admin/views/audit-logs.php');
if (!is_string($adminAuditLogsView)) {
    $errors[] = 'Admin audit logs view cannot be read.';
} elseif (
    strpos($adminAuditLogsView, 'toy_admin_audit_log_display_message($log)') === false
    || strpos($adminAuditLogsView, 'toy_admin_audit_log_display_metadata($log)') === false
) {
    $errors[] = 'Admin audit logs view must render messages and metadata through redaction helpers.';
}

$coreOpsHelper = file_get_contents($root . '/core/helpers/ops.php');
if (!is_string($coreOpsHelper)) {
    $errors[] = 'Core ops helper cannot be read.';
} elseif (
    strpos($coreOpsHelper, 'function toy_audit_metadata_sanitize') === false
    || strpos($coreOpsHelper, 'function toy_audit_metadata_key_is_secret') === false
    || strpos($coreOpsHelper, 'function toy_log_sensitive_text_sanitize') === false
    || strpos($coreOpsHelper, 'toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 1000))') === false
    || strpos($coreOpsHelper, "toy_log_sensitive_text_sanitize(toy_log_line_value((string) (\$data['message'] ?? ''), 1000))") === false
    || strpos($coreOpsHelper, 'return toy_log_sensitive_text_sanitize($value);') === false
    || strpos($coreOpsHelper, 'toy_audit_metadata_sanitize($metadata)') === false
    || strpos($coreOpsHelper, 'toy_audit_metadata_sanitize($payload)') === false
    || strpos($coreOpsHelper, 'password|token|secret|credential|bearer|authorization') === false
    || strpos($coreOpsHelper, 'Bearer [masked]') === false
    || strpos($coreOpsHelper, "'[masked]'") === false
) {
    $errors[] = 'Core ops helper must sanitize secret-like values before storing audit logs, markers, and exception logs.';
}

$adminPrivacyRequestsHelper = file_get_contents($root . '/modules/admin/helpers/privacy-requests.php');
if (!is_string($adminPrivacyRequestsHelper)) {
    $errors[] = 'Admin privacy requests helper cannot be read.';
} elseif (
    strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_list_preview') === false
    || strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_requester_display') === false
    || strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_terminal_statuses') === false
    || strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_export_reauth_errors') === false
    || strpos($adminPrivacyRequestsHelper, 'privacy_request_export_reauth') === false
    || strpos($adminPrivacyRequestsHelper, "toy_post_string_without_truncation('admin_note', 2000)") === false
    || strpos($adminPrivacyRequestsHelper, '$adminNote === null') === false
    || strpos($adminPrivacyRequestsHelper, 'catch (Throwable $exception)') === false
    || strpos($adminPrivacyRequestsHelper, "toy_log_exception(\$exception, 'privacy_request_export_member_' . (int) \$privacyRequest['id'])") === false
    || strpos($adminPrivacyRequestsHelper, "'member_data_unavailable'") === false
    || strpos($adminPrivacyRequestsHelper, '종결된 개인정보 요청 상태는 다시 변경할 수 없습니다.') === false
    || strpos($adminPrivacyRequestsHelper, "toy_admin_post_positive_int('request_id')") === false
    || strpos($adminPrivacyRequestsHelper, "return \$prefix . '***@' . \$domain;") === false
    || strpos($adminPrivacyRequestsHelper, "return mb_substr(\$preview, 0, \$maxLength) . '...';") === false
) {
    $errors[] = 'Admin privacy request helpers must reduce list exposure, validate request ids, protect terminal status changes, isolate member export failures, and reauthenticate exports.';
}

$adminPrivacyRequestsView = file_get_contents($root . '/modules/admin/views/privacy-requests.php');
if (!is_string($adminPrivacyRequestsView)) {
    $errors[] = 'Admin privacy requests view cannot be read.';
} elseif (
    strpos($adminPrivacyRequestsView, 'toy_admin_privacy_request_requester_display($request)') === false
    || strpos($adminPrivacyRequestsView, "toy_admin_privacy_request_list_preview(\$request['request_message'] ?? null)") === false
    || strpos($adminPrivacyRequestsView, 'name="admin_password"') === false
    || strpos($adminPrivacyRequestsView, 'placeholder="새 관리자 메모"') === false
    || strpos($adminPrivacyRequestsView, "\$request['admin_note'] ?? ''") !== false
) {
    $errors[] = 'Admin privacy requests view must reduce requester/message exposure and avoid prefilled admin notes.';
}

$adminPrivacyRequestExportAction = file_get_contents($root . '/modules/admin/actions/privacy-request-export.php');
if (!is_string($adminPrivacyRequestExportAction)) {
    $errors[] = 'Admin privacy request export action cannot be read.';
} elseif (strpos($adminPrivacyRequestExportAction, "toy_admin_post_positive_int('id')") === false) {
    $errors[] = 'Admin privacy request export action must validate request ids strictly.';
}

$coreSettingsHelper = file_get_contents($root . '/core/helpers/settings.php');
if (!is_string($coreSettingsHelper)) {
    $errors[] = 'Core settings helper cannot be read.';
} elseif (strpos($coreSettingsHelper, "/\\A[a-z][a-z0-9_]{1,39}\\z/") === false) {
    $errors[] = 'Core module key validation must require a letter prefix and bounded length.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function toy_module_contract_is_loadable') === false
    || strpos($coreSettingsHelper, 'toy_module_contract_errors($metadata) === []') === false
    || strpos($coreSettingsHelper, 'toy_module_contract_is_loadable($moduleKey)') === false
)) {
    $errors[] = 'Core module runtime loading must require the current module contract metadata.';
}

$frontController = file_get_contents($root . '/index.php');
if (!is_string($frontController)) {
    $errors[] = 'Front controller cannot be read.';
} elseif (
    strpos($frontController, "toy_enabled_module_contract_files(\$pdo, 'paths.php')") === false
    || strpos($frontController, '$paths = include $pathsFile;') === false
) {
    $errors[] = 'Front controller must load module paths.php through the contract file loader.';
}

$adminNavigationHelper = file_get_contents($root . '/modules/admin/helpers/navigation.php');
if (!is_string($adminNavigationHelper)) {
    $errors[] = 'Admin navigation helper cannot be read.';
} elseif (
    strpos($adminNavigationHelper, "toy_enabled_module_contract_files(\$pdo, 'admin-menu.php', ['admin'])") === false
    || strpos($adminNavigationHelper, "toy_enabled_module_contract_files(\$pdo, 'paths.php', ['admin'])") === false
    || strpos($adminNavigationHelper, '$paths = $pathsFile !== \'\' ? include $pathsFile : [];') === false
) {
    $errors[] = 'Admin navigation must load module paths.php through the contract file loader.';
}

$adminModuleSourcesHelper = file_get_contents($root . '/modules/admin/helpers/module-sources.php');
if (!is_string($adminModuleSourcesHelper)) {
    $errors[] = 'Admin module sources helper cannot be read.';
} elseif (
    strpos($adminModuleSourcesHelper, 'function toy_admin_zip_entry_is_symlink') === false
    || strpos($adminModuleSourcesHelper, 'toy_admin_zip_entry_is_symlink($zip, $i)') === false
    || strpos($adminModuleSourcesHelper, 'zip 안에 심볼릭 링크가 있습니다.') === false
) {
    $errors[] = 'Admin module source zip checks must reject symlink entries before extraction.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, "preg_match('/[\\x00-\\x1F\\x7F]/', \$name)") === false
    || strpos($adminModuleSourcesHelper, "str_contains(\$name, ':')") === false
    || strpos($adminModuleSourcesHelper, "str_contains(\$name, '//')") === false
    || strpos($adminModuleSourcesHelper, "\$segment === '.'") === false
)) {
    $errors[] = 'Admin module source zip paths must reject control characters, colon separators, and ambiguous path segments.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, "throw new RuntimeException('zip 항목 속성을 확인할 수 없습니다.');") === false
)) {
    $errors[] = 'Admin module source zip symlink checks must fail closed when entry attributes cannot be read.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_validate_extracted_module_tree') === false
    || strpos($adminModuleSourcesHelper, 'toy_admin_validate_extracted_module_tree($extractDir)') === false
    || strpos($adminModuleSourcesHelper, '압축 해제된 모듈에 심볼릭 링크가 있습니다.') === false
    || strpos($adminModuleSourcesHelper, '압축 해제된 모듈 경로가 작업 디렉터리 밖을 가리킵니다.') === false
    || strpos($adminModuleSourcesHelper, 'toy_admin_path_is_inside($item->getPathname(), $extractDir)') === false
)) {
    $errors[] = 'Admin module source extraction must verify the extracted file tree stays inside the work directory.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_is_https_public_url') === false
    || strpos($adminModuleSourcesHelper, "strtolower((string) parse_url(\$url, PHP_URL_SCHEME)) === 'https'") === false
    || strpos($adminModuleSourcesHelper, "toy_admin_is_https_public_url((string) (\$entry['zip_url'] ?? ''))") === false
    || strpos($adminModuleSourcesHelper, 'toy_admin_is_https_public_url($repository)') === false
)) {
    $errors[] = 'Admin module source registry URLs must be restricted to HTTPS public URLs at runtime.';
}
if (is_string($adminModuleSourcesHelper) && (
    substr_count($adminModuleSourcesHelper, "'follow_location' => 0") < 2
    || substr_count($adminModuleSourcesHelper, "'max_redirects' => 0") < 2
)) {
    $errors[] = 'Admin module source downloads must not follow redirects after registry URL validation.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_http_stream_status_is_success') === false
    || strpos($adminModuleSourcesHelper, 'stream_get_meta_data($stream)') === false
    || substr_count($adminModuleSourcesHelper, 'toy_admin_http_stream_status_is_success($source)') < 2
    || strpos($adminModuleSourcesHelper, 'registry release zip 다운로드 응답이 성공 상태가 아닙니다.') === false
    || strpos($adminModuleSourcesHelper, 'repository archive zip 다운로드 응답이 성공 상태가 아닙니다.') === false
)) {
    $errors[] = 'Admin module source downloads must reject non-2xx HTTP responses before saving zip bodies.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_registry_entry_contract_ready') === false
    || strpos($adminModuleSourcesHelper, 'TOY_MODULE_CONTRACT_VERSION') === false
    || strpos($adminModuleSourcesHelper, 'return $moduleContract === TOY_MODULE_CONTRACT_VERSION;') === false
    || strpos($adminModuleSourcesHelper, '$moduleContract === \'\' ||') !== false
    || substr_count($adminModuleSourcesHelper, 'toy_admin_registry_entry_contract_ready($entry)') < 2
)) {
    $errors[] = 'Admin module source registry downloads and repository archives must require compatible module contracts.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_module_metadata_errors') === false
    || strpos($adminModuleSourcesHelper, 'toy_module_contract_errors($metadata)') === false
    || strpos($adminModuleSourcesHelper, 'module.php의 version은 YYYY.MM.NNN 형식이어야 합니다.') === false
)) {
    $errors[] = 'Admin module source helper must expose shared module metadata and contract validation.';
}
if (is_string($adminModuleSourcesHelper) && (
    strpos($adminModuleSourcesHelper, 'function toy_admin_install_module_source_files') === false
    || strpos($adminModuleSourcesHelper, '!rename($backupDir, $targetDir)') === false
    || strpos($adminModuleSourcesHelper, "throw new RuntimeException('기존 모듈 백업을 복구할 수 없습니다.', 0, \$exception)") === false
)) {
    $errors[] = 'Admin module source replacement must fail closed when backup restore fails.';
}

$adminModuleActionsHelper = file_get_contents($root . '/modules/admin/helpers/module-actions.php');
if (!is_string($adminModuleActionsHelper)) {
    $errors[] = 'Admin module actions helper cannot be read.';
} elseif (
    strpos($adminModuleActionsHelper, "'result' => 'failure'") === false
    || strpos($adminModuleActionsHelper, 'Module source zip upload failed.') === false
    || strpos($adminModuleActionsHelper, 'Module source zip download failed.') === false
    || substr_count($adminModuleActionsHelper, 'toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 500))') < 2
) {
    $errors[] = 'Admin module source failures must write and display sanitized failure messages.';
}
if (is_string($adminModuleActionsHelper) && substr_count($adminModuleActionsHelper, 'toy_admin_module_metadata_errors($metadata)') < 3) {
    $errors[] = 'Admin module install, enable, and version sync actions must validate module metadata contracts server-side.';
}

$adminUpdatesHelper = file_get_contents($root . '/modules/admin/helpers/updates.php');
if (!is_string($adminUpdatesHelper)) {
    $errors[] = 'Admin updates helper cannot be read.';
} elseif (
    substr_count($adminUpdatesHelper, 'toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 500))') < 2
    || strpos($adminUpdatesHelper, "'schema.update.failed'") === false
    || strpos($adminUpdatesHelper, '\'message\' => toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 500))') === false
    || strpos($adminUpdatesHelper, "toy_log_sensitive_text_sanitize(toy_log_line_value((string) (\$decoded['message'] ?? ''), 500))") === false
) {
    $errors[] = 'Admin schema update failures must write sanitized audit and marker messages.';
}

$adminDashboardHelper = file_get_contents($root . '/modules/admin/helpers/dashboard.php');
if (!is_string($adminDashboardHelper)) {
    $errors[] = 'Admin dashboard helper cannot be read.';
} elseif (strpos($adminDashboardHelper, "toy_log_sensitive_text_sanitize(toy_log_line_value((string) (\$decoded['message'] ?? ''), 500))") === false) {
    $errors[] = 'Admin dashboard recovery markers must mask secret-like messages before display.';
}

if ($errors !== []) {
    fwrite(STDERR, "admin action security checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin action security checks completed.\n";
