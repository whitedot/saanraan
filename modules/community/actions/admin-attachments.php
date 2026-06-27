<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/attachments', sr_request_method() === 'POST' ? 'edit' : 'view');

if (!function_exists('sr_community_admin_filter_values')) {
    function sr_community_admin_filter_values(string $key, array $allowedValues): array
    {
        $raw = $_GET[$key] ?? [];
        if (!is_array($raw)) {
            $raw = (string) $raw === '' ? [] : [(string) $raw];
        }

        $values = [];
        foreach ($raw as $value) {
            $value = (string) $value;
            if (in_array($value, $allowedValues, true)) {
                $values[$value] = $value;
            }
        }

        return array_values($values);
    }
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $targetStatus = sr_post_string('target_status', 30);
    $rawSelectedIds = $_POST['selected_attachment_ids'] ?? [];
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
    $errors = [];

    if (!in_array($targetStatus, ['active', 'hidden'], true)) {
        $errors[] = '변경할 첨부파일 상태가 올바르지 않습니다.';
    }
    if ($selectedIds === []) {
        $errors[] = '상태를 변경할 첨부파일을 선택하세요.';
    }
    if (count($selectedIds) > 100) {
        $errors[] = '첨부파일 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $result = sr_community_admin_set_attachment_status($pdo, $selectedIds, $targetStatus);
            $pdo->commit();

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community_attachment.bulk_status_updated',
                'target_type' => 'community_attachment',
                'target_id' => '',
                'result' => 'success',
                'message' => 'Community attachment statuses updated in bulk.',
                'metadata' => [
                    'target_status' => $targetStatus,
                    'requested_count' => count($selectedIds),
                    'changed_count' => (int) ($result['changed'] ?? 0),
                    'skipped_count' => (int) ($result['skipped'] ?? 0),
                    'selected_ids' => $selectedIds,
                ],
            ]);

            $statusLabel = $targetStatus === 'active' ? '사용' : '숨김';
            sr_admin_flash_result(sr_admin_action_result([], '첨부파일 ' . number_format((int) ($result['changed'] ?? 0)) . '건의 상태를 ' . $statusLabel . '(으)로 변경했습니다.'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sr_log_exception($exception, 'community_attachment_batch_status_failed');
            sr_admin_flash_result(sr_admin_action_result(['첨부파일 상태 일괄 변경 중 오류가 발생했습니다.'], ''));
        }
    } else {
        sr_admin_flash_result(sr_admin_action_result($errors, ''));
    }

    sr_redirect('/admin/community/attachments');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$filters = [
    'status' => sr_community_admin_filter_values('status', ['active', 'hidden']),
    'board_id' => (int) sr_get_string('board_id', 20),
    'post_id' => (int) sr_get_string('post_id', 20),
    'q' => sr_get_string('q', 120),
];
$attachmentSortOptions = sr_community_admin_attachment_sort_options();
$attachmentDefaultSort = sr_community_admin_attachment_default_sort();
$attachmentSort = sr_admin_sort_from_request($attachmentSortOptions, $attachmentDefaultSort);
$attachmentStatusCounts = sr_community_admin_attachment_status_counts($pdo);
$attachmentPagination = sr_admin_pagination_from_total($pdo, sr_community_admin_attachment_count($pdo, $filters));
$attachments = sr_community_admin_attachments($pdo, $filters, (int) $attachmentPagination['per_page'], sr_admin_pagination_offset($attachmentPagination), $attachmentSort);
$boards = sr_community_boards($pdo);

$adminPageTitle = '첨부파일 관리';
$adminPageSubtitle = '';

include SR_ROOT . '/modules/community/views/admin-attachments.php';
