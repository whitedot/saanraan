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
$adminIconDefaults = sr_admin_material_icon_names();
$adminIconOverrides = sr_admin_icon_custom_map($pdo);
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
        try {
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
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'admin_settings_save_failed');
            $errors[] = '설정 저장 중 오류가 발생했습니다. 오류 로그를 확인해 주세요.';
            $values = array_merge(sr_admin_post_site_setting_values($site ?? null), [
                'admin_skin_key' => $postedSkinKey,
                'admin_color_scheme' => $postedColorScheme,
                'list_pagination_per_page' => $postedListPaginationPerPageInput,
                'admin_editor' => $postedAdminEditor,
            ]);
            $adminSkinKey = $postedSkinKey;
            $adminColorScheme = $postedColorScheme;
            $listPaginationPerPage = $postedListPaginationPerPageInput;
            $adminEditorKey = $postedAdminEditor;
        }
    }
} elseif (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'icon_settings') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
    sr_require_csrf();
    $postedIconTypes = $_POST['icon_key_type'] ?? [];
    $postedIconMaterialNames = $_POST['icon_key_material_name'] ?? [];
    $postedIconRemoveImages = $_POST['icon_key_remove_image'] ?? [];
    $postedIconDeletes = $_POST['icon_key_delete'] ?? [];
    $postedCustomIconKeys = $_POST['custom_icon_key'] ?? [];
    $postedCustomIconTypes = $_POST['custom_icon_key_type'] ?? [];
    $postedCustomIconMaterialNames = $_POST['custom_icon_key_material_name'] ?? [];
    $postedIconOverrides = [];
    $uploadedIconReferences = [];
    $iconOverridesSaved = false;

    if (
        !is_array($postedIconTypes)
        || !is_array($postedIconMaterialNames)
        || !is_array($postedIconRemoveImages)
        || !is_array($postedIconDeletes)
        || !is_array($postedCustomIconKeys)
        || !is_array($postedCustomIconTypes)
        || !is_array($postedCustomIconMaterialNames)
    ) {
        $errors[] = '아이콘 키 설정 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $applyPostedIcon = static function (
            string $symbolName,
            string $defaultMaterialName,
            array $existing,
            bool $isDefault,
            string $iconType,
            string $materialNameInput,
            bool $removeImage,
            mixed $file
        ) use (&$errors, &$postedIconOverrides, &$uploadedIconReferences): void {
            $symbolName = (string) $symbolName;
            $iconType = trim($iconType);
            if (!in_array($iconType, ['material', 'image'], true)) {
                $errors[] = '아이콘 키 표시 방식이 올바르지 않습니다.';
                return;
            }

            if ($iconType === 'material') {
                $materialName = sr_material_icon_name($materialNameInput !== '' ? $materialNameInput : $defaultMaterialName);
                if (!$isDefault || $materialName !== (string) $defaultMaterialName) {
                    $postedIconOverrides[$symbolName] = [
                        'type' => 'material',
                        'material_name' => $materialName,
                    ];
                }
                return;
            }

            $uploadProvided = sr_admin_icon_upload_was_provided($file);
            if ($removeImage && !$uploadProvided) {
                $materialName = sr_material_icon_name($materialNameInput !== '' ? $materialNameInput : $defaultMaterialName);
                if (!$isDefault || $materialName !== (string) $defaultMaterialName) {
                    $postedIconOverrides[$symbolName] = [
                        'type' => 'material',
                        'material_name' => $materialName,
                    ];
                }
                return;
            }

            $storageReference = '';
            if (!$removeImage && (string) ($existing['type'] ?? '') === 'image') {
                $existingReference = (string) ($existing['storage_reference'] ?? '');
                if (sr_admin_icon_image_storage_reference($existingReference) !== null) {
                    $storageReference = $existingReference;
                }
            }

            if ($uploadProvided) {
                try {
                    $uploaded = sr_admin_icon_upload_image($file);
                    $storageReference = (string) ($uploaded['storage_reference'] ?? '');
                    if ($storageReference !== '') {
                        $uploadedIconReferences[$storageReference] = true;
                    }
                } catch (Throwable $exception) {
                    $errors[] = (string) $symbolName . ' 아이콘 이미지: ' . $exception->getMessage();
                    return;
                }
            }

            if ($storageReference === '') {
                $errors[] = (string) $symbolName . ' 아이콘 이미지를 업로드하세요.';
                return;
            }

            $postedIconOverrides[$symbolName] = [
                'type' => 'image',
                'storage_reference' => $storageReference,
            ];
        };

        foreach ($adminIconDefaults as $symbolName => $defaultMaterialName) {
            $symbolName = (string) $symbolName;
            $existing = is_array($adminIconOverrides[$symbolName] ?? null) ? $adminIconOverrides[$symbolName] : [];
            $applyPostedIcon(
                $symbolName,
                (string) $defaultMaterialName,
                $existing,
                true,
                trim((string) ($postedIconTypes[$symbolName] ?? 'material')),
                trim((string) ($postedIconMaterialNames[$symbolName] ?? $defaultMaterialName)),
                !empty($postedIconRemoveImages[$symbolName]),
                $_FILES['icon_key_image_' . $symbolName] ?? null
            );
        }

        foreach ($adminIconOverrides as $symbolName => $existing) {
            $symbolName = (string) $symbolName;
            if (isset($adminIconDefaults[$symbolName])) {
                continue;
            }
            if (!sr_admin_custom_icon_key_is_valid($symbolName)) {
                continue;
            }
            if (!empty($postedIconDeletes[$symbolName])) {
                continue;
            }

            $existing = is_array($existing) ? $existing : [];
            $defaultMaterialName = (string) ($existing['type'] ?? '') === 'material'
                ? sr_material_icon_name((string) ($existing['material_name'] ?? $symbolName))
                : sr_material_icon_name($symbolName);
            $applyPostedIcon(
                $symbolName,
                $defaultMaterialName,
                $existing,
                false,
                trim((string) ($postedIconTypes[$symbolName] ?? ($existing['type'] ?? 'material'))),
                trim((string) ($postedIconMaterialNames[$symbolName] ?? $defaultMaterialName)),
                !empty($postedIconRemoveImages[$symbolName]),
                $_FILES['icon_key_image_' . $symbolName] ?? null
            );
        }

        $customIconFiles = isset($_FILES['custom_icon_key_image']) && is_array($_FILES['custom_icon_key_image'])
            ? $_FILES['custom_icon_key_image']
            : [];
        foreach ($postedCustomIconKeys as $customIndex => $customKey) {
            $customKey = strtolower(trim((string) $customKey));
            if ($customKey === '') {
                continue;
            }
            if (!sr_admin_custom_icon_key_is_valid($customKey)) {
                $errors[] = '추가 아이콘 키는 영문 소문자로 시작하고 영문 소문자, 숫자, _만 사용할 수 있습니다.';
                continue;
            }
            if (isset($adminIconDefaults[$customKey]) || isset($postedIconOverrides[$customKey])) {
                $errors[] = '중복된 아이콘 키입니다: ' . $customKey;
                continue;
            }

            $customIconType = is_array($postedCustomIconTypes) ? trim((string) ($postedCustomIconTypes[$customIndex] ?? 'material')) : 'material';
            $customMaterialName = is_array($postedCustomIconMaterialNames)
                ? trim((string) ($postedCustomIconMaterialNames[$customIndex] ?? $customKey))
                : $customKey;
            $applyPostedIcon(
                $customKey,
                sr_material_icon_name($customMaterialName !== '' ? $customMaterialName : $customKey),
                [],
                false,
                $customIconType,
                $customMaterialName,
                false,
                $customIconFiles !== [] ? sr_admin_icon_upload_array_file($customIconFiles, (int) $customIndex) : null
            );
        }
    }

    if ($errors !== []) {
        $failedIconDeletes = sr_admin_delete_icon_image_references($uploadedIconReferences);
        if ($failedIconDeletes !== []) {
            $errors[] = '업로드 실패 처리 중 일부 아이콘 이미지 파일을 삭제하지 못했습니다. 오류 로그를 확인해 주세요.';
        }
    }

    if ($errors === []) {
        try {
            $previousIconReferences = sr_admin_icon_image_references($adminIconOverrides);
            $nextIconReferences = sr_admin_icon_image_references($postedIconOverrides);
            if ($postedIconOverrides !== $adminIconOverrides) {
                sr_admin_save_icon_key_overrides($pdo, $postedIconOverrides);
                $iconOverridesSaved = true;
                $failedIconDeletes = sr_admin_delete_icon_image_references(array_diff_key($previousIconReferences, $nextIconReferences));
                if ($failedIconDeletes !== []) {
                    $errors[] = '아이콘 설정은 저장했지만 일부 이전 아이콘 이미지 파일을 삭제하지 못했습니다. 오류 로그를 확인해 주세요.';
                }
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'admin.icon_settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'admin',
                    'result' => 'success',
                    'message' => 'Admin icon settings updated.',
                    'metadata' => [
                        'before' => ['icon_key_overrides' => $adminIconOverrides],
                        'after' => ['icon_key_overrides' => $postedIconOverrides],
                    ],
                ]);
            }
            $notice = '공용 아이콘 설정을 저장했습니다.';
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'admin_icon_settings_save_failed');
            if (!$iconOverridesSaved) {
                $failedIconDeletes = sr_admin_delete_icon_image_references($uploadedIconReferences);
                if ($failedIconDeletes !== []) {
                    $errors[] = '저장 오류 처리 중 일부 아이콘 이미지 파일을 삭제하지 못했습니다. 오류 로그를 확인해 주세요.';
                }
            }
            $errors[] = '공용 아이콘 설정 저장 중 오류가 발생했습니다. 오류 로그를 확인해 주세요.';
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
$siteNameReadReferences = sr_read_reference_collect($pdo, 'site-setting-references.php', [
    'owner_module_key' => 'admin',
    'target_type' => 'site_setting',
    'target_id' => 0,
    'target_key' => 'site.name',
], [
    'old_value' => (string) ($values['name'] ?? ''),
    'new_value' => (string) ($values['name'] ?? ''),
]);

include SR_ROOT . '/modules/admin/views/settings.php';
