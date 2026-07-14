<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$seriesIdValue = sr_post_string('series_id', 20);
$seriesId = preg_match('/\A[1-9][0-9]*\z/', $seriesIdValue) === 1 ? (int) $seriesIdValue : 0;
$targetType = sr_post_string('target_type', 30);
$intent = sr_post_string('intent', 20);
$returnTo = sr_post_string('return_to', 20);
$returnPostPageValue = sr_post_string('return_post_page', 20);
$returnSeriesPageValue = sr_post_string('return_series_page', 20);
$returnPostPage = preg_match('/\A[1-9][0-9]*\z/', $returnPostPageValue) === 1 ? (int) $returnPostPageValue : 1;
$returnSeriesPage = preg_match('/\A[1-9][0-9]*\z/', $returnSeriesPageValue) === 1 ? (int) $returnSeriesPageValue : 1;
$scrapReturnQuery = [];
if ($returnPostPage > 1) {
    $scrapReturnQuery['post_page'] = $returnPostPage;
}
if ($returnSeriesPage > 1) {
    $scrapReturnQuery['series_page'] = $returnSeriesPage;
}
$scrapReturnPath = '/community/scraps' . ($scrapReturnQuery !== [] ? '?' . http_build_query($scrapReturnQuery) : '');

if ($targetType === 'series') {
    if ($intent === 'remove') {
        $removed = sr_community_remove_series_scrap($pdo, (int) $account['id'], $seriesId);
        if ($removed) {
            $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.series_scrap_removed');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'community.series_scrap.removed',
                'target_type' => 'community_series',
                'target_id' => (string) $seriesId,
                'result' => 'success',
                'message' => 'Community series scrap removed.',
            ]);
        } else {
            $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.series_scrap_already_removed');
        }
        sr_redirect($returnTo === 'scraps' ? $scrapReturnPath : '/community/scraps');
    }

    $series = sr_community_series_for_read($pdo, $seriesId, $account);
    if (!is_array($series)) {
        sr_render_error(404, sr_t('community::action.error.series_not_found'));
    }

    $added = sr_community_add_series_scrap($pdo, (int) $account['id'], $seriesId);
    if ($added) {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.series_scrap_added');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.series_scrap.added',
            'target_type' => 'community_series',
            'target_id' => (string) $seriesId,
            'result' => 'success',
            'message' => 'Community series scrap added.',
            'metadata' => [
                'board_id' => (int) ($series['board_id'] ?? 0),
            ],
        ]);
    } else {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.series_scrap_already_added');
    }
    sr_redirect('/community/scraps');
}

if ($intent === 'remove') {
    $removed = sr_community_remove_scrap($pdo, (int) $account['id'], $postId);
    if ($removed) {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.scrap_removed');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.scrap.removed',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community scrap removed.',
        ]);
    } else {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.scrap_already_removed');
    }
    if ($returnTo === 'scraps') {
        sr_redirect($scrapReturnPath);
    }
    $post = sr_community_post_for_read($pdo, $postId, $account);
    if (!is_array($post)) {
        sr_redirect('/community/scraps');
    }
    sr_redirect('/community/post?id=' . (string) $postId);
}

$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
} else {
    $added = sr_community_add_scrap($pdo, (int) $account['id'], $postId);
    if ($added) {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.scrap_added');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.scrap.added',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community scrap added.',
            'metadata' => [
                'board_key' => (string) $post['board_key'],
            ],
        ]);
    } else {
        $_SESSION['sr_community_scrap_notice'] = sr_t('community::action.notice.scrap_already_added');
    }
}

sr_redirect('/community/post?id=' . (string) $postId);
