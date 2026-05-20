<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$postIdValue = sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$account = sr_member_current_account($pdo);
$post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    $rawPost = sr_community_admin_post_by_id($pdo, $postId);
    if (is_array($rawPost)) {
        $board = sr_community_board_by_id($pdo, (int) $rawPost['board_id']);
        if (is_array($board) && sr_community_board_requires_login($board) && !is_array($account)) {
            $account = sr_member_require_login($pdo);
            $post = sr_community_post_for_read($pdo, $postId, $account);
        }
        if (!is_array($post)
            && is_array($board)
            && (string) $rawPost['status'] === 'published'
            && (string) $board['status'] === 'enabled'
            && !sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)
        ) {
            sr_render_error(403, '이 게시글을 볼 수 없습니다.');
        }
    }
}
if (!is_array($post)) {
    sr_render_error(404, '게시글을 찾을 수 없습니다.');
}
$postBoard = sr_community_board_by_id($pdo, (int) $post['board_id']);
if (is_array($postBoard)) {
    foreach ([
        'banner_before_view_id',
        'banner_after_view_id',
        'popup_layer_view_id',
    ] as $displaySettingKey) {
        $post[$displaySettingKey] = (int) ($postBoard[$displaySettingKey] ?? 0);
    }
}
$settings = sr_community_settings($pdo);
$assetReadNotices = [];
if (is_array($postBoard)) {
    $paidReadConfig = sr_community_asset_event_config($pdo, $postBoard, $settings, 'paid_read', 'once');
    $isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
    if (!$isAuthor && sr_community_asset_event_required($paidReadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $paidReadResult = sr_community_run_asset_event(
            $pdo,
            $paidReadConfig,
            (int) $account['id'],
            'post_read',
            'community.post',
            (int) $post['id'],
            'use',
            '커뮤니티 게시글 열람'
        );
        if (empty($paidReadResult['allowed'])) {
            sr_render_error(403, (string) ($paidReadResult['message'] ?? '회원 자산이 부족해 게시글을 볼 수 없습니다.'));
        }
        if (!empty($paidReadResult['processed'])) {
            $assetReadNotices[] = sr_community_asset_module_label((string) $paidReadConfig['asset_module']) . ' ' . number_format((int) $paidReadConfig['amount']) . '을(를) 차감했습니다.';
        }
    }
}
sr_community_increment_post_view_count($pdo, (int) $post['id']);
$post['view_count'] = (int) $post['view_count'] + 1;
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, is_array($account) ? $account : null);

$commentsPerPage = max(1, min(100, (int) ($settings['comments_per_page'] ?? 50)));
$comments = sr_community_post_comments($pdo, (int) $post['id'], $commentsPerPage);
$attachments = sr_community_post_attachments($pdo, (int) $post['id']);
$imageAttachments = [];
$fileAttachments = [];
foreach ($attachments as $attachment) {
    if (sr_community_attachment_is_image($attachment)) {
        $imageAttachments[] = $attachment;
    } else {
        $fileAttachments[] = $attachment;
    }
}
$canComment = is_array($account) && sr_community_account_can_comment_post($pdo, $post, $account);
$commentUnavailableMessage = '';
if (!is_array($account)) {
    $commentUnavailableMessage = '로그인하면 댓글을 작성할 수 있습니다.';
} elseif (!$canComment) {
    $commentUnavailableMessage = '이 게시글에는 댓글을 작성할 수 없습니다.';
}
$isScrapped = is_array($account) && sr_community_account_has_scrap($pdo, (int) $account['id'], (int) $post['id']);
$postActionUnavailableMessage = is_array($account) ? '' : '로그인하면 스크랩과 신고를 사용할 수 있습니다.';
$canReportPost = is_array($account) && (int) $post['author_account_id'] !== (int) $account['id'];
$reportReasonKeys = sr_community_report_reason_keys();
$postNotices = $assetReadNotices;
if (isset($_SESSION['sr_community_post_notice']) && is_string($_SESSION['sr_community_post_notice'])) {
    $postNotices[] = $_SESSION['sr_community_post_notice'];
}
if (isset($_SESSION['sr_community_scrap_notice']) && is_string($_SESSION['sr_community_scrap_notice'])) {
    $postNotices[] = $_SESSION['sr_community_scrap_notice'];
}
unset($_SESSION['sr_community_post_notice'], $_SESSION['sr_community_scrap_notice']);
$reportErrors = [];
$reportNotice = '';
if (isset($_SESSION['sr_community_report_errors']) && is_array($_SESSION['sr_community_report_errors'])) {
    foreach ($_SESSION['sr_community_report_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $reportErrors[] = $error;
        }
    }
}
if (isset($_SESSION['sr_community_report_notice']) && is_string($_SESSION['sr_community_report_notice'])) {
    $reportNotice = $_SESSION['sr_community_report_notice'];
}
unset($_SESSION['sr_community_report_errors'], $_SESSION['sr_community_report_notice']);
$commentErrors = [];
$commentNotice = '';
$commentBody = '';
if (isset($_SESSION['sr_community_comment_notice']) && is_string($_SESSION['sr_community_comment_notice'])) {
    $commentNotice = $_SESSION['sr_community_comment_notice'];
}
if (isset($_SESSION['sr_community_comment_errors']) && is_array($_SESSION['sr_community_comment_errors'])) {
    foreach ($_SESSION['sr_community_comment_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $commentErrors[] = $error;
        }
    }
}
if (isset($_SESSION['sr_community_comment_body']) && is_string($_SESSION['sr_community_comment_body'])) {
    $commentBody = $_SESSION['sr_community_comment_body'];
}
unset($_SESSION['sr_community_comment_notice'], $_SESSION['sr_community_comment_errors'], $_SESSION['sr_community_comment_body']);
$skinKey = sr_community_board_skin_key($pdo, $post);
$skinView = sr_community_skin_view($skinKey, 'post');

include $skinView;
