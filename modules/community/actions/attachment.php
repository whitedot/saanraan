<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$attachmentIdValue = sr_get_string('id', 20);
$attachmentId = preg_match('/\A[1-9][0-9]*\z/', $attachmentIdValue) === 1 ? (int) $attachmentIdValue : 0;
$attachment = sr_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null);
if (!is_array($attachment)) {
    $board = sr_community_attachment_read_board($pdo, $attachmentId);
    if (is_array($board) && in_array(sr_community_effective_board_policy($pdo, $board, 'read_policy'), ['member', 'group'], true) && !is_array($account)) {
        $account = sr_member_require_login($pdo);
        $attachment = sr_community_attachment_for_read($pdo, $attachmentId, $account);
    }
    if (!is_array($attachment)
        && is_array($board)
        && !sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)
    ) {
        sr_render_error(403, sr_t('community::action.error.attachment_view_forbidden'));
    }
}
if (!is_array($attachment)) {
    sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
}

$mimeType = (string) $attachment['mime_type'];
$driver = sr_community_attachment_storage_driver($attachment);
$storageKey = sr_community_attachment_storage_key($attachment);
if (!sr_community_attachment_mime_is_allowed($mimeType) || $storageKey === '') {
    sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
}

$recordedSize = (int) ($attachment['size_bytes'] ?? 0);
$recordedChecksum = (string) ($attachment['checksum_sha256'] ?? '');
$head = sr_storage_head($driver, $storageKey);
if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
    sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
}

$actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
    sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
}

$post = is_array($attachment['post'] ?? null) ? $attachment['post'] : [];
$board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
$isUploader = is_array($account) && (int) ($attachment['uploader_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
$isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
if (is_array($board)) {
    $settings = sr_community_settings($pdo);
    $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
    if (!$isUploader && !$isAuthor && sr_community_asset_event_required($paidReadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $skipPaidReadCharge = (string) ($paidReadConfig['charge_policy'] ?? 'once') === 'once'
            && sr_community_has_paid_read_session((int) $account['id'], (int) ($post['id'] ?? 0));
        if (!$skipPaidReadCharge) {
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
                        $couponReadResult = sr_coupon_redeem_for_target($pdo, (int) $account['id'], 'community_board', (string) ($post['board_id'] ?? 0), $couponContext);
                    }
                }
            }

            $paidReadResult = !empty($couponReadResult['allowed'])
                ? ['allowed' => true, 'processed' => false]
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
                sr_render_error(403, (string) ($paidReadResult['message'] ?? sr_t('community::action.error.paid_read_attachment_failed')));
            }
            sr_community_mark_paid_read_session((int) $account['id'], (int) $post['id']);
        }
    }
}

$disposition = sr_community_attachment_is_image($attachment) ? 'inline' : 'attachment';
if ($disposition === 'attachment' && is_array($board)) {
    $downloadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_attachment_download', 'once');
    if (!$isUploader && !$isAuthor && sr_community_asset_event_required($downloadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $downloadResult = sr_community_run_asset_event(
            $pdo,
            $downloadConfig,
            (int) $account['id'],
            'attachment_download',
            'community.attachment',
            (int) $attachment['id'],
            'use',
            'community.attachment.download'
        );
        if (empty($downloadResult['allowed'])) {
            sr_render_error(403, (string) ($downloadResult['message'] ?? sr_t('community::action.error.download_attachment_failed')));
        }
    }
}
if ($driver === 's3') {
    $downloadUrl = sr_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => sr_download_content_type($mimeType),
        'response-content-disposition' => $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"',
    ]);
    if ($downloadUrl === '') {
        sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_external($downloadUrl);
}

$filePath = sr_community_attachment_file_path($attachment);
if (!is_string($filePath)) {
    sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
}

header('Content-Type: ' . sr_download_content_type($mimeType));
header('Content-Disposition: ' . $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"');
header('Content-Length: ' . (string) $recordedSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
sr_finish_response();
