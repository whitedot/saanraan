<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$account = sr_member_current_account($pdo);
$attachmentIdValue = sr_request_method() === 'POST' ? sr_post_string('id', 20) : sr_get_string('id', 20);
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

$disposition = sr_community_attachment_is_image($attachment) ? 'inline' : 'attachment';
$downloadUrl = '';
$filePath = null;
if ($driver === 's3') {
    $downloadUrl = sr_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => sr_download_content_type($mimeType),
        'response-content-disposition' => $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"',
    ]);
    if ($downloadUrl === '') {
        sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
    }
} else {
    $filePath = sr_community_attachment_file_path($attachment);
    if (!is_string($filePath)) {
        sr_render_error(404, sr_t('community::action.error.attachment_not_found'));
    }
}

$post = is_array($attachment['post'] ?? null) ? $attachment['post'] : [];
$board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
$isUploader = is_array($account) && (int) ($attachment['uploader_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
$isAuthor = is_array($account) && (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
if ($disposition === 'attachment' && is_array($board)) {
    $settings = sr_community_settings($pdo);
}
if (is_array($board)) {
    $settings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
    $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
    if (!$isUploader && !$isAuthor && sr_community_asset_event_required($paidReadConfig)) {
        if (!is_array($account)) {
            $account = sr_member_require_login($pdo);
        }

        $couponDedupeKey = 'community.post.read:coupon:' . (string) $account['id'] . ':' . (string) $post['id'];
        if ((string) ($paidReadConfig['charge_policy'] ?? 'once') !== 'once') {
            $couponDedupeKey .= ':' . bin2hex(random_bytes(8));
        }
        $paidReadChargePolicy = (string) ($paidReadConfig['charge_policy'] ?? 'once');
        $paidReadConfirmationFingerprint = '';
        $paidReadBridgeCreatedAt = 0;
        $skipPaidReadCharge = $paidReadChargePolicy === 'once'
            && sr_community_has_paid_read_session((int) $account['id'], (int) ($post['id'] ?? 0))
            && sr_community_once_access_already_granted($pdo, $paidReadConfig, (int) $account['id'], 'post_read', (int) $post['id'], $couponDedupeKey);
        if (
            !$skipPaidReadCharge
            && sr_community_asset_policy_requires_confirmation($paidReadChargePolicy)
        ) {
            $assetModules = sr_community_asset_module_keys_from_value($paidReadConfig['asset_module'] ?? '', true);
            $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
            $amounts = is_array($paidReadConfig['amounts'] ?? null) ? $paidReadConfig['amounts'] : [];
            $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, (int) $account['id'], $assetModules, $amounts, (int) ($paidReadConfig['amount'] ?? 0), $paidReadConfig['group_policies_json'] ?? '', (int) ($paidReadConfig['policy_set_id'] ?? 0), 'use');
            $paidReadConfirmationFingerprint = sr_community_asset_confirmation_fingerprint('post_read', 'community.post', $paidReadChargePolicy, $assetModuleValue, (int) $policyAmounts['amount'], is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [], sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']));
            $paidReadBridgeCreatedAt = sr_community_asset_modules_available($pdo, $assetModules)
                ? sr_community_consume_attachment_paid_read_bridge_created_at((int) $account['id'], (int) $attachment['id'], $paidReadConfirmationFingerprint)
                : 0;
            if ($paidReadBridgeCreatedAt > 0) {
                $skipPaidReadCharge = true;
            }
        }
        if (!$skipPaidReadCharge) {
            $couponReadResult = ['allowed' => false, 'processed' => false];
            if (sr_community_asset_policy_requires_confirmation($paidReadChargePolicy) && sr_request_method() !== 'POST') {
                $paidReadResult = sr_community_run_asset_event(
                    $pdo,
                    $paidReadConfig,
                    (int) $account['id'],
                    'post_read',
                    'community.post',
                    (int) $post['id'],
                    'use',
                    'community.post.read',
                    false
                );
            } else {
                $couponReadResult = sr_community_try_paid_read_coupon_access($pdo, (int) $account['id'], $post, $paidReadConfig, $couponDedupeKey);
                $paidReadResult = !empty($couponReadResult['allowed'])
                    ? [
                        'allowed' => true,
                        'processed' => false,
                        'confirmation_fingerprint' => (string) ($couponReadResult['confirmation_fingerprint'] ?? ''),
                    ]
                    : sr_community_run_asset_event(
                        $pdo,
                        $paidReadConfig,
                        (int) $account['id'],
                        'post_read',
                        'community.post',
                        (int) $post['id'],
                        'use',
                        'community.post.read',
                        sr_request_method() === 'POST'
                    );
            }
            if (empty($paidReadResult['allowed'])) {
                if ((string) ($paidReadResult['error_key'] ?? '') === 'asset_confirmation_required') {
                    $assetConfirmationMessage = (string) ($paidReadResult['message'] ?? sr_community_asset_confirmation_required_message());
                    $assetConfirmationAction = '/community/attachment';
                    $assetConfirmationId = (int) $attachment['id'];
                    include SR_ROOT . '/modules/community/views/asset-confirmation.php';
                    return;
                }
                sr_render_error(403, (string) ($paidReadResult['message'] ?? sr_t('community::action.error.paid_read_attachment_failed')));
            }
            if (sr_request_method() === 'POST' && sr_community_asset_policy_requires_confirmation($paidReadChargePolicy)) {
                $paidReadConfirmationFingerprint = (string) ($paidReadResult['confirmation_fingerprint'] ?? '');
                sr_community_mark_asset_confirmation_session('post_read', 'community.post', (int) $account['id'], (int) $post['id'], $paidReadConfirmationFingerprint);
                sr_community_mark_attachment_paid_read_bridge((int) $account['id'], (int) $attachment['id'], $paidReadConfirmationFingerprint);
                sr_redirect('/community/attachment?id=' . rawurlencode((string) $attachment['id']));
            }
            sr_community_mark_paid_read_session((int) $account['id'], (int) $post['id']);
        }
    }
}

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
            'community.attachment.download',
            sr_request_method() === 'POST'
        );
        if (empty($downloadResult['allowed'])) {
            if ((string) ($downloadResult['error_key'] ?? '') === 'asset_confirmation_required') {
                $assetConfirmationMessage = (string) ($downloadResult['message'] ?? sr_community_asset_confirmation_required_message());
                $assetConfirmationAction = '/community/attachment';
                $assetConfirmationId = (int) $attachment['id'];
                if ($paidReadConfirmationFingerprint !== '' && $paidReadBridgeCreatedAt > 0) {
                    sr_community_mark_attachment_paid_read_bridge((int) $account['id'], (int) $attachment['id'], $paidReadConfirmationFingerprint, $paidReadBridgeCreatedAt);
                }
                include SR_ROOT . '/modules/community/views/asset-confirmation.php';
                return;
            }
            sr_render_error(403, (string) ($downloadResult['message'] ?? sr_t('community::action.error.download_attachment_failed')));
        }
        if (
            sr_request_method() === 'POST'
            && sr_community_asset_policy_requires_confirmation((string) ($downloadConfig['charge_policy'] ?? 'once'))
        ) {
            sr_community_mark_asset_confirmation_session('attachment_download', 'community.attachment', (int) $account['id'], (int) $attachment['id'], (string) ($downloadResult['confirmation_fingerprint'] ?? ''));
            if ($paidReadConfirmationFingerprint !== '' && $paidReadBridgeCreatedAt > 0) {
                sr_community_mark_attachment_paid_read_bridge((int) $account['id'], (int) $attachment['id'], $paidReadConfirmationFingerprint, $paidReadBridgeCreatedAt);
            }
            sr_redirect('/community/attachment?id=' . rawurlencode((string) $attachment['id']));
        }
        sr_community_grant_attachment_publisher_reward($pdo, $board, $settings, $post, $attachment, (int) $account['id'], $downloadResult);
    }
}
if ($downloadUrl !== '') {
    header('Cache-Control: private, max-age=300');
    sr_redirect_external($downloadUrl);
}

header('Content-Type: ' . sr_download_content_type($mimeType));
header('Content-Disposition: ' . $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"');
header('Content-Length: ' . (string) $recordedSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
sr_finish_response();
