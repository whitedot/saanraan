#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

$errors = [];

function sr_admin_action_security_module_dirs(string $root): array
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

function sr_admin_action_security_path_is_safe(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

function sr_admin_action_security_next_code_token(array $tokens, int $start): array
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return [$i, $token];
    }

    return [$count, null];
}

function sr_admin_action_security_string_literal(?array $token): ?string
{
    if ($token === null || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }

    $literal = $token[1];
    if (strlen($literal) < 2) {
        return null;
    }

    $quote = $literal[0];
    if ($quote !== "'" && $quote !== '"') {
        return null;
    }

    return stripcslashes(substr($literal, 1, -1));
}

function sr_admin_action_security_has_raw_exit(string $content): bool
{
    foreach (token_get_all($content) as $token) {
        if (is_array($token) && $token[0] === T_EXIT) {
            return true;
        }
    }

    return false;
}

function sr_admin_action_security_has_location_header(string $content): bool
{
    $tokens = token_get_all($content);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'header') {
            continue;
        }

        [, $openToken] = sr_admin_action_security_next_code_token($tokens, $i + 1);
        if ($openToken !== '(') {
            continue;
        }

        [, $firstArgument] = sr_admin_action_security_next_code_token($tokens, $i + 2);
        $literal = is_array($firstArgument) ? sr_admin_action_security_string_literal($firstArgument) : null;
        if (is_string($literal) && str_starts_with(strtolower($literal), 'location:')) {
            return true;
        }
    }

    return false;
}

function sr_admin_action_security_has_unsafe_header_call(string $content): bool
{
    $allowedHeaderPrefixes = [
        'cache-control:',
        'clear-site-data:',
        'content-disposition:',
        'content-length:',
        'content-security-policy:',
        'content-type:',
        'pragma:',
        'x-content-type-options:',
    ];

    $tokens = token_get_all($content);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'header') {
            continue;
        }

        [, $openToken] = sr_admin_action_security_next_code_token($tokens, $i + 1);
        if ($openToken !== '(') {
            continue;
        }

        [, $firstArgument] = sr_admin_action_security_next_code_token($tokens, $i + 2);
        $literal = is_array($firstArgument) ? sr_admin_action_security_string_literal($firstArgument) : null;
        if (!is_string($literal)) {
            return true;
        }

        $lowerLiteral = strtolower(ltrim($literal));
        foreach ($allowedHeaderPrefixes as $prefix) {
            if (str_starts_with($lowerLiteral, $prefix)) {
                continue 2;
            }
        }

        return true;
    }

    return false;
}

function sr_admin_action_security_has_direct_json_response(string $content): bool
{
    return str_contains($content, 'echo json_encode(')
        || str_contains($content, "header('Content-Type: application/json")
        || str_contains($content, 'header("Content-Type: application/json');
}

function sr_admin_action_security_self_test(): void
{
    global $errors;

    $cases = [
        'raw exit' => [sr_admin_action_security_has_raw_exit('<?php if ($ok) { exit; }'), true],
        'raw die' => [sr_admin_action_security_has_raw_exit('<?php die("no");'), true],
        'plain header ignored' => [sr_admin_action_security_has_location_header('<?php header("Content-Type: text/plain");'), false],
        'location header detected' => [sr_admin_action_security_has_location_header('<?php header("Location: /admin");'), true],
        'lowercase location header detected' => [sr_admin_action_security_has_location_header('<?php header("location: /admin");'), true],
        'content type header allowed' => [sr_admin_action_security_has_unsafe_header_call('<?php header("Content-Type: " . $mimeType);'), false],
        'cache control header allowed' => [sr_admin_action_security_has_unsafe_header_call('<?php header("Cache-Control: no-store");'), false],
        'clear site data header allowed' => [sr_admin_action_security_has_unsafe_header_call('<?php header(\'Clear-Site-Data: "cache"\');'), false],
        'direct location header unsafe' => [sr_admin_action_security_has_unsafe_header_call('<?php header("Location: /admin");'), true],
        'dynamic header unsafe' => [sr_admin_action_security_has_unsafe_header_call('<?php header($headerValue);'), true],
        'direct json response unsafe' => [sr_admin_action_security_has_direct_json_response('<?php header("Content-Type: application/json; charset=utf-8"); echo json_encode([]);'), true],
        'json helper response accepted' => [sr_admin_action_security_has_direct_json_response('<?php sr_json_response(["ok" => true]);'), false],
        'safe action path accepted' => [sr_admin_action_security_path_is_safe('actions/admin-settings.php'), true],
        'nested safe action path accepted' => [sr_admin_action_security_path_is_safe('actions/admin/settings-save.php'), true],
        'path traversal rejected' => [sr_admin_action_security_path_is_safe('actions/../admin.php'), false],
        'backslash path rejected' => [sr_admin_action_security_path_is_safe('actions\\admin.php'), false],
    ];

    foreach ($cases as $label => [$actual, $expected]) {
        if ((bool) $actual !== (bool) $expected) {
            $errors[] = 'Admin action security self-test failed: ' . $label;
        }
    }
}

sr_admin_action_security_self_test();

function sr_admin_action_security_effective_content(string $root, string $content): string
{
    preg_match_all(
        "#include\s+SR_ROOT\s*\.\s*'(/modules/[a-z0-9_]+/actions/[a-z0-9_\-]+\.php)'#",
        $content,
        $matches
    );

    foreach ($matches[1] as $includePath) {
        $absolutePath = $root . (string) $includePath;
        if (!is_file($absolutePath)) {
            continue;
        }

        $includedContent = file_get_contents($absolutePath);
        if (is_string($includedContent)) {
            $content .= "\n" . $includedContent;
        }
    }

    foreach ([
        "sr_quiz_render_skin(\$pdo, \$quizSettings, 'view')" => '/modules/quiz/skins/basic/view.php',
        "sr_survey_render_skin(\$pdo, \$settings, 'view')" => '/modules/survey/skins/basic/view.php',
    ] as $marker => $includePath) {
        if (!str_contains($content, $marker)) {
            continue;
        }

        $absolutePath = $root . $includePath;
        $includedContent = is_file($absolutePath) ? file_get_contents($absolutePath) : false;
        if (is_string($includedContent)) {
            $content .= "\n" . $includedContent;
        }
    }

    return $content;
}

foreach (sr_admin_action_security_module_dirs($root) as $moduleDir) {
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

        if (!sr_admin_action_security_path_is_safe($actionRelativePath)) {
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
        $effectiveContent = sr_admin_action_security_effective_content($root, $content);

        if ($method === 'POST' && strpos($effectiveContent, 'sr_require_csrf(') === false) {
            $errors[] = 'POST action must require CSRF: ' . $route . ' -> ' . $actionFile;
        }

        if (sr_admin_action_security_has_raw_exit($effectiveContent)) {
            $errors[] = 'Action must end through sr_redirect(), sr_render_error(), or sr_finish_response() instead of raw exit/die: ' . $route . ' -> ' . $actionFile;
        }

        if (sr_admin_action_security_has_location_header($effectiveContent)) {
            $errors[] = 'Action must use sr_redirect() instead of a direct Location header: ' . $route . ' -> ' . $actionFile;
        }

        if (sr_admin_action_security_has_unsafe_header_call($effectiveContent)) {
            $errors[] = 'Action header() calls must start with an allowlisted response header literal and must not build dynamic header names: ' . $route . ' -> ' . $actionFile;
        }

        if (sr_admin_action_security_has_direct_json_response($effectiveContent)) {
            $errors[] = 'JSON action responses must use sr_json_response() instead of direct JSON headers or echo json_encode(): ' . $route . ' -> ' . $actionFile;
        }

        if (strpos($effectiveContent, 'sr_request_contract_mark(') !== false || strpos($effectiveContent, 'sr_request_contract_guard_blocked(') !== false) {
            $errors[] = 'Action must use public guard helpers instead of low-level request contract markers: ' . $route . ' -> ' . $actionFile;
        }

        if (str_starts_with($path, '/admin')) {
            if (strpos($effectiveContent, 'sr_member_require_login(') === false) {
                $errors[] = 'Admin action must require login: ' . $route . ' -> ' . $actionFile;
            }

            $permissionPosition = strpos($effectiveContent, 'sr_admin_require_permission(');
            $ownerPosition = strpos($effectiveContent, 'sr_admin_require_owner(');
            $legacyRolePosition = strpos($effectiveContent, 'sr_admin_require_role(');
            $rolePositionCandidates = array_values(array_filter([$permissionPosition, $ownerPosition, $legacyRolePosition], static function ($position): bool {
                return $position !== false;
            }));
            $rolePosition = $rolePositionCandidates === [] ? false : min($rolePositionCandidates);

            if ($rolePosition === false) {
                $errors[] = 'Admin action must require an admin permission or owner guard: ' . $route . ' -> ' . $actionFile;
            }

            $directRedirectPosition = strpos($content, 'sr_redirect(');
            if (
                $directRedirectPosition !== false
                && (
                    strpos($content, 'sr_member_require_login(') === false
                    || (
                        strpos($content, 'sr_admin_require_permission(') === false
                        && strpos($content, 'sr_admin_require_owner(') === false
                        && strpos($content, 'sr_admin_require_role(') === false
                    )
                    || strpos($content, 'sr_member_require_login(') > $directRedirectPosition
                    || (
                        strpos($content, 'sr_admin_require_permission(') !== false
                        && strpos($content, 'sr_admin_require_permission(') > $directRedirectPosition
                    )
                    || (
                        strpos($content, 'sr_admin_require_owner(') !== false
                        && strpos($content, 'sr_admin_require_owner(') > $directRedirectPosition
                    )
                    || (
                        strpos($content, 'sr_admin_require_role(') !== false
                        && strpos($content, 'sr_admin_require_role(') > $directRedirectPosition
                    )
                )
            ) {
                $errors[] = 'Admin action must check login and admin permission before redirecting: ' . $route . ' -> ' . $actionFile;
            }

            if ($method === 'POST') {
                $loginPosition = strpos($effectiveContent, 'sr_member_require_login(');
                $csrfPosition = strpos($effectiveContent, 'sr_require_csrf(');
                if (
                    $loginPosition !== false
                    && $rolePosition !== false
                    && $csrfPosition !== false
                    && ($loginPosition > $csrfPosition || $rolePosition > $csrfPosition)
                ) {
                    $errors[] = 'Admin POST action must check login and admin permission before CSRF: ' . $route . ' -> ' . $actionFile;
                }
            }
        }
    }
}

$adminRolesHelper = file_get_contents($root . '/modules/admin/helpers/roles.php');
if (!is_string($adminRolesHelper) || strpos($adminRolesHelper, 'function sr_admin_active_owner_count') === false) {
    $errors[] = 'Admin role helper must expose an active owner count guard.';
} elseif (
    strpos($adminRolesHelper, 'sr_admin_active_owner_count($pdo) <= 1') === false
    || strpos($adminRolesHelper, "sr_t('admin::action.roles.last_active_owner_revoke_disallowed')") === false
) {
    $errors[] = 'Admin role helper must prevent revoking the last active owner role.';
}

if (is_string($adminRolesHelper) && (
    strpos($adminRolesHelper, "sr_t('admin::action.roles.intent_invalid')") === false
    || strpos($adminRolesHelper, "sr_t('admin::action.roles.owner_permission_redundant')") === false
    || strpos($adminRolesHelper, "sr_t('admin::action.roles.inactive_account_grant_disallowed')") === false
    || strpos($adminRolesHelper, "\$addsOwnerRole = \$selectedIsOwner && !\$beforeIsOwner;") === false
    || strpos($adminRolesHelper, "\$addsPermissionKeys = array_values(array_diff(\$selectedPermissionKeys, \$beforePermissionKeys));") === false
)) {
    $errors[] = 'Admin role helper must reject invalid permission intents, redundant owner menu grants, and new grants to inactive accounts.';
}

$adminInputHelper = file_get_contents($root . '/modules/admin/helpers/input.php');
if (!is_string($adminInputHelper)) {
    $errors[] = 'Admin input helper cannot be read.';
} elseif (
    strpos($adminInputHelper, 'function sr_admin_post_positive_int') === false
    || strpos($adminInputHelper, 'function sr_admin_post_int_in_range') === false
    || strpos($adminInputHelper, "\$value = \$_POST[\$key] ?? '';") === false
    || strpos($adminInputHelper, 'is_array($value)') === false
    || strpos($adminInputHelper, 'strlen($value) > $maxLength') === false
    || strpos($adminInputHelper, "preg_match('/\\A[1-9][0-9]*\\z/', \$value)") === false
    || strpos($adminInputHelper, "preg_match('/\\A\\d+\\z/', \$value)") === false
    || strpos($adminInputHelper, '$integerValue < $min || $integerValue > $max') === false
    || strpos($adminInputHelper, 'return (int) $value;') === false
    || strpos($adminRolesHelper, "sr_admin_post_positive_int('account_id')") === false
) {
    $errors[] = 'Admin POST numeric inputs must be accepted only as strict integer strings.';
}

$adminMembersHelper = file_get_contents($root . '/modules/member/helpers/admin-members.php');
if (!is_string($adminMembersHelper)) {
    $errors[] = 'Admin members helper cannot be read.';
} else {
    if (
        strpos($adminMembersHelper, "in_array(\$intent, ['status', 'revoke_sessions'], true)") === false
        && strpos($adminMembersHelper, "in_array(\$intent, ['status', 'edit', 'revoke_sessions'], true)") === false
        && strpos($adminMembersHelper, "in_array(\$intent, ['status', 'edit', 'revoke_sessions', 'evaluate_groups'], true)") === false
        || strpos($adminMembersHelper, "sr_t('member::action.admin.intent_invalid')") === false
        || strpos($adminMembersHelper, "sr_admin_post_positive_int('account_id')") === false
    ) {
        $errors[] = 'Admin members helper must allowlist member management intents and strict account ids.';
    }

    if (
        strpos($adminMembersHelper, 'sr_admin_current_roles($pdo, $targetAccountId)') === false
        || strpos($adminMembersHelper, 'sr_admin_is_owner($pdo, (int) $account[\'id\'])') === false
        || strpos($adminMembersHelper, "sr_t('member::action.admin.owner_only')") === false
    ) {
        $errors[] = 'Admin members helper must prevent non-owner admins from changing owner accounts.';
    }

    if (
        strpos($adminMembersHelper, 'sr_admin_active_owner_count($pdo) <= 1') === false
        || strpos($adminMembersHelper, "sr_t('member::action.admin.last_owner_disable_disallowed')") === false
    ) {
        $errors[] = 'Admin members helper must prevent deactivating the last active owner.';
    }

    if (
        strpos($adminMembersHelper, 'function sr_admin_member_email_display') === false
        || strpos($adminMembersHelper, 'function sr_admin_member_display_name_preview') === false
        || strpos($adminMembersHelper, "return \$prefix . '***@' . \$domain;") === false
        || strpos($adminMembersHelper, "sr_log_line_value((string) (\$member['display_name'] ?? ''), 80)") === false
    ) {
        $errors[] = 'Admin member lists must reduce member email and display name exposure before display.';
    }
}

$adminMembersView = file_get_contents($root . '/modules/member/views/admin-members.php');
if (!is_string($adminMembersView)) {
    $errors[] = 'Admin members view cannot be read.';
} elseif (
    strpos($adminMembersView, 'sr_admin_member_email_display($member)') === false
    || strpos($adminMembersView, 'sr_admin_member_display_name_preview($member)') === false
    || strpos($adminMembersView, "\$memberListShowNicknameColumn = !empty(\$memberSettings['nickname_enabled'])") === false
    || strpos($adminMembersView, "sr_admin_sort_header_html(sr_t('member::ui.nickname'), 'nickname'") === false
) {
    $errors[] = 'Admin members view must render member identity fields through privacy display helpers.';
}

$adminRolesView = file_get_contents($root . '/modules/admin/views/roles.php');
if (!is_string($adminRolesView)) {
    $errors[] = 'Admin roles view cannot be read.';
} elseif (
    strpos($adminRolesView, 'sr_admin_member_email_display($adminAccount)') === false
    || strpos($adminRolesView, 'sr_admin_member_display_name_preview($adminAccount)') === false
) {
    $errors[] = 'Admin roles view must render member identity fields through privacy display helpers.';
}

$adminRolesHelper = file_get_contents($root . '/modules/admin/helpers/roles.php');
if (!is_string($adminRolesHelper)) {
    $errors[] = 'Admin roles helper cannot be read.';
} elseif (
    strpos($adminRolesHelper, 'function sr_admin_permission_reauth_errors') === false
    || strpos($adminRolesHelper, 'admin_permission_reauth') === false
    || strpos($adminRolesHelper, '$beforeIsOwner !== $selectedIsOwner || $addsPermissionKeys !== []') === false
    || strpos($adminRolesHelper, 'sr_admin_permission_reauth_errors($pdo, $account, $intent, $targetAccountId)') === false
    || !is_string($adminRolesView)
    || strpos($adminRolesView, 'name="owner_password"') === false
) {
    $errors[] = 'Admin role grants and owner role changes must require owner password reauthentication.';
}

$adminSettingsHelper = file_get_contents($root . '/modules/admin/helpers/settings.php');
if (!isset($adminModuleActionsHelper)) {
    $adminModuleActionsHelper = file_get_contents($root . '/modules/admin/helpers/module-actions.php');
}
if (!is_string($adminSettingsHelper)) {
    $errors[] = 'Admin settings helper cannot be read.';
} elseif (
    strpos($adminSettingsHelper, "\$intent = sr_post_string('intent', 40)") === false
    || strpos($adminSettingsHelper, "\$intent !== 'site'") === false
    || strpos($adminSettingsHelper, '사이트 설정 작업 값이 올바르지 않습니다.') === false
) {
    $errors[] = 'Admin settings helper must allowlist site setting intents.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, 'function sr_admin_sensitive_site_setting_keys') === false
    || strpos($adminSettingsHelper, "'admin.module_sources_enabled' => true") === false
    || !is_string($adminModuleActionsHelper ?? null)
    || strpos($adminModuleActionsHelper, 'enable_module_source_writes') === false
    || strpos($adminModuleActionsHelper, 'disable_module_source_writes') === false
    || strpos($adminModuleActionsHelper, 'sr_admin_module_source_reauth_errors($pdo, $account, $intent)') === false
    || strpos($adminModuleActionsHelper, '!$moduleSourcesEnabled') === false
    || strpos($adminModuleActionsHelper, '모듈 zip 업로드는 소유자 재인증 요청에서만 일시 허용됩니다.') === false
    || strpos($adminModuleActionsHelper, "sr_save_site_setting(\$pdo, 'admin.module_sources_enabled', '0', 'bool')") === false
    || strpos($adminModuleActionsHelper, "sr_save_site_setting(\$pdo, 'admin.module_sources_enabled', '1', 'bool')") === false
)) {
    $errors[] = 'Admin settings helper must keep sensitive site toggles narrow and protect module source writes with reauthentication.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, 'function sr_admin_setting_value_is_secret') === false
    || strpos($adminSettingsHelper, 'function sr_admin_setting_display_value') === false
    || strpos($adminSettingsHelper, 'function sr_admin_setting_value_type_errors') === false
    || strpos($adminSettingsHelper, 'function sr_admin_normalize_setting_value') === false
    || strpos($adminSettingsHelper, 'password|token|secret|credential|bearer') === false
    || strpos($adminSettingsHelper, "'[masked]'") === false
)) {
    $errors[] = 'Admin settings helper must expose shared secret-like setting masking helpers.';
}
if (is_string($adminSettingsHelper) && (
    strpos($adminSettingsHelper, "preg_match('/\\A-?\\d+\\z/', \$settingValue)") === false
    || strpos($adminSettingsHelper, 'bool 설정값은 1/0, true/false, yes/no, on/off 중 하나여야 합니다.') === false
    || strpos($adminSettingsHelper, "return in_array(strtolower(\$settingValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';") === false
)) {
    $errors[] = 'Admin settings helper must expose typed setting validation and normalization helpers.';
}

if (!isset($adminModuleActionsHelper)) {
    $adminModuleActionsHelper = file_get_contents($root . '/modules/admin/helpers/module-actions.php');
}
if (is_string($adminModuleActionsHelper) && (
    strpos($adminModuleActionsHelper, 'function sr_admin_module_source_reauth_errors') === false
    || strpos($adminModuleActionsHelper, 'module_source_reauth') === false
    || strpos($adminModuleActionsHelper, 'module.source.reauth_failed') === false
)) {
    $errors[] = 'Admin module source helper must require reauthentication for source writes.';
}

$adminSettingsView = file_get_contents($root . '/modules/admin/views/settings.php');
if (!is_string($adminSettingsView)) {
    $errors[] = 'Admin settings view cannot be read.';
} elseif (
    strpos($adminSettingsView, '<input type="hidden" name="intent" value="site">') === false
    || strpos($adminSettingsView, 'name="public_layout_key"') === false
    || strpos($adminSettingsView, 'name="admin_skin_key"') === false
) {
    $errors[] = 'Admin settings view must expose only the supported site settings form.';
}

$adminModulesView = file_get_contents($root . '/modules/admin/views/modules.php');
if (!is_string($adminModulesView)) {
    $errors[] = 'Admin modules view cannot be read.';
} elseif (
    substr_count($adminModulesView, 'name="owner_password"') < 3
    || strpos($adminModulesView, 'name="intent" value="enable_module_source_writes"') === false
    || strpos($adminModulesView, 'name="intent" value="disable_module_source_writes"') === false
    || strpos($adminModulesView, 'name="module_zip"') === false
    || strpos($adminModulesView, 'name="upload_module_key" maxlength="40" pattern="[a-z][a-z0-9_]{1,39}"') === false
    || strpos($adminModulesView, 'name="confirm_file_replace"') === false
    || strpos($adminModulesView, '$moduleSourcesEnabled') === false
) {
    $errors[] = 'Admin modules view must collect owner reauthentication for module source writes.';
}

$adminAuditLogsHelper = file_get_contents($root . '/modules/admin/helpers/audit-logs.php');
if (!is_string($adminAuditLogsHelper)) {
    $errors[] = 'Admin audit logs helper cannot be read.';
} elseif (
    strpos($adminAuditLogsHelper, 'function sr_admin_audit_metadata_redact') === false
    || strpos($adminAuditLogsHelper, 'function sr_admin_audit_log_identifier_filter') === false
    || strpos($adminAuditLogsHelper, 'function sr_admin_audit_log_result_filter') === false
    || strpos($adminAuditLogsHelper, 'function sr_admin_audit_log_date_filter') === false
    || strpos($adminAuditLogsHelper, "preg_match('/\\A[a-z][a-z0-9_.-]*\\z/', \$value)") === false
    || strpos($adminAuditLogsHelper, "in_array(\$value, ['success', 'failure'], true)") === false
    || strpos($adminAuditLogsHelper, "preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/', \$value)") === false
    || strpos($adminAuditLogsHelper, "DateTimeImmutable::createFromFormat('!Y-m-d', \$value)") === false
    || strpos($adminAuditLogsHelper, 'DateTimeImmutable::getLastErrors()') === false
    || strpos($adminAuditLogsHelper, 'function sr_admin_audit_log_display_metadata') === false
    || strpos($adminAuditLogsHelper, 'function sr_admin_audit_log_display_message') === false
    || strpos($adminAuditLogsHelper, 'sr_admin_setting_value_is_secret($key)') === false
    || strpos($adminAuditLogsHelper, 'return sr_log_sensitive_text_sanitize($value);') === false
    || strpos($adminAuditLogsHelper, "sr_log_sensitive_text_sanitize(sr_log_line_value((string) (\$log['message'] ?? ''), 1000))") === false
    || strpos($adminAuditLogsHelper, 'json_decode($metadataJson, true)') === false
    || strpos($adminAuditLogsHelper, "'[invalid metadata]'") === false
) {
    $errors[] = 'Admin audit logs helper must validate filters and redact secret-like metadata before display.';
}

$adminAuditLogsView = file_get_contents($root . '/modules/admin/views/audit-logs.php');
if (!is_string($adminAuditLogsView)) {
    $errors[] = 'Admin audit logs view cannot be read.';
} elseif (
    strpos($adminAuditLogsView, 'sr_admin_audit_log_display_message($log)') === false
    || strpos($adminAuditLogsView, 'sr_admin_audit_log_display_metadata($log)') === false
) {
    $errors[] = 'Admin audit logs view must render messages and metadata through redaction helpers.';
}

$coreOpsHelper = file_get_contents($root . '/core/helpers/ops.php');
if (!is_string($coreOpsHelper)) {
    $errors[] = 'Core ops helper cannot be read.';
} elseif (
    strpos($coreOpsHelper, 'function sr_audit_metadata_sanitize') === false
    || strpos($coreOpsHelper, 'function sr_audit_metadata_key_is_secret') === false
    || strpos($coreOpsHelper, 'function sr_log_sensitive_text_sanitize') === false
    || strpos($coreOpsHelper, 'sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 1000))') === false
    || strpos($coreOpsHelper, "sr_log_sensitive_text_sanitize(sr_log_line_value((string) (\$data['message'] ?? ''), 1000))") === false
    || strpos($coreOpsHelper, 'return sr_log_sensitive_text_sanitize($value);') === false
    || strpos($coreOpsHelper, 'sr_audit_metadata_sanitize($metadata)') === false
    || strpos($coreOpsHelper, 'sr_audit_metadata_sanitize($payload)') === false
    || strpos($coreOpsHelper, 'password|token|secret|credential|bearer|authorization') === false
    || strpos($coreOpsHelper, "'[redacted-email]'") === false
    || strpos($coreOpsHelper, "'[redacted-phone]'") === false
    || strpos($coreOpsHelper, "'[redacted-id]'") === false
    || strpos($coreOpsHelper, 'Bearer [masked]') === false
    || strpos($coreOpsHelper, "'[masked]'") === false
) {
    $errors[] = 'Core ops helper must sanitize secret-like values before storing audit logs, markers, and exception logs.';
}
if (is_string($coreOpsHelper) && (
    strpos($coreOpsHelper, 'function sr_start_request_contract') === false
    || strpos($coreOpsHelper, 'function sr_request_contract_mark') === false
    || strpos($coreOpsHelper, 'function sr_request_contract_guard_blocked') === false
    || strpos($coreOpsHelper, 'function sr_enforce_request_contract') === false
    || strpos($coreOpsHelper, 'function sr_fail_request_contract') === false
    || strpos($coreOpsHelper, 'register_shutdown_function') === false
    || strpos($coreOpsHelper, "'exit_reason' => null") === false
    || strpos($coreOpsHelper, "'guard_blocked'") === false
)) {
    $errors[] = 'Core ops helper must expose request contract state, guard-blocked exits, enforcement, and shutdown logging.';
}

$coreOutputHelper = file_get_contents($root . '/core/helpers/output.php');
if (!is_string($coreOutputHelper)) {
    $errors[] = 'Core output helper cannot be read.';
} elseif (
    strpos($coreOutputHelper, "sr_enforce_request_contract('before_redirect')") === false
    || strpos($coreOutputHelper, 'function sr_finish_response') === false
    || strpos($coreOutputHelper, 'function sr_json_response(mixed $payload') === false
    || strpos($coreOutputHelper, 'sr_response_header_is_allowed($header)') === false
    || strpos($coreOutputHelper, 'JSON_INVALID_UTF8_SUBSTITUTE') === false
    || strpos($coreOutputHelper, "sr_enforce_request_contract('before_response_end')") === false
    || strpos($coreOutputHelper, "sr_request_contract_mark('csrf_checked')") === false
    || strpos($coreOutputHelper, "sr_request_contract_guard_blocked('csrf')") === false
) {
    $errors[] = 'Core output helper must enforce the request contract at redirect/response exits, centralize JSON responses, and mark CSRF checks.';
}

$memberAccountsHelper = file_get_contents($root . '/modules/member/helpers/accounts.php');
if (!is_string($memberAccountsHelper)) {
    $errors[] = 'Member accounts helper cannot be read.';
} elseif (
    strpos($memberAccountsHelper, "sr_request_contract_mark('auth_checked')") === false
    || strpos($memberAccountsHelper, "sr_request_contract_guard_blocked('auth')") === false
) {
    $errors[] = 'Member login guard must mark request contract auth checks and guard-blocked redirects.';
}

if (is_string($adminRolesHelper) && (
    strpos($adminRolesHelper, "sr_request_contract_mark('role_checked')") === false
    || (
        strpos($adminRolesHelper, "sr_request_contract_guard_blocked('role')") === false
        && strpos($adminRolesHelper, "sr_request_contract_guard_blocked('permission')") === false
    )
)) {
    $errors[] = 'Admin permission guard must mark request contract permission checks and guard-blocked errors.';
}

$adminPrivacyRequestsHelper = file_get_contents($root . '/modules/privacy/helpers/requests.php');
if (!is_string($adminPrivacyRequestsHelper)) {
    $errors[] = 'Admin privacy requests helper cannot be read.';
} elseif (
    strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_list_preview') === false
    || strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_requester_display') === false
    || strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_terminal_statuses') === false
    || strpos($adminPrivacyRequestsHelper, 'function sr_admin_handle_privacy_request_create_post') === false
    || strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_export_reauth_errors') === false
    || strpos($adminPrivacyRequestsHelper, 'privacy_request_export_reauth') === false
    || strpos($adminPrivacyRequestsHelper, "sr_admin_post_positive_int('account_id')") === false
    || strpos($adminPrivacyRequestsHelper, "sr_post_string_without_truncation('request_message', 2000)") === false
    || strpos($adminPrivacyRequestsHelper, "'source' => 'admin_manual'") === false
    || strpos($adminPrivacyRequestsHelper, "sr_post_string_without_truncation('admin_note', 2000)") === false
    || strpos($adminPrivacyRequestsHelper, '$adminNote === null') === false
    || strpos($adminPrivacyRequestsHelper, 'catch (Throwable $exception)') === false
    || strpos($adminPrivacyRequestsHelper, "sr_log_exception(\$exception, 'privacy_request_export_account_' . (int) \$privacyRequest['id'])") === false
    || strpos($adminPrivacyRequestsHelper, "'account_data_unavailable'") === false
    || strpos($adminPrivacyRequestsHelper, '종결된 개인정보 처리 요청 상태는 다시 변경할 수 없습니다.') === false
    || strpos($adminPrivacyRequestsHelper, '$preserveTerminalHandler = !$statusChanged && $isTerminalStatus;') === false
    || strpos($adminPrivacyRequestsHelper, "sr_admin_post_positive_int('request_id')") === false
    || strpos($adminPrivacyRequestsHelper, "return \$prefix . '***@' . \$domain;") === false
    || strpos($adminPrivacyRequestsHelper, "return mb_substr(\$preview, 0, \$maxLength) . '...';") === false
) {
    $errors[] = 'Admin privacy request helpers must reduce list exposure, validate request ids, protect terminal status changes, isolate member export failures, and reauthenticate exports.';
}

$adminPrivacyRequestsView = file_get_contents($root . '/modules/privacy/views/admin-privacy-requests.php');
if (!is_string($adminPrivacyRequestsView)) {
    $errors[] = 'Admin privacy requests view cannot be read.';
} elseif (
    strpos($adminPrivacyRequestsView, 'sr_admin_privacy_request_requester_display($request)') === false
    || strpos($adminPrivacyRequestsView, "sr_admin_privacy_request_list_preview(\$request['request_message'] ?? null)") === false
    || strpos($adminPrivacyRequestsView, 'name="intent" value="create_request"') === false
    || strpos($adminPrivacyRequestsView, 'name="requester_snapshot"') === false
    || strpos($adminPrivacyRequestsView, 'name="admin_password"') === false
    || strpos($adminPrivacyRequestsView, "placeholder=\"<?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?>\"") === false
    || strpos($adminPrivacyRequestsView, "><?php echo sr_e((string) (\$request['admin_note'] ?? '')); ?></textarea>") !== false
    || strpos($adminPrivacyRequestsView, "><?php echo sr_e(\$request['admin_note'] ?? ''); ?></textarea>") !== false
) {
    $errors[] = 'Admin privacy requests view must reduce requester/message exposure and avoid prefilled admin notes.';
}

$adminPrivacyRequestExportAction = file_get_contents($root . '/modules/privacy/actions/admin-privacy-request-export.php');
if (!is_string($adminPrivacyRequestExportAction)) {
    $errors[] = 'Admin privacy request export action cannot be read.';
} elseif (strpos($adminPrivacyRequestExportAction, "sr_admin_post_positive_int('id')") === false) {
    $errors[] = 'Admin privacy request export action must validate request ids strictly.';
}

$coreSettingsHelper = file_get_contents($root . '/core/helpers/settings.php');
if (!is_string($coreSettingsHelper)) {
    $errors[] = 'Core settings helper cannot be read.';
} elseif (strpos($coreSettingsHelper, "/\\A[a-z][a-z0-9_]{1,39}\\z/") === false) {
    $errors[] = 'Core module key validation must require a letter prefix and bounded length.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function sr_module_contract_is_loadable') === false
    || strpos($coreSettingsHelper, 'sr_module_metadata_errors($metadata) === []') === false
    || strpos($coreSettingsHelper, 'sr_module_contract_is_loadable($moduleKey)') === false
)) {
    $errors[] = 'Core module runtime loading must require the current module contract metadata.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function sr_module_metadata_errors') === false
    || strpos($coreSettingsHelper, 'sr_module_contract_errors($metadata)') === false
    || strpos($coreSettingsHelper, 'module.php의 version은 YYYY.MM.NNN 형식이어야 합니다.') === false
)) {
    $errors[] = 'Core module metadata validation must include module metadata and contract requirements.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function sr_module_known_contract_files') === false
    || strpos($coreSettingsHelper, 'function sr_module_contract_file_errors') === false
    || strpos($coreSettingsHelper, 'sr_module_declared_contract_files($metadata, \'provides\')') === false
    || strpos($coreSettingsHelper, 'contracts.provides에 선언한') === false
)) {
    $errors[] = 'Core module contract file validation must require declared and actual contract files to match.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function sr_module_metadata') === false
    || strpos($coreSettingsHelper, 'catch (Throwable $exception)') === false
    || strpos($coreSettingsHelper, "sr_log_exception(\$exception, 'module_metadata_load_failed_' . \$moduleKey)") === false
    || strpos($coreSettingsHelper, '$cache[$moduleKey] = [];') === false
)) {
    $errors[] = 'Core module metadata loading must fail closed when module.php throws.';
}
if (is_string($coreSettingsHelper) && (
    strpos($coreSettingsHelper, 'function sr_load_module_contract_file') === false
    || strpos($coreSettingsHelper, 'include $realFile') === false
    || strpos($coreSettingsHelper, "sr_log_exception(\$exception, 'module_contract_load_failed_' . \$moduleKey . '_' . \$contractLabel)") === false
    || strpos($coreSettingsHelper, 'return null;') === false
)) {
    $errors[] = 'Core module contract file loading must fail closed when contract files throw.';
}

$frontController = file_get_contents($root . '/index.php');
if (!is_string($frontController)) {
    $errors[] = 'Front controller cannot be read.';
} elseif (
    strpos($frontController, "sr_enabled_module_contract_files(\$pdo, 'paths.php')") === false
    || strpos($frontController, 'sr_load_module_contract_file($moduleKey, $pathsFile)') === false
) {
    $errors[] = 'Front controller must load module paths.php through the contract file loader.';
}
if (is_string($frontController) && (
    strpos($frontController, 'sr_start_request_contract($method, $path,') === false
    || strpos($frontController, "sr_enforce_request_contract('after_action')") === false
)) {
    $errors[] = 'Front controller must wrap matched action includes with request contract start and enforcement.';
}

$adminNavigationHelper = file_get_contents($root . '/modules/admin/helpers/navigation.php');
if (!is_string($adminNavigationHelper)) {
    $errors[] = 'Admin navigation helper cannot be read.';
} elseif (
    strpos($adminNavigationHelper, "sr_enabled_module_contract_files(\$pdo, 'admin-menu.php', ['admin'])") === false
    || strpos($adminNavigationHelper, "sr_enabled_module_contract_files(\$pdo, 'paths.php', ['admin'])") === false
    || strpos($adminNavigationHelper, 'sr_load_module_contract_file($moduleKey, $file)') === false
    || strpos($adminNavigationHelper, 'sr_load_module_contract_file($moduleKey, $pathsFile)') === false
    || strpos($adminNavigationHelper, 'function sr_admin_navigation_groups(PDO $pdo): array') === false
    || strpos($adminNavigationHelper, 'function sr_admin_builtin_menu_groups(PDO $pdo): array') === false
    || !is_file($root . '/modules/member/admin-menu.php')
    || !is_file($root . '/modules/privacy/admin-menu.php')
) {
    $errors[] = 'Admin navigation must group builtin admin links and load module paths.php through the contract file loader.';
}

$adminSettingsHelper = file_get_contents($root . '/modules/admin/helpers/settings.php');
$adminLayoutHeader = file_get_contents($root . '/modules/admin/views/layout-header.php');
if (!is_string($adminSettingsHelper) || !is_string($adminLayoutHeader)) {
    $errors[] = 'Admin skin files cannot be read.';
} elseif (
    strpos($adminSettingsHelper, 'function sr_admin_skin_options(): array') === false
    || strpos($adminSettingsHelper, 'function sr_admin_skin_view(string $skinKey, string $viewKey): string') === false
    || strpos($adminLayoutHeader, "sr_admin_skin_view(sr_admin_skin_key(\$adminSettings), 'layout-header')") === false
    || !is_file($root . '/modules/admin/skins/basic/layout-header.php')
    || !is_file($root . '/modules/admin/skins/basic/layout-footer.php')
) {
    $errors[] = 'Admin layout must render through explicit admin skin views with a basic fallback.';
}

$bannerHelper = file_get_contents($root . '/modules/banner/helpers.php');
if (!is_string($bannerHelper)) {
    $errors[] = 'Banner helper cannot be read.';
} elseif (
    strpos($bannerHelper, 'function sr_banner_skin_options(): array') === false
    || strpos($bannerHelper, 'function sr_banner_render_basic_item(array $banner): string') === false
    || strpos($bannerHelper, 'sr_banner_render_item($banner, $skinKey)') === false
    || !is_file($root . '/modules/banner/skins/basic/item.php')
) {
    $errors[] = 'Banner rendering must use explicit banner skin views with a basic fallback.';
}

$moduleLifecycleHelper = file_get_contents($root . '/core/helpers/module-lifecycle.php');
$moduleSourceHelper = file_get_contents($root . '/core/helpers/module-source.php');
$schemaUpdatesHelper = file_get_contents($root . '/core/helpers/schema-updates.php');
$adminModuleSourcesHelper = file_get_contents($root . '/modules/admin/helpers/module-sources.php');
if (!is_string($adminModuleSourcesHelper)) {
    $errors[] = 'Admin module sources helper cannot be read.';
    $adminModuleSourcesHelper = '';
}
$moduleSourceSafetyContent = (is_string($moduleLifecycleHelper) ? $moduleLifecycleHelper : '') . "\n" . (is_string($moduleSourceHelper) ? $moduleSourceHelper : '') . "\n" . $adminModuleSourcesHelper;
if (
    strpos($moduleSourceSafetyContent, 'function sr_module_zip_entry_is_symlink') === false
    || strpos($moduleSourceSafetyContent, 'sr_module_zip_entry_is_symlink($zip, $i)') === false
    || strpos($moduleSourceSafetyContent, 'zip 안에 심볼릭 링크가 있습니다.') === false
) {
    $errors[] = 'Admin module source zip checks must reject symlink entries before extraction.';
}
if (
    strpos($moduleSourceSafetyContent, "preg_match('/[\\x00-\\x1F\\x7F]/', \$name)") === false
    || strpos($moduleSourceSafetyContent, "str_contains(\$name, '\\\\')") === false
    || strpos($moduleSourceSafetyContent, "str_contains(\$name, ':')") === false
    || strpos($moduleSourceSafetyContent, "str_contains(\$name, '//')") === false
    || strpos($moduleSourceSafetyContent, "\$segment === '.'") === false
) {
    $errors[] = 'Admin module source zip paths must reject control characters, colon separators, and ambiguous path segments.';
}
if (
    strpos($moduleSourceSafetyContent, "throw new RuntimeException('zip 항목 속성을 확인할 수 없습니다.');") === false
) {
    $errors[] = 'Admin module source zip symlink checks must fail closed when entry attributes cannot be read.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_validate_extracted_module_tree') === false
    || strpos($moduleSourceSafetyContent, 'sr_validate_extracted_module_tree($extractDir)') === false
    || strpos($moduleSourceSafetyContent, '압축 해제된 모듈에 심볼릭 링크가 있습니다.') === false
    || strpos($moduleSourceSafetyContent, '압축 해제된 모듈 경로가 작업 디렉터리 밖을 가리킵니다.') === false
    || strpos($moduleSourceSafetyContent, 'sr_path_is_inside($item->getPathname(), $extractDir)') === false
) {
    $errors[] = 'Admin module source extraction must verify the extracted file tree stays inside the work directory.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_validate_module_source') === false
    || strpos($moduleSourceSafetyContent, 'sr_module_metadata_errors($metadata)') === false
    || strpos($moduleSourceSafetyContent, 'sr_module_contract_file_errors($sourceDir, $metadata)') === false
) {
    $errors[] = 'Admin module source helper must expose shared module metadata and contract validation.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_module_source_route_conflict_errors') === false
    || strpos($moduleSourceSafetyContent, "sr_module_record_status(\$pdo, \$moduleKey) !== 'enabled'") === false
    || strpos($moduleSourceSafetyContent, "sr_enabled_module_contract_files(\$pdo, 'paths.php', [\$moduleKey])") === false
    || strpos($moduleSourceSafetyContent, 'sr_module_routes_conflict((string) $candidateRoute, $route)') === false
) {
    $errors[] = 'Admin module source upload must be able to reject route conflicts before replacing enabled module files.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_module_source_update_errors') === false
    || strpos($moduleSourceSafetyContent, 'sr_module_source_update_errors($moduleKey, $sourceDir, $metadata)') === false
    || strpos($moduleSourceSafetyContent, "업데이트 SQL 파일명은 updates/YYYY.MM.NNN.sql 형식이어야 합니다") === false
    || strpos($moduleSourceSafetyContent, "업데이트 SQL 버전은 module.php version보다 높을 수 없습니다") === false
) {
    $errors[] = 'Admin module source upload must reject ignored or future module update SQL files.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_module_source_file_errors') === false
    || strpos($moduleSourceSafetyContent, "function sr_module_source_is_server_config_name") === false
    || strpos($moduleSourceSafetyContent, "function sr_module_source_is_repository_meta_name") === false
    || strpos($moduleSourceSafetyContent, "\$basename === '.htaccess'") === false
    || strpos($moduleSourceSafetyContent, "str_starts_with(\$basename, '.htaccess.')") === false
    || strpos($moduleSourceSafetyContent, "str_starts_with(\$basename, '.env.')") === false
    || strpos($moduleSourceSafetyContent, "'phtml' => true") === false
    || strpos($moduleSourceSafetyContent, "'phar' => true") === false
    || strpos($moduleSourceSafetyContent, "'php7' => true") === false
    || strpos($moduleSourceSafetyContent, "'pht' => true") === false
    || strpos($moduleSourceSafetyContent, "'sqlite' => true") === false
    || strpos($moduleSourceSafetyContent, "'key' => true") === false
    || strpos($moduleSourceSafetyContent, 'function sr_module_source_is_public_asset_executable') === false
    || strpos($moduleSourceSafetyContent, "str_starts_with(\$relative, 'assets/')") === false
    || strpos($moduleSourceSafetyContent, "assets 디렉터리에는 실행 파일 또는 SQL 파일을 포함할 수 없습니다") === false
    || strpos($moduleSourceSafetyContent, 'sr_module_source_file_errors($extractDir)') === false
    || strpos($moduleSourceSafetyContent, 'sr_module_source_file_errors($sourceDir)') === false
    || strpos($moduleSourceSafetyContent, 'zip 안에 여러 모듈 구조가 있습니다.') === false
    || strpos($moduleSourceSafetyContent, 'zip 안에는 요청한 모듈 하나만 포함해야 합니다.') === false
) {
    $errors[] = 'Admin module source validation must reject server config files and unsafe executable extensions.';
}
if (
    strpos($moduleSourceSafetyContent, 'function sr_install_module_source_files') === false
    || strpos($moduleSourceSafetyContent, '!rename($backupDir, $targetDir)') === false
    || strpos($moduleSourceSafetyContent, "throw new RuntimeException('기존 모듈 백업을 복구할 수 없습니다.', 0, \$exception)") === false
) {
    $errors[] = 'Admin module source replacement must fail closed when backup restore fails.';
}

$adminModuleActionsHelper = file_get_contents($root . '/modules/admin/helpers/module-actions.php');
if (!is_string($adminModuleActionsHelper)) {
    $errors[] = 'Admin module actions helper cannot be read.';
} elseif (
    strpos($adminModuleActionsHelper, "'result' => 'failure'") === false
    || strpos($adminModuleActionsHelper, 'Module source zip upload failed.') === false
    || substr_count($adminModuleActionsHelper, 'sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))') < 2
) {
    $errors[] = 'Admin module source failures must write and display sanitized failure messages.';
}
if (is_string($adminModuleActionsHelper) && strpos($adminModuleActionsHelper, 'sr_module_source_route_conflict_errors($pdo, $moduleKey, (string) $source[\'source_dir\'])') === false) {
    $errors[] = 'Admin module source upload must reject enabled module route conflicts before installing source files.';
}
if (is_string($adminModuleActionsHelper) && (
    strpos($adminModuleActionsHelper, '$closeModuleSourcesAfterRequest = false;') === false
    || strpos($adminModuleActionsHelper, "in_array(\$intent, ['upload_module_zip', 'sync_module_version'], true) && \$moduleSourcesEnabled") === false
    || strpos($adminModuleActionsHelper, 'if ($closeModuleSourcesAfterRequest)') === false
    || strpos($adminModuleActionsHelper, "sr_save_site_setting(\$pdo, 'admin.module_sources_enabled', '0', 'bool');") === false
)) {
    $errors[] = 'Admin module source write requests must close temporary source-write allowance after success or validation failure.';
}
if (is_string($adminModuleActionsHelper) && substr_count($adminModuleActionsHelper . "\n" . $moduleSourceSafetyContent, 'sr_module_metadata_errors($metadata)') < 3) {
    $errors[] = 'Admin module install, enable, and version sync actions must validate module metadata contracts server-side.';
}
if (is_string($adminModuleActionsHelper) && substr_count($adminModuleActionsHelper . "\n" . $moduleSourceSafetyContent, 'sr_module_contract_file_errors(') < 5) {
    $errors[] = 'Admin module install, enable, sync, and listing flows must validate declared contract files server-side.';
}

$adminUpdatesHelper = file_get_contents($root . '/modules/admin/helpers/updates.php');
if (!is_string($adminUpdatesHelper)) {
    $errors[] = 'Admin updates helper cannot be read.';
} elseif (
    substr_count($adminUpdatesHelper, 'sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))') < 2
    || strpos($adminUpdatesHelper, "'schema.update.failed'") === false
    || strpos($adminUpdatesHelper, '\'message\' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))') === false
    || strpos($adminUpdatesHelper . "\n" . (is_string($moduleLifecycleHelper) ? $moduleLifecycleHelper : '') . "\n" . (is_string($schemaUpdatesHelper) ? $schemaUpdatesHelper : ''), "sr_log_sensitive_text_sanitize(sr_log_line_value((string) (\$decoded['message'] ?? ''), 500))") === false
) {
    $errors[] = 'Admin schema update failures must write sanitized audit and marker messages.';
}

$adminDashboardHelper = file_get_contents($root . '/modules/admin/helpers/dashboard.php');
if (!is_string($adminDashboardHelper)) {
    $errors[] = 'Admin dashboard helper cannot be read.';
} elseif (strpos($adminDashboardHelper, "sr_log_sensitive_text_sanitize(sr_log_line_value((string) (\$decoded['message'] ?? ''), 500))") === false) {
    $errors[] = 'Admin dashboard recovery markers must mask secret-like messages before display.';
}

$communityReportsHelper = file_get_contents($root . '/modules/community/helpers/reports.php');
$communityMessageDeleteAction = file_get_contents($root . '/modules/community/actions/message-delete.php');
$communityPostsHelper = file_get_contents($root . '/modules/community/helpers/posts.php');
$communityAdminPostsView = file_get_contents($root . '/modules/community/views/admin-posts.php');
if (!is_string($communityReportsHelper) || !is_string($communityMessageDeleteAction)) {
    $errors[] = 'Community message report/delete files cannot be read.';
} elseif (
    strpos($communityReportsHelper, 'sr_community_message_participants_for_account($pdo, $targetId, $actorAccountId)') === false
    || strpos($communityMessageDeleteAction, 'sr_community_message_participants_for_account($pdo, $messageId, (int) $account[\'id\'])') === false
) {
    $errors[] = 'Community message report and delete flows must avoid loading message bodies.';
}
if (!is_string($communityReportsHelper) || !is_string($communityPostsHelper) || !is_string($communityAdminPostsView)) {
    $errors[] = 'Community account label files cannot be read.';
} elseif (
    strpos($communityReportsHelper, '회원 #') !== false
    || strpos($communityPostsHelper, '회원 #') !== false
    || strpos($communityAdminPostsView, "author_display_name'] ?? '') . ' #'") !== false
) {
    $errors[] = 'Community account labels must avoid exposing numeric member ids.';
}

$communityNotificationsHelper = file_get_contents($root . '/modules/community/helpers/notifications.php');
$communityMessagesHelper = file_get_contents($root . '/modules/community/helpers/messages.php');
$memberAccountsHelper = file_get_contents($root . '/modules/member/helpers/accounts.php');
$communityMessageWriteAction = file_get_contents($root . '/modules/community/actions/message-write.php');
$communityMessageViewAction = file_get_contents($root . '/modules/community/actions/message-view.php');
$communityMessageWriteView = file_get_contents($root . '/modules/community/views/message-write.php');
$communityMessageViewView = file_get_contents($root . '/modules/community/views/message-view.php');
$communityCommentAction = file_get_contents($root . '/modules/community/actions/comment.php');
$communityReportAction = file_get_contents($root . '/modules/community/actions/report.php');
if (
    !is_string($communityNotificationsHelper)
    || !is_string($communityMessagesHelper)
    || !is_string($memberAccountsHelper)
    || !is_string($communityMessageWriteAction)
    || !is_string($communityMessageViewAction)
    || !is_string($communityMessageWriteView)
    || !is_string($communityMessageViewView)
    || !is_string($communityCommentAction)
    || !is_string($communityReportAction)
) {
    $errors[] = 'Community notification integration files cannot be read.';
} elseif (
    strpos($communityNotificationsHelper, "sr_module_contract_function(\$pdo, 'notification', 'notification-events.php', 'create_function')") === false
    || strpos($communityNotificationsHelper, 'catch (Throwable $exception)') === false
    || strpos($communityNotificationsHelper, 'function sr_community_create_admin_report_notifications') === false
    || strpos($communityNotificationsHelper, "p.menu_path = '/admin/community/reports'") === false
    || strpos($communityNotificationsHelper, "p.action_key = 'view'") === false
    || (
        strpos($memberAccountsHelper, 'function sr_member_public_account_summaries_by_hash') === false
        && strpos($memberAccountsHelper, 'function sr_member_public_account_summary_by_hash') === false
    )
    || strpos($memberAccountsHelper, 'static $cachedMaps = [];') === false
    || strpos($communityMessagesHelper, 'recipient_account_hash') === false
    || strpos($communityMessagesHelper, "return \$label;") === false
    || strpos($communityMessageWriteAction, 'sr_community_create_account_notification(') === false
    || (
        strpos($communityMessageWriteAction, 'sr_member_public_account_summary_by_hash($pdo, $config,') === false
        && strpos($communityMessageWriteAction, 'sr_community_public_account_summary_by_hash($pdo, $config,') === false
    )
    || strpos($communityMessageWriteAction, "sr_get_string('to',") !== false
    || strpos($communityMessageWriteAction, "'recipient_identifier' => ''") === false
    || strpos($communityMessageViewAction, 'sr_member_public_account_hash($config, $replyAccountId)') === false
    || strpos($communityMessageWriteView, 'name="recipient_account_id"') !== false
    || strpos($communityMessageWriteView, 'name="recipient_account_hash"') === false
    || strpos($communityMessageViewView, "'/community/message/write?to_account=' . (string) \$replyAccountId") !== false
    || strpos($communityMessageViewView, 'rawurlencode($replyAccountHash)') === false
    || strpos($communityCommentAction, 'sr_community_create_account_event_notification(') === false
    || strpos($communityCommentAction, "'comment.created'") === false
    || strpos($communityCommentAction, "(int) \$post['author_account_id'] !== (int) \$account['id']") === false
    || strpos($communityReportAction, 'sr_community_create_admin_report_notifications(') === false
) {
    $errors[] = 'Community message, comment, and report notifications must remain optional, hash message recipients, and avoid self comment notifications.';
}

$communityWriteAction = file_get_contents($root . '/modules/community/actions/write.php');
$communityAdminPostsAction = file_get_contents($root . '/modules/community/actions/admin-posts.php');
$communityDeleteAction = file_get_contents($root . '/modules/community/actions/delete.php');
$communityMemberGroupsHelper = file_get_contents($root . '/modules/community/helpers/member-groups.php');
if (!is_string($communityWriteAction) || !is_string($communityAdminPostsAction) || !is_string($communityDeleteAction) || !is_string($communityMemberGroupsHelper)) {
    $errors[] = 'Community post group evaluation action files cannot be read.';
} else {
    if (
        strpos($communityMemberGroupsHelper, 'function sr_community_member_group_evaluation_metadata') === false
        || strpos($communityMemberGroupsHelper, "'group_rules_evaluated' => (int) (\$summary['evaluated'] ?? 0)") === false
        || strpos($communityMemberGroupsHelper, "'group_memberships_granted' => (int) (\$summary['granted'] ?? 0)") === false
        || strpos($communityMemberGroupsHelper, "'group_memberships_revoked' => (int) (\$summary['revoked'] ?? 0)") === false
    ) {
        $errors[] = 'Community member group evaluation metadata helper must expose evaluated, granted, and revoked counts.';
    }

    foreach ([
        'post create' => $communityWriteAction,
        'post status update' => $communityAdminPostsAction,
        'post delete' => $communityDeleteAction,
    ] as $label => $source) {
        if (
            strpos($source, 'sr_member_group_evaluate_account($pdo,') === false
            || strpos($source, "'source_module_key' => 'community'") === false
            || strpos($source, 'sr_community_member_group_evaluation_metadata($groupEvaluationSummary)') === false
        ) {
            $errors[] = 'Community ' . $label . ' flow must evaluate community member group rules and audit the summary.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "admin action security checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin action security checks completed.\n";
