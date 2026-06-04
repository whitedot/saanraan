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
$adminPageSubtitle = '콘텐츠에 연결할 다운로드 파일과 과금 정책을 별도로 관리합니다.';

include SR_ROOT . '/modules/content/views/admin-download-files.php';
