<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (sr_module_enabled($pdo, 'antispam') && is_file(SR_ROOT . '/modules/antispam/helpers.php')) {
    require_once SR_ROOT . '/modules/antispam/helpers.php';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$postIdValue = sr_request_method() === 'POST' ? sr_post_string('id', 20) : sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$communityCommentFragmentRequest = sr_request_method() === 'GET' && sr_get_string('comment_fragment', 1) === '1';
$communityCommentPageRequestValue = sr_request_method() === 'GET' ? sr_get_string('comment_page', 20) : '';
$communityCommentPageNavigationRequest = preg_match('/\A[1-9][0-9]*\z/', $communityCommentPageRequestValue) === 1;
$account = sr_member_current_account($pdo);
$communityAdminPreviewRequested = sr_get_string('preview', 20) === 'admin';
$communityAdminPreview = $communityAdminPreviewRequested
    && is_array($account)
    && (sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'view')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete'));
$post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    $rawPost = sr_community_admin_post_by_id($pdo, $postId);
    if (is_array($rawPost)) {
        $board = sr_community_board_by_id($pdo, (int) $rawPost['board_id']);
        $boardRequiresVerificationLogin = is_array($board)
            && sr_community_board_requires_verification_login($pdo, $board, null, 'read');
        if (is_array($board) && (sr_community_board_requires_login($board) || $boardRequiresVerificationLogin) && !is_array($account)) {
            $account = sr_member_require_login($pdo);
            $post = sr_community_post_for_read($pdo, $postId, $account);
        }
        if (!is_array($post)
            && sr_request_method() === 'POST'
            && sr_post_string('intent', 40) === 'remove_og_image'
            && is_array($board)
            && (string) ($rawPost['status'] ?? '') === 'published'
            && (string) ($board['status'] ?? '') === 'enabled'
        ) {
            if (!is_array($account)) {
                $account = sr_member_require_login($pdo);
            }
            if (sr_community_account_can_remove_post_og_image($pdo, $rawPost, $account)) {
                $post = $rawPost;
            }
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
if (sr_request_method() === 'POST' && sr_post_string('intent', 40) === 'remove_og_image') {
    if (!is_array($account)) {
        $account = sr_member_require_login($pdo);
    }
    if (!sr_community_account_can_remove_post_og_image($pdo, $post, $account)) {
        sr_render_error(403, '게시글 OG 이미지를 제거할 권한이 없습니다.');
    }

    sr_community_update_post_og_image($pdo, (int) $post['id'], null);
    $isAdminOgRemove = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit')
        || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete');
    $isBoardManagerOgRemove = !$isAdminOgRemove
        && sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), (int) $account['id'], 'remove_post_og_image');
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => $isAdminOgRemove ? 'admin' : 'community_board_manager',
        'event_type' => 'community.post.og_image_removed',
        'target_type' => 'community_post',
        'target_id' => (string) (int) $post['id'],
        'result' => 'success',
        'message' => 'Community post OG image removed.',
        'metadata' => [
            'board_key' => (string) ($post['board_key'] ?? ''),
            'removed_attachment_id' => (int) ($post['og_image_attachment_id'] ?? 0),
            'permission_source' => $isAdminOgRemove ? 'admin' : 'board_manager',
        ],
    ]);
    $_SESSION['sr_community_post_notice'] = '게시글 OG 이미지를 제거했습니다.';
    sr_redirect('/community/post?id=' . rawurlencode((string) $post['id']));
}
$postBoard = sr_community_board_by_id($pdo, (int) $post['board_id']);
$settings = sr_community_settings($pdo);
if (!$communityAdminPreview && is_array($postBoard)) {
    if (!is_array($account) && sr_community_board_identity_action_required($pdo, $postBoard, 'read', $settings)) {
        $account = sr_member_require_login($pdo);
    }
    $communityPostIdentityPolicy = sr_community_identity_action_policy(
        $pdo,
        $postBoard,
        is_array($account) ? $account : null,
        'read',
        '/community/post?id=' . rawurlencode((string) $post['id']),
        $settings
    );
    if (!empty($communityPostIdentityPolicy['required']) && empty($communityPostIdentityPolicy['satisfied'])) {
        sr_render_error(403, sr_community_identity_action_error_message('read', (string) ($communityPostIdentityPolicy['purpose'] ?? 'real_name')));
    }
}
if (is_array($postBoard)) {
    foreach ([
        'banner_before_view_id',
        'banner_after_view_id',
        'popup_layer_view_id',
    ] as $displaySettingKey) {
        $post[$displaySettingKey] = (int) ($postBoard[$displaySettingKey] ?? 0);
    }
}
$categoryEnabled = is_array($postBoard) && sr_community_board_category_enabled($pdo, (int) $postBoard['id']);
$canViewPostBody = sr_community_account_can_view_post_body($pdo, $post, is_array($account) ? $account : null);
$hasCommentPageAccess = sr_community_has_comment_page_access((int) $post['id']);
if ($communityCommentFragmentRequest && (!$canViewPostBody || !$hasCommentPageAccess)) {
    sr_render_error(403, '댓글 페이지를 불러올 권한이 없거나 열람 세션이 만료되었습니다.');
}
$communityAuthorizedCommentPagingRequest = $hasCommentPageAccess
    && ($communityCommentFragmentRequest || $communityCommentPageNavigationRequest);
$secretCommentsEnabled = is_array($postBoard) ? sr_community_effective_board_secret_comments_enabled($pdo, $postBoard, $settings) : false;
$assetReadNotices = [];
$paidReadConfirmationRequired = false;
$paidReadBlocked = false;
$paidReadBlockedMessage = '';
$paidReadConfirmationRequestToken = '';
$paidReadConfirmationCouponIssues = [];
$paidReadConfirmationResult = [];
if (!$communityAdminPreview && !$communityAuthorizedCommentPagingRequest && $canViewPostBody && is_array($postBoard)) {
    $paidReadConfig = sr_community_asset_event_config($pdo, $postBoard, $settings, 'paid_read', 'once');
    $isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
    if (!$isAuthor && sr_community_asset_event_required($paidReadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $couponDedupeKey = 'community.post.read:coupon:' . (string) $account['id'] . ':' . (string) $post['id'];
        if ((string) ($paidReadConfig['charge_policy'] ?? 'once') !== 'once') {
            $couponDedupeKey .= ':' . bin2hex(random_bytes(8));
        }
        $assetConfirmedPost = sr_request_method() === 'POST' && sr_post_string('asset_confirm', 1) === '1';
        $assetExchangeConfirmed = $assetConfirmedPost && sr_post_string('asset_exchange_confirm', 1) === '1';
        $couponIssueIdValue = sr_request_method() === 'POST' ? (sr_post_string('coupon_issue_id', 20) ?? '') : '';
        $couponIssueId = $assetConfirmedPost && preg_match('/\A[1-9][0-9]*\z/', $couponIssueIdValue) === 1 ? (int) $couponIssueIdValue : 0;
        $couponReadResult = ['allowed' => false, 'processed' => false];
        if (sr_community_asset_policy_requires_confirmation((string) ($paidReadConfig['charge_policy'] ?? 'once')) && sr_request_method() !== 'POST') {
            $paidReadResult = sr_community_run_asset_event(
                $pdo,
                $paidReadConfig,
                (int) $account['id'],
                'post_read',
                'community.post',
                (int) $post['id'],
                'use',
                'community.post.read',
                false,
                '',
                false
            );
        } else {
            if ($couponIssueId > 0) {
                $couponStartedTransaction = !$pdo->inTransaction();
                if ($couponStartedTransaction) {
                    $pdo->beginTransaction();
                }
                try {
                    $couponReadResult = sr_community_try_paid_read_coupon_access($pdo, (int) $account['id'], $post, $paidReadConfig, $couponDedupeKey, $couponIssueId);
                    if (!empty($couponReadResult['allowed'])) {
                        $remainingAmount = max(0, (int) ($couponReadResult['remaining_amount'] ?? 0));
                        if ($remainingAmount > 0) {
                            $paidReadResult = sr_community_run_asset_event(
                                $pdo,
                                $paidReadConfig,
                                (int) $account['id'],
                                'post_read',
                                'community.post',
                                (int) $post['id'],
                                'use',
                                'community.post.read',
                                sr_request_method() === 'POST',
                                sr_post_string_without_truncation('asset_request_token', 64) ?? '',
                                true,
                                $assetConfirmedPost,
                                $assetExchangeConfirmed,
                                $remainingAmount,
                                [
                                    'coupon_result' => $couponReadResult,
                                    'payable_amount' => $remainingAmount + max(0, (int) ($couponReadResult['discount_amount'] ?? 0)),
                                    'board_id' => (int) ($post['board_id'] ?? 0),
                                    'post_title_snapshot' => (string) ($post['title'] ?? ''),
                                ]
                            );
                            $paidReadResult['coupon_used'] = !empty($couponReadResult['processed']);
                            $paidReadResult['coupon_discount_amount'] = (int) ($couponReadResult['discount_amount'] ?? 0);
                        } else {
                            $paidReadResult = [
                                'allowed' => true,
                                'processed' => false,
                                'coupon_used' => !empty($couponReadResult['processed']),
                                'confirmation_fingerprint' => (string) ($couponReadResult['confirmation_fingerprint'] ?? ''),
                            ];
                        }
                        if (!empty($paidReadResult['allowed'])) {
                            if ($couponStartedTransaction && $pdo->inTransaction()) {
                                $pdo->commit();
                            }
                        } elseif ($couponStartedTransaction && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    } else {
                        if ($couponStartedTransaction && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $paidReadResult = [
                            'allowed' => false,
                            'processed' => false,
                            'message' => '선택한 쿠폰을 사용할 수 없습니다.',
                        ];
                    }
                } catch (Throwable $exception) {
                    if ($couponStartedTransaction && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (function_exists('sr_log_exception')) {
                        sr_log_exception($exception, 'community_paid_read_coupon_asset_mixed_failed');
                    }
                    $paidReadResult = [
                        'allowed' => false,
                        'processed' => false,
                        'message' => sr_t('community::action.error.paid_read_post_failed'),
                    ];
                }
            } else {
                $paidReadResult = sr_community_run_asset_event(
                    $pdo,
                    $paidReadConfig,
                    (int) $account['id'],
                    'post_read',
                    'community.post',
                    (int) $post['id'],
                    'use',
                    'community.post.read',
                    sr_request_method() === 'POST',
                    sr_post_string_without_truncation('asset_request_token', 64) ?? '',
                    true,
                    $assetConfirmedPost,
                    $assetExchangeConfirmed,
                    null,
                    [
                        'board_id' => (int) ($post['board_id'] ?? 0),
                        'post_title_snapshot' => (string) ($post['title'] ?? ''),
                    ]
                );
            }
        }
        if (empty($paidReadResult['allowed'])) {
            if ((string) ($paidReadResult['error_key'] ?? '') === 'asset_confirmation_required') {
                $paidReadConfirmationRequired = true;
                $paidReadConfirmationRequestToken = (string) ($paidReadResult['confirmation_request_token'] ?? '');
                $paidReadConfirmationCouponIssues = sr_community_available_paid_read_coupon_issues($pdo, (int) $account['id'], $post);
                $paidReadConfirmationResult = $paidReadResult;
            } else {
                $paidReadBlocked = true;
                $paidReadBlockedMessage = (string) ($paidReadResult['message'] ?? sr_t('community::action.error.paid_read_post_failed'));
            }
        }
        if (
            !$paidReadConfirmationRequired
            && !$paidReadBlocked
            && sr_request_method() === 'POST'
            && sr_community_asset_policy_requires_confirmation((string) ($paidReadConfig['charge_policy'] ?? 'once'))
        ) {
            sr_community_mark_asset_confirmation_session('post_read', 'community.post', (int) $account['id'], (int) $post['id'], (string) ($paidReadResult['confirmation_fingerprint'] ?? ''));
            sr_redirect('/community/post?id=' . rawurlencode((string) $post['id']));
        }
        if (!$paidReadConfirmationRequired && !$paidReadBlocked) {
            sr_community_mark_paid_read_session((int) $account['id'], (int) $post['id']);
        }
        if (!$paidReadConfirmationRequired && !$paidReadBlocked && !empty($paidReadResult['processed'])) {
            $assetReadNotices[] = sr_t('community::action.notice.asset_used', [
                'asset' => sr_community_asset_module_labels((string) $paidReadConfig['asset_module'], $pdo),
                'amount' => number_format((int) $paidReadConfig['amount']),
            ]);
        } elseif (!$paidReadConfirmationRequired && !$paidReadBlocked && !empty($paidReadResult['coupon_used'])) {
            $assetReadNotices[] = '쿠폰으로 열람했습니다.';
        }
    }
}
if (!$communityCommentFragmentRequest && !$paidReadConfirmationRequired && !$paidReadBlocked && $canViewPostBody) {
    sr_community_mark_comment_page_access((int) $post['id']);
}
if (!$communityAdminPreview && !$communityAuthorizedCommentPagingRequest && !$paidReadConfirmationRequired && !$paidReadBlocked && $canViewPostBody && sr_community_should_count_post_view((int) $post['id'])) {
    sr_community_increment_post_view_count($pdo, (int) $post['id']);
    $post['view_count'] = (int) $post['view_count'] + 1;
}
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, is_array($account) ? $account : null);

$commentsPerPage = is_array($postBoard)
    ? sr_community_board_comments_per_page($pdo, $postBoard, $settings)
    : max(1, min(100, (int) ($settings['comments_per_page'] ?? 20)));
$commentPageInput = $communityCommentPageRequestValue;
$requestedCommentPage = preg_match('/\A[1-9][0-9]*\z/', $commentPageInput) === 1 ? (int) $commentPageInput : 1;
$commentPage = $paidReadConfirmationRequired || $paidReadBlocked || !$canViewPostBody
    ? ['comments' => [], 'page' => 1, 'per_page' => $commentsPerPage, 'total' => 0, 'total_pages' => 1, 'has_previous' => false, 'has_next' => false]
    : sr_community_post_comment_page($pdo, (int) $post['id'], $requestedCommentPage, $commentsPerPage);
$comments = is_array($commentPage['comments'] ?? null) ? $commentPage['comments'] : [];
$communityCommentPermissionContext = sr_community_comment_permission_context($pdo, $post, is_array($account) ? $account : null);
$communityCommentAuthorAccountIds = [(int) ($post['author_account_id'] ?? 0)];
foreach ($comments as $communityCommentAuthorRow) {
    $communityCommentAuthorAccountIds[] = (int) ($communityCommentAuthorRow['author_account_id'] ?? 0);
}
$communityFollowStatuses = is_array($account) && function_exists('sr_member_follow_statuses')
    ? sr_member_follow_statuses($pdo, (int) $account['id'], $communityCommentAuthorAccountIds)
    : [];
$post['published_comment_count'] = $paidReadConfirmationRequired || $paidReadBlocked || !$canViewPostBody
    ? 0
    : (int) ($commentPage['total'] ?? 0);
$canComment = !$paidReadConfirmationRequired && !$paidReadBlocked && $canViewPostBody && sr_community_account_can_comment_post($pdo, $post, is_array($account) ? $account : null);
$commentUnavailableMessage = '';
if (!$canComment && !is_array($account)) {
    $commentUnavailableMessage = sr_t('community::action.notice.login_required_to_comment');
} elseif (!$canComment) {
    $commentUnavailableMessage = sr_t('community::action.notice.comment_unavailable');
}
$postActionUnavailableMessage = is_array($account) ? '' : sr_t('community::action.notice.login_required_to_post_actions');
$canReportPost = !$paidReadConfirmationRequired && !$paidReadBlocked && is_array($account) && (int) $post['author_account_id'] !== (int) $account['id'];
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
$commentGuestAuthorName = '';
$commentParentId = 0;
$commentExtraFieldDefinitions = is_array($postBoard)
    ? sr_comment_extra_field_definitions(sr_community_effective_board_setting($pdo, $postBoard, 'comment_extra_fields_json', '[]'))
    : [];
$commentExtraFieldValues = isset($_SESSION['sr_community_comment_extra_field_values']) && is_array($_SESSION['sr_community_comment_extra_field_values'])
    ? $_SESSION['sr_community_comment_extra_field_values']
    : [];
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
if (isset($_SESSION['sr_community_comment_guest_author_name']) && is_string($_SESSION['sr_community_comment_guest_author_name'])) {
    $commentGuestAuthorName = $_SESSION['sr_community_comment_guest_author_name'];
}
$commentIsSecret = !empty($_SESSION['sr_community_comment_is_secret']);
if (isset($_SESSION['sr_community_comment_parent_id'])) {
    $commentParentId = (int) $_SESSION['sr_community_comment_parent_id'];
}
unset($_SESSION['sr_community_comment_notice'], $_SESSION['sr_community_comment_errors'], $_SESSION['sr_community_comment_body'], $_SESSION['sr_community_comment_guest_author_name'], $_SESSION['sr_community_comment_is_secret'], $_SESSION['sr_community_comment_parent_id'], $_SESSION['sr_community_comment_extra_field_values']);
$skinKey = sr_community_board_skin_key($pdo, $post);
$skinView = sr_community_skin_view($skinKey, 'post');

$communityThemeFallbackViewFile = $skinView;
if ($communityCommentFragmentRequest) {
    header('Content-Type: text/html; charset=UTF-8');
    include sr_community_public_view_file($pdo, $settings, 'post.php', $skinView);
    sr_finish_response();
}

$attachments = $paidReadConfirmationRequired || $paidReadBlocked || !$canViewPostBody ? [] : sr_community_post_attachments($pdo, (int) $post['id']);
$communitySeriesContext = $paidReadConfirmationRequired || $paidReadBlocked || !$canViewPostBody ? null : sr_community_series_for_post($pdo, (int) $post['id'], is_array($account) ? $account : null);
$imageAttachments = [];
$fileAttachments = [];
foreach ($attachments as $attachment) {
    if (sr_community_attachment_is_image($attachment)) {
        $attachment['original_url'] = sr_community_attachment_public_url($attachment);
        $attachment['thumbnail_url'] = is_array($postBoard)
            ? sr_community_post_view_image_thumbnail_url($pdo, $attachment, $postBoard, $settings)
            : $attachment['original_url'];
        $imageAttachments[] = $attachment;
    } else {
        $fileAttachments[] = $attachment;
    }
}
$isScrapped = !$paidReadConfirmationRequired && !$paidReadBlocked && is_array($account) && sr_community_account_has_scrap($pdo, (int) $account['id'], (int) $post['id']);
$isSeriesScrapped = !$paidReadConfirmationRequired
    && !$paidReadBlocked
    && is_array($account)
    && is_array($communitySeriesContext)
    && sr_community_account_has_series_scrap($pdo, (int) $account['id'], (int) $communitySeriesContext['id']);

include sr_community_public_view_file($pdo, $settings, 'post.php', $skinView);
