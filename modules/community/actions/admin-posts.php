<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedPostStatuses = toy_community_post_statuses();
$allowedCommentStatuses = toy_community_comment_statuses();

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);
    $status = toy_post_string('status', 30);

    if ($intent === 'post_status') {
        $postIdValue = toy_post_string('post_id', 20);
        $postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
        $post = toy_community_admin_post_by_id($pdo, $postId);

        if (!is_array($post)) {
            $errors[] = '게시글을 찾을 수 없습니다.';
        }

        if (!in_array($status, $allowedPostStatuses, true)) {
            $errors[] = '게시글 상태 값이 올바르지 않습니다.';
        }

        if ($errors === [] && is_array($post)) {
            toy_community_update_post_status($pdo, $postId, $status);
            $groupEvaluationSummary = toy_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
                'source_module_key' => 'community',
            ]);
            $updatedAttachmentCount = 0;
            if (in_array($status, ['hidden', 'deleted'], true)) {
                $updatedAttachmentCount = toy_community_update_post_attachments_status($pdo, $postId, $status);
            } elseif ($status === 'published' && (string) $post['status'] === 'hidden') {
                $updatedAttachmentCount = toy_community_restore_hidden_post_attachments($pdo, $postId);
            }
            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.post.status_updated',
                'target_type' => 'community_post',
                'target_id' => (string) $postId,
                'result' => 'success',
                'message' => 'Community post status updated.',
                'metadata' => [
                    'before_status' => (string) $post['status'],
                    'after_status' => $status,
                    'updated_attachment_count' => $updatedAttachmentCount,
                    'group_rules_evaluated' => (int) $groupEvaluationSummary['evaluated'],
                    'group_memberships_granted' => (int) $groupEvaluationSummary['granted'],
                    'group_memberships_revoked' => (int) $groupEvaluationSummary['revoked'],
                ],
            ]);
            $notice = '게시글 상태를 변경했습니다.';
        }
    } elseif ($intent === 'comment_status') {
        $commentIdValue = toy_post_string('comment_id', 20);
        $commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
        $comment = toy_community_admin_comment_by_id($pdo, $commentId);

        if (!is_array($comment)) {
            $errors[] = '댓글을 찾을 수 없습니다.';
        }

        if (!in_array($status, $allowedCommentStatuses, true)) {
            $errors[] = '댓글 상태 값이 올바르지 않습니다.';
        }

        if ($errors === [] && is_array($comment)) {
            toy_community_update_comment_status($pdo, $commentId, $status);
            toy_audit_log($pdo, [
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
                ],
            ]);
            $notice = '댓글 상태를 변경했습니다.';
        }
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$posts = toy_community_admin_posts($pdo, 100);
$comments = toy_community_admin_comments($pdo, 100);

include TOY_ROOT . '/modules/community/views/admin-posts.php';
