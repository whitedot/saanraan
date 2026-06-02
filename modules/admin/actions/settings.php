<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$values = sr_admin_site_setting_values($site ?? null, $pdo);
$adminSettings = sr_admin_settings($pdo);
$adminSkinOptions = sr_admin_skin_options();
$adminSkinKey = sr_admin_skin_key($adminSettings);
$adminColorScheme = sr_admin_color_scheme($adminSettings);
$listPaginationPerPage = sr_admin_list_pagination_per_page($adminSettings);
$adminEditorKey = sr_admin_editor_key($pdo, $adminSettings);
$adminEditorOptions = sr_editor_options($pdo);
$timezoneOptions = timezone_identifiers_list();
$localeOptions = sr_available_locale_options($site ?? null);
$values = array_merge($values, [
    'admin_skin_key' => $adminSkinKey,
    'admin_color_scheme' => $adminColorScheme,
    'list_pagination_per_page' => $listPaginationPerPage,
    'admin_editor' => $adminEditorKey,
]);

if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'site') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
    sr_require_csrf();
    $postedSkinKey = sr_post_string('admin_skin_key', 40);
    $postedColorScheme = sr_post_string('admin_color_scheme', 20);
    $postedAdminEditorInput = sr_post_string('admin_editor', 30);
    $postedAdminEditor = sr_editor_normalize_key($postedAdminEditorInput);
    $postedListPaginationPerPageInput = trim(sr_post_string('list_pagination_per_page', 20));
    $postedListPaginationPerPage = null;
    if (!isset($adminSkinOptions[$postedSkinKey])) {
        $errors[] = '관리자 스킨 값이 올바르지 않습니다.';
    }

    if (!isset(sr_color_scheme_options()[$postedColorScheme])) {
        $errors[] = '관리자 색상 모드 값이 올바르지 않습니다.';
    }

    if ($postedAdminEditorInput !== $postedAdminEditor || !array_key_exists($postedAdminEditor, $adminEditorOptions)) {
        $errors[] = '관리자 화면 에디터 값이 올바르지 않습니다.';
    }

    if (preg_match('/\A[1-9][0-9]*\z/', $postedListPaginationPerPageInput) !== 1) {
        $errors[] = '페이징 기본수는 10개 이상 500개 이하의 정수여야 합니다.';
    } else {
        $postedListPaginationPerPage = (int) $postedListPaginationPerPageInput;
        if ($postedListPaginationPerPage < 10 || $postedListPaginationPerPage > 500) {
            $errors[] = '페이징 기본수는 10개 이상 500개 이하로 입력하세요.';
        }
    }

    if ($errors !== []) {
        $values = array_merge(sr_admin_post_site_setting_values($site ?? null), [
            'admin_skin_key' => $postedSkinKey,
            'admin_color_scheme' => $postedColorScheme,
            'list_pagination_per_page' => $postedListPaginationPerPageInput,
            'admin_editor' => $postedAdminEditor,
        ]);
        $adminSkinKey = $postedSkinKey;
        $adminColorScheme = $postedColorScheme;
        $listPaginationPerPage = $postedListPaginationPerPageInput;
    }

    if ($errors === []) {
        $postResult = sr_admin_handle_settings_post(
            $pdo,
            $account,
            $site ?? null
        );
        $errors = $postResult['errors'];
        $notice = (string) $postResult['notice'];
        $values = array_merge($postResult['values'], [
            'admin_skin_key' => $postedSkinKey,
            'admin_color_scheme' => $postedColorScheme,
            'list_pagination_per_page' => $postedListPaginationPerPageInput,
            'admin_editor' => $postedAdminEditor,
        ]);
        $site = is_array($postResult['site']) ? $postResult['site'] : ($site ?? null);

        if ($errors === []) {
            $previousSkinKey = $adminSkinKey;
            $previousColorScheme = $adminColorScheme;
            $previousListPaginationPerPage = $listPaginationPerPage;
            $previousAdminEditor = $adminEditorKey;
            $adminSettingsBefore = [
                'admin_skin_key' => $previousSkinKey,
                'admin_color_scheme' => $previousColorScheme,
                'list_pagination_per_page' => $previousListPaginationPerPage,
                'admin_editor' => $previousAdminEditor,
            ];
            $adminSettingsAfter = [
                'admin_skin_key' => $postedSkinKey,
                'admin_color_scheme' => $postedColorScheme,
                'list_pagination_per_page' => (int) $postedListPaginationPerPage,
                'admin_editor' => $postedAdminEditor,
            ];

            if ($postedSkinKey !== $previousSkinKey) {
                sr_admin_save_skin_key($pdo, $postedSkinKey);
            }

            if ($postedColorScheme !== $previousColorScheme) {
                sr_admin_save_color_scheme($pdo, $postedColorScheme);
            }

            if ((int) $postedListPaginationPerPage !== $previousListPaginationPerPage) {
                sr_admin_save_list_pagination_per_page($pdo, (int) $postedListPaginationPerPage);
            }

            if ($postedAdminEditor !== $previousAdminEditor) {
                sr_admin_save_editor_key($pdo, $postedAdminEditor);
            }

            if ($adminSettingsAfter !== $adminSettingsBefore) {
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'admin.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'admin',
                    'result' => 'success',
                    'message' => 'Admin settings updated.',
                    'metadata' => [
                        'before' => $adminSettingsBefore,
                        'after' => $adminSettingsAfter,
                    ],
                ]);
            }

            $adminSettings = sr_admin_settings($pdo);
            $adminSkinKey = sr_admin_skin_key($adminSettings);
            $adminColorScheme = sr_admin_color_scheme($adminSettings);
            $listPaginationPerPage = sr_admin_list_pagination_per_page($adminSettings);
            $adminEditorKey = sr_admin_editor_key($pdo, $adminSettings);
            $values = array_merge($values, [
                'admin_skin_key' => $adminSkinKey,
                'admin_color_scheme' => $adminColorScheme,
                'list_pagination_per_page' => $listPaginationPerPage,
                'admin_editor' => $adminEditorKey,
            ]);
            $notice = '설정을 저장했습니다.';
        } else {
            $adminSkinKey = $postedSkinKey;
            $adminColorScheme = $postedColorScheme;
            $listPaginationPerPage = $postedListPaginationPerPageInput;
            $adminEditorKey = $postedAdminEditor;
        }
    }
} elseif (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
}

if (sr_request_method() === 'POST') {
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/settings');
}

$localeOptions = sr_available_locale_options($site ?? null);
$homepageCandidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
$currentHomepageAvailable = sr_site_home_path_is_available($pdo, (string) ($values['home_path'] ?? '/'));

include SR_ROOT . '/modules/admin/views/settings.php';
