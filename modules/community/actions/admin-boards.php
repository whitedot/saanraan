<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
if (!in_array($communityBoardsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardsPage = 'list';
}
if (sr_request_method() === 'GET' && in_array($communityBoardsPage, ['new', 'edit'], true)) {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'edit');
}
$allowedStatuses = sr_community_board_statuses();
$allowedReadPolicies = sr_community_policy_values('read');
$allowedWritePolicies = sr_community_policy_values('write');
$allowedCommentPolicies = sr_community_policy_values('comment');
$communitySkinOptions = sr_community_skin_options();
$settings = sr_community_settings($pdo);
$editorOptions = sr_editor_options($pdo);
$assetModuleOptions = sr_community_asset_module_options($pdo);
$assetPolicySets = sr_community_asset_policy_sets($pdo);
$reactionPresetOptions = function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$maxLevel = sr_community_max_level_value($settings);
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$publicBannerSettingLabels = sr_community_public_banner_setting_labels();
$publicPopupLayerSettingLabels = sr_community_public_popup_layer_setting_labels();
$publicDisplaySettingLabels = $publicBannerSettingLabels + $publicPopupLayerSettingLabels;
$memberGroups = sr_member_groups($pdo);
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
        continue;
    }

    $enabledMemberGroups[] = $memberGroup;
    $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
}

$boardGroups = sr_community_board_groups($pdo);
$boardGroupIds = [];
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupId = (int) $boardGroup['id'];
    $boardGroupIds[$boardGroupId] = true;
    $boardGroupSettings[$boardGroupId] = sr_community_board_group_settings($pdo, $boardGroupId);
}
$boardGroupFilterValue = sr_get_string('group_id', 20);
$boardGroupFilterId = preg_match('/\A[1-9][0-9]*\z/', $boardGroupFilterValue) === 1 ? (int) $boardGroupFilterValue : 0;
if ($boardGroupFilterId > 0 && !isset($boardGroupIds[$boardGroupFilterId])) {
    $boardGroupFilterId = 0;
}
$boardListFilters = [
    'status' => sr_admin_get_allowed_array('status', $allowedStatuses, 30),
    'group_id' => $boardGroupFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
$newBoardGroupId = $communityBoardsPage === 'new' ? $boardGroupFilterId : 0;
if (!in_array($boardListFilters['field'], ['all', 'key', 'title', 'group'], true)) {
    $boardListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', in_array($intent, ['delete_board', 'retry_storage_cleanup_failure'], true) ? 'delete' : 'edit');

    if (in_array($intent, ['board_manager_grant', 'board_manager_revoke'], true)) {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = sr_t('community::action.error.board_not_found');
        }

        if ($intent === 'board_manager_grant') {
            $targetAccountIdValue = sr_post_string('account_id', 20);
            $targetAccountId = preg_match('/\A[1-9][0-9]*\z/', $targetAccountIdValue) === 1 ? (int) $targetAccountIdValue : 0;
            $accountIdentifier = sr_post_string('account_identifier', 120);
            $accountField = sr_post_string('account_identifier_field', 20);
            if (!in_array($accountField, ['all', 'id', 'email', 'login_id', 'display_name', 'nickname'], true)) {
                $accountField = 'all';
            }
            if ($targetAccountId < 1) {
                $targetAccountId = sr_admin_member_account_id_from_lookup($pdo, sr_runtime_config(), $accountField, $accountIdentifier);
            }
            $permissionKeys = [];
            $permissionInput = $_POST['permission_keys'] ?? [];
            if (is_array($permissionInput)) {
                foreach ($permissionInput as $permissionKey) {
                    $permissionKey = (string) $permissionKey;
                    if (sr_community_board_manager_permission_is_valid($permissionKey)) {
                        $permissionKeys[] = $permissionKey;
                    }
                }
            }
            $permissionKeys = array_values(array_unique($permissionKeys));
            if ($targetAccountId < 1) {
                $errors[] = '권한을 부여할 회원을 찾을 수 없습니다.';
            }
            if ($permissionKeys === []) {
                $errors[] = '부여할 게시판 관리권한을 선택해 주세요.';
            }
            if ($errors === [] && is_array($board)) {
                $granted = sr_community_grant_board_management_permissions($pdo, $boardId, $targetAccountId, $permissionKeys, (int) $account['id']);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board_manager.granted',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board manager permissions granted.',
                    'metadata' => [
                        'board_key' => (string) ($board['board_key'] ?? ''),
                        'account_id' => $targetAccountId,
                        'permission_keys' => $granted,
                    ],
                ]);
                $notice = '게시판 관리권한을 부여했습니다.';
            }
        } else {
            $managerIdValue = sr_post_string('manager_id', 20);
            $managerId = preg_match('/\A[1-9][0-9]*\z/', $managerIdValue) === 1 ? (int) $managerIdValue : 0;
            $revoked = $errors === [] ? sr_community_revoke_board_management_permission($pdo, $managerId, $boardId, (int) $account['id']) : null;
            if (!is_array($revoked)) {
                $errors[] = '회수할 게시판 관리권한을 찾을 수 없습니다.';
            }
            if ($errors === [] && is_array($board) && is_array($revoked)) {
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board_manager.revoked',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board manager permission revoked.',
                    'metadata' => [
                        'board_key' => (string) ($board['board_key'] ?? ''),
                        'account_id' => (int) ($revoked['account_id'] ?? 0),
                        'permission_key' => (string) ($revoked['permission_key'] ?? ''),
                    ],
                ]);
                $notice = '게시판 관리권한을 회수했습니다.';
            }
        }

        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect('/admin/community/boards/edit?id=' . (string) $boardId);
    } elseif ($intent === 'delete_board') {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $deleteCheck = sr_community_can_delete_board($pdo, $boardId);
        $deleteBoard = is_array($deleteCheck['board'] ?? null) ? $deleteCheck['board'] : null;
        $deleteReferences = is_array($deleteCheck['references'] ?? null) ? $deleteCheck['references'] : [];
        $deleteTargetRecords = (int) ($deleteReferences['posts'] ?? 0)
            + (int) ($deleteReferences['comments'] ?? 0)
            + (int) ($deleteReferences['attachments'] ?? 0)
            + (int) ($deleteReferences['series'] ?? 0);
        $deleteLoadAssessment = sr_admin_high_load_assessment([
            'target_records' => $deleteTargetRecords,
            'file_operations' => (int) ($deleteReferences['attachments'] ?? 0),
            'table_count' => 8,
            'long_transaction' => true,
            'rollback_limited' => true,
        ]);
        $deleteConfirmText = is_array($deleteBoard) ? '삭제 ' . (string) ($deleteBoard['board_key'] ?? '') : '';
        if (!is_array($deleteBoard)) {
            $errors[] = sr_t('community::action.error.board_not_found');
        } elseif (sr_post_string('delete_confirm_text', 80) !== $deleteConfirmText) {
            $errors[] = '게시판 삭제 확인 문구가 일치하지 않습니다.';
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.delete_confirmation_failed',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'failure',
                'message' => 'Community board delete confirmation failed.',
                'metadata' => [
                    'board_key' => (string) ($deleteBoard['board_key'] ?? ''),
                    'target_records' => $deleteTargetRecords,
                    'load_grade' => (string) $deleteLoadAssessment['grade'],
                    'confirmation_checked' => false,
                ],
            ]);
        }
        if ($errors !== []) {
            sr_admin_flash_result(sr_admin_action_result($errors, $notice));
            sr_redirect(is_array($deleteBoard) ? '/admin/community/boards/edit?id=' . (string) $boardId : '/admin/community/boards');
        }
        $deleteResult = sr_community_delete_board($pdo, $boardId);
        $errors = array_merge($errors, is_array($deleteResult['errors'] ?? null) ? $deleteResult['errors'] : []);
        $board = is_array($deleteResult['board'] ?? null) ? $deleteResult['board'] : null;
        if ($errors === [] && is_array($board)) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.deleted',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board deleted.',
                'metadata' => [
                    'board_key' => (string) ($board['board_key'] ?? ''),
                    'title' => (string) ($board['title'] ?? ''),
                    'deleted_settings' => (int) ($deleteResult['deleted_settings'] ?? 0),
                    'deleted_setting_sources' => (int) ($deleteResult['deleted_setting_sources'] ?? 0),
                    'deleted_board_managers' => (int) ($deleteResult['deleted_board_managers'] ?? 0),
                    'deleted_categories' => (int) ($deleteResult['deleted_categories'] ?? 0),
                    'deleted_posts' => (int) ($deleteResult['deleted_posts'] ?? 0),
                    'deleted_comments' => (int) ($deleteResult['deleted_comments'] ?? 0),
                    'deleted_attachments' => (int) ($deleteResult['deleted_attachments'] ?? 0),
                    'deleted_attachment_files' => (int) ($deleteResult['deleted_attachment_files'] ?? 0),
                    'failed_attachment_files' => (int) ($deleteResult['failed_attachment_files'] ?? 0),
                    'failed_attachment_file_refs' => array_slice(array_map('strval', is_array($deleteResult['failed_attachment_file_refs'] ?? null) ? $deleteResult['failed_attachment_file_refs'] : []), 0, 20),
                    'deleted_series' => (int) ($deleteResult['deleted_series'] ?? 0),
                    'target_records' => $deleteTargetRecords,
                    'batch' => false,
                    'load_grade' => (string) $deleteLoadAssessment['grade'],
                    'confirmation_checked' => true,
                ],
            ]);
            if ((int) ($deleteResult['failed_attachment_files'] ?? 0) > 0) {
                $notice = '게시판 데이터는 삭제됐지만 일부 첨부 파일 저장소 정리가 실패했습니다. 저장소 정리 실패 기록을 확인해 주세요.';
            } else {
                $notice = '게시판을 삭제했습니다.';
            }
        }

        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect('/admin/community/boards');
    } elseif ($intent === 'retry_storage_cleanup_failure') {
        $failureIdValue = sr_post_string('failure_id', 20);
        $failureId = preg_match('/\A[1-9][0-9]*\z/', $failureIdValue) === 1 ? (int) $failureIdValue : 0;
        $retryResult = sr_community_retry_storage_cleanup_failure($pdo, $failureId);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.storage_cleanup.retry',
            'target_type' => 'community_storage_cleanup_failure',
            'target_id' => (string) $failureId,
            'result' => empty($retryResult['ok']) ? 'failure' : 'success',
            'message' => 'Community storage cleanup retry processed.',
            'metadata' => [
                'failure_id' => $failureId,
                'message' => (string) ($retryResult['message'] ?? ''),
            ],
        ]);
        if (empty($retryResult['ok'])) {
            $createAdminNotificationFunction = sr_module_contract_function($pdo, 'notification', 'admin-notification-events.php', 'create_function');
            if ($createAdminNotificationFunction !== '') {
                try {
                    $createAdminNotificationFunction($pdo, [
                        'title' => '커뮤니티 저장소 정리 재시도에 실패했습니다.',
                        'body_text' => (string) ($retryResult['message'] ?? '저장소 파일 정리 재시도에 실패했습니다.'),
                        'severity' => 'warning',
                        'source_module_key' => 'community',
                        'event_key' => 'storage_cleanup.retry_failed',
                        'target_type' => 'community_storage_cleanup_failure',
                        'target_id' => (string) $failureId,
                        'action_url' => '/admin/community/boards',
                        'permission_path' => '/admin/community/boards',
                        'permission_action' => 'delete',
                        'dedupe_key' => 'community.storage_cleanup.retry_failed.' . (string) $failureId,
                        'created_by_account_id' => (int) $account['id'],
                    ]);
                } catch (Throwable $exception) {
                    sr_log_exception($exception, 'community_storage_cleanup_admin_notification_create');
                }
            }
            $errors[] = (string) ($retryResult['message'] ?? '저장소 파일 정리 재시도에 실패했습니다.');
        } else {
            $notice = (string) ($retryResult['message'] ?? '저장소 파일 정리를 완료했습니다.');
        }
        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect('/admin/community/boards');
    } elseif (in_array($intent, ['category_create', 'category_update', 'category_delete'], true)) {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = sr_t('community::action.error.board_not_found');
        }
        if (!sr_community_categories_supported($pdo)) {
            $errors[] = '카테고리 스키마 업데이트가 아직 적용되지 않았습니다.';
        }

        $categoryId = 0;
        $category = null;
        if (in_array($intent, ['category_update', 'category_delete'], true)) {
            $categoryIdValue = sr_post_string('category_id', 20);
            $categoryId = preg_match('/\A[1-9][0-9]*\z/', $categoryIdValue) === 1 ? (int) $categoryIdValue : 0;
            $category = sr_community_category_by_id($pdo, $categoryId);
            if (!is_array($category) || (int) ($category['board_id'] ?? 0) !== $boardId) {
                $errors[] = '카테고리를 찾을 수 없습니다.';
            }
        }

        $categoryKey = strtolower(trim(sr_post_string('category_key', 60)));
        $categoryTitle = sr_post_string('category_title', 120);
        $categoryDescription = sr_post_string_without_truncation('category_description', 2000);
        $categoryStatus = sr_post_string('category_status', 30);
        $categorySortOrder = sr_admin_post_int_in_range('category_sort_order', 0, 1000000);

        if ($intent === 'category_create' && !sr_community_category_key_is_valid($categoryKey)) {
            $errors[] = '카테고리 관리용 키는 소문자, 숫자, 밑줄만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
        }
        if ($intent !== 'category_delete' && $categoryTitle === '') {
            $errors[] = '카테고리 이름을 입력해 주세요.';
        }
        if ($intent !== 'category_delete' && !in_array($categoryStatus, sr_community_category_statuses(), true)) {
            $errors[] = '카테고리 상태 값이 올바르지 않습니다.';
        }
        if ($intent !== 'category_delete' && $categorySortOrder === null) {
            $errors[] = '카테고리 정렬값이 올바르지 않습니다.';
            $categorySortOrder = 0;
        }
        if ($intent !== 'category_delete' && !is_string($categoryDescription)) {
            $errors[] = '카테고리 설명이 너무 깁니다.';
            $categoryDescription = '';
        }
        if ($intent === 'category_create' && $errors === [] && sr_community_category_by_key($pdo, $boardId, $categoryKey) !== null) {
            $errors[] = '같은 게시판에 이미 같은 관리용 키의 카테고리가 있습니다.';
        }

        if ($errors === [] && is_array($board)) {
            if ($intent === 'category_create') {
                $createdCategoryId = sr_community_create_category($pdo, $boardId, [
                    'category_key' => $categoryKey,
                    'title' => $categoryTitle,
                    'description' => (string) $categoryDescription,
                    'status' => $categoryStatus,
                    'sort_order' => (int) $categorySortOrder,
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.category.created',
                    'target_type' => 'community_category',
                    'target_id' => (string) $createdCategoryId,
                    'result' => 'success',
                    'message' => 'Community category created.',
                    'metadata' => [
                        'board_key' => (string) $board['board_key'],
                        'category_key' => $categoryKey,
                        'status' => $categoryStatus,
                    ],
                ]);
                $notice = '카테고리를 추가했습니다.';
            } elseif ($intent === 'category_update' && is_array($category)) {
                sr_community_update_category($pdo, $categoryId, [
                    'title' => $categoryTitle,
                    'description' => (string) $categoryDescription,
                    'status' => $categoryStatus,
                    'sort_order' => (int) $categorySortOrder,
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.category.updated',
                    'target_type' => 'community_category',
                    'target_id' => (string) $categoryId,
                    'result' => 'success',
                    'message' => 'Community category updated.',
                    'metadata' => [
                        'board_key' => (string) $board['board_key'],
                        'category_key' => (string) $category['category_key'],
                        'before_status' => (string) $category['status'],
                        'after_status' => $categoryStatus,
                    ],
                ]);
                $notice = '카테고리를 수정했습니다.';
            } elseif ($intent === 'category_delete' && is_array($category)) {
                if (!sr_community_delete_category($pdo, $categoryId)) {
                    $errors[] = '참조 중인 게시글이 있는 카테고리는 삭제할 수 없습니다. 비활성으로 전환해 주세요.';
                } else {
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.category.deleted',
                        'target_type' => 'community_category',
                        'target_id' => (string) $categoryId,
                        'result' => 'success',
                        'message' => 'Community category deleted.',
                        'metadata' => [
                            'board_key' => (string) $board['board_key'],
                            'category_key' => (string) $category['category_key'],
                        ],
                    ]);
                    $notice = '카테고리를 삭제했습니다.';
                }
            }
        }

        if ($errors === []) {
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/community/boards/edit?id=' . (string) $boardId);
        }
    } elseif (in_array($intent, ['create', 'update'], true)) {
        $boardKey = strtolower(trim(sr_post_string('board_key', 60)));
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $readPolicy = sr_post_string('read_policy', 30);
        $writePolicy = sr_post_string('write_policy', 30);
        $commentPolicy = sr_post_string('comment_policy', 30);
        $skinKey = sr_post_string('skin_key', 40);
        $postEditorInput = sr_post_string('post_editor', 30);
        $postEditor = sr_community_post_editor_key($postEditorInput);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
        $attachmentMaxBytes = sr_admin_post_int_in_range('attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = sr_admin_post_int_in_range('attachment_max_count', 0, 10);
        $publicDisplaySettingValues = [];
        foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
            $publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range($displaySettingKey, 0, 999999999);
        }
        $imageUploadsEnabled = ($_POST['image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = sr_admin_post_int_in_range('file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = sr_admin_post_int_in_range('file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = sr_post_string_without_truncation('file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? sr_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = sr_admin_post_int_in_range('read_min_level', 0, $maxLevel);
        $writeMinLevel = sr_admin_post_int_in_range('write_min_level', 0, $maxLevel);
        $commentMinLevel = sr_admin_post_int_in_range('comment_min_level', 0, $maxLevel);
        $categoryEnabled = ($_POST['category_enabled'] ?? '') === '1';
        $categoryRequired = ($_POST['category_required'] ?? '') === '1';
        if ($categoryRequired) {
            $categoryEnabled = true;
        }
        $secretPostsEnabled = ($_POST['secret_posts_enabled'] ?? '') === '1';
        $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
        $postEditLockCommentCount = sr_admin_post_int_in_range('post_edit_lock_comment_count', 0, 1000000);
        $postDeleteLockCommentCount = sr_admin_post_int_in_range('post_delete_lock_comment_count', 0, 1000000);
        $postBodyMinLength = sr_admin_post_int_in_range('post_body_min_length', 0, 20000);
        $postBodyMaxLength = sr_admin_post_int_in_range('post_body_max_length', 0, 20000);
        $commentBodyMinLength = sr_admin_post_int_in_range('comment_body_min_length', 0, 5000);
        $commentBodyMaxLength = sr_admin_post_int_in_range('comment_body_max_length', 0, 5000);
        $listExcerptEnabled = ($_POST['list_excerpt_enabled'] ?? '') === '1';
        $listExcerptLength = sr_admin_post_int_in_range('list_excerpt_length', 1, 1000);
        $listPerPage = sr_admin_post_int_in_range('list_per_page', 1, 100);
        $listDefaultSortInput = sr_post_string('list_default_sort', 20);
        $listDefaultSort = sr_community_board_list_sort_key($listDefaultSortInput);
        $reactionPostPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_post_preset_key', 80)) : '';
        $reactionCommentPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_comment_preset_key', 80)) : '';
        $privacyConsentEnabled = ($_POST['privacy_consent_enabled'] ?? '') === '1';
        $editingBoardId = 0;
        if ($intent === 'update') {
            $editingBoardIdValue = sr_post_string('board_id', 20);
            $editingBoardId = preg_match('/\A[1-9][0-9]*\z/', $editingBoardIdValue) === 1 ? (int) $editingBoardIdValue : 0;
        }
        $existingPrivacyConsentSettings = $settings;
        if ($editingBoardId > 0) {
            $existingBoard = sr_community_board_by_id($pdo, $editingBoardId);
            if (is_array($existingBoard)) {
                foreach (sr_community_privacy_consent_setting_keys() as $privacyConsentSettingKey) {
                    $existingPrivacyConsentSettings[$privacyConsentSettingKey] = sr_community_effective_board_setting(
                        $pdo,
                        $existingBoard,
                        $privacyConsentSettingKey,
                        (string) ($settings[$privacyConsentSettingKey] ?? '')
                    );
                }
            }
        }
        $privacyConsentDocumentKeys = [];
        $privacyConsentRequires = [];
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            $privacyConsentDocumentKeys[$privacyConsentTargetKey] = array_key_exists($privacyConsentDocumentSettingKey, $_POST)
                ? sr_community_privacy_consent_clean_document_key(sr_post_string($privacyConsentDocumentSettingKey, 80))
                : sr_community_privacy_consent_admin_document_key_from_settings($existingPrivacyConsentSettings, $privacyConsentTargetKey);
            $privacyConsentRequires[$privacyConsentTargetKey] = $privacyConsentDocumentKeys[$privacyConsentTargetKey] !== '';
        }
        $selectedPrivacyConsentDocumentKeys = array_filter($privacyConsentDocumentKeys, static fn (string $value): bool => $value !== '');
        $privacyConsentDocumentKey = (string) (reset($selectedPrivacyConsentDocumentKeys) ?: ($settings['privacy_consent_document_key'] ?? 'community_privacy_default'));
        $privacyConsentDocumentInheritPolicy = sr_post_string('privacy_consent_document_inherit_policy', 20);
        if (!in_array($privacyConsentDocumentInheritPolicy, ['inherit', 'override', 'disabled'], true)) {
            $privacyConsentDocumentInheritPolicy = 'override';
        }
        $privacyConsentRequirePost = !empty($privacyConsentRequires['post']);
        $privacyConsentRequireComment = !empty($privacyConsentRequires['comment']);
        $privacyConsentRequireAttachmentUpload = !empty($privacyConsentRequires['attachment_upload']);
        $extraFieldsInput = sr_post_string_without_truncation('extra_fields_json', 20000);
        $extraFieldDefinitionErrors = sr_community_extra_field_definitions_input_errors($extraFieldsInput);
        $extraFieldsJson = $extraFieldDefinitionErrors === [] && is_string($extraFieldsInput) ? sr_community_extra_field_definitions_json_from_input($extraFieldsInput) : null;
        $boardSeoValues = [
            'seo_title' => sr_community_seo_text(sr_post_string('seo_title', 160), 160),
            'seo_description' => sr_community_seo_text(sr_post_string('seo_description', 255), 255),
            'og_title' => sr_community_seo_text(sr_post_string('og_title', 160), 160),
            'og_description' => sr_community_seo_text(sr_post_string('og_description', 255), 255),
            'og_image_url' => trim(sr_post_string('og_image_url', 255)),
        ];
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $boardGroupId = sr_admin_post_int_in_range('board_group_id', 0, 999999999);
        $boardGroupId = is_int($boardGroupId) ? $boardGroupId : 0;
        $readGroupKeysInput = $_POST['read_group_keys'] ?? [];
        $writeGroupKeysInput = $_POST['write_group_keys'] ?? [];
        $commentGroupKeysInput = $_POST['comment_group_keys'] ?? [];
        $readGroupKeys = sr_community_board_group_keys_from_input_value($readGroupKeysInput);
        $writeGroupKeys = sr_community_board_group_keys_from_input_value($writeGroupKeysInput);
        $commentGroupKeys = sr_community_board_group_keys_from_input_value($commentGroupKeysInput);
        $assetSettings = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $policySetIds = sr_community_asset_policy_set_ids_from_value($_POST[$assetPrefix . '_policy_set_ids'] ?? []);
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? '', true), true)
                : sr_community_asset_module_key_or_empty(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
            $assetSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_policy_set_selection_json_from_ids($policySetIds);
            $assetSettings[$assetPrefix . '_policy_set_id'] = sr_community_asset_policy_set_first_id($policySetIds);
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $assetModules = sr_community_asset_module_keys_from_value($assetSettings[$assetPrefix . '_asset_module'], true);
                $assetSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                    sr_community_asset_amounts_from_post($assetPrefix . '_amounts', $assetModules, (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0))
                );
                $assetSettings[$assetPrefix . '_amount'] = sr_community_asset_amount_total(
                    sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'], $assetModules),
                    (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0)
                );
            }
        }
        $legacyAssetPolicySource = sr_community_asset_policy_source(sr_post_string('asset_policy_source', 20));
        $legacyAssetSettingSource = $legacyAssetPolicySource === 'global' ? 'all' : $legacyAssetPolicySource;
        $assetSettingSources = [];
        $assetPrefixSources = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $legacyPrefixSource = sr_post_string('source_' . $assetPrefix, 20);
            if ($legacyPrefixSource === '') {
                $legacyPrefixSource = $legacyAssetSettingSource;
            }
            $assetModuleSource = sr_post_string('source_' . $assetPrefix . '_asset_module', 20);
            foreach (sr_community_asset_prefix_setting_keys((string) $assetPrefix) as $settingKey) {
                $postedSettingSource = sr_post_string('source_' . $settingKey, 20);
                if ($postedSettingSource === '' && in_array($settingKey, [$assetPrefix . '_amount', $assetPrefix . '_amounts_json'], true)) {
                    $postedSettingSource = $assetModuleSource;
                }
                $assetSettingSources[$settingKey] = sr_community_normalize_board_setting_source($postedSettingSource !== '' ? $postedSettingSource : $legacyPrefixSource);
            }
            $assetSettingSources[$assetPrefix . '_group_policies_json'] = $assetSettingSources[$assetPrefix . '_policy_set_id'] ?? $assetSettingSources[$assetPrefix . '_group_policies_json'];
            $assetPrefixSources[$assetPrefix] = $assetSettingSources[$assetPrefix . '_enabled'] ?? sr_community_normalize_board_setting_source($legacyPrefixSource);
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_publisher_reward_enabled'] = ($_POST['paid_attachment_download_publisher_reward_enabled'] ?? '') === '1';
        $assetSettings['paid_attachment_download_publisher_reward_rate'] = sr_admin_post_int_in_range('paid_attachment_download_publisher_reward_rate', 0, 100);
        $assetSettingSources['paid_attachment_download_publisher_reward_enabled'] = sr_community_normalize_board_setting_source(sr_post_string('source_paid_attachment_download_publisher_reward_enabled', 20));
        $assetSettingSources['paid_attachment_download_publisher_reward_rate'] = sr_community_normalize_board_setting_source(sr_post_string('source_paid_attachment_download_publisher_reward_rate', 20));
        $assetSettingLabels = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $assetSettingLabels[$assetPrefix] = sr_community_asset_setting_label($assetPrefix);
        }
        $settingSources = [];
        foreach (sr_community_board_group_setting_keys() as $settingKey) {
            $settingSources[$settingKey] = sr_community_normalize_board_setting_source(sr_post_string('source_' . $settingKey, 20));
        }
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            $privacyConsentSettingKey = 'privacy_consent_require_' . $privacyConsentTargetKey;
            $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            $privacyConsentDocumentSource = (string) ($settingSources[$privacyConsentDocumentSettingKey] ?? 'board');
            $settingSources[$privacyConsentSettingKey] = $privacyConsentDocumentSource;
            $privacyConsentRequires[$privacyConsentTargetKey] = (string) ($privacyConsentDocumentKeys[$privacyConsentTargetKey] ?? '') !== '';
        }
        $privacyConsentRequirePost = !empty($privacyConsentRequires['post']);
        $privacyConsentRequireComment = !empty($privacyConsentRequires['comment']);
        $privacyConsentRequireAttachmentUpload = !empty($privacyConsentRequires['attachment_upload']);
        $boardSettingValues = [];
        if ($intent === 'create' && !sr_community_board_key_is_valid($boardKey)) {
            $errors[] = sr_t('community::action.admin.board_key_invalid');
        }

        if ($title === '') {
            $errors[] = sr_t('community::action.admin.board_title_required');
        }

        if ($description === null) {
            $errors[] = sr_t('community::action.admin.description_too_long');
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.board_status_invalid');
        }

        if (!in_array($readPolicy, $allowedReadPolicies, true)) {
            $errors[] = sr_t('community::action.admin.read_policy_invalid');
        }

        if (!in_array($writePolicy, $allowedWritePolicies, true)) {
            $errors[] = sr_t('community::action.admin.write_policy_invalid');
        }

        if (!in_array($commentPolicy, $allowedCommentPolicies, true)) {
            $errors[] = sr_t('community::action.admin.comment_policy_invalid');
        }

        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = sr_t('community::action.admin.board_skin_invalid');
            $skinKey = 'basic';
        }

        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시판 에디터 값이 올바르지 않습니다.';
            $postEditor = 'textarea';
        }

        if ($sortOrder === null) {
            $errors[] = sr_t('community::action.admin.sort_order_invalid');
            $sortOrder = 0;
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.image_max_bytes_invalid');
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.image_max_count_invalid');
            $attachmentMaxCount = 1;
        }

        foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
            $displaySettingLabel = (string) ($publicDisplaySettingLabels[$displaySettingKey] ?? $displaySettingKey);
            if ($displaySettingValue === null) {
                $errors[] = sr_t('community::action.admin.display_value_invalid', ['label' => $displaySettingLabel]);
                $publicDisplaySettingValues[$displaySettingKey] = 0;
                continue;
            }

            if (isset($publicBannerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicBannerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_banner_invalid', ['label' => $displaySettingLabel]);
            }

            if (isset($publicPopupLayerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicPopupLayerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_popup_invalid', ['label' => $displaySettingLabel]);
            }
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.file_max_bytes_invalid');
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.file_max_count_invalid');
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = sr_t('community::action.admin.file_extensions_too_long');
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = sr_t('community::action.admin.file_extensions_invalid', ['extensions' => implode(', ', $invalidFileExtensions)]);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = sr_t('community::action.admin.file_extensions_required');
        }

        if ($readMinLevel === null) {
            $errors[] = sr_t('community::action.admin.read_min_level_invalid', ['max' => (string) $maxLevel]);
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = sr_t('community::action.admin.write_min_level_invalid', ['max' => (string) $maxLevel]);
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = sr_t('community::action.admin.comment_min_level_invalid', ['max' => (string) $maxLevel]);
            $commentMinLevel = 0;
        }

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($categoryRequired) {
            $categoryBoardId = $intent === 'update' ? $editingBoardId : 0;
            if ($categoryBoardId < 1 || sr_community_categories($pdo, $categoryBoardId, true) === []) {
                $errors[] = '활성 카테고리가 1개 이상 있어야 카테고리 필수를 켤 수 있습니다.';
            }
        }

        foreach ([
            'postEditLockCommentCount' => ['value' => $postEditLockCommentCount, 'message' => '게시글 수정 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postDeleteLockCommentCount' => ['value' => $postDeleteLockCommentCount, 'message' => '게시글 삭제 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMinLength' => ['value' => $postBodyMinLength, 'message' => '게시글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMaxLength' => ['value' => $postBodyMaxLength, 'message' => '게시글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMinLength' => ['value' => $commentBodyMinLength, 'message' => '댓글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMaxLength' => ['value' => $commentBodyMaxLength, 'message' => '댓글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'listExcerptLength' => ['value' => $listExcerptLength, 'message' => '목록 본문 요약 길이가 올바르지 않습니다.', 'fallback' => 120],
            'listPerPage' => ['value' => $listPerPage, 'message' => '목록 페이지당 글 수가 올바르지 않습니다.', 'fallback' => 20],
        ] as $numericSettingKey => $numericSetting) {
            if ($numericSetting['value'] === null) {
                $errors[] = (string) $numericSetting['message'];
                ${$numericSettingKey} = (int) $numericSetting['fallback'];
            }
        }
        if ($postBodyMinLength > 0 && $postBodyMaxLength > 0 && $postBodyMinLength > $postBodyMaxLength) {
            $errors[] = '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($commentBodyMinLength > 0 && $commentBodyMaxLength > 0 && $commentBodyMinLength > $commentBodyMaxLength) {
            $errors[] = '댓글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($listDefaultSortInput !== $listDefaultSort) {
            $errors[] = '목록 기본 정렬 값이 올바르지 않습니다.';
        }

        if ($privacyConsentEnabled) {
            if (!sr_community_submission_consents_table_exists($pdo)) {
                $errors[] = '개인정보 수집 및 이용동의 스키마 업데이트가 아직 적용되지 않았습니다.';
            }
            if (!$privacyConsentRequirePost && !$privacyConsentRequireComment && !$privacyConsentRequireAttachmentUpload) {
                $errors[] = '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.';
            }
            foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
                if (empty($privacyConsentRequires[$privacyConsentTargetKey])) {
                    continue;
                }
                $targetDocumentKey = (string) ($privacyConsentDocumentKeys[$privacyConsentTargetKey] ?? '');
                $targetDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
                if (($settingSources[$targetDocumentSettingKey] ?? 'board') === 'board'
                    && ($targetDocumentKey === '' || !is_array(sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey)))) {
                    $errors[] = sr_community_privacy_consent_admin_label($privacyConsentTargetKey) . ' 정책 문서를 선택해 주세요.';
                }
            }
        }

        if ($boardSeoValues['og_image_url'] !== '' && !sr_is_http_url($boardSeoValues['og_image_url']) && !sr_is_safe_relative_url($boardSeoValues['og_image_url'])) {
            $errors[] = '게시판 OG 이미지 URL은 http(s) URL 또는 /로 시작하는 내부 경로만 입력해 주세요.';
        }

        if ($boardGroupId > 0 && !isset($boardGroupIds[$boardGroupId])) {
            $errors[] = sr_t('community::action.admin.board_group_invalid');
        }

        foreach ($settingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $errors[] = sr_t('community::action.admin.setting_group_source_requires_group', ['setting' => $settingKey]);
            }
        }

        foreach ($assetSettingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $assetPrefix = sr_community_asset_prefix_from_setting_key((string) $settingKey);
                $assetLabel = (string) ($assetSettingLabels[$assetPrefix] ?? $settingKey);
                $errors[] = sr_t('community::action.admin.asset_group_source_requires_group', ['label' => $assetLabel]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeysInput],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeysInput = $groupKeyValidation['value'];
            if (sr_community_board_group_keys_input_too_long($groupKeysInput)) {
                $errors[] = sr_t('community::action.admin.group_list_too_long', ['label' => $label]);
                continue;
            }

            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($groupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_invalid', ['label' => $label, 'keys' => implode(', ', $invalidGroupKeys)]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeys],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeys],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeys],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeys = $groupKeyValidation['value'];
            $unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys));
            if ($unknownGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_inactive', ['label' => $label, 'keys' => implode(', ', $unknownGroupKeys)]);
            }
        }

        $writeGroupKeys = array_values(array_intersect($writeGroupKeys, $readGroupKeys));
        $commentGroupKeys = array_values(array_intersect($commentGroupKeys, $readGroupKeys));

        foreach ($assetSettingLabels as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (($assetPrefixSources[$assetPrefix] ?? 'board') === 'board' && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
                    }
                    $amounts = sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'] ?? '', $assetModules);
                    if (count($amounts) < count($assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_amounts_required', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.asset_module_inactive', [
                        'label' => $assetLabel,
                        'module' => sr_community_asset_module_label($assetModule, $pdo),
                    ]);
                }
            }
            $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_community_asset_group_policies_from_value($assetSettings[$assetPrefix . '_group_policies_json'] ?? ''), $assetLabel));
            $assetPolicySetIds = sr_community_asset_policy_set_ids_with_legacy($assetSettings[$assetPrefix . '_group_policies_json'] ?? '', (int) ($assetSettings[$assetPrefix . '_policy_set_id'] ?? 0));
            $assetModulesForPolicy = sr_community_asset_module_keys_from_value((string) ($assetSettings[$assetPrefix . '_asset_module'] ?? ''), true);
            $errors = array_merge($errors, sr_community_asset_policy_set_ids_validation_errors($pdo, $assetPolicySetIds, $assetLabel));
            $errors = array_merge($errors, sr_community_asset_policy_set_asset_match_errors($pdo, $assetPolicySetIds, $assetModulesForPolicy, $assetLabel));
        }
        if ($assetSettings['paid_attachment_download_publisher_reward_rate'] === null) {
            $errors[] = '첨부 다운로드 게시자 리워드 지급률이 올바르지 않습니다.';
            $assetSettings['paid_attachment_download_publisher_reward_rate'] = 0;
        }
        foreach ([$reactionPostPresetKey, $reactionCommentPresetKey] as $reactionPresetKey) {
            if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                $errors[] = '게시판 리액션 프리셋 값이 올바르지 않습니다.';
                break;
            }
        }
        if ($extraFieldDefinitionErrors !== []) {
            $errors = array_merge($errors, $extraFieldDefinitionErrors);
            $extraFieldsJson = '[]';
        }

        if ($errors === [] && $intent === 'create' && sr_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_key_duplicate');
        }

        if ($errors === []) {
            $boardSettingValues = [
                'status' => $status,
                'skin_key' => $skinKey,
                'post_editor' => $postEditor,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'read_group_keys' => sr_community_board_group_keys_setting_value($readGroupKeys),
                'write_group_keys' => sr_community_board_group_keys_setting_value($writeGroupKeys),
                'comment_group_keys' => sr_community_board_group_keys_setting_value($commentGroupKeys),
                'read_min_level' => (string) $readMinLevel,
                'write_min_level' => (string) $writeMinLevel,
                'comment_min_level' => (string) $commentMinLevel,
                'category_enabled' => $categoryEnabled ? '1' : '0',
                'category_required' => $categoryRequired ? '1' : '0',
                'secret_posts_enabled' => $secretPostsEnabled ? '1' : '0',
                'secret_comments_enabled' => $secretCommentsEnabled ? '1' : '0',
                'post_edit_lock_comment_count' => (string) $postEditLockCommentCount,
                'post_delete_lock_comment_count' => (string) $postDeleteLockCommentCount,
                'post_body_min_length' => (string) $postBodyMinLength,
                'post_body_max_length' => (string) $postBodyMaxLength,
                'comment_body_min_length' => (string) $commentBodyMinLength,
                'comment_body_max_length' => (string) $commentBodyMaxLength,
                'list_excerpt_enabled' => $listExcerptEnabled ? '1' : '0',
                'list_excerpt_length' => (string) $listExcerptLength,
                'list_per_page' => (string) $listPerPage,
                'list_default_sort' => $listDefaultSort,
                'reaction_post_preset_key' => $reactionPostPresetKey,
                'reaction_comment_preset_key' => $reactionCommentPresetKey,
                'privacy_consent_enabled' => $privacyConsentEnabled ? '1' : '0',
                'privacy_consent_document_key' => $privacyConsentDocumentKey !== '' ? $privacyConsentDocumentKey : 'community_privacy_default',
                'privacy_consent_post_document_key' => (string) ($privacyConsentDocumentKeys['post'] ?? ''),
                'privacy_consent_comment_document_key' => (string) ($privacyConsentDocumentKeys['comment'] ?? ''),
                'privacy_consent_attachment_upload_document_key' => (string) ($privacyConsentDocumentKeys['attachment_upload'] ?? ''),
                'privacy_consent_document_inherit_policy' => $privacyConsentDocumentInheritPolicy,
                'privacy_consent_title' => '',
                'privacy_consent_body' => '',
                'privacy_consent_version' => '',
                'privacy_consent_require_post' => $privacyConsentRequirePost ? '1' : '0',
                'privacy_consent_require_comment' => $privacyConsentRequireComment ? '1' : '0',
                'privacy_consent_require_attachment_upload' => $privacyConsentRequireAttachmentUpload ? '1' : '0',
                'extra_fields_json' => $extraFieldsJson,
                'level_post_score' => (string) $levelPostScore,
                'level_comment_score' => (string) $levelCommentScore,
                'image_uploads_enabled' => $imageUploadsEnabled ? '1' : '0',
                'attachment_max_bytes' => (string) $attachmentMaxBytes,
                'attachment_max_count' => (string) $attachmentMaxCount,
                'file_uploads_enabled' => $fileUploadsEnabled ? '1' : '0',
                'file_attachment_max_bytes' => (string) $fileAttachmentMaxBytes,
                'file_attachment_max_count' => (string) $fileAttachmentMaxCount,
                'file_allowed_extensions' => implode(',', $fileAllowedExtensions),
            ];
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                $boardSettingValues[(string) $displaySettingKey] = (string) $displaySettingValue;
            }
            foreach ($boardSeoValues as $seoSettingKey => $seoSettingValue) {
                $boardSettingValues[(string) $seoSettingKey] = (string) $seoSettingValue;
            }
        }

        if ($intent === 'create' && $errors === []) {
            $boardId = sr_community_create_board($pdo, [
                'board_group_id' => $boardGroupId,
                'board_key' => $boardKey,
                'title' => $title,
                'description' => (string) $description,
                'status' => $status,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'image_uploads_enabled' => $imageUploadsEnabled,
                'sort_order' => (int) $sortOrder,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.created',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board created.',
                'metadata' => array_merge([
                    'board_key' => $boardKey,
                    'board_group_id' => $boardGroupId,
                    'status' => $status,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'file_uploads_enabled' => $fileUploadsEnabled,
                    'attachment_max_bytes' => $attachmentMaxBytes,
                    'attachment_max_count' => $attachmentMaxCount,
                    'file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                    'file_attachment_max_count' => $fileAttachmentMaxCount,
                    'file_allowed_extensions' => $fileAllowedExtensions,
                    'read_group_keys' => $readGroupKeys,
                    'write_group_keys' => $writeGroupKeys,
                    'comment_group_keys' => $commentGroupKeys,
                    'read_min_level' => $readMinLevel,
                    'write_min_level' => $writeMinLevel,
                    'comment_min_level' => $commentMinLevel,
                    'category_enabled' => $categoryEnabled,
                    'category_required' => $categoryRequired,
                    'level_post_score' => $levelPostScore,
                    'level_comment_score' => $levelCommentScore,
                    'secret_posts_enabled' => $secretPostsEnabled,
                    'secret_comments_enabled' => $secretCommentsEnabled,
                    'skin_key' => $skinKey,
                    'asset_settings' => $assetSettings,
                    'asset_prefix_sources' => $assetPrefixSources,
                    'asset_setting_sources' => $assetSettingSources,
                    'setting_sources' => $settingSources,
                ], $publicDisplaySettingValues),
            ]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
            }
            sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'category_enabled', $categoryEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'reaction_post_preset_key', $reactionPostPresetKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'reaction_comment_preset_key', $reactionCommentPresetKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
            sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
            foreach ($boardSettingValues as $settingKey => $settingValue) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
            }
            foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) ($settingSources['extra_fields_json'] ?? 'board')) as $targetBoardId) {
                sr_community_sync_board_field_definitions($pdo, (int) $targetBoardId, sr_community_extra_field_definitions_from_json($extraFieldsJson));
            }
            foreach ($assetSettingSources as $settingKey => $source) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
            }

            $notice = sr_t('community::action.admin.board_created');
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/community/boards');
        } elseif ($intent === 'update' && $errors === []) {
            $boardIdValue = sr_post_string('board_id', 20);
            $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
            $board = sr_community_board_by_id($pdo, $boardId);
            if (!is_array($board)) {
                $errors[] = sr_t('community::action.error.board_not_found');
            }

            if ($errors === [] && is_array($board)) {
                $beforeAttachmentMaxBytes = sr_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = sr_community_board_attachment_max_count($pdo, $boardId);
                $beforePublicDisplaySettingValues = [];
                foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
                    $beforePublicDisplaySettingValues[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, $boardId, $displaySettingKey) ?? 0);
                }
                $beforeFileAttachmentMaxBytes = sr_community_board_file_attachment_max_bytes($pdo, $boardId);
                $beforeFileAttachmentMaxCount = sr_community_board_file_attachment_max_count($pdo, $boardId);
                $beforeFileAllowedExtensions = sr_community_board_file_allowed_extensions($pdo, $boardId);
                $beforeReadGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'read_group_keys');
                $beforeWriteGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'write_group_keys');
                $beforeCommentGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'comment_group_keys');
                $beforeReadMinLevel = sr_community_board_min_level($pdo, $boardId, 'read_min_level');
                $beforeWriteMinLevel = sr_community_board_min_level($pdo, $boardId, 'write_min_level');
                $beforeCommentMinLevel = sr_community_board_min_level($pdo, $boardId, 'comment_min_level');
                $beforeCategoryEnabled = sr_community_board_category_enabled($pdo, $boardId);
                $beforeCategoryRequired = sr_community_board_category_required($pdo, $boardId);
                $beforeLevelPostScore = sr_community_board_level_score($pdo, $boardId, 'level_post_score', $settings);
                $beforeLevelCommentScore = sr_community_board_level_score($pdo, $boardId, 'level_comment_score', $settings);
                $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
                $beforeAssetSettingSources = [];
                foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
                    $beforeAssetSettingSources[$assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeAssetSettings = [];
                foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                    $beforeAssetSettings[$assetSettingKey] = sr_community_board_setting_value($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeCurrentBoardAssetSettingsForAudit = sr_community_board_asset_settings_for_audit($pdo, $boardId);
                sr_community_update_board($pdo, $boardId, [
                    'board_group_id' => $boardGroupId,
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'read_policy' => $readPolicy,
                    'write_policy' => $writePolicy,
                    'comment_policy' => $commentPolicy,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'sort_order' => (int) $sortOrder,
                ]);
                sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
                }
                sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
                sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'category_enabled', $categoryEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'reaction_post_preset_key', $reactionPostPresetKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'reaction_comment_preset_key', $reactionCommentPresetKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
                foreach ($boardSettingValues as $settingKey => $settingValue) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
                }
                foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) ($settingSources['extra_fields_json'] ?? 'board')) as $targetBoardId) {
                    sr_community_sync_board_field_definitions($pdo, (int) $targetBoardId, sr_community_extra_field_definitions_from_json($extraFieldsJson));
                }
                $boardAssetAudits = [];
                foreach ($assetSettingSources as $settingKey => $source) {
                    foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) $source) as $targetBoardId) {
                        $targetBoardId = (int) $targetBoardId;
                        if ($targetBoardId < 1) {
                            continue;
                        }

                        if (!isset($boardAssetAudits[$targetBoardId])) {
                            $targetBoard = sr_community_board_by_id($pdo, $targetBoardId);
                            $boardAssetAudits[$targetBoardId] = [
                                'board_key' => is_array($targetBoard) ? (string) ($targetBoard['board_key'] ?? '') : '',
                                'before_asset_settings' => $targetBoardId === $boardId ? $beforeCurrentBoardAssetSettingsForAudit : sr_community_board_asset_settings_for_audit($pdo, $targetBoardId),
                                'applied_setting_keys' => [],
                            ];
                        }
                        $boardAssetAudits[$targetBoardId]['applied_setting_keys'][(string) $settingKey] = true;
                    }
                }
                sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
                foreach ($assetSettingSources as $settingKey => $source) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
                }

                $publicDisplayMetadata = [];
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    $publicDisplayMetadata['before_' . $displaySettingKey] = (int) ($beforePublicDisplaySettingValues[$displaySettingKey] ?? 0);
                    $publicDisplayMetadata['after_' . $displaySettingKey] = (int) $displaySettingValue;
                }

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.updated',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board updated.',
                    'metadata' => array_merge([
                        'board_key' => (string) $board['board_key'],
                        'before_status' => (string) $board['status'],
                        'after_status' => $status,
                        'before_board_group_id' => (int) ($board['board_group_id'] ?? 0),
                        'after_board_group_id' => $boardGroupId,
                        'before_image_uploads_enabled' => (int) $board['image_uploads_enabled'] === 1,
                        'after_image_uploads_enabled' => $imageUploadsEnabled,
                        'after_file_uploads_enabled' => $fileUploadsEnabled,
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $attachmentMaxBytes,
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $attachmentMaxCount,
                        'before_file_attachment_max_bytes' => $beforeFileAttachmentMaxBytes,
                        'after_file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                        'before_file_attachment_max_count' => $beforeFileAttachmentMaxCount,
                        'after_file_attachment_max_count' => $fileAttachmentMaxCount,
                        'before_file_allowed_extensions' => $beforeFileAllowedExtensions,
                        'after_file_allowed_extensions' => $fileAllowedExtensions,
                        'before_read_group_keys' => $beforeReadGroupKeys,
                        'after_read_group_keys' => $readGroupKeys,
                        'before_write_group_keys' => $beforeWriteGroupKeys,
                        'after_write_group_keys' => $writeGroupKeys,
                        'before_comment_group_keys' => $beforeCommentGroupKeys,
                        'after_comment_group_keys' => $commentGroupKeys,
                        'before_read_min_level' => $beforeReadMinLevel,
                        'after_read_min_level' => $readMinLevel,
                        'before_write_min_level' => $beforeWriteMinLevel,
                        'after_write_min_level' => $writeMinLevel,
                        'before_comment_min_level' => $beforeCommentMinLevel,
                        'after_comment_min_level' => $commentMinLevel,
                        'before_category_enabled' => $beforeCategoryEnabled,
                        'after_category_enabled' => $categoryEnabled,
                        'before_category_required' => $beforeCategoryRequired,
                        'after_category_required' => $categoryRequired,
                        'before_level_post_score' => $beforeLevelPostScore,
                        'after_level_post_score' => $levelPostScore,
                        'before_level_comment_score' => $beforeLevelCommentScore,
                        'after_level_comment_score' => $levelCommentScore,
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $skinKey,
                        'before_asset_setting_sources' => $beforeAssetSettingSources,
                        'after_asset_prefix_sources' => $assetPrefixSources,
                        'after_asset_setting_sources' => $assetSettingSources,
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $assetSettings,
                        'setting_sources' => $settingSources,
                    ], $publicDisplayMetadata),
                ]);
                foreach ($boardAssetAudits as $targetBoardId => $boardAssetAudit) {
                    $appliedSettingKeys = array_keys(is_array($boardAssetAudit['applied_setting_keys'] ?? null) ? $boardAssetAudit['applied_setting_keys'] : []);
                    sort($appliedSettingKeys);
                    sr_admin_audit_asset_settings_update($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.board.asset_settings.updated',
                        'target_type' => 'community_board',
                        'target_id' => (string) $targetBoardId,
                        'asset_settings_scope' => 'community.board',
                        'before_asset_settings' => is_array($boardAssetAudit['before_asset_settings'] ?? null) ? $boardAssetAudit['before_asset_settings'] : [],
                        'after_asset_settings' => sr_community_board_asset_settings_for_audit($pdo, (int) $targetBoardId),
                        'message' => 'Community board asset settings updated.',
                        'metadata' => [
                            'board_key' => (string) ($boardAssetAudit['board_key'] ?? ''),
                            'source' => 'community_board',
                            'source_board_key' => (string) $board['board_key'],
                            'applied_setting_keys' => $appliedSettingKeys,
                        ],
                    ]);
                }

                $notice = sr_t('community::action.admin.board_updated');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
}

$communityAdminPrepareBoard = static function (array $board) use ($pdo, $settings, $publicDisplaySettingLabels): array {
    $board['setting_sources'] = sr_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = sr_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = sr_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
        $board[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, (int) $board['id'], $displaySettingKey) ?? 0);
    }
    $board['effective_attachment_max_bytes'] = sr_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_count'] = sr_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_uploads_enabled'] = sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0');
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['file_attachment_max_bytes'] = sr_community_board_own_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['file_attachment_max_count'] = sr_community_board_own_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_bytes'] = sr_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_count'] = sr_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_allowed_extensions'] = sr_community_board_own_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['effective_file_allowed_extensions'] = sr_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['read_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['effective_write_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['effective_comment_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['read_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['write_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['comment_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['category_enabled'] = sr_community_board_category_enabled($pdo, (int) $board['id']) ? '1' : '0';
    $board['category_required'] = sr_community_board_category_required($pdo, (int) $board['id']) ? '1' : '0';
    $board['effective_read_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['effective_write_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['effective_comment_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['categories'] = sr_community_categories($pdo, (int) $board['id'], false);
    $board['level_post_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['level_comment_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['effective_level_post_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['effective_level_comment_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['skin_key'] = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'skin_key') ?? 'basic')]);
    $board['post_editor'] = sr_community_post_editor_key((string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'post_editor') ?? 'textarea'));
    $board['effective_post_editor'] = sr_community_effective_post_editor($pdo, $board, $settings);
    $board['reaction_post_preset_key'] = (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'reaction_post_preset_key') ?? '');
    $board['reaction_comment_preset_key'] = (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'reaction_comment_preset_key') ?? '');
    foreach (sr_community_privacy_consent_setting_keys() as $privacyConsentSettingKey) {
        $defaultValue = match ($privacyConsentSettingKey) {
            'privacy_consent_title' => '개인정보 수집 및 이용동의',
            'privacy_consent_version' => '1',
            'privacy_consent_document_key' => 'community_privacy_default',
            'privacy_consent_document_inherit_policy' => 'inherit',
            default => '0',
        };
        $board[$privacyConsentSettingKey] = sr_community_effective_board_setting($pdo, $board, (string) $privacyConsentSettingKey, $defaultValue);
    }
    $storedExtraFieldDefinitions = sr_community_board_setting_source($pdo, (int) $board['id'], 'extra_fields_json') === 'board'
        ? sr_community_extra_field_definitions_from_storage($pdo, (int) $board['id'])
        : [];
    $board['extra_fields_json'] = $storedExtraFieldDefinitions !== []
        ? json_encode($storedExtraFieldDefinitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : sr_community_effective_board_setting($pdo, $board, 'extra_fields_json', '[]');
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $board['source_' . $assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], (string) $assetSettingKey);
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $board[$assetPrefix . '_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_enabled', !empty($settings[$assetPrefix . '_enabled']) ? '1' : '0');
        $board[$assetPrefix . '_asset_module'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? ''));
        $board[$assetPrefix . '_amount'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0));
        $board[$assetPrefix . '_group_policies_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_group_policies_json', (string) ($settings[$assetPrefix . '_group_policies_json'] ?? ''));
        $board[$assetPrefix . '_policy_set_id'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_policy_set_id', (string) ($settings[$assetPrefix . '_policy_set_id'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $board[$assetPrefix . '_amounts_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amounts_json', (string) ($settings[$assetPrefix . '_amounts_json'] ?? ''));
        }
        if (in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)) {
            $board[$assetPrefix . '_charge_policy'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_charge_policy', (string) ($settings[$assetPrefix . '_charge_policy'] ?? 'once'));
        }
    }
    $board['paid_attachment_download_publisher_reward_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_enabled', !empty($settings['paid_attachment_download_publisher_reward_enabled']) ? '1' : '0');
    $board['paid_attachment_download_publisher_reward_rate'] = sr_community_asset_board_setting($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_rate', (string) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0));
    $board['source_paid_attachment_download_publisher_reward_enabled'] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], 'paid_attachment_download_publisher_reward_enabled');
    $board['source_paid_attachment_download_publisher_reward_rate'] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], 'paid_attachment_download_publisher_reward_rate');

    return $board;
};

$boardStatusCounts = sr_community_admin_board_status_counts($pdo, $allowedStatuses);
$boardSort = sr_admin_sort_from_request(sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort());
$boardPagination = sr_admin_pagination_from_total($pdo, $communityBoardsPage === 'list' ? sr_community_admin_board_count($pdo, $boardListFilters) : 0);
$boards = [];
if ($communityBoardsPage === 'list') {
    foreach (sr_community_admin_boards($pdo, $boardListFilters, (int) $boardPagination['per_page'], sr_admin_pagination_offset($boardPagination), $boardSort) as $board) {
        $boards[] = $communityAdminPrepareBoard($board);
    }
}
$communityStorageCleanupFailures = $communityBoardsPage === 'list' ? sr_community_storage_cleanup_failures($pdo) : [];

$editBoard = null;
if ($communityBoardsPage === 'edit') {
    $editBoardIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editBoardId = preg_match('/\A[1-9][0-9]*\z/', $editBoardIdValue) === 1 ? (int) $editBoardIdValue : 0;
    $editBoard = sr_community_board_by_id($pdo, $editBoardId);
    if (is_array($editBoard)) {
        $editBoard = $communityAdminPrepareBoard($editBoard);
    }

    if (!is_array($editBoard)) {
        sr_render_error(404, sr_t('community::action.error.board_not_found'));
    }
}

include SR_ROOT . '/modules/community/views/admin-boards.php';
