<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$communityPostsPage = isset($communityPostsPage) ? (string) $communityPostsPage : 'posts';
$communityPostsPermissionPath = $communityPostsPage === 'comments' ? '/admin/community/comments' : '/admin/community/posts';
sr_admin_require_permission($pdo, (int) $account['id'], $communityPostsPermissionPath, 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$allowedPostStatuses = sr_community_post_statuses();
$allowedCommentStatuses = sr_community_comment_statuses();
$settings = sr_community_settings($pdo);
$memberSettings = sr_member_settings($pdo);
$postBoardOptionsStmt = $pdo->query(
    'SELECT b.id, b.board_key, b.title, g.title AS board_group_title
     FROM sr_community_boards b
     LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
     ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC'
);
$postBoardOptions = $postBoardOptionsStmt->fetchAll();
$postBoardIds = [];
foreach ($postBoardOptions as $postBoardOption) {
    $postBoardIds[(int) $postBoardOption['id']] = true;
}
$postBoardFilterValue = sr_get_string('board_id', 20);
$postBoardFilterId = preg_match('/\A[1-9][0-9]*\z/', $postBoardFilterValue) === 1 ? (int) $postBoardFilterValue : 0;
if ($postBoardFilterId > 0 && !isset($postBoardIds[$postBoardFilterId])) {
    $postBoardFilterId = 0;
}
$postCategoryOptions = $postBoardFilterId > 0 ? sr_community_categories($pdo, $postBoardFilterId, false) : [];
$postCategoryIds = [];
foreach ($postCategoryOptions as $postCategoryOption) {
    $postCategoryIds[(int) $postCategoryOption['id']] = true;
}
$postCategoryFilterValue = sr_get_string('category_id', 20);
$postCategoryFilterId = preg_match('/\A[1-9][0-9]*\z/', $postCategoryFilterValue) === 1 ? (int) $postCategoryFilterValue : 0;
if ($postCategoryFilterId > 0 && !isset($postCategoryIds[$postCategoryFilterId])) {
    $postCategoryFilterId = 0;
}
$postListFilters = [
    'status' => sr_admin_get_allowed_array('status', $allowedPostStatuses, 30),
    'board_id' => $postBoardFilterId,
    'category_id' => $postCategoryFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
    'extra_values_supported' => sr_community_post_extra_values_column_exists($pdo),
    'extra_field_values_supported' => sr_community_post_field_values_table_exists($pdo),
];
$postSearchFields = ['all', 'title', 'author', 'board'];
if (!empty($postListFilters['extra_values_supported'])) {
    $postSearchFields[] = 'extra';
}
if (!in_array($postListFilters['field'], $postSearchFields, true)) {
    $postListFilters['field'] = 'all';
}
$commentListFilters = [
    'status' => sr_admin_get_allowed_array('status', $allowedCommentStatuses, 30),
    'board_id' => $postBoardFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if (!in_array($commentListFilters['field'], ['all', 'body', 'author', 'post', 'board'], true)) {
    $commentListFilters['field'] = 'all';
}

$communityHiddenOptionsFromPost = static function (int $accountId): array {
    $duration = sr_post_string('hidden_duration', 20);
    $allowedDurations = ['7', '15', '30', '90', 'permanent'];
    if (!in_array($duration, $allowedDurations, true)) {
        $duration = '30';
    }

    $hiddenUntil = null;
    if ($duration !== 'permanent') {
        $date = new DateTimeImmutable('now');
        $hiddenUntil = $date->modify('+' . $duration . ' days')->format('Y-m-d H:i:s');
    }

    $reason = sr_post_string('hidden_reason', 40);
    if (!in_array($reason, ['rights_request', 'moderation', 'spam', 'policy', 'other'], true)) {
        $reason = 'moderation';
    }

    return [
        'hidden_until' => $hiddenUntil,
        'hidden_reason' => $reason,
        'hidden_note' => trim(sr_post_string('hidden_note', 1000)),
        'hidden_by_account_id' => $accountId,
    ];
};

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $status = sr_post_string('status', 30);
    sr_admin_require_permission($pdo, (int) $account['id'], $communityPostsPermissionPath, $status === 'deleted' ? 'delete' : 'edit');

    if ($intent === 'post_status') {
        $postIdValue = sr_post_string('post_id', 20);
        $postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
        $post = sr_community_admin_post_by_id($pdo, $postId);

        if (!is_array($post)) {
            $errors[] = sr_t('community::action.error.post_not_found');
        }

        if (!in_array($status, $allowedPostStatuses, true)) {
            $errors[] = sr_t('community::action.admin.post_status_invalid');
        }
        if ($errors === [] && is_array($post) && (string) $post['status'] === 'deleted' && $status !== 'deleted') {
            $errors[] = sr_t('community::redaction.deleted_post_restore_forbidden');
        }

        if ($errors === [] && is_array($post)) {
            $recoveryResult = ['recovery_status' => 'not_needed'];
            $postAttachmentStorageRefs = $status === 'deleted' ? sr_community_post_attachment_storage_refs($pdo, $postId) : [];
            try {
                $pdo->beginTransaction();
                if (!empty($settings['post_reward_reversal_enabled']) && in_array($status, ['hidden', 'deleted'], true) && (string) $post['status'] === 'published') {
                    $recoveryResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) $post['author_account_id'], 'post_reward', 'community.post', $postId, 'post_reward_reversal', 'community.post.reward_reversal', 'community.post.reward_reversal', [
                        'operation_event_key' => 'community.post.status_updated',
                        'before_status' => (string) $post['status'],
                        'after_status' => $status,
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'route_context' => 'admin.community.posts',
                    ]);
                    if (empty($recoveryResult['operation_allowed'])) {
                        throw new RuntimeException(sr_community_asset_reversal_error_message($recoveryResult, 'community::action.admin.post_reward_reversal_balance_low', 'community::action.admin.post_reward_reversal_status_failed'));
                    }
                }
                if ($status === 'hidden') {
                    sr_community_update_post_status($pdo, $postId, $status, $communityHiddenOptionsFromPost((int) $account['id']));
                } elseif ($status === 'deleted') {
                    sr_community_update_post_status($pdo, $postId, $status, ['defer_file_cleanup' => true]);
                } else {
                    sr_community_update_post_status($pdo, $postId, $status);
                }
                $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_status_updated');
                $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
                    'source_module_key' => 'community',
                ]);
                $updatedAttachmentCount = 0;
                if (in_array($status, ['hidden', 'deleted'], true)) {
                    // Release check contract: sr_community_update_post_attachments_status($pdo, $postId, $status)
                    $updatedAttachmentCount = sr_community_update_post_attachments_status($pdo, $postId, $status, $status !== 'deleted');
                } elseif ($status === 'published' && (string) $post['status'] === 'hidden') {
                    $updatedAttachmentCount = sr_community_restore_hidden_post_attachments($pdo, $postId);
                }
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.post.status_updated',
                    'target_type' => 'community_post',
                    'target_id' => (string) $postId,
                    'result' => 'success',
                    'message' => 'Community post status updated.',
                    'metadata' => array_merge([
                        'before_status' => (string) $post['status'],
                        'after_status' => $status,
                        'updated_attachment_count' => $updatedAttachmentCount,
                        'recovery_status' => (string) ($recoveryResult['recovery_status'] ?? 'not_needed'),
                        'recovery_failure_id' => (int) ($recoveryResult['recovery_failure_id'] ?? 0),
                        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
                    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
                ]);
                $pdo->commit();
                if ($status === 'deleted') {
                    sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
                    sr_community_cleanup_attachment_storage_refs($pdo, $postAttachmentStorageRefs);
                }
                $notice = sr_t('community::action.admin.post_status_updated');
                if ((string) ($recoveryResult['recovery_status'] ?? '') === 'unrecovered') {
                    $notice .= ' 보상 회수 미완료 기록을 남겼습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage() !== '' ? $exception->getMessage() : sr_t('community::action.admin.post_reward_reversal_status_failed');
            }
        }
    } elseif ($intent === 'batch_post_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_post_ids'] ?? [];
        $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);

        if ($communityPostsPage !== 'posts') {
            $errors[] = sr_t('community::action.error.intent_invalid');
        }
        if ($operationKey !== 'community.post_set_status') {
            $errors[] = '허용되지 않은 게시글 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['published', 'hidden'], true) || !in_array($targetStatus, $allowedPostStatuses, true)) {
            $errors[] = '변경할 게시글 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 게시글을 선택하세요.';
        }
        if ($hasInvalidSelectedId) {
            $errors[] = '선택한 게시글 ID 값이 올바르지 않습니다.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '게시글 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedPosts = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'post_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT p.id, p.author_account_id, p.status
                 FROM sr_community_posts p
                 WHERE p.id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedPosts[(int) $row['id']] = $row;
            }
            if (count($selectedPosts) !== count($selectedIds)) {
                $errors[] = '선택한 게시글 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($errors === [] && $selectedPosts !== []) {
            foreach ($selectedIds as $selectedId) {
                $currentStatus = (string) ($selectedPosts[$selectedId]['status'] ?? '');
                $allowedTransition = $targetStatus === 'hidden'
                    ? in_array($currentStatus, ['published', 'hidden'], true)
                    : in_array($currentStatus, ['hidden', 'published'], true);
                if (!$allowedTransition) {
                    $errors[] = '선택한 게시글 중 숨김/복구할 수 없는 상태가 있습니다. 공개 또는 숨김 상태의 게시글만 선택하세요.';
                    break;
                }
            }
        }

        if ($errors === [] && $selectedPosts !== []) {
            $changedCount = 0;
            $skippedCount = 0;
            $updatedAttachmentCount = 0;
            $affectedAccountIds = [];
            $recoveryCounts = ['completed' => 0, 'not_needed' => 0, 'unrecovered' => 0, 'failed' => 0];
            $batchFailureMessage = '';
            try {
                $pdo->beginTransaction();
                $postHiddenColumnsExist = sr_community_hidden_columns_exist($pdo, 'sr_community_posts');
                $postBatchHiddenOptions = $communityHiddenOptionsFromPost((int) $account['id']);
                if ($postHiddenColumnsExist && $targetStatus === 'hidden') {
                    $updatePostStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_posts
                         SET status = :status,
                             hidden_at = :hidden_at,
                             hidden_until = :hidden_until,
                             hidden_reason = :hidden_reason,
                             hidden_note = :hidden_note,
                             hidden_by_account_id = :hidden_by_account_id,
                             hidden_before_status = CASE WHEN status <> \'hidden\' THEN status ELSE hidden_before_status END,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                } elseif ($postHiddenColumnsExist && $targetStatus === 'published') {
                    $updatePostStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_posts
                         SET status = :status,
                             hidden_at = NULL,
                             hidden_until = NULL,
                             hidden_reason = \'\',
                             hidden_note = NULL,
                             hidden_by_account_id = NULL,
                             hidden_before_status = \'\',
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                } else {
                    $updatePostStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_posts
                         SET status = :status,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                }
                foreach ($selectedIds as $selectedId) {
                    $post = $selectedPosts[$selectedId];
                    $beforeStatus = (string) $post['status'];
                    if ($beforeStatus === $targetStatus) {
                        $skippedCount++;
                        continue;
                    }

                    if (!empty($settings['post_reward_reversal_enabled']) && $targetStatus === 'hidden' && $beforeStatus === 'published') {
                        $reversalResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) $post['author_account_id'], 'post_reward', 'community.post', $selectedId, 'post_reward_reversal', 'community.post.reward_reversal', 'community.post.reward_reversal', [
                            'operation_event_key' => 'community.post.bulk_status_updated',
                            'batch_operation_key' => $operationKey,
                            'before_status' => $beforeStatus,
                            'after_status' => $targetStatus,
                            'actor_account_id' => (int) $account['id'],
                            'actor_type' => 'admin',
                            'route_context' => 'admin.community.posts',
                        ]);
                        $recoveryStatus = (string) ($reversalResult['recovery_status'] ?? 'failed');
                        if (isset($recoveryCounts[$recoveryStatus])) {
                            $recoveryCounts[$recoveryStatus]++;
                        }
                        if (empty($reversalResult['operation_allowed'])) {
                            $recoveryCounts['failed']++;
                            $batchFailureMessage = sr_community_asset_reversal_error_message($reversalResult, 'community::action.admin.post_reward_reversal_balance_low', 'community::action.admin.post_reward_reversal_status_failed');
                            throw new RuntimeException($batchFailureMessage);
                        }
                    } else {
                        $recoveryCounts['not_needed']++;
                    }

                    $postStatusParams = [
                        'status' => $targetStatus,
                        'updated_at' => sr_now(),
                        'id' => $selectedId,
                        'before_status' => $beforeStatus,
                    ];
                    if ($postHiddenColumnsExist && $targetStatus === 'hidden') {
                        $postStatusParams['hidden_at'] = sr_now();
                        $postStatusParams['hidden_until'] = $postBatchHiddenOptions['hidden_until'];
                        $postStatusParams['hidden_reason'] = $postBatchHiddenOptions['hidden_reason'];
                        $postStatusParams['hidden_note'] = $postBatchHiddenOptions['hidden_note'];
                        $postStatusParams['hidden_by_account_id'] = $postBatchHiddenOptions['hidden_by_account_id'];
                    }
                    $updatePostStatusStmt->execute($postStatusParams);
                    if ($updatePostStatusStmt->rowCount() < 1) {
                        $batchFailureMessage = '선택한 게시글 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    $affectedAccountIds[(int) $post['author_account_id']] = (int) $post['author_account_id'];
                    if ($targetStatus === 'hidden') {
                        $updatedAttachmentCount += sr_community_update_post_attachments_status($pdo, $selectedId, $targetStatus);
                    } elseif ($targetStatus === 'published' && $beforeStatus === 'hidden') {
                        $updatedAttachmentCount += sr_community_restore_hidden_post_attachments($pdo, $selectedId);
                    }
                    $changedCount++;
                }
                foreach ($affectedAccountIds as $affectedAccountId) {
                    sr_community_maybe_recalculate_account_level($pdo, $affectedAccountId, null, 'post_status_updated');
                    sr_member_group_evaluate_account($pdo, $affectedAccountId, [
                        'source_module_key' => 'community',
                    ]);
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.post.bulk_status_updated',
                    'target_type' => 'community_post',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Community post statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'updated_attachment_count' => $updatedAttachmentCount,
                        'affected_account_count' => count($affectedAccountIds),
                        'recovery_completed_count' => $recoveryCounts['completed'],
                        'recovery_not_needed_count' => $recoveryCounts['not_needed'],
                        'recovery_unrecovered_count' => $recoveryCounts['unrecovered'],
                        'recovery_failed_count' => $recoveryCounts['failed'],
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '게시글 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'content_status') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
                if ($recoveryCounts['unrecovered'] > 0) {
                    $notice .= ' 보상 회수 미완료 ' . number_format($recoveryCounts['unrecovered']) . '건을 기록했습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $errors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'community_post_batch_status_failed');
                    $errors[] = '게시글 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }
    } elseif ($intent === 'batch_comment_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_comment_ids'] ?? [];
        $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);

        if ($communityPostsPage !== 'comments') {
            $errors[] = sr_t('community::action.error.intent_invalid');
        }
        if ($operationKey !== 'community.comment_set_status') {
            $errors[] = '허용되지 않은 댓글 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['published', 'hidden'], true) || !in_array($targetStatus, $allowedCommentStatuses, true)) {
            $errors[] = '변경할 댓글 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 댓글을 선택하세요.';
        }
        if ($hasInvalidSelectedId) {
            $errors[] = '선택한 댓글 ID 값이 올바르지 않습니다.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '댓글 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedComments = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'comment_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT c.id, c.post_id, c.author_account_id, c.status
                 FROM sr_community_comments c
                 WHERE c.id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedComments[(int) $row['id']] = $row;
            }
            if (count($selectedComments) !== count($selectedIds)) {
                $errors[] = '선택한 댓글 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($errors === [] && $selectedComments !== []) {
            foreach ($selectedIds as $selectedId) {
                $currentStatus = (string) ($selectedComments[$selectedId]['status'] ?? '');
                $allowedTransition = $targetStatus === 'hidden'
                    ? in_array($currentStatus, ['published', 'hidden'], true)
                    : in_array($currentStatus, ['hidden', 'published'], true);
                if (!$allowedTransition) {
                    $errors[] = '선택한 댓글 중 숨김/복구할 수 없는 상태가 있습니다. 공개 또는 숨김 상태의 댓글만 선택하세요.';
                    break;
                }
            }
        }

        if ($errors === [] && $selectedComments !== []) {
            $changedCount = 0;
            $skippedCount = 0;
            $affectedAccountIds = [];
            $recoveryCounts = ['completed' => 0, 'not_needed' => 0, 'unrecovered' => 0, 'failed' => 0];
            $batchFailureMessage = '';
            try {
                $pdo->beginTransaction();
                $commentHiddenColumnsExist = sr_community_hidden_columns_exist($pdo, 'sr_community_comments');
                $commentBatchHiddenOptions = $communityHiddenOptionsFromPost((int) $account['id']);
                if ($commentHiddenColumnsExist && $targetStatus === 'hidden') {
                    $updateCommentStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_comments
                         SET status = :status,
                             hidden_at = :hidden_at,
                             hidden_until = :hidden_until,
                             hidden_reason = :hidden_reason,
                             hidden_note = :hidden_note,
                             hidden_by_account_id = :hidden_by_account_id,
                             hidden_before_status = CASE WHEN status <> \'hidden\' THEN status ELSE hidden_before_status END,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                } elseif ($commentHiddenColumnsExist && $targetStatus === 'published') {
                    $updateCommentStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_comments
                         SET status = :status,
                             hidden_at = NULL,
                             hidden_until = NULL,
                             hidden_reason = \'\',
                             hidden_note = NULL,
                             hidden_by_account_id = NULL,
                             hidden_before_status = \'\',
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                } else {
                    $updateCommentStatusStmt = $pdo->prepare(
                        'UPDATE sr_community_comments
                         SET status = :status,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status = :before_status'
                    );
                }
                foreach ($selectedIds as $selectedId) {
                    $comment = $selectedComments[$selectedId];
                    $beforeStatus = (string) $comment['status'];
                    if ($beforeStatus === $targetStatus) {
                        $skippedCount++;
                        continue;
                    }

                    if (!empty($settings['comment_reward_reversal_enabled']) && $targetStatus === 'hidden' && $beforeStatus === 'published') {
                        $reversalResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) $comment['author_account_id'], 'comment_reward', 'community.comment', $selectedId, 'comment_reward_reversal', 'community.comment.reward_reversal', 'community.comment.reward_reversal', [
                            'operation_event_key' => 'community.comment.bulk_status_updated',
                            'batch_operation_key' => $operationKey,
                            'before_status' => $beforeStatus,
                            'after_status' => $targetStatus,
                            'actor_account_id' => (int) $account['id'],
                            'actor_type' => 'admin',
                            'route_context' => 'admin.community.comments',
                        ]);
                        $recoveryStatus = (string) ($reversalResult['recovery_status'] ?? 'failed');
                        if (isset($recoveryCounts[$recoveryStatus])) {
                            $recoveryCounts[$recoveryStatus]++;
                        }
                        if (empty($reversalResult['operation_allowed'])) {
                            $recoveryCounts['failed']++;
                            $batchFailureMessage = sr_community_asset_reversal_error_message($reversalResult, 'community::action.admin.comment_reward_reversal_balance_low', 'community::action.admin.comment_reward_reversal_status_failed');
                            throw new RuntimeException($batchFailureMessage);
                        }
                    } else {
                        $recoveryCounts['not_needed']++;
                    }

                    $commentStatusParams = [
                        'status' => $targetStatus,
                        'updated_at' => sr_now(),
                        'id' => $selectedId,
                        'before_status' => $beforeStatus,
                    ];
                    if ($commentHiddenColumnsExist && $targetStatus === 'hidden') {
                        $commentStatusParams['hidden_at'] = sr_now();
                        $commentStatusParams['hidden_until'] = $commentBatchHiddenOptions['hidden_until'];
                        $commentStatusParams['hidden_reason'] = $commentBatchHiddenOptions['hidden_reason'];
                        $commentStatusParams['hidden_note'] = $commentBatchHiddenOptions['hidden_note'];
                        $commentStatusParams['hidden_by_account_id'] = $commentBatchHiddenOptions['hidden_by_account_id'];
                    }
                    $updateCommentStatusStmt->execute($commentStatusParams);
                    if ($updateCommentStatusStmt->rowCount() < 1) {
                        $batchFailureMessage = '선택한 댓글 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    $affectedAccountIds[(int) $comment['author_account_id']] = (int) $comment['author_account_id'];
                    $changedCount++;
                }
                foreach ($affectedAccountIds as $affectedAccountId) {
                    sr_community_maybe_recalculate_account_level($pdo, $affectedAccountId, null, 'comment_status_updated');
                    sr_member_group_evaluate_account($pdo, $affectedAccountId, [
                        'source_module_key' => 'community',
                    ]);
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.comment.bulk_status_updated',
                    'target_type' => 'community_comment',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Community comment statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'affected_account_count' => count($affectedAccountIds),
                        'recovery_completed_count' => $recoveryCounts['completed'],
                        'recovery_not_needed_count' => $recoveryCounts['not_needed'],
                        'recovery_unrecovered_count' => $recoveryCounts['unrecovered'],
                        'recovery_failed_count' => $recoveryCounts['failed'],
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '댓글 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'content_status') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
                if ($recoveryCounts['unrecovered'] > 0) {
                    $notice .= ' 보상 회수 미완료 ' . number_format($recoveryCounts['unrecovered']) . '건을 기록했습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $errors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'community_comment_batch_status_failed');
                    $errors[] = '댓글 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }
    } elseif ($intent === 'comment_status') {
        $commentIdValue = sr_post_string('comment_id', 20);
        $commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
        $comment = sr_community_admin_comment_by_id($pdo, $commentId);

        if (!is_array($comment)) {
            $errors[] = sr_t('community::action.error.comment_not_found');
        }

        if (!in_array($status, $allowedCommentStatuses, true)) {
            $errors[] = sr_t('community::action.admin.comment_status_invalid');
        }
        if ($errors === [] && is_array($comment) && (string) $comment['status'] === 'deleted' && $status !== 'deleted') {
            $errors[] = sr_t('community::redaction.deleted_comment_restore_forbidden');
        }

        if ($errors === [] && is_array($comment)) {
            $recoveryResult = ['recovery_status' => 'not_needed'];
            try {
                $pdo->beginTransaction();
                if (!empty($settings['comment_reward_reversal_enabled']) && in_array($status, ['hidden', 'deleted'], true) && (string) $comment['status'] === 'published') {
                    $recoveryResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) $comment['author_account_id'], 'comment_reward', 'community.comment', $commentId, 'comment_reward_reversal', 'community.comment.reward_reversal', 'community.comment.reward_reversal', [
                        'operation_event_key' => 'community.comment.status_updated',
                        'before_status' => (string) $comment['status'],
                        'after_status' => $status,
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'route_context' => 'admin.community.comments',
                    ]);
                    if (empty($recoveryResult['operation_allowed'])) {
                        throw new RuntimeException(sr_community_asset_reversal_error_message($recoveryResult, 'community::action.admin.comment_reward_reversal_balance_low', 'community::action.admin.comment_reward_reversal_status_failed'));
                    }
                }
                if ($status === 'hidden') {
                    sr_community_update_comment_status($pdo, $commentId, $status, $communityHiddenOptionsFromPost((int) $account['id']));
                } else {
                    sr_community_update_comment_status($pdo, $commentId, $status);
                }
                $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $comment['author_account_id'], null, 'comment_status_updated');
                $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $comment['author_account_id'], [
                    'source_module_key' => 'community',
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.comment.status_updated',
                    'target_type' => 'community_comment',
                    'target_id' => (string) $commentId,
                    'result' => 'success',
                    'message' => 'Community comment status updated.',
                    'metadata' => array_merge([
                        'before_status' => (string) $comment['status'],
                        'after_status' => $status,
                        'post_id' => (int) $comment['post_id'],
                        'recovery_status' => (string) ($recoveryResult['recovery_status'] ?? 'not_needed'),
                        'recovery_failure_id' => (int) ($recoveryResult['recovery_failure_id'] ?? 0),
                        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
                    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
                ]);
                $pdo->commit();
                $notice = sr_t('community::action.admin.comment_status_updated');
                if ((string) ($recoveryResult['recovery_status'] ?? '') === 'unrecovered') {
                    $notice .= ' 보상 회수 미완료 기록을 남겼습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage() !== '' ? $exception->getMessage() : sr_t('community::action.admin.comment_reward_reversal_status_failed');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $redirectPath = $communityPostsPage === 'comments' ? '/admin/community/comments' : '/admin/community/posts';
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), $redirectPath . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

$postStatusCounts = ['total' => 0];
foreach ($allowedPostStatuses as $status) {
    $postStatusCounts[$status] = 0;
}
$postStatusCountStmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_posts GROUP BY status');
foreach ($postStatusCountStmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $postStatusCounts)) {
        $postStatusCounts[$status] = $count;
    }
    $postStatusCounts['total'] += $count;
}

$postSort = sr_admin_sort_from_request(sr_community_admin_post_sort_options(), sr_community_admin_post_default_sort());
$postPagination = sr_admin_pagination_from_total($pdo, $communityPostsPage === 'posts' ? sr_community_admin_post_count($pdo, $postListFilters) : 0);
$posts = $communityPostsPage === 'posts'
    ? sr_community_admin_posts($pdo, (int) $postPagination['per_page'], $postListFilters, sr_admin_pagination_offset($postPagination), $postSort)
    : [];
$commentStatusCounts = ['total' => 0];
foreach ($allowedCommentStatuses as $status) {
    $commentStatusCounts[$status] = 0;
}
$commentStatusCountStmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_comments GROUP BY status');
foreach ($commentStatusCountStmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $commentStatusCounts)) {
        $commentStatusCounts[$status] = $count;
    }
    $commentStatusCounts['total'] += $count;
}

$commentSort = sr_admin_sort_from_request(sr_community_admin_comment_sort_options(), sr_community_admin_comment_default_sort());
$commentPagination = sr_admin_pagination_from_total($pdo, $communityPostsPage === 'comments' ? sr_community_admin_comment_count($pdo, $commentListFilters) : 0);
$comments = $communityPostsPage === 'comments'
    ? sr_community_admin_comments($pdo, (int) $commentPagination['per_page'], $commentListFilters, sr_admin_pagination_offset($commentPagination), $commentSort)
    : [];

include SR_ROOT . '/modules/community/views/admin-posts.php';
