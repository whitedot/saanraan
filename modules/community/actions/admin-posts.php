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
            $errors[] = '게시글을 찾을 수 없습니다.';
        }

        if (!in_array($status, $allowedPostStatuses, true)) {
            $errors[] = '게시글 상태 값이 올바르지 않습니다.';
        }

        if ($errors === [] && is_array($post)) {
            sr_community_update_post_status($pdo, $postId, $status);
            $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
                'source_module_key' => 'community',
            ]);
            $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_status_updated');
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
            $notice = '게시글 상태를 변경했습니다.';
        }
    } elseif ($intent === 'comment_status') {
        $commentIdValue = sr_post_string('comment_id', 20);
        $commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
        $comment = sr_community_admin_comment_by_id($pdo, $commentId);

        if (!is_array($comment)) {
            $errors[] = '댓글을 찾을 수 없습니다.';
        }

        if (!in_array($status, $allowedCommentStatuses, true)) {
            $errors[] = '댓글 상태 값이 올바르지 않습니다.';
        }

        if ($errors === [] && is_array($comment)) {
            sr_community_update_comment_status($pdo, $commentId, $status);
            $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $comment['author_account_id'], null, 'comment_status_updated');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.comment.status_updated',
                'target_type' => 'community_comment',
                'target_id' => (string) $commentId,
                'result' => 'success',
                'message' => 'Community comment status updated.',
                'metadata' => [
                    'before_status' => (string) $comment['status'],
                    'after_status' => $status,
                    'post_id' => (int) $comment['post_id'],
                    'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                    'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
                ],
            ]);
            $notice = '댓글 상태를 변경했습니다.';
        }
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$posts = sr_community_admin_posts($pdo, 100);
$comments = sr_community_admin_comments($pdo, 100);

include SR_ROOT . '/modules/community/views/admin-posts.php';
