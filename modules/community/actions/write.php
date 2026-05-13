<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$boardKey = sr_get_string('key', 60);
$board = sr_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    sr_render_error(404, '게시판을 찾을 수 없습니다.');
}

$isAdminWriter = sr_admin_has_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
if (!sr_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)) {
    sr_render_error(403, '이 게시판에 글을 작성할 수 없습니다.');
}

$settings = sr_community_settings($pdo);
$settings['attachment_max_bytes'] = sr_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
$settings['attachment_max_count'] = sr_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
$settings['file_attachment_max_bytes'] = sr_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
$settings['file_attachment_max_count'] = sr_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
$settings['file_allowed_extensions'] = sr_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
$board['image_uploads_enabled'] = sr_community_effective_board_image_uploads_enabled($pdo, $board) ? 1 : 0;
$board['file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
$errors = [];
$notice = '';
$values = [
    'title' => '',
    'body_text' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sr_require_csrf();

    $values = sr_community_post_input_values();
    $errors = sr_community_validate_post_input($values);

    if ($errors === [] && sr_community_post_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = '짧은 시간에 글을 너무 많이 작성했습니다. 잠시 후 다시 시도해 주세요.';
    }

    if ($errors === []) {
        $postId = sr_community_create_post($pdo, (int) $board['id'], (int) $account['id'], $values);
        sr_community_record_post_rate_limit($pdo, (int) $account['id'], $settings);
        $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $account['id'], [
            'source_module_key' => 'community',
        ]);
        $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $account['id'], $settings, 'post_created');
        $attachmentId = null;
        $attachmentIds = [];
        $attachmentResults = [];
        if ((int) $board['image_uploads_enabled'] === 1 && isset($_FILES['image_attachment']) && is_array($_FILES['image_attachment'])) {
            try {
                $attachmentId = sr_community_upload_post_image($pdo, $postId, (int) $account['id'], $_FILES['image_attachment'], $settings);
                if (is_int($attachmentId) && $attachmentId > 0) {
                    $attachmentIds[] = $attachmentId;
                    $attachmentResults[] = 'image_attached';
                    $_SESSION['sr_community_post_notice'] = '이미지를 첨부했습니다.';
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'member',
                        'event_type' => 'community.attachment.created',
                        'target_type' => 'community_attachment',
                        'target_id' => (string) $attachmentId,
                        'result' => 'success',
                        'message' => 'Community attachment created.',
                        'metadata' => [
                            'post_id' => $postId,
                            'board_key' => (string) $board['board_key'],
                        ],
                    ]);
                } else {
                    $attachmentResults[] = 'image_none';
                }
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'community_post_image_upload');
                $attachmentResults[] = 'image_failed';
                $_SESSION['sr_community_post_notice'] = '게시글은 등록했지만 이미지 첨부는 처리하지 못했습니다.';
            }
        }
        if ((int) $board['file_uploads_enabled'] === 1 && isset($_FILES['file_attachments']) && is_array($_FILES['file_attachments'])) {
            try {
                $fileAttachmentIds = sr_community_upload_post_files($pdo, $postId, (int) $account['id'], $_FILES['file_attachments'], $settings);
                foreach ($fileAttachmentIds as $fileAttachmentId) {
                    $attachmentIds[] = (int) $fileAttachmentId;
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'member',
                        'event_type' => 'community.attachment.created',
                        'target_type' => 'community_attachment',
                        'target_id' => (string) $fileAttachmentId,
                        'result' => 'success',
                        'message' => 'Community attachment created.',
                        'metadata' => [
                            'post_id' => $postId,
                            'board_key' => (string) $board['board_key'],
                            'attachment_kind' => 'file',
                        ],
                    ]);
                }
                $attachmentResults[] = $fileAttachmentIds === [] ? 'file_none' : 'file_attached';
                if ($fileAttachmentIds !== []) {
                    $_SESSION['sr_community_post_notice'] = '첨부파일을 등록했습니다.';
                }
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'community_post_file_upload');
                $attachmentResults[] = 'file_failed';
                $_SESSION['sr_community_post_notice'] = '게시글은 등록했지만 첨부파일은 처리하지 못했습니다.';
            }
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.post.created',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post created.',
            'metadata' => array_merge([
                'board_key' => (string) $board['board_key'],
                'attachment_id' => $attachmentId,
                'attachment_ids' => $attachmentIds,
                'attachment_result' => $attachmentResults === [] ? 'not_requested' : implode(',', $attachmentResults),
                'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
                'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
            ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
        ]);
        sr_redirect('/community/post?id=' . (string) $postId);
    }
}

$skinKey = sr_community_board_skin_key($pdo, $board);
$skinView = sr_community_skin_view($skinKey, 'form');

include $skinView;
