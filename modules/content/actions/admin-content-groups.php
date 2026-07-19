<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-groups', 'view');

$errors = [];
$notice = $_SESSION['sr_content_group_admin_notice'] ?? '';
unset($_SESSION['sr_content_group_admin_notice']);
$sessionErrors = $_SESSION['sr_content_group_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_content_group_admin_values'] ?? [];
unset($_SESSION['sr_content_group_admin_errors'], $_SESSION['sr_content_group_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}

$pageGroupsPage = isset($pageGroupsPage) ? (string) $pageGroupsPage : 'list';
if (!in_array($pageGroupsPage, ['list', 'new', 'edit'], true)) {
    $pageGroupsPage = 'list';
}
if (sr_request_method() === 'GET' && in_array($pageGroupsPage, ['new', 'edit'], true)) {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-groups', 'edit');
}

$allowedGroupStatuses = sr_content_group_statuses();
$assetModuleOptions = sr_content_asset_module_options($pdo);
$assetPolicySets = sr_content_asset_policy_sets($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);
$reactionPresetOptions = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];
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
$editPageGroup = null;
if ($pageGroupsPage === 'edit') {
    $groupId = (int) sr_get_string('id', 20);
    $editPageGroup = sr_content_group_by_id($pdo, $groupId);
    if (!is_array($editPageGroup)) {
        sr_render_error(404, sr_t('content::action.error.content_group_edit_not_found'));
    }
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-groups', in_array($intent, ['delete_group', 'retry_storage_cleanup_failure'], true) ? 'delete' : 'edit');
    $groupId = (int) sr_post_string('group_id', 20);
    $isUpdate = $intent === 'update_group';

    if ($intent === 'delete_group') {
        $deleteResult = sr_content_delete_group($pdo, $groupId);
        $errors = array_merge($errors, is_array($deleteResult['errors'] ?? null) ? $deleteResult['errors'] : []);
        $group = is_array($deleteResult['group'] ?? null) ? $deleteResult['group'] : null;
        if ($errors === [] && is_array($group)) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'content_group.deleted',
                'target_type' => 'content_group',
                'target_id' => (string) $groupId,
                'result' => 'success',
                'message' => 'Content group deleted.',
                'metadata' => [
                    'group_key' => (string) ($group['group_key'] ?? ''),
                    'title' => (string) ($group['title'] ?? ''),
                    'deleted_settings' => (int) ($deleteResult['deleted_settings'] ?? 0),
                    'revision_references' => (int) ($deleteResult['references']['revision_references'] ?? 0),
                    'detached_contents' => (int) ($deleteResult['detached_contents'] ?? 0),
                ],
            ]);
            $_SESSION['sr_content_group_admin_notice'] = '콘텐츠 그룹을 삭제했습니다.';
        } else {
            $_SESSION['sr_content_group_admin_errors'] = $errors;
        }
        sr_redirect('/admin/content-groups');
    }

    if ($intent === 'retry_storage_cleanup_failure') {
        $failureId = (int) sr_post_string('failure_id', 20);
        $retryResult = sr_content_retry_storage_cleanup_failure($pdo, $failureId);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'content.storage_cleanup.retry',
            'target_type' => 'content_storage_cleanup_failure',
            'target_id' => (string) $failureId,
            'result' => empty($retryResult['ok']) ? 'failure' : 'success',
            'message' => 'Content storage cleanup retry processed.',
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
                        'title' => '콘텐츠 저장소 정리 재시도에 실패했습니다.',
                        'body_text' => (string) ($retryResult['message'] ?? '저장소 파일 정리 재시도에 실패했습니다.'),
                        'severity' => 'warning',
                        'source_module_key' => 'content',
                        'event_key' => 'storage_cleanup.retry_failed',
                        'target_type' => 'content_storage_cleanup_failure',
                        'target_id' => (string) $failureId,
                        'action_url' => '/admin/content-groups',
                        'permission_path' => '/admin/content-groups',
                        'permission_action' => 'delete',
                        'dedupe_key' => 'content.storage_cleanup.retry_failed.' . (string) $failureId,
                        'created_by_account_id' => (int) $account['id'],
                    ]);
                } catch (Throwable $exception) {
                    sr_log_exception($exception, 'content_storage_cleanup_admin_notification_create');
                }
            }
            $_SESSION['sr_content_group_admin_errors'] = [(string) ($retryResult['message'] ?? '저장소 파일 정리 재시도에 실패했습니다.')];
        } else {
            $_SESSION['sr_content_group_admin_notice'] = (string) ($retryResult['message'] ?? '저장소 파일 정리를 완료했습니다.');
        }
        sr_redirect('/admin/content-groups');
    }

    if ($intent === 'batch_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_group_ids'] ?? [];
        $selectedIds = [];
        if (is_array($rawSelectedIds)) {
            foreach ($rawSelectedIds as $rawSelectedId) {
                $selectedId = (int) $rawSelectedId;
                if ($selectedId > 0) {
                    $selectedIds[$selectedId] = $selectedId;
                }
            }
        }
        $selectedIds = array_values($selectedIds);

        if ($operationKey !== 'content.group_set_status') {
            $errors[] = '허용되지 않은 콘텐츠 그룹 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, $allowedGroupStatuses, true)) {
            $errors[] = '변경할 콘텐츠 그룹 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 콘텐츠 그룹을 선택하세요.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '콘텐츠 그룹 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedGroups = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'group_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id, group_key, status
                 FROM sr_content_groups
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedGroups[(int) $row['id']] = $row;
            }
            if (count($selectedGroups) !== count($selectedIds)) {
                $errors[] = '선택한 콘텐츠 그룹 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($errors === [] && $selectedGroups !== []) {
            $changedCount = 0;
            $skippedCount = 0;
            $batchFailureMessage = '';
            $now = sr_now();
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_content_groups
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND status = :before_status'
                );
                foreach ($selectedIds as $selectedId) {
                    $group = $selectedGroups[$selectedId];
                    $beforeStatus = (string) $group['status'];
                    if ($beforeStatus === $targetStatus) {
                        $skippedCount++;
                        continue;
                    }
                    $stmt->execute([
                        'status' => $targetStatus,
                        'updated_at' => $now,
                        'id' => $selectedId,
                        'before_status' => $beforeStatus,
                    ]);
                    if ($stmt->rowCount() < 1) {
                        $batchFailureMessage = '선택한 콘텐츠 그룹 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    $changedCount++;
                }
                $pdo->commit();
                if ($changedCount > 0) {
                    sr_content_sidebar_clear_group_menu_cache();
                }

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'content_group.bulk_status_updated',
                    'target_type' => 'content_group',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Content group statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $_SESSION['sr_content_group_admin_notice'] = '콘텐츠 그룹 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'content_status') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $_SESSION['sr_content_group_admin_notice'] .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $errors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'content_group_batch_status_failed');
                    $errors[] = '콘텐츠 그룹 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }

        if ($errors !== []) {
            $_SESSION['sr_content_group_admin_errors'] = $errors;
        }
        sr_redirect('/admin/content-groups');
    }

    if (!in_array($intent, ['create_group', 'update_group'], true)) {
        $errors[] = '요청한 작업이 올바르지 않습니다.';
    }

    $existing = $isUpdate ? sr_content_group_by_id($pdo, $groupId) : null;
    if ($isUpdate && !is_array($existing)) {
        $errors[] = '수정할 콘텐츠 그룹을 찾을 수 없습니다.';
    }

    $groupKey = $isUpdate && is_array($existing) ? (string) ($existing['group_key'] ?? '') : sr_content_clean_slug(sr_post_string('group_key', 60));
    $title = sr_content_clean_single_line(sr_post_string('title', 120), 120);
    $description = sr_content_clean_text(sr_post_string('description', 2000), 2000);
    $status = sr_post_string('status', 30);
    $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
    if (!$isUpdate && !sr_content_group_key_is_valid($groupKey)) {
        $errors[] = '그룹 식별값은 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (!$isUpdate && sr_content_group_key_exists($pdo, $groupKey)) {
        $errors[] = '이미 사용 중인 그룹 식별값입니다.';
    }
    if ($title === '') {
        $errors[] = '그룹 이름을 입력하세요.';
    }
    if (!in_array($status, $allowedGroupStatuses, true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    if ($sortOrder === null) {
        $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
        $sortOrder = 0;
    }

    $values = [
        'id' => $groupId,
        'group_key' => $groupKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'sort_order' => (int) $sortOrder,
    ];

    if ($errors !== []) {
        $_SESSION['sr_content_group_admin_errors'] = $errors;
        $_SESSION['sr_content_group_admin_values'] = $values;
        sr_redirect($isUpdate && $groupId > 0 ? '/admin/content-groups/edit?id=' . (string) $groupId : '/admin/content-groups/new');
    }

    if ($isUpdate) {
        sr_content_update_group($pdo, $groupId, $values);
        $savedGroupId = $groupId;
    } else {
        $savedGroupId = sr_content_create_group($pdo, $values);
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => $isUpdate ? 'content_group.updated' : 'content_group.created',
        'target_type' => 'content_group',
        'target_id' => (string) $savedGroupId,
        'result' => 'success',
        'message' => $isUpdate ? 'Content group updated.' : 'Content group created.',
        'metadata' => [
            'group_key' => $groupKey,
            'status' => $status,
        ],
    ]);

    $_SESSION['sr_content_group_admin_notice'] = $isUpdate ? '콘텐츠 그룹을 저장했습니다.' : '콘텐츠 그룹을 만들었습니다.';
    sr_redirect($isUpdate ? '/admin/content-groups/edit?id=' . (string) $savedGroupId : '/admin/content-groups');

}

$pageGroupFilters = sr_content_admin_group_filters();
$pageGroupSort = sr_admin_sort_from_request(sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort());
$pageGroupStatusCounts = sr_content_admin_group_status_counts($pdo);
$pageGroupPagination = sr_admin_pagination_from_total($pdo, $pageGroupsPage === 'list' ? sr_content_admin_group_count($pdo, $pageGroupFilters) : 0);
$pageGroups = $pageGroupsPage === 'list'
    ? sr_content_admin_group_list($pdo, $pageGroupFilters, (int) $pageGroupPagination['per_page'], sr_admin_pagination_offset($pageGroupPagination), $pageGroupSort)
    : [];
$contentStorageCleanupFailures = $pageGroupsPage === 'list' ? sr_content_storage_cleanup_failures($pdo) : [];
$values = is_array($sessionValues) ? $sessionValues : [];
$pageGroupSettings = [];
if ($pageGroupsPage === 'edit' && is_array($editPageGroup)) {
    $pageGroupSettings = sr_content_group_settings($pdo, (int) $editPageGroup['id']);
}

include SR_ROOT . '/modules/content/views/admin-content-groups.php';
