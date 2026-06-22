<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/files', sr_request_method() === 'POST' ? 'edit' : 'view');

$notice = $_SESSION['sr_content_file_admin_notice'] ?? '';
$errors = $_SESSION['sr_content_file_admin_errors'] ?? [];
$values = $_SESSION['sr_content_file_admin_values'] ?? [];
unset($_SESSION['sr_content_file_admin_notice'], $_SESSION['sr_content_file_admin_errors'], $_SESSION['sr_content_file_admin_values']);
if (!is_array($errors)) {
    $errors = [];
}
if (!is_array($values)) {
    $values = [];
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    if ($intent === 'batch_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_file_ids'] ?? [];
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
        $batchErrors = [];

        if ($operationKey !== 'content.file_set_status') {
            $batchErrors[] = '허용되지 않은 다운로드 파일 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['active', 'hidden'], true)) {
            $batchErrors[] = '변경할 다운로드 파일 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $batchErrors[] = '상태를 변경할 다운로드 파일을 선택하세요.';
        }
        if (count($selectedIds) > 100) {
            $batchErrors[] = '다운로드 파일 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedFiles = [];
        if ($batchErrors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'file_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id, status
                 FROM sr_content_files
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedFiles[(int) $row['id']] = $row;
            }
            if (count($selectedFiles) !== count($selectedIds)) {
                $batchErrors[] = '선택한 다운로드 파일 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($batchErrors === [] && $selectedFiles !== []) {
            $changedCount = 0;
            $skippedCount = 0;
            $batchFailureMessage = '';
            $now = sr_now();
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_content_files
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND status = :before_status'
                );
                foreach ($selectedIds as $selectedId) {
                    $file = $selectedFiles[$selectedId];
                    $beforeStatus = (string) $file['status'];
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
                        $batchFailureMessage = '선택한 다운로드 파일 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    $changedCount++;
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'content_file.bulk_status_updated',
                    'target_type' => 'content_file',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Content download file statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $_SESSION['sr_content_file_admin_notice'] = '다운로드 파일 ' . number_format($changedCount) . '건의 상태를 ' . ($targetStatus === 'active' ? '사용' : '숨김') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $_SESSION['sr_content_file_admin_notice'] .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $batchErrors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'content_download_file_batch_status_failed');
                    $batchErrors[] = '다운로드 파일 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }

        if ($batchErrors !== []) {
            $_SESSION['sr_content_file_admin_errors'] = $batchErrors;
        }
        sr_redirect('/admin/content/files');
    }

    $fileId = (int) sr_post_string('file_id', 20);
    $action = sr_post_string('action', 20);
    $redirectTarget = '/admin/content/files';
    $postedValues = sr_content_new_file_values_from_post($pdo);
    $postedValues['title'] = sr_content_clean_single_line(sr_post_string('title', 160), 160);
    $status = sr_content_clean_slug(sr_post_string('status', 20));
    if (!in_array($status, ['active', 'hidden'], true)) {
        $status = 'active';
    }
    $postedValues['status'] = $status;

    $saveErrors = [];
    if ($action === 'hide') {
        if ($fileId < 1 || !is_array(sr_content_admin_download_file_by_id($pdo, $fileId))) {
            $saveErrors[] = '숨김 처리할 다운로드 파일을 찾을 수 없습니다.';
        }
    } else {
        if ($fileId < 1 && !sr_content_file_upload_was_provided($_FILES['download_file_upload'] ?? null)) {
            $saveErrors[] = '파일을 선택하세요.';
        }
        if ($fileId > 0 && !is_array(sr_content_admin_download_file_by_id($pdo, $fileId))) {
            $saveErrors[] = '수정할 다운로드 파일을 찾을 수 없습니다.';
        }
        if ($fileId > 0 && (string) ($postedValues['title'] ?? '') === '') {
            $existingFile = sr_content_admin_download_file_by_id($pdo, $fileId);
            if (is_array($existingFile)) {
                $postedValues['title'] = (string) ($existingFile['original_name'] ?? '첨부 파일');
            }
        }
        if (sr_content_file_upload_was_provided($_FILES['download_file_upload'] ?? null)) {
            try {
                sr_upload_validate_file($_FILES['download_file_upload'], [
                    'max_bytes' => sr_content_file_upload_max_bytes(),
                    'allowed_extensions' => sr_content_file_allowed_extensions(),
                    'allowed_mime_types' => sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()),
                ]);
            } catch (Throwable $exception) {
                $saveErrors[] = $exception->getMessage();
            }
        }
        $saveErrors = array_merge($saveErrors, sr_content_file_asset_validation_errors($pdo, $postedValues, '다운로드 파일'));
    }

    if ($saveErrors !== []) {
        $_SESSION['sr_content_file_admin_errors'] = $saveErrors;
        $_SESSION['sr_content_file_admin_values'] = $postedValues;
        sr_redirect($fileId > 0 ? '/admin/content/files?id=' . (string) $fileId : '/admin/content/files?new=1');
    }

    if ($action === 'hide') {
        sr_content_hide_file($pdo, $fileId);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'content_file.hidden',
            'target_type' => 'content_file',
            'target_id' => (string) $fileId,
            'result' => 'success',
            'message' => 'Content download file hidden.',
        ]);
        $_SESSION['sr_content_file_admin_notice'] = '다운로드 파일을 숨김 처리했습니다.';
        sr_redirect($redirectTarget);
    }

    try {
        if ($fileId > 0) {
            sr_content_update_file($pdo, $fileId, $postedValues);
            $stmt = $pdo->prepare('UPDATE sr_content_files SET status = :status, updated_at = :updated_at WHERE id = :id');
            $stmt->execute([
                'status' => $status,
                'updated_at' => sr_now(),
                'id' => $fileId,
            ]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'content_file.updated',
                'target_type' => 'content_file',
                'target_id' => (string) $fileId,
                'result' => 'success',
                'message' => 'Content download file updated.',
                'metadata' => [
                    'status' => $status,
                ],
            ]);
            $_SESSION['sr_content_file_admin_notice'] = '다운로드 파일을 저장했습니다.';
        } else {
            $createdFileId = sr_content_upload_file($pdo, 0, (int) $account['id'], $_FILES['download_file_upload'], $postedValues);
            if ($status !== 'active') {
                $stmt = $pdo->prepare('UPDATE sr_content_files SET status = :status, updated_at = :updated_at WHERE id = :id');
                $stmt->execute([
                    'status' => $status,
                    'updated_at' => sr_now(),
                    'id' => $createdFileId,
                ]);
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'content_file.created',
                'target_type' => 'content_file',
                'target_id' => (string) $createdFileId,
                'result' => 'success',
                'message' => 'Content download file created.',
            ]);
            $_SESSION['sr_content_file_admin_notice'] = '다운로드 파일을 등록했습니다.';
        }
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_download_file_save_failed');
        }
        $_SESSION['sr_content_file_admin_errors'] = ['다운로드 파일 저장에 실패했습니다: ' . $exception->getMessage()];
        $_SESSION['sr_content_file_admin_values'] = $postedValues;
        sr_redirect($fileId > 0 ? '/admin/content/files?id=' . (string) $fileId : '/admin/content/files?new=1');
    }

    sr_redirect($redirectTarget);
}

$assetModuleOptions = sr_content_asset_module_options($pdo);
$assetPolicySets = sr_content_asset_policy_sets($pdo);
$fileId = (int) sr_get_string('id', 20);
$editingFile = $fileId > 0 ? sr_content_admin_download_file_by_id($pdo, $fileId) : null;
$showForm = isset($_GET['new']) || is_array($editingFile);
if ($fileId > 0 && !is_array($editingFile)) {
    sr_render_error(404, '수정할 다운로드 파일을 찾을 수 없습니다.');
}

$filters = [
    'status' => sr_content_admin_single_filter_values('status', ['active', 'hidden']),
    'q' => sr_get_string('q', 120),
];
$downloadFileSortOptions = sr_content_admin_download_file_sort_options();
$downloadFileDefaultSort = sr_content_admin_download_file_default_sort();
$downloadFileSort = sr_admin_sort_from_request($downloadFileSortOptions, $downloadFileDefaultSort);
$downloadFileStatusCounts = sr_content_admin_download_file_status_counts($pdo);
$downloadFilePagination = sr_admin_pagination_from_total($pdo, sr_content_admin_download_file_count($pdo, $filters));
$downloadFiles = sr_content_admin_download_files($pdo, $filters, (int) $downloadFilePagination['per_page'], sr_admin_pagination_offset($downloadFilePagination), $downloadFileSort);

$adminPageTitle = $showForm ? (is_array($editingFile) ? '다운로드 파일 수정' : '다운로드 파일 추가') : '다운로드 파일 관리';
$adminPageSubtitle = '';

include SR_ROOT . '/modules/content/views/admin-download-files.php';
