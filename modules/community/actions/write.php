<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (sr_module_enabled($pdo, 'antispam') && is_file(SR_ROOT . '/modules/antispam/helpers.php')) {
    require_once SR_ROOT . '/modules/antispam/helpers.php';
}

$account = sr_member_current_account($pdo);
$isPostRequest = sr_request_method() === 'POST';
$boardKey = sr_get_string('key', 60);
$board = sr_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    sr_render_error(404, sr_t('community::action.error.board_not_found'));
}

$writeReturnUrl = '/community/write?key=' . rawurlencode($boardKey);
if (!is_array($account) && sr_community_board_identity_action_required($pdo, $board, 'write')) {
    $account = sr_member_require_login($pdo);
}
$isAdminWriter = is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
$canWriteBoard = sr_community_account_can_write_board($pdo, $board, is_array($account) ? $account : null, $isAdminWriter);
$canWriteNotice = sr_community_account_can_write_notice($pdo, $board, is_array($account) ? $account : null, $isAdminWriter);
if (!$canWriteBoard && !$canWriteNotice) {
    if (!is_array($account) && sr_community_effective_board_policy($pdo, $board, 'write_policy') !== 'guest') {
        $account = sr_member_require_login($pdo);
    }
    sr_render_error(403, sr_t('community::action.error.board_write_forbidden'));
}
$communityWriteIdentityPolicy = sr_community_identity_action_policy($pdo, $board, is_array($account) ? $account : null, 'write', $writeReturnUrl);
if (!empty($communityWriteIdentityPolicy['required']) && empty($communityWriteIdentityPolicy['satisfied'])) {
    sr_render_error(403, sr_community_identity_action_error_message('write', (string) ($communityWriteIdentityPolicy['purpose'] ?? 'real_name')));
}
$isGuestAuthor = !is_array($account);
$antispamPostContext = ['account' => is_array($account) ? $account : null];

$settings = sr_community_settings($pdo);
$settings['attachment_max_bytes'] = sr_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
$settings['attachment_max_count'] = sr_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
$settings['file_attachment_max_bytes'] = sr_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
$settings['file_attachment_max_count'] = sr_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
$settings['file_allowed_extensions'] = sr_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
$board['image_uploads_enabled'] = sr_community_effective_board_image_uploads_enabled($pdo, $board) ? 1 : 0;
$board['file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
$secretPostsEnabled = sr_community_effective_board_secret_posts_enabled($pdo, $board, $settings);
$writeChargeConfig = sr_community_asset_event_config($pdo, $board, $settings, 'write_charge', 'every_action');
$postRewardConfig = sr_community_asset_event_config($pdo, $board, $settings, 'post_reward', 'once');
$categoryEnabled = sr_community_board_category_enabled($pdo, (int) $board['id']);
$categories = $categoryEnabled ? sr_community_categories($pdo, (int) $board['id'], true) : [];
$currentCategory = null;
$categoryRequired = $categoryEnabled && sr_community_board_category_required($pdo, (int) $board['id']);
$extraFieldDefinitions = sr_community_board_extra_field_definitions($pdo, $board);
$extraFieldValues = [];
$seriesEnabled = sr_community_effective_board_series_enabled($pdo, $board, $settings);
$seriesOptions = $seriesEnabled && is_array($account) ? sr_community_account_series($pdo, (int) $account['id'], (int) $board['id']) : [];
$currentSeriesItem = null;
$seriesValues = [
    'series_mode' => 'none',
    'series_id' => 0,
    'new_series_title' => '',
    'episode_label' => '',
    'sort_order' => 0,
];
$errors = [];
$notice = '';
$values = [
    'title' => '',
    'category_id' => 0,
    'body_text' => '',
    'body_format' => 'plain',
    'seo_title' => '',
    'seo_description' => '',
    'og_title' => '',
    'og_description' => '',
    'is_secret' => 0,
    'is_notice' => !$canWriteBoard && $canWriteNotice ? 1 : 0,
];

$postFormFlash = isset($_SESSION['sr_community_post_form_flash']) && is_array($_SESSION['sr_community_post_form_flash'])
    ? $_SESSION['sr_community_post_form_flash']
    : [];
if ($postFormFlash !== []
    && (string) ($postFormFlash['action'] ?? '') === 'write'
    && (int) ($postFormFlash['board_id'] ?? 0) === (int) $board['id']
) {
    unset($_SESSION['sr_community_post_form_flash']);
    $flashValues = is_array($postFormFlash['values'] ?? null) ? $postFormFlash['values'] : [];
    $values = array_merge($values, array_intersect_key($flashValues, $values));
    $extraFieldValues = is_array($postFormFlash['extra_field_values'] ?? null) ? $postFormFlash['extra_field_values'] : $extraFieldValues;
    $seriesValues = is_array($postFormFlash['series_values'] ?? null)
        ? array_merge($seriesValues, array_intersect_key($postFormFlash['series_values'], $seriesValues))
        : $seriesValues;
    $errors = isset($postFormFlash['errors']) && is_array($postFormFlash['errors'])
        ? array_values(array_filter(array_map('strval', $postFormFlash['errors']), static fn (string $error): bool => $error !== ''))
        : [];
} elseif ($postFormFlash !== [] && (string) ($postFormFlash['action'] ?? '') === 'write') {
    unset($_SESSION['sr_community_post_form_flash']);
}

if ($isPostRequest) {
    sr_require_csrf();

    $values = sr_community_post_input_values($pdo, $board, $settings);
    if (!$categoryEnabled) {
        $values['category_id'] = 0;
    }
    $requestedIsNotice = (int) ($values['is_notice'] ?? 0) === 1;
    $values['is_notice'] = $requestedIsNotice && $canWriteNotice ? 1 : 0;
    $extraFieldValues = sr_community_extra_field_input_values($extraFieldDefinitions);
    $values['extra_values_json'] = sr_community_extra_field_values_json($extraFieldDefinitions, $extraFieldValues);
    $values['extra_field_definitions'] = $extraFieldDefinitions;
    $values['extra_field_values'] = $extraFieldValues;
    if ($isGuestAuthor) {
        $values['body_format'] = 'plain';
        $values = array_merge($values, sr_community_guest_author_input_values());
    }
    if (function_exists('sr_antispam_verify')) {
        $antispamResult = sr_antispam_verify($pdo, 'community.post.guest', 'community_post_' . (string) (int) $board['id'], $_POST, $antispamPostContext);
        $errors = array_merge($errors, (array) ($antispamResult['errors'] ?? []));
    }
    $seriesSortOrder = sr_community_series_post_sort_order();
    $seriesValues = [
        'series_mode' => sr_post_string('series_mode', 20),
        'series_id' => (int) sr_post_string('series_id', 20),
        'new_series_title' => trim(sr_post_string('new_series_title', 160)),
        'episode_label' => trim(sr_post_string('series_episode_label', 80)),
        'sort_order' => $seriesSortOrder ?? 0,
    ];
    if (!in_array((string) $seriesValues['series_mode'], ['none', 'existing', 'new'], true)) {
        $seriesValues['series_mode'] = 'none';
    }
    $errors = array_merge($errors, sr_community_validate_post_input($values, $pdo));
    $errors = array_merge($errors, sr_community_validate_post_body_length($pdo, $board, $values, $settings));
    $errors = array_merge($errors, sr_community_validate_extra_field_values($extraFieldDefinitions, $extraFieldValues));
    if ($isGuestAuthor) {
        $errors = array_merge($errors, sr_community_validate_guest_author_input($values));
        if (sr_community_uploaded_file_present($_FILES['image_attachment'] ?? null)
            || sr_community_uploaded_file_present($_FILES['file_attachments'] ?? null)
            || sr_community_privacy_consent_body_upload_targets_from_values($values) !== []) {
            $errors[] = '비회원은 첨부 업로드를 사용할 수 없습니다.';
        }
    }
    $errors = array_merge($errors, sr_community_post_category_validation_errors($pdo, $board, $values));
    if (!$canWriteBoard && $canWriteNotice && (int) ($values['is_notice'] ?? 0) !== 1) {
        $errors[] = '공지사항 작성 권한으로는 공지사항만 작성할 수 있습니다.';
    }
    if ($requestedIsNotice && !$canWriteNotice) {
        $errors[] = '공지사항으로 지정할 권한이 없습니다.';
    }
    $privacyConsentActionKeys = sr_community_privacy_consent_post_targets_from_request($values);
    $errors = array_merge($errors, sr_community_privacy_consent_validation_errors($pdo, $board, $privacyConsentActionKeys));
    if ((string) $seriesValues['series_mode'] !== 'none' && !$seriesEnabled) {
        $errors[] = sr_community_series_supported($pdo) ? '이 게시판은 시리즈를 사용할 수 없습니다.' : sr_community_series_unavailable_message($pdo);
    }
    if ($isGuestAuthor && (string) $seriesValues['series_mode'] !== 'none') {
        $errors[] = '비회원은 시리즈를 연결할 수 없습니다.';
    }
    if ((string) $seriesValues['series_mode'] !== 'none' && $seriesSortOrder === null) {
        $errors[] = '시리즈 정렬 순서를 확인해 주세요.';
    }
    if ((string) $seriesValues['series_mode'] === 'existing') {
        $selectedSeries = sr_community_series_by_id($pdo, (int) $seriesValues['series_id']);
        if (!is_array($selectedSeries)
            || !is_array($account)
            || (int) ($selectedSeries['owner_account_id'] ?? 0) !== (int) $account['id']
            || (int) ($selectedSeries['board_id'] ?? 0) !== (int) $board['id']
            || !in_array((string) ($selectedSeries['status'] ?? ''), ['pending', 'active', 'hidden'], true)
        ) {
            $errors[] = '연결할 시리즈를 확인해 주세요.';
        }
    } elseif ((string) $seriesValues['series_mode'] === 'new' && (string) $seriesValues['new_series_title'] === '') {
        $errors[] = '새 시리즈 제목을 입력해 주세요.';
    }

    if ($errors === [] && ($isGuestAuthor ? sr_community_guest_post_rate_limited($pdo, $settings) : sr_community_post_rate_limited($pdo, (int) $account['id'], $settings))) {
        $errors[] = sr_t('community::action.rate_limit.post');
    }

    if ($errors === [] && sr_community_asset_event_required($writeChargeConfig)) {
        if ($isGuestAuthor) {
            $errors[] = '포인트/금액 차감이 필요한 게시판에는 비회원으로 작성할 수 없습니다.';
        }
        $assetModules = sr_community_asset_module_keys_from_value($writeChargeConfig['asset_module'] ?? '', true);
        if ($errors === [] && !sr_community_asset_modules_available($pdo, $assetModules)) {
            $errors[] = sr_t('community::action.error.write_asset_modules_unavailable');
        } elseif ($errors === [] && !sr_community_asset_use_balance_available($pdo, $writeChargeConfig, (int) $account['id'])) {
            $errors[] = sr_community_asset_config_balance_shortage_message($pdo, $writeChargeConfig, (int) $account['id'], '글을 작성할 수 없습니다.', sr_t('community::action.error.write_asset_balance_low'));
        }
    }

    $accountGuardWriteDecision = ['action' => 'allow', 'initial_status' => 'published', 'guard_type' => 'none'];
    if ($errors === [] && !$isGuestAuthor && !$isAdminWriter) {
        $accountGuardWriteDecision = sr_community_account_guard_write_decision($pdo, (int) $account['id'], 'post');
        if ((string) ($accountGuardWriteDecision['action'] ?? '') === 'block') {
            $errors[] = (string) ($accountGuardWriteDecision['message'] ?? '커뮤니티 작성 제한이 적용 중입니다. 잠시 후 다시 시도해 주세요.');
        } elseif ((string) ($accountGuardWriteDecision['initial_status'] ?? 'published') === 'pending') {
            $values['initial_status'] = 'pending';
        }
    }

    if ($errors === []) {
        $authorAccountId = is_array($account) ? (int) $account['id'] : 0;
        $postId = sr_community_create_post($pdo, (int) $board['id'], $authorAccountId, $values);
        $createdPostStatus = (string) ($values['initial_status'] ?? 'published');
        $privacyConsentRecordCount = 0;
        $writeChargeResult = !$isGuestAuthor && sr_community_asset_event_required($writeChargeConfig)
            ? sr_community_run_asset_event($pdo, $writeChargeConfig, $authorAccountId, 'post_write_charge', 'community.post', $postId, 'use', 'community.post.write')
            : ['allowed' => true, 'processed' => false];
        if (empty($writeChargeResult['allowed'])) {
            sr_community_update_post_status($pdo, $postId, 'deleted');
            sr_render_error(403, (string) ($writeChargeResult['message'] ?? sr_t('community::action.error.write_charge_failed')));
        }
        $privacyConsentRecordCount = sr_community_record_submission_consents($pdo, (int) $board['id'], $authorAccountId, 'community.post', $postId, $privacyConsentActionKeys, $board);
        if (!$isGuestAuthor && (string) $seriesValues['series_mode'] === 'new') {
            $seriesValues['series_id'] = sr_community_create_series($pdo, (int) $board['id'], $authorAccountId, [
                'title' => (string) $seriesValues['new_series_title'],
                'description' => '',
                'status' => 'active',
                'visibility' => 'public',
            ], $authorAccountId);
        }
        if (!$isGuestAuthor && in_array((string) $seriesValues['series_mode'], ['existing', 'new'], true)) {
            sr_community_set_post_series($pdo, $postId, (int) $seriesValues['series_id'], (string) $seriesValues['episode_label'], (int) $seriesValues['sort_order'], $authorAccountId);
        }
        $postRewardResult = !$isGuestAuthor && $createdPostStatus === 'published' && sr_community_asset_event_required($postRewardConfig)
            ? sr_community_run_asset_event($pdo, $postRewardConfig, $authorAccountId, 'post_reward', 'community.post', $postId, 'grant', 'community.post.reward')
            : ['allowed' => true, 'processed' => false];
        if (!empty($postRewardResult['processed'])) {
            $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.asset_granted', [
                'asset' => sr_community_asset_module_label((string) $postRewardConfig['asset_module'], $pdo),
                'amount' => number_format((int) $postRewardConfig['amount']),
            ]);
        }
        if ($isGuestAuthor) {
            sr_community_record_guest_post_rate_limit($pdo, $settings);
            $levelSnapshot = ['level_value' => 0, 'score_value' => 0];
            $groupEvaluationSummary = [];
        } else {
            sr_community_record_post_rate_limit($pdo, $authorAccountId, $settings);
            $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, $authorAccountId, $settings, 'post_created');
            $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, $authorAccountId, [
                'source_module_key' => 'community',
            ]);
        }
        $attachmentId = null;
        $attachmentIds = [];
        $attachmentResults = [];
        if (!$isGuestAuthor && (int) $board['image_uploads_enabled'] === 1 && isset($_FILES['image_attachment']) && is_array($_FILES['image_attachment'])) {
            try {
                $attachmentId = sr_community_upload_post_image($pdo, $postId, (int) $account['id'], $_FILES['image_attachment'], $settings);
                if (is_int($attachmentId) && $attachmentId > 0) {
                    sr_community_update_post_og_image($pdo, $postId, $attachmentId);
                    $attachmentIds[] = $attachmentId;
                    $attachmentResults[] = 'image_attached';
                    $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.image_attached');
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
                $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.image_attach_failed_after_post');
            }
        }
        if (!$isGuestAuthor && (int) $board['file_uploads_enabled'] === 1 && isset($_FILES['file_attachments']) && is_array($_FILES['file_attachments'])) {
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
                    $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.file_attached');
                }
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'community_post_file_upload');
                $attachmentResults[] = 'file_failed';
                $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.file_attach_failed_after_post');
            }
        }
        sr_audit_log($pdo, [
            'actor_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
            'actor_type' => $isGuestAuthor ? 'guest' : 'member',
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
                'asset_write_charge_processed' => !empty($writeChargeResult['processed']),
                'asset_post_reward_processed' => !empty($postRewardResult['processed']),
                'privacy_consent_record_count' => $privacyConsentRecordCount,
                'initial_status' => $createdPostStatus,
                'account_guard_write_decision' => $accountGuardWriteDecision,
            ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
        ]);
        $followNotificationCount = $createdPostStatus === 'published' ? sr_community_create_post_follow_notifications($pdo, [
            'id' => $postId,
            'author_account_id' => $authorAccountId,
            'status' => $createdPostStatus,
            'title' => (string) $values['title'],
            'board_key' => (string) $board['board_key'],
            'board_title' => (string) $board['title'],
        ], $authorAccountId > 0 ? $authorAccountId : null) : 0;
        if ($followNotificationCount > 0) {
            sr_audit_log($pdo, [
                'actor_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
                'actor_type' => $isGuestAuthor ? 'guest' : 'member',
                'event_type' => 'community.post.follow_notifications_created',
                'target_type' => 'community_post',
                'target_id' => (string) $postId,
                'result' => 'success',
                'message' => 'Community post follower notifications created.',
                'metadata' => [
                    'notification_count' => $followNotificationCount,
                ],
            ]);
        }
        if ($createdPostStatus === 'pending') {
            $_SESSION['sr_community_post_notice'] = (string) ($accountGuardWriteDecision['message'] ?? '게시글이 검토 대기 상태로 저장되었습니다.');
            if (!$isGuestAuthor) {
                sr_community_draft_delete($pdo, (int) $account['id'], (int) $board['id'], 'create');
            }
            sr_redirect('/community/my?type=posts');
        }
        if (!$isGuestAuthor) {
            sr_community_draft_delete($pdo, (int) $account['id'], (int) $board['id'], 'create');
        }
        sr_redirect('/community/post?id=' . (string) $postId);
    }

    $_SESSION['sr_community_post_form_flash'] = [
        'action' => 'write',
        'board_id' => (int) $board['id'],
        'errors' => $errors,
        'values' => $values,
        'extra_field_values' => $extraFieldValues,
        'series_values' => $seriesValues,
    ];
    sr_redirect('/community/write?key=' . rawurlencode((string) $board['board_key']));
}

$communityDraftPayload = [];
if (
    !$isGuestAuthor
    && sr_community_draft_autosave_enabled($settings)
    && sr_community_post_drafts_table_exists($pdo)
) {
    try {
        sr_community_draft_cleanup($pdo, $settings, 20);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'community_draft_write_cleanup');
    }
    $communityDraftPayload = sr_community_draft_restore_payload(sr_community_draft_fetch($pdo, (int) $account['id'], (int) $board['id'], 'create'));
}

$skinKey = sr_community_board_skin_key($pdo, $board);
$skinView = sr_community_skin_view($skinKey, 'form');

$communityThemeFallbackViewFile = $skinView;
include sr_community_public_view_file($pdo, $settings, 'form.php', $skinView);
