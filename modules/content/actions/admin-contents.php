<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
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
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'view');

$notice = $_SESSION['sr_content_admin_notice'] ?? '';
unset($_SESSION['sr_content_admin_notice']);
$errors = [];
$pageAdminPage = isset($pageAdminPage) ? (string) $pageAdminPage : 'list';
if (!in_array($pageAdminPage, ['list', 'form'], true)) {
    $pageAdminPage = 'list';
}
if (sr_request_method() === 'GET' && $pageAdminPage === 'form') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');

    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 40);
    if ($intent !== 'batch_status') {
        $errors[] = '허용되지 않은 콘텐츠 일괄 작업입니다.';
    }

    $operationKey = sr_post_string('operation_key', 80);
    $targetStatus = sr_post_string('target_status', 30);
    $rawSelectedIds = $_POST['selected_content_ids'] ?? [];
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

    if ($operationKey !== 'content.set_status') {
        $errors[] = '허용되지 않은 콘텐츠 일괄 작업입니다.';
    }
    if (!in_array($targetStatus, ['draft', 'published', 'hidden'], true)) {
        $errors[] = '변경할 콘텐츠 상태가 올바르지 않습니다.';
    }
    if ($selectedIds === []) {
        $errors[] = '상태를 변경할 콘텐츠를 선택하세요.';
    }
    if (count($selectedIds) > 100) {
        $errors[] = '콘텐츠 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
    }

    $selectedContents = [];
    if ($errors === []) {
        $placeholders = [];
        $params = [];
        foreach ($selectedIds as $index => $selectedId) {
            $paramKey = 'content_id_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $selectedId;
        }
        $stmt = $pdo->prepare(
            'SELECT id, slug, status, published_at
             FROM sr_content_items
             WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        foreach ($params as $paramKey => $selectedId) {
            $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $selectedContents[(int) $row['id']] = $row;
        }
        if (count($selectedContents) !== count($selectedIds)) {
            $errors[] = '선택한 콘텐츠 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
        }
        foreach ($selectedContents as $selectedContent) {
            if ((string) ($selectedContent['status'] ?? '') === 'deleted') {
                $errors[] = sr_t('content::redaction.deleted_content_restore_forbidden');
                break;
            }
        }
    }

    if ($errors === [] && $selectedContents !== []) {
        $changedCount = 0;
        $skippedCount = 0;
        $batchFailureMessage = '';
        $now = sr_now();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'UPDATE sr_content_items
                 SET status = :status,
                     published_at = CASE
                         WHEN :status_for_publish = \'published\' THEN COALESCE(published_at, :published_at)
                         ELSE NULL
                     END,
                     updated_by = :updated_by,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND status = :before_status'
            );
            foreach ($selectedIds as $selectedId) {
                $content = $selectedContents[$selectedId];
                $beforeStatus = (string) $content['status'];
                if ($beforeStatus === $targetStatus) {
                    $skippedCount++;
                    continue;
                }
                $stmt->execute([
                    'status' => $targetStatus,
                    'status_for_publish' => $targetStatus,
                    'published_at' => $now,
                    'updated_by' => (int) $account['id'],
                    'updated_at' => $now,
                    'id' => $selectedId,
                    'before_status' => $beforeStatus,
                ]);
                if ($stmt->rowCount() < 1) {
                    $batchFailureMessage = '선택한 콘텐츠 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                    throw new RuntimeException($batchFailureMessage);
                }
                $changedCount++;
            }
            $pdo->commit();

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'content.bulk_status_updated',
                'target_type' => 'content',
                'target_id' => '',
                'result' => 'success',
                'message' => 'Content statuses updated in bulk.',
                'metadata' => [
                    'operation_key' => $operationKey,
                    'target_status' => $targetStatus,
                    'requested_count' => count($selectedIds),
                    'changed_count' => $changedCount,
                    'skipped_count' => $skippedCount,
                    'selected_ids' => $selectedIds,
                ],
            ]);

            $notice = '콘텐츠 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'content_status') . '(으)로 변경했습니다.';
            if ($skippedCount > 0) {
                $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($batchFailureMessage !== '') {
                $errors[] = $batchFailureMessage;
            } else {
                sr_log_exception($exception, 'content_batch_status_failed');
                $errors[] = '콘텐츠 상태 일괄 변경 중 오류가 발생했습니다.';
            }
        }
    }

    $_SESSION['sr_content_admin_errors'] = $errors;
    $_SESSION['sr_content_admin_notice'] = $notice;
    sr_redirect('/admin/content');
}

$editPage = null;
$contentFiles = [];
$downloadFiles = [];
$linkedDownloadFileIds = [];
$values = [];
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$assetModuleOptions = sr_content_asset_module_options($pdo);
$assetPolicySets = sr_content_asset_policy_sets($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);
$reactionPresetOptions = function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$pageGroups = sr_content_groups($pdo);
$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];
$contentSeriesOptions = sr_content_series_list($pdo);
$currentContentSeriesItem = null;
$pageGroupIds = [];
foreach ($pageGroups as $pageGroup) {
    $pageGroupIds[(int) ($pageGroup['id'] ?? 0)] = true;
}

if ($pageAdminPage === 'form') {
    $downloadFiles = sr_content_all_active_download_files($pdo);
    $pageId = (int) sr_get_string('id', 20);
    if ($pageId > 0) {
        $editPage = sr_content_by_id($pdo, $pageId);
        if (!is_array($editPage)) {
            sr_render_error(404, sr_t('content::action.error.content_edit_not_found'));
        }
        $editPage['setting_sources'] = sr_content_setting_sources($pdo, $pageId);
        $contentFiles = sr_content_files_for_content($pdo, $pageId);
        $linkedDownloadFileIds = sr_content_linked_file_ids($pdo, $pageId);
        $currentContentSeriesItem = sr_content_active_series_item_for_content($pdo, $pageId);
    } else {
        $newContentGroupValue = sr_get_string('content_group_id', 20);
        $newContentGroupId = preg_match('/\A[1-9][0-9]*\z/', $newContentGroupValue) === 1 ? (int) $newContentGroupValue : 0;
        if ($newContentGroupId > 0 && !isset($pageGroupIds[$newContentGroupId])) {
            $newContentGroupId = 0;
        }

        $values = sr_content_default_values($pdo, $site ?? null);
        $values['content_group_id'] = $newContentGroupId;
    }
} else {
    $filters = sr_content_admin_filters();
    if ((int) ($filters['content_group_id'] ?? 0) > 0 && !is_array(sr_content_group_by_id($pdo, (int) $filters['content_group_id']))) {
        $filters['content_group_id'] = 0;
    }
    $contentSort = sr_content_admin_sort_from_request();
    $pageStatusCounts = sr_content_admin_status_counts($pdo);
    $pagePagination = sr_admin_pagination_from_total($pdo, sr_content_admin_count($pdo, $filters));
    $pages = sr_content_admin_list($pdo, $filters, (int) $pagePagination['per_page'], sr_admin_pagination_offset($pagePagination), $contentSort);
}

include SR_ROOT . '/modules/content/views/admin-contents.php';
