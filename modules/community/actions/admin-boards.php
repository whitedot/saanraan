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
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'view');
$canViewCommunityThumbnailFileCache = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'view');
sr_community_use_board_settings_runtime_cache(sr_request_method() === 'GET');

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
$editorOptions = [];
$assetModuleOptions = [];
$assetPolicySets = [];
$reactionPresetOptions = ['' => '리액션 기본값'];
$maxLevel = sr_community_max_level_value($settings);
$publicBanners = [];
$publicBannerIds = [];
$publicPopupLayers = [];
$publicPopupLayerIds = [];
$publicBannerSettingLabels = sr_community_public_banner_setting_labels();
$publicPopupLayerSettingLabels = sr_community_public_popup_layer_setting_labels();
$publicDisplaySettingLabels = $publicBannerSettingLabels + $publicPopupLayerSettingLabels;
$memberGroups = [];
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
$boardGroups = sr_community_board_groups($pdo);
$boardGroupIds = [];
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupId = (int) $boardGroup['id'];
    $boardGroupIds[$boardGroupId] = true;
    if (in_array($communityBoardsPage, ['new', 'edit'], true)) {
        $boardGroupSettings[$boardGroupId] = sr_community_board_group_settings($pdo, $boardGroupId);
    }
}
if (in_array($communityBoardsPage, ['new', 'edit'], true)) {
    $editorOptions = sr_editor_options($pdo);
    $assetModuleOptions = sr_community_asset_module_options($pdo);
    $assetPolicySets = sr_community_asset_policy_sets($pdo);
    $reactionPresetOptions = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
    $publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
        ? sr_banner_public_banners($pdo)
        : [];
    foreach ($publicBanners as $publicBanner) {
        $publicBannerIds[(int) $publicBanner['id']] = true;
    }
    $publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
        ? sr_popup_layer_public_layers($pdo)
        : [];
    foreach ($publicPopupLayers as $publicPopupLayer) {
        $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
    }
    $memberGroups = sr_member_groups($pdo);
    foreach ($memberGroups as $memberGroup) {
        if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
            continue;
        }

        $enabledMemberGroups[] = $memberGroup;
        $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
    }
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
                $errors[] = '부여할 게시판 스탭 권한을 선택해 주세요.';
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
                $notice = '게시판 스탭 권한을 부여했습니다.';
            }
        } else {
            $managerIdValue = sr_post_string('manager_id', 20);
            $managerId = preg_match('/\A[1-9][0-9]*\z/', $managerIdValue) === 1 ? (int) $managerIdValue : 0;
            $revoked = $errors === [] ? sr_community_revoke_board_management_permission($pdo, $managerId, $boardId, (int) $account['id']) : null;
            if (!is_array($revoked)) {
                $errors[] = '회수할 게시판 스탭 권한을 찾을 수 없습니다.';
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
                $notice = '게시판 스탭 권한을 회수했습니다.';
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
            $errors[] = '카테고리 Key는 소문자, 숫자, 밑줄만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
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
            $errors[] = '같은 게시판에 이미 같은 Key의 카테고리가 있습니다.';
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
        $saveResult = sr_community_admin_handle_board_save_post($pdo, $intent, $account, [
            'allowed_statuses' => $allowedStatuses,
            'allowed_read_policies' => $allowedReadPolicies,
            'allowed_write_policies' => $allowedWritePolicies,
            'allowed_comment_policies' => $allowedCommentPolicies,
            'community_skin_options' => $communitySkinOptions,
            'editor_options' => $editorOptions,
            'settings' => $settings,
            'max_level' => $maxLevel,
            'public_display_setting_labels' => $publicDisplaySettingLabels,
            'public_banner_setting_labels' => $publicBannerSettingLabels,
            'public_popup_layer_setting_labels' => $publicPopupLayerSettingLabels,
            'public_banner_ids' => $publicBannerIds,
            'public_popup_layer_ids' => $publicPopupLayerIds,
            'enabled_member_group_keys' => $enabledMemberGroupKeys,
            'asset_module_options' => $assetModuleOptions,
            'reaction_preset_options' => $reactionPresetOptions,
        ]);
        $errors = is_array($saveResult['errors'] ?? null) ? $saveResult['errors'] : [];
        $notice = (string) ($saveResult['notice'] ?? '');
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    $redirectPath = '/admin/community/boards';
    $postedBoardIdValue = sr_post_string('board_id', 20);
    $postedBoardId = preg_match('/\A[1-9][0-9]*\z/', $postedBoardIdValue) === 1 ? (int) $postedBoardIdValue : 0;
    if ($postedBoardId > 0) {
        $redirectPath = '/admin/community/boards/edit?id=' . (string) $postedBoardId;
    } elseif ($communityBoardsPage === 'new') {
        $redirectPath = '/admin/community/boards/new';
    }
    sr_redirect($redirectPath);
}

$boardStatusCounts = sr_community_admin_board_status_counts($pdo, $allowedStatuses);
$boardSort = sr_admin_sort_from_request(sr_community_admin_board_sort_options(), sr_community_admin_board_default_sort());
$boardPagination = sr_admin_pagination_from_total($pdo, $communityBoardsPage === 'list' ? sr_community_admin_board_count($pdo, $boardListFilters) : 0);
$boards = [];
if ($communityBoardsPage === 'list') {
    $boards = sr_community_admin_boards($pdo, $boardListFilters, (int) $boardPagination['per_page'], sr_admin_pagination_offset($boardPagination), $boardSort, false);
}
$communityStorageCleanupFailures = $communityBoardsPage === 'list' ? sr_community_storage_cleanup_failures($pdo) : [];

$editBoard = null;
if ($communityBoardsPage === 'edit') {
    $editBoardIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editBoardId = preg_match('/\A[1-9][0-9]*\z/', $editBoardIdValue) === 1 ? (int) $editBoardIdValue : 0;
    $editBoard = sr_community_board_by_id($pdo, $editBoardId);
    if (is_array($editBoard)) {
        $editBoard = sr_community_admin_prepare_board_row($pdo, $editBoard, $settings, $publicDisplaySettingLabels);
    }

    if (!is_array($editBoard)) {
        sr_render_error(404, sr_t('community::action.error.board_not_found'));
    }
}

include SR_ROOT . '/modules/community/views/admin-boards.php';
