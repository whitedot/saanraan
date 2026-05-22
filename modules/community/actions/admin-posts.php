<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedPostStatuses = sr_community_post_statuses();
$allowedCommentStatuses = sr_community_comment_statuses();
$settings = sr_community_settings($pdo);
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
$postListFilters = [
    'status' => sr_get_string('status', 30),
    'board_id' => $postBoardFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if ($postListFilters['status'] !== '' && !in_array($postListFilters['status'], $allowedPostStatuses, true)) {
    $postListFilters['status'] = '';
}
if (!in_array($postListFilters['field'], ['all', 'title', 'author', 'board'], true)) {
    $postListFilters['field'] = 'all';
}
$commentListFilters = [
    'status' => sr_get_string('status', 30),
    'board_id' => $postBoardFilterId,
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if ($commentListFilters['status'] !== '' && !in_array($commentListFilters['status'], $allowedCommentStatuses, true)) {
    $commentListFilters['status'] = '';
}
if (!in_array($commentListFilters['field'], ['all', 'body', 'author', 'post', 'board'], true)) {
    $commentListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $status = sr_post_string('status', 30);

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

        if ($errors === [] && is_array($post)) {
            if (!empty($settings['post_reward_reversal_enabled']) && in_array($status, ['hidden', 'deleted'], true) && (string) $post['status'] === 'published') {
                $reversalResult = sr_community_reverse_asset_grant($pdo, (int) $post['author_account_id'], 'post_reward', 'community.post', $postId, 'post_reward_reversal', 'community.post.reward_reversal');
                if (empty($reversalResult['allowed'])) {
                    $errors[] = (string) ($reversalResult['message'] ?? sr_t('community::action.admin.post_reward_reversal_status_failed'));
                }
            }

            if ($errors === []) {
                sr_community_update_post_status($pdo, $postId, $status);
                $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_status_updated');
                $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
                    'source_module_key' => 'community',
                ]);
                $updatedAttachmentCount = 0;
                if (in_array($status, ['hidden', 'deleted'], true)) {
                    $updatedAttachmentCount = sr_community_update_post_attachments_status($pdo, $postId, $status);
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
                        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
                    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
                ]);
                $notice = sr_t('community::action.admin.post_status_updated');
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

        if ($errors === [] && is_array($comment)) {
            if (!empty($settings['comment_reward_reversal_enabled']) && in_array($status, ['hidden', 'deleted'], true) && (string) $comment['status'] === 'published') {
                $reversalResult = sr_community_reverse_asset_grant($pdo, (int) $comment['author_account_id'], 'comment_reward', 'community.comment', $commentId, 'comment_reward_reversal', 'community.comment.reward_reversal');
                if (empty($reversalResult['allowed'])) {
                    $errors[] = (string) ($reversalResult['message'] ?? sr_t('community::action.admin.comment_reward_reversal_status_failed'));
                }
            }

            if ($errors === []) {
                sr_community_update_comment_status($pdo, $commentId, $status);
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
                        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
                    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
                ]);
                $notice = sr_t('community::action.admin.comment_status_updated');
            }
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }
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

$posts = sr_community_admin_posts($pdo, 100, $postListFilters);
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

$comments = sr_community_admin_comments($pdo, 100, $commentListFilters);

include SR_ROOT . '/modules/community/views/admin-posts.php';
