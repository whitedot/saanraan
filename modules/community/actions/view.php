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
            sr_render_error(403, sr_t('community::action.error.post_view_forbidden'));
        }
    }
}
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
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
if (is_array($account)) {
    sr_community_require_member_nickname($pdo, $account, $settings, (string) ($_SERVER['REQUEST_URI'] ?? '/community'));
}
$assetReadNotices = [];
if (is_array($postBoard)) {
    $paidReadConfig = sr_community_asset_event_config($pdo, $postBoard, $settings, 'paid_read', 'once');
    $isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
    if (!$isAuthor && sr_community_asset_event_required($paidReadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $couponReadResult = ['allowed' => false, 'processed' => false];
        if (sr_module_enabled($pdo, 'coupon') && is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
            require_once SR_ROOT . '/modules/coupon/helpers.php';
            if (function_exists('sr_coupon_redeem_for_target')) {
                $couponContext = [
                    'dedupe_key' => 'community.post.read:coupon:' . (string) $account['id'] . ':' . (string) $post['id'],
                    'reference_module' => 'community',
                    'reference_type' => 'community.post',
                    'reference_id' => (string) $post['id'],
                ];
                $couponReadResult = sr_coupon_redeem_for_target($pdo, (int) $account['id'], 'community_post', (string) $post['id'], $couponContext);
                if (empty($couponReadResult['allowed'])) {
                    $couponReadResult = sr_coupon_redeem_for_target($pdo, (int) $account['id'], 'community_board', (string) $post['board_id'], $couponContext);
                }
            }
        }

        $paidReadResult = !empty($couponReadResult['allowed'])
            ? ['allowed' => true, 'processed' => false, 'coupon_used' => !empty($couponReadResult['processed'])]
            : sr_community_run_asset_event(
                $pdo,
                $paidReadConfig,
                (int) $account['id'],
                'post_read',
                'community.post',
                (int) $post['id'],
                'use',
                'community.post.read'
            );
        if (empty($paidReadResult['allowed'])) {
            sr_render_error(403, (string) ($paidReadResult['message'] ?? sr_t('community::action.error.paid_read_post_failed')));
        }
        sr_community_mark_paid_read_session((int) $account['id'], (int) $post['id']);
        if (!empty($paidReadResult['processed'])) {
            $assetReadNotices[] = sr_t('community::action.notice.asset_used', [
                'asset' => sr_community_asset_module_labels((string) $paidReadConfig['asset_module'], $pdo),
                'amount' => number_format((int) $paidReadConfig['amount']),
            ]);
        } elseif (!empty($paidReadResult['coupon_used'])) {
            $assetReadNotices[] = '쿠폰으로 열람했습니다.';
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
    $commentUnavailableMessage = sr_t('community::action.notice.login_required_to_comment');
} elseif (!$canComment) {
    $commentUnavailableMessage = sr_t('community::action.notice.comment_unavailable');
}
$isScrapped = is_array($account) && sr_community_account_has_scrap($pdo, (int) $account['id'], (int) $post['id']);
$postActionUnavailableMessage = is_array($account) ? '' : sr_t('community::action.notice.login_required_to_post_actions');
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
