<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
$communityReactionAvailable = sr_module_enabled($pdo, 'reaction')
    && is_file(SR_ROOT . '/modules/reaction/helpers.php');
if ($communityReactionAvailable) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$communityIdentityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
if ($communityIdentityVerificationModuleAvailable) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}
$communityIdentityRestrictedBoardAvailable = $communityIdentityVerificationModuleAvailable
    && function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, 'community.restricted_board');

$account = sr_member_require_login($pdo);

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_community_settings($pdo);
$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$communitySettingsPermissionPath = $communitySettingsPage === 'levels' ? '/admin/community/levels' : '/admin/community/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $communitySettingsPermissionPath, 'view');
$canViewCommunityThumbnailFileCache = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/storage-cache', 'view');
$communityLayoutOptions = sr_community_layout_options($pdo);
$communityThemeOptions = sr_community_theme_options();
$editorOptions = sr_editor_options($pdo);
$toolbarPresetOptions = sr_community_post_toolbar_preset_options();
$reactionPresetOptions = $communityReactionAvailable && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$communityPrivacyConsentPolicyDocumentsAvailable = sr_community_privacy_consent_policy_documents_available($pdo);
$privacyConsentDocumentOptions = sr_community_privacy_consent_policy_document_options($pdo, (string) ($settings['privacy_consent_document_key'] ?? ''));
foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
    $privacyConsentDocumentOptions += sr_community_privacy_consent_policy_document_options($pdo, (string) ($settings[sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey)] ?? ''));
}
$siteMenuOptions = [];
if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
    require_once SR_ROOT . '/modules/site_menu/helpers.php';
    $siteMenuOptions = sr_site_menu_options($pdo);
}
$assetModuleOptions = sr_community_asset_module_options($pdo);
$assetPolicySets = sr_community_asset_policy_sets($pdo);
$levels = sr_community_levels($pdo, $settings);
$maxLevel = sr_community_max_level_value($settings);
$memberGroups = sr_member_groups($pdo);
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
        continue;
    }

    $enabledMemberGroups[] = $memberGroup;
    $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $communitySettingsPermissionPath, 'edit');

    $intent = sr_post_string('intent', 40);

    if ($intent === 'save_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelDisplayName = sr_community_level_text_setting(sr_post_string('level_display_name', 80), '', 40);
        $levelShortLabel = sr_community_level_text_setting(sr_post_string('level_short_label', 40), '', 20);
        $levelMaxValue = sr_admin_post_int_in_range('level_max_value', 1, 100);
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $levelMaxForValidation = $levelMaxValue !== null ? $levelMaxValue : $maxLevel;
        $identityRestrictedBoardRequired = ($_POST['identity_restricted_board_required'] ?? '') === '1';
        if (!$communityIdentityRestrictedBoardAvailable && $identityRestrictedBoardRequired) {
            $errors[] = '제한 게시판 본인확인을 사용하려면 본인확인 사용을 켜고 제한 게시판 목적을 지원하는 제공자를 설정하세요.';
            $identityRestrictedBoardRequired = false;
        }
        $reportAutoActionEnabled = ($_POST['report_auto_action_enabled'] ?? '') === '1';
        $reportAutoActionThreshold = sr_admin_post_int_in_range('report_auto_action_threshold', 2, 100);
        $reportAutoActionWindowDays = sr_admin_post_int_in_range('report_auto_action_window_days', 0, 365);
        $reportAutoActionPublicModeInput = sr_post_string('report_auto_action_public_mode', 20);
        $reportAutoActionPublicMode = in_array($reportAutoActionPublicModeInput, ['exclude', 'placeholder'], true)
            ? $reportAutoActionPublicModeInput
            : 'exclude';
        $accountGuardPublicationHoldEnabled = ($_POST['account_guard_publication_hold_enabled'] ?? '') === '1';
        $accountGuardPublicationHoldThreshold = sr_admin_post_int_in_range('account_guard_publication_hold_threshold', 2, 20);
        $accountGuardPublicationHoldOverlapReviewPercent = sr_admin_post_int_in_range('account_guard_publication_hold_overlap_review_percent', 0, 100);
        $accountGuardPublicationHoldDurationMinutes = sr_admin_post_int_in_range('account_guard_publication_hold_duration_minutes', 10, 10080);
        $accountGuardConfirmedHoldEnabled = ($_POST['account_guard_confirmed_hold_enabled'] ?? '') === '1';
        $accountGuardConfirmedHoldThreshold = sr_admin_post_int_in_range('account_guard_confirmed_hold_threshold', 2, 20);
        $accountGuardConfirmedHoldWindowDays = sr_admin_post_int_in_range('account_guard_confirmed_hold_window_days', 1, 365);
        $accountGuardConfirmedHoldDurationMinutes = sr_admin_post_int_in_range('account_guard_confirmed_hold_duration_minutes', 10, 10080);
        $postEditorInput = sr_post_string('post_editor', 30);
        $postEditor = sr_editor_effective_key($pdo, sr_community_post_editor_key($postEditorInput));
        $postToolbarPresetInput = sr_post_string('post_toolbar_preset', 80);
        $postToolbarPreset = sr_community_post_toolbar_preset_key($postToolbarPresetInput);
        $postBodyMaxSettingLength = sr_community_post_body_setting_max_length();
        $postBodyMinLength = sr_admin_post_int_in_range('post_body_min_length', 0, $postBodyMaxSettingLength);
        $postBodyMaxLength = sr_admin_post_int_in_range('post_body_max_length', 0, $postBodyMaxSettingLength);
        $externalEmbedEnabled = ($_POST['external_embed_enabled'] ?? '') === '1';
        $internalEmbedEnabled = ($_POST['internal_embed_enabled'] ?? '') === '1';
        $businessInfoVisible = ($_POST['business_info_visible'] ?? '') === '1';
        $plainTextAutoLinkUrls = ($_POST['plain_text_auto_link_urls'] ?? '') === '1';
        $plainTextAutoLinkNewTab = ($_POST['plain_text_auto_link_new_tab'] ?? '') === '1';
        $secretPostsEnabled = ($_POST['secret_posts_enabled'] ?? '') === '1';
        $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
        $thumbnailEnabled = ($_POST['thumbnail_enabled'] ?? '') === '1';
        $thumbnailCriterionInput = sr_post_string('thumbnail_criterion', 20);
        $thumbnailCriterion = sr_community_thumbnail_criterion($thumbnailCriterionInput);
        $thumbnailMinWidth = sr_admin_post_int_in_range('thumbnail_min_width', 1, 4000);
        $thumbnailMinBytes = sr_admin_post_int_in_range('thumbnail_min_bytes', 0, 20971520);
        $privacyConsentEnabled = ($_POST['privacy_consent_enabled'] ?? '') === '1';
        $privacyConsentDocumentKeys = [];
        $privacyConsentRequires = [];
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            $privacyConsentDocumentKeys[$privacyConsentTargetKey] = array_key_exists($privacyConsentDocumentSettingKey, $_POST)
                ? sr_community_privacy_consent_clean_document_key(sr_post_string($privacyConsentDocumentSettingKey, 80))
                : sr_community_privacy_consent_admin_document_key_from_settings($settings, $privacyConsentTargetKey);
            $privacyConsentRequires[$privacyConsentTargetKey] = $privacyConsentDocumentKeys[$privacyConsentTargetKey] !== '';
        }
        $selectedPrivacyConsentDocumentKeys = array_filter($privacyConsentDocumentKeys, static fn (string $value): bool => $value !== '');
        $privacyConsentDocumentKey = (string) (reset($selectedPrivacyConsentDocumentKeys) ?: ($settings['privacy_consent_document_key'] ?? 'community_privacy_default'));
        $privacyConsentRequirePost = !empty($privacyConsentRequires['post']);
        $privacyConsentRequireComment = !empty($privacyConsentRequires['comment']);
        $privacyConsentRequireAttachmentUpload = !empty($privacyConsentRequires['attachment_upload']);
        if (!$communityPrivacyConsentPolicyDocumentsAvailable) {
            if ($privacyConsentEnabled) {
                $errors[] = '개인정보 수집 및 이용동의를 사용하려면 약관/방침 관리 모듈을 활성화하고 게시된 정책 문서를 먼저 준비하세요.';
            }
            $privacyConsentEnabled = false;
            $privacyConsentDocumentKeys = array_fill_keys(sr_community_privacy_consent_target_keys(), '');
            $privacyConsentDocumentKey = 'community_privacy_default';
            $privacyConsentRequirePost = false;
            $privacyConsentRequireComment = false;
            $privacyConsentRequireAttachmentUpload = false;
        }
        $reactionEnabledInput = ($_POST['reaction_enabled'] ?? '') === '1';
        $reactionPostPresetInput = sr_post_string('reaction_post_preset_key', 80);
        $reactionCommentPresetInput = sr_post_string('reaction_comment_preset_key', 80);
        $reactionEnabled = $communityReactionAvailable && $reactionEnabledInput;
        $reactionPostPresetKey = $communityReactionAvailable && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $reactionPostPresetInput) : '';
        $reactionCommentPresetKey = $communityReactionAvailable && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $reactionCommentPresetInput) : '';
        $onceHistoryPolicyInput = sr_post_string('once_history_policy', 40);
        $onceHistoryPolicy = sr_community_once_history_policy($onceHistoryPolicyInput);
        $layoutKey = sr_public_layout_normalize_key(sr_post_string('layout_key', 80));
        $themeKey = sr_view_theme_post_key(sr_post_string('theme_key', 80));
        $layoutPrimaryMenuKey = sr_community_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60));
        $layoutExtraMenuItems = sr_community_layout_extra_menu_items_from_pair_values($_POST['layout_extra_menu_area_keys'] ?? [], $_POST['layout_extra_menu_labels'] ?? [], $_POST['layout_extra_menu_keys'] ?? []);
        $layoutExtraMenuKeys = sr_community_layout_extra_menu_keys_from_value($layoutExtraMenuItems);
        $seriesEnabled = ($_POST['series_enabled'] ?? '') === '1';
        $draftAutosaveEnabled = ($_POST['draft_autosave_enabled'] ?? '') === '1';
        $draftAutosaveIntervalSeconds = sr_admin_post_int_in_range('draft_autosave_interval_seconds', 30, 600);
        $draftRetentionDays = sr_admin_post_int_in_range('draft_retention_days', 1, 30);
        $draftMaxCountPerAccount = sr_admin_post_int_in_range('draft_max_count_per_account', 1, 100);
        $defaultSettlementCurrency = sr_site_default_currency($pdo);
        $assetSettings = [];
        foreach (sr_community_module_asset_setting_prefixes() as $assetPrefix) {
            $policySetIds = sr_community_asset_policy_set_ids_from_value($_POST[$assetPrefix . '_policy_set_ids'] ?? []);
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? '', true), true)
                : sr_community_asset_module_key_or_empty(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
            $assetSettings[$assetPrefix . '_settlement_currency'] = sr_community_asset_settlement_currency($pdo, [
                'asset_settlement_currency' => (string) ($settings[$assetPrefix . '_settlement_currency'] ?? $defaultSettlementCurrency),
            ]);
            $assetSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_policy_set_selection_json_from_ids($policySetIds);
            $assetSettings[$assetPrefix . '_policy_set_id'] = sr_community_asset_policy_set_first_id($policySetIds);
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $assetModules = sr_community_asset_module_keys_from_value($assetSettings[$assetPrefix . '_asset_module'], true);
                $assetSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                    sr_community_asset_amounts_from_post($assetPrefix . '_amounts', $assetModules, (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0))
                );
                $assetSettings[$assetPrefix . '_amount'] = sr_community_asset_amount_total(
                    sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'], $assetModules),
                    (int) ($assetSettings[$assetPrefix . '_amount'] ?? 0)
                );
            }
        }
        $assetSettings['post_reward_reversal_enabled'] = ($_POST['post_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['comment_reward_reversal_enabled'] = ($_POST['comment_reward_reversal_enabled'] ?? '') === '1';
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_publisher_reward_enabled'] = ($_POST['paid_attachment_download_publisher_reward_enabled'] ?? '') === '1';
        $assetSettings['paid_attachment_download_publisher_reward_rate'] = sr_admin_post_int_in_range('paid_attachment_download_publisher_reward_rate', 0, 100);
        $multiAssetPaymentEnabled = ($_POST['multi_asset_payment_enabled'] ?? '') === '1';
        $beforeAssetSettings = sr_community_asset_settings_for_audit($settings, true);

        if (!$levelEnabled) {
            $levelDisplayName = (string) $settings['level_display_name'];
            $levelShortLabel = (string) $settings['level_short_label'];
            $levelMaxValue = (int) $settings['level_max_value'];
            $levelAutoRecalculate = false;
            $levelPostScore = (int) $settings['level_post_score'];
            $levelCommentScore = (int) $settings['level_comment_score'];
        } elseif (!$levelAutoRecalculate) {
            $levelPostScore = (int) $settings['level_post_score'];
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($levelEnabled && $levelDisplayName === '') {
            $errors[] = '레벨 표시명을 입력하세요.';
            $levelDisplayName = (string) $settings['level_display_name'];
        }

        if ($levelEnabled && $levelAutoRecalculate && $levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelEnabled && $levelMaxValue === null) {
            $errors[] = sr_t('community::action.admin.level_max_value_invalid');
            $levelMaxValue = (int) $settings['level_max_value'];
        }

        if ($levelEnabled && $levelAutoRecalculate && $levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        $levelMaxChanged = $levelMaxValue !== (int) $settings['level_max_value'];
        if ($levelEnabled && $levelMaxChanged && (
            sr_post_string('level_max_change_confirmed', 1) !== '1'
            || sr_post_string('level_max_change_confirm_text', 40) !== sr_t('community::ui.level_max_change_confirmation_text')
        )) {
            $errors[] = sr_t('community::action.admin.level_max_change_confirmation_required');
        }

        if ($reportAutoActionThreshold === null) {
            $errors[] = '신고 자동 임시 조치 임계값은 2 이상 100 이하로 입력하세요.';
            $reportAutoActionThreshold = (int) ($settings['report_auto_action_threshold'] ?? 5);
        }
        if ($reportAutoActionWindowDays === null) {
            $errors[] = '신고 자동 임시 조치 집계 기간은 0 이상 365 이하로 입력하세요.';
            $reportAutoActionWindowDays = (int) ($settings['report_auto_action_window_days'] ?? 0);
        }
        if ($reportAutoActionPublicModeInput !== $reportAutoActionPublicMode) {
            $errors[] = '신고 자동 임시 조치 공개 처리 방식 값이 올바르지 않습니다.';
            $reportAutoActionPublicMode = (string) ($settings['report_auto_action_public_mode'] ?? 'exclude');
        }
        if ($accountGuardPublicationHoldThreshold === null) {
            $errors[] = '반복 신고 작성자 게시 보류의 자동조치 게시글 수는 2 이상 20 이하로 입력하세요.';
            $accountGuardPublicationHoldThreshold = (int) ($settings['account_guard_publication_hold_threshold'] ?? 3);
        }
        if ($accountGuardPublicationHoldOverlapReviewPercent === null) {
            $errors[] = '반복 신고 작성자 게시 보류의 신고자 중복률 검토 기준은 0 이상 100 이하로 입력하세요.';
            $accountGuardPublicationHoldOverlapReviewPercent = (int) ($settings['account_guard_publication_hold_overlap_review_percent'] ?? 80);
        }
        if ($accountGuardPublicationHoldDurationMinutes === null) {
            $errors[] = '반복 신고 작성자 게시 보류 기간은 10분 이상 10080분 이하로 입력하세요.';
            $accountGuardPublicationHoldDurationMinutes = (int) ($settings['account_guard_publication_hold_duration_minutes'] ?? 120);
        }
        if ($accountGuardConfirmedHoldThreshold === null) {
            $errors[] = '확정 조치 반복 게시 보류의 확정 조치 건수는 2 이상 20 이하로 입력하세요.';
            $accountGuardConfirmedHoldThreshold = (int) ($settings['account_guard_confirmed_hold_threshold'] ?? 3);
        }
        if ($accountGuardConfirmedHoldWindowDays === null) {
            $errors[] = '확정 조치 반복 게시 보류의 집계 기간은 1일 이상 365일 이하로 입력하세요.';
            $accountGuardConfirmedHoldWindowDays = (int) ($settings['account_guard_confirmed_hold_window_days'] ?? 30);
        }
        if ($accountGuardConfirmedHoldDurationMinutes === null) {
            $errors[] = '확정 조치 반복 게시 보류 기간은 10분 이상 10080분 이하로 입력하세요.';
            $accountGuardConfirmedHoldDurationMinutes = (int) ($settings['account_guard_confirmed_hold_duration_minutes'] ?? 1440);
        }

        if (!isset($communityLayoutOptions[$layoutKey])) {
            $errors[] = sr_t('community::action.admin.layout_invalid');
            $layoutKey = sr_community_layout_key($settings, $site ?? null, $pdo);
        }
        if (!isset($communityThemeOptions[$themeKey])) {
            $errors[] = '커뮤니티 공개 테마 값이 올바르지 않습니다.';
            $themeKey = sr_community_theme_key((string) ($settings['theme_key'] ?? 'basic'));
        }
        foreach (array_merge([$layoutPrimaryMenuKey], $layoutExtraMenuKeys) as $layoutMenuKey) {
            if ($layoutMenuKey !== '' && !isset($siteMenuOptions[$layoutMenuKey]) && !sr_community_layout_menu_key_is_builtin($layoutMenuKey)) {
                $errors[] = '레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
                break;
            }
        }
        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시글 에디터 값이 올바르지 않습니다.';
            $postEditor = (string) ($settings['post_editor'] ?? 'textarea');
        }
        if ($postToolbarPresetInput !== $postToolbarPreset || !array_key_exists($postToolbarPreset, $toolbarPresetOptions)) {
            $errors[] = '게시글 툴바 구성 값이 올바르지 않습니다.';
            $postToolbarPreset = (string) ($settings['post_toolbar_preset'] ?? 'community_post_basic');
        }
        if ($postBodyMinLength === null) {
            $errors[] = '게시글 본문 최소 길이가 올바르지 않습니다.';
            $postBodyMinLength = (int) ($settings['post_body_min_length'] ?? 0);
        }
        if ($postBodyMaxLength === null) {
            $errors[] = '게시글 본문 최대 길이가 올바르지 않습니다.';
            $postBodyMaxLength = (int) ($settings['post_body_max_length'] ?? 0);
        }
        if ($postBodyMinLength > 0 && $postBodyMaxLength > 0 && $postBodyMinLength > $postBodyMaxLength) {
            $errors[] = '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($draftAutosaveIntervalSeconds === null) {
            $errors[] = '임시저장 간격은 30초 이상 600초 이하로 입력하세요.';
            $draftAutosaveIntervalSeconds = (int) ($settings['draft_autosave_interval_seconds'] ?? 60);
        }
        if ($draftRetentionDays === null) {
            $errors[] = '임시저장 보존기간은 1일 이상 30일 이하로 입력하세요.';
            $draftRetentionDays = (int) ($settings['draft_retention_days'] ?? 7);
        }
        if ($draftMaxCountPerAccount === null) {
            $errors[] = '계정당 임시저장 최대 개수는 1개 이상 100개 이하로 입력하세요.';
            $draftMaxCountPerAccount = (int) ($settings['draft_max_count_per_account'] ?? 20);
        }
        if ($thumbnailCriterionInput !== $thumbnailCriterion) {
            $errors[] = '썸네일 생성 기준 선택이 올바르지 않습니다.';
        }
        if ($thumbnailCriterion === 'width' && $thumbnailMinWidth === null) {
            $errors[] = '썸네일 생성 기준 너비가 올바르지 않습니다.';
            $thumbnailMinWidth = 320;
        }
        if ($thumbnailCriterion === 'bytes' && $thumbnailMinBytes === null) {
            $errors[] = '썸네일 생성 기준 용량이 올바르지 않습니다.';
            $thumbnailMinBytes = 102400;
        }
        if ($thumbnailMinWidth === null) {
            $thumbnailMinWidth = 320;
        }
        if ($thumbnailMinBytes === null) {
            $thumbnailMinBytes = 102400;
        }
        if ($privacyConsentEnabled) {
            if (!sr_community_submission_consents_table_exists($pdo)) {
                $errors[] = '개인정보 수집 및 이용동의 스키마 업데이트가 아직 적용되지 않았습니다.';
            }
            if (!$privacyConsentRequirePost && !$privacyConsentRequireComment && !$privacyConsentRequireAttachmentUpload) {
                $errors[] = '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.';
            }
            foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
                if (empty($privacyConsentRequires[$privacyConsentTargetKey])) {
                    continue;
                }
                $targetDocumentKey = (string) ($privacyConsentDocumentKeys[$privacyConsentTargetKey] ?? '');
                if ($targetDocumentKey === '' || !is_array(sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey))) {
                    $errors[] = sr_community_privacy_consent_admin_label($privacyConsentTargetKey) . ' 정책 문서를 선택해 주세요.';
                }
            }
        }
        if ($onceHistoryPolicyInput !== $onceHistoryPolicy) {
            $errors[] = sr_t('community::action.admin.once_history_policy_invalid');
            $onceHistoryPolicy = (string) ($settings['once_history_policy'] ?? 'all_access');
        }
        if (!$communityReactionAvailable) {
            if ($reactionEnabledInput || $reactionPostPresetInput !== '' || $reactionCommentPresetInput !== '') {
                $errors[] = '커뮤니티 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
            }
        } else {
            foreach (['reaction_post_preset_key' => $reactionPostPresetKey, 'reaction_comment_preset_key' => $reactionCommentPresetKey] as $reactionSettingKey => $reactionPresetKey) {
                if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                    $errors[] = '커뮤니티 리액션 프리셋 값이 올바르지 않습니다.';
                    break;
                }
            }
        }

        foreach (sr_community_module_asset_setting_prefixes() as $assetPrefix) {
            $assetLabel = sr_community_asset_setting_label($assetPrefix);
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (!empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
                    } elseif (!$multiAssetPaymentEnabled && in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true) && count($assetModules) > 1) {
                        $errors[] = $assetLabel . ' 항목은 포인트/금액 항목을 하나만 선택하세요.';
                    }
                    $amounts = sr_community_asset_amounts_from_value($assetSettings[$assetPrefix . '_amounts_json'] ?? '', $assetModules);
                    if (count($amounts) < count($assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_amounts_required', ['label' => $assetLabel]);
                    }
                } elseif (!isset($assetModuleOptions[$assetModule])) {
                    $errors[] = sr_t('community::action.admin.asset_module_inactive', [
                        'label' => $assetLabel,
                        'module' => sr_community_asset_module_label($assetModule, $pdo),
                    ]);
                }
            }
            $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_community_asset_group_policies_from_value($assetSettings[$assetPrefix . '_group_policies_json'] ?? ''), $assetLabel));
            $assetPolicySetIds = sr_community_asset_policy_set_ids_with_legacy($assetSettings[$assetPrefix . '_group_policies_json'] ?? '', (int) ($assetSettings[$assetPrefix . '_policy_set_id'] ?? 0));
            $assetModulesForPolicy = sr_community_asset_module_keys_from_value((string) ($assetSettings[$assetPrefix . '_asset_module'] ?? ''), true);
            $errors = array_merge($errors, sr_community_asset_policy_set_ids_validation_errors($pdo, $assetPolicySetIds, $assetLabel));
            $errors = array_merge($errors, sr_community_asset_policy_set_asset_match_errors($pdo, $assetPolicySetIds, $assetModulesForPolicy, $assetLabel));
        }
        if ($assetSettings['paid_attachment_download_publisher_reward_rate'] === null) {
            $errors[] = '첨부 다운로드 게시자 보상 지급률이 올바르지 않습니다.';
            $assetSettings['paid_attachment_download_publisher_reward_rate'] = 0;
        }

        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }

        if ($errors === [] && is_array($communityModule ?? null)) {
            $rows = [
                ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                ['level_display_name', $levelDisplayName, 'string'],
                ['level_short_label', $levelShortLabel, 'string'],
                ['level_max_value', (string) $levelMaxValue, 'int'],
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
                ['identity_restricted_board_required', $identityRestrictedBoardRequired ? '1' : '0', 'bool'],
                ['report_auto_action_enabled', $reportAutoActionEnabled ? '1' : '0', 'bool'],
                ['report_auto_action_threshold', (string) $reportAutoActionThreshold, 'int'],
                ['report_auto_action_window_days', (string) $reportAutoActionWindowDays, 'int'],
                ['report_auto_action_public_mode', $reportAutoActionPublicMode, 'string'],
                ['account_guard_publication_hold_enabled', $accountGuardPublicationHoldEnabled ? '1' : '0', 'bool'],
                ['account_guard_publication_hold_threshold', (string) $accountGuardPublicationHoldThreshold, 'int'],
                ['account_guard_publication_hold_overlap_review_percent', (string) $accountGuardPublicationHoldOverlapReviewPercent, 'int'],
                ['account_guard_publication_hold_duration_minutes', (string) $accountGuardPublicationHoldDurationMinutes, 'int'],
                ['account_guard_confirmed_hold_enabled', $accountGuardConfirmedHoldEnabled ? '1' : '0', 'bool'],
                ['account_guard_confirmed_hold_threshold', (string) $accountGuardConfirmedHoldThreshold, 'int'],
                ['account_guard_confirmed_hold_window_days', (string) $accountGuardConfirmedHoldWindowDays, 'int'],
                ['account_guard_confirmed_hold_duration_minutes', (string) $accountGuardConfirmedHoldDurationMinutes, 'int'],
                ['layout_key', $layoutKey, 'string'],
                ['theme_key', $themeKey, 'string'],
                ['layout_primary_menu_key', $layoutPrimaryMenuKey, 'string'],
                ['layout_extra_menu_keys_json', sr_community_layout_extra_menu_keys_json($layoutExtraMenuItems), 'json'],
                ['business_info_visible', $businessInfoVisible ? '1' : '0', 'bool'],
                ['series_enabled', $seriesEnabled ? '1' : '0', 'bool'],
                ['draft_autosave_enabled', $draftAutosaveEnabled ? '1' : '0', 'bool'],
                ['draft_autosave_interval_seconds', (string) $draftAutosaveIntervalSeconds, 'int'],
                ['draft_retention_days', (string) $draftRetentionDays, 'int'],
                ['draft_max_count_per_account', (string) $draftMaxCountPerAccount, 'int'],
                ['post_editor', $postEditor, 'string'],
                ['post_toolbar_preset', $postToolbarPreset, 'string'],
                ['post_body_min_length', (string) $postBodyMinLength, 'int'],
                ['post_body_max_length', (string) $postBodyMaxLength, 'int'],
                ['external_embed_enabled', $externalEmbedEnabled ? '1' : '0', 'bool'],
                ['internal_embed_enabled', $internalEmbedEnabled ? '1' : '0', 'bool'],
                ['plain_text_auto_link_urls', $plainTextAutoLinkUrls ? '1' : '0', 'bool'],
                ['plain_text_auto_link_new_tab', $plainTextAutoLinkNewTab ? '1' : '0', 'bool'],
                ['secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool'],
                ['secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool'],
                ['thumbnail_enabled', $thumbnailEnabled ? '1' : '0', 'bool'],
                ['thumbnail_criterion', $thumbnailCriterion, 'string'],
                ['thumbnail_min_width', (string) $thumbnailMinWidth, 'int'],
                ['thumbnail_min_bytes', (string) $thumbnailMinBytes, 'int'],
                ['privacy_consent_enabled', $privacyConsentEnabled ? '1' : '0', 'bool'],
                ['privacy_consent_document_key', $privacyConsentDocumentKey !== '' ? $privacyConsentDocumentKey : 'community_privacy_default', 'string'],
                ['privacy_consent_post_document_key', (string) ($privacyConsentDocumentKeys['post'] ?? ''), 'string'],
                ['privacy_consent_comment_document_key', (string) ($privacyConsentDocumentKeys['comment'] ?? ''), 'string'],
                ['privacy_consent_attachment_upload_document_key', (string) ($privacyConsentDocumentKeys['attachment_upload'] ?? ''), 'string'],
                ['privacy_consent_document_inherit_policy', 'override', 'string'],
                ['privacy_consent_title', '', 'string'],
                ['privacy_consent_body', '', 'string'],
                ['privacy_consent_version', '', 'string'],
                ['privacy_consent_require_post', $privacyConsentRequirePost ? '1' : '0', 'bool'],
                ['privacy_consent_require_comment', $privacyConsentRequireComment ? '1' : '0', 'bool'],
                ['privacy_consent_require_attachment_upload', $privacyConsentRequireAttachmentUpload ? '1' : '0', 'bool'],
                ['reaction_enabled', $reactionEnabled ? '1' : '0', 'bool'],
                ['reaction_post_preset_key', $reactionPostPresetKey, 'string'],
                ['reaction_comment_preset_key', $reactionCommentPresetKey, 'string'],
                ['post_reward_enabled', $assetSettings['post_reward_enabled'] ? '1' : '0', 'bool'],
                ['post_reward_asset_module', (string) $assetSettings['post_reward_asset_module'], 'string'],
                ['post_reward_amount', (string) $assetSettings['post_reward_amount'], 'int'],
                ['post_reward_group_policies_json', (string) $assetSettings['post_reward_group_policies_json'], 'json'],
                ['post_reward_policy_set_id', (string) $assetSettings['post_reward_policy_set_id'], 'int'],
                ['post_reward_reversal_enabled', $assetSettings['post_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_enabled', $assetSettings['comment_reward_enabled'] ? '1' : '0', 'bool'],
                ['comment_reward_asset_module', (string) $assetSettings['comment_reward_asset_module'], 'string'],
                ['comment_reward_amount', (string) $assetSettings['comment_reward_amount'], 'int'],
                ['comment_reward_group_policies_json', (string) $assetSettings['comment_reward_group_policies_json'], 'json'],
                ['comment_reward_policy_set_id', (string) $assetSettings['comment_reward_policy_set_id'], 'int'],
                ['comment_reward_reversal_enabled', $assetSettings['comment_reward_reversal_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_enabled', $assetSettings['write_charge_enabled'] ? '1' : '0', 'bool'],
                ['write_charge_asset_module', (string) $assetSettings['write_charge_asset_module'], 'string'],
                ['write_charge_amount', (string) $assetSettings['write_charge_amount'], 'int'],
                ['write_charge_settlement_currency', (string) $assetSettings['write_charge_settlement_currency'], 'string'],
                ['write_charge_amounts_json', (string) $assetSettings['write_charge_amounts_json'], 'json'],
                ['write_charge_group_policies_json', (string) $assetSettings['write_charge_group_policies_json'], 'json'],
                ['write_charge_policy_set_id', (string) $assetSettings['write_charge_policy_set_id'], 'int'],
                ['comment_charge_enabled', $assetSettings['comment_charge_enabled'] ? '1' : '0', 'bool'],
                ['comment_charge_asset_module', (string) $assetSettings['comment_charge_asset_module'], 'string'],
                ['comment_charge_amount', (string) $assetSettings['comment_charge_amount'], 'int'],
                ['comment_charge_settlement_currency', (string) $assetSettings['comment_charge_settlement_currency'], 'string'],
                ['comment_charge_amounts_json', (string) $assetSettings['comment_charge_amounts_json'], 'json'],
                ['comment_charge_group_policies_json', (string) $assetSettings['comment_charge_group_policies_json'], 'json'],
                ['comment_charge_policy_set_id', (string) $assetSettings['comment_charge_policy_set_id'], 'int'],
                ['paid_read_enabled', $assetSettings['paid_read_enabled'] ? '1' : '0', 'bool'],
                ['paid_read_asset_module', (string) $assetSettings['paid_read_asset_module'], 'string'],
                ['paid_read_amount', (string) $assetSettings['paid_read_amount'], 'int'],
                ['paid_read_settlement_currency', (string) $assetSettings['paid_read_settlement_currency'], 'string'],
                ['paid_read_amounts_json', (string) $assetSettings['paid_read_amounts_json'], 'json'],
                ['paid_read_group_policies_json', (string) $assetSettings['paid_read_group_policies_json'], 'json'],
                ['paid_read_policy_set_id', (string) $assetSettings['paid_read_policy_set_id'], 'int'],
                ['paid_read_charge_policy', (string) $assetSettings['paid_read_charge_policy'], 'string'],
                ['once_history_policy', $onceHistoryPolicy, 'string'],
                ['paid_attachment_download_enabled', $assetSettings['paid_attachment_download_enabled'] ? '1' : '0', 'bool'],
                ['paid_attachment_download_asset_module', (string) $assetSettings['paid_attachment_download_asset_module'], 'string'],
                ['paid_attachment_download_amount', (string) $assetSettings['paid_attachment_download_amount'], 'int'],
                ['paid_attachment_download_settlement_currency', (string) $assetSettings['paid_attachment_download_settlement_currency'], 'string'],
                ['paid_attachment_download_amounts_json', (string) $assetSettings['paid_attachment_download_amounts_json'], 'json'],
                ['paid_attachment_download_group_policies_json', (string) $assetSettings['paid_attachment_download_group_policies_json'], 'json'],
                ['paid_attachment_download_policy_set_id', (string) $assetSettings['paid_attachment_download_policy_set_id'], 'int'],
                ['paid_attachment_download_charge_policy', (string) $assetSettings['paid_attachment_download_charge_policy'], 'string'],
                ['paid_attachment_download_publisher_reward_enabled', $assetSettings['paid_attachment_download_publisher_reward_enabled'] ? '1' : '0', 'bool'],
                ['paid_attachment_download_publisher_reward_rate', (string) $assetSettings['paid_attachment_download_publisher_reward_rate'], 'int'],
                ['multi_asset_payment_enabled', $multiAssetPaymentEnabled ? '1' : '0', 'bool'],
            ];
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_module_settings
                        (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                     VALUES
                        (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        value_type = VALUES(value_type),
                        updated_at = VALUES(updated_at)'
                );
                $now = sr_now();
                foreach ($rows as $row) {
                    $stmt->execute([
                        'module_id' => (int) $communityModule['id'],
                        'setting_key' => $row[0],
                        'setting_value' => $row[1],
                        'value_type' => $row[2],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $createdLevelCount = sr_community_ensure_level_definitions($pdo, (int) $levelMaxValue);
                $pdo->commit();
                sr_clear_module_settings_cache('community');
                $settings = sr_community_settings($pdo);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'result' => 'success',
                    'message' => 'Community settings updated.',
                    'metadata' => [
                        'level_enabled' => $levelEnabled,
                        'level_max_value' => $levelMaxValue,
                        'created_level_count' => $createdLevelCount,
                        'level_auto_recalculate' => $levelAutoRecalculate,
                        'identity_restricted_board_required' => $identityRestrictedBoardRequired,
                        'report_auto_action_enabled' => $reportAutoActionEnabled,
                        'report_auto_action_threshold' => $reportAutoActionThreshold,
                        'report_auto_action_window_days' => $reportAutoActionWindowDays,
                        'report_auto_action_public_mode' => $reportAutoActionPublicMode,
                        'account_guard_publication_hold_enabled' => $accountGuardPublicationHoldEnabled,
                        'account_guard_publication_hold_threshold' => $accountGuardPublicationHoldThreshold,
                        'account_guard_publication_hold_overlap_review_percent' => $accountGuardPublicationHoldOverlapReviewPercent,
                        'account_guard_publication_hold_duration_minutes' => $accountGuardPublicationHoldDurationMinutes,
                        'account_guard_confirmed_hold_enabled' => $accountGuardConfirmedHoldEnabled,
                        'account_guard_confirmed_hold_threshold' => $accountGuardConfirmedHoldThreshold,
                        'account_guard_confirmed_hold_window_days' => $accountGuardConfirmedHoldWindowDays,
                        'account_guard_confirmed_hold_duration_minutes' => $accountGuardConfirmedHoldDurationMinutes,
                        'layout_key' => $layoutKey,
                        'theme_key' => $themeKey,
                        'layout_primary_menu_key' => $layoutPrimaryMenuKey,
                        'layout_extra_menu_keys_json' => $layoutExtraMenuItems,
                        'business_info_visible' => $businessInfoVisible,
                        'series_enabled' => $seriesEnabled,
                        'post_editor' => $postEditor,
                        'post_toolbar_preset' => $postToolbarPreset,
                        'plain_text_auto_link_urls' => $plainTextAutoLinkUrls,
                        'plain_text_auto_link_new_tab' => $plainTextAutoLinkNewTab,
                        'secret_posts_enabled' => $secretPostsEnabled,
                        'secret_comments_enabled' => $secretCommentsEnabled,
                        'thumbnail_enabled' => $thumbnailEnabled,
                        'thumbnail_criterion' => $thumbnailCriterion,
                        'thumbnail_min_width' => $thumbnailMinWidth,
                        'thumbnail_min_bytes' => $thumbnailMinBytes,
                        'privacy_consent_enabled' => $privacyConsentEnabled,
                        'privacy_consent_targets' => array_values(array_filter([
                            $privacyConsentRequirePost ? 'post' : '',
                            $privacyConsentRequireComment ? 'comment' : '',
                            $privacyConsentRequireAttachmentUpload ? 'attachment_upload' : '',
                        ])),
                        'reaction_post_preset_key' => $reactionPostPresetKey,
                        'reaction_comment_preset_key' => $reactionCommentPresetKey,
                        'reaction_enabled' => $reactionEnabled,
                        'once_history_policy' => $onceHistoryPolicy,
                        'asset_settings' => $assetSettings,
                    ],
                ]);
                sr_admin_audit_asset_settings_update($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.settings.asset_settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'asset_settings_scope' => 'community.settings',
                    'before_asset_settings' => $beforeAssetSettings,
                    'after_asset_settings' => sr_community_asset_settings_for_audit($assetSettings, true),
                    'message' => 'Community asset settings updated.',
                ]);

                $notice = $createdLevelCount > 0
                    ? sr_t('community::action.admin.settings_saved_levels_created', ['count' => (string) $createdLevelCount])
                    : sr_t('community::action.admin.settings_saved');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'community_settings_save_failed');
                $errors[] = sr_t('community::action.admin.settings_save_failed');
            }
        }
    } elseif ($intent === 'save_level_settings') {
        $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
        $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);

        if (!$levelEnabled) {
            $levelAutoRecalculate = false;
            $levelPostScore = (int) $settings['level_post_score'];
            $levelCommentScore = (int) $settings['level_comment_score'];
        } elseif (!$levelAutoRecalculate) {
            $levelPostScore = (int) $settings['level_post_score'];
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($levelEnabled && $levelAutoRecalculate && $levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }
        if ($levelEnabled && $levelAutoRecalculate && $levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }
        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }
        if ($errors === [] && is_array($communityModule ?? null)) {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_module_settings
                    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                 VALUES
                    (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    value_type = VALUES(value_type),
                    updated_at = VALUES(updated_at)'
            );
            $now = sr_now();
            foreach ([
                ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                ['level_post_score', (string) $levelPostScore, 'int'],
                ['level_comment_score', (string) $levelCommentScore, 'int'],
            ] as $row) {
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => $row[0],
                    'setting_value' => $row[1],
                    'value_type' => $row[2],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            sr_clear_module_settings_cache('community');
            $settings = sr_community_settings($pdo);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.level_settings.updated',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'success',
                'message' => 'Community level settings updated.',
                'metadata' => [
                    'level_enabled' => $levelEnabled,
                    'level_auto_recalculate' => $levelAutoRecalculate,
                    'level_post_score' => $levelPostScore,
                    'level_comment_score' => $levelCommentScore,
                ],
            ]);
            $notice = sr_t('community::action.admin.level_settings_saved');
        }
    } elseif ($intent === 'save_level_definitions') {
        $levelSettingsSubmitted = array_key_exists('level_post_score', $_POST)
            || array_key_exists('level_comment_score', $_POST);
        $levelEnabled = !empty($settings['level_enabled']);
        $levelAutoRecalculate = !empty($settings['level_auto_recalculate']);
        $levelPostScore = (int) $settings['level_post_score'];
        $levelCommentScore = (int) $settings['level_comment_score'];
        if ($levelSettingsSubmitted) {
            $levelEnabled = ($_POST['level_enabled'] ?? '') === '1';
            $levelAutoRecalculate = ($_POST['level_auto_recalculate'] ?? '') === '1';
            $postedLevelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
            $postedLevelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
            if ($postedLevelPostScore === null) {
                $errors[] = sr_t('community::action.admin.post_score_invalid');
            } else {
                $levelPostScore = $postedLevelPostScore;
            }
            if ($postedLevelCommentScore === null) {
                $errors[] = sr_t('community::action.admin.comment_score_invalid');
            } else {
                $levelCommentScore = $postedLevelCommentScore;
            }
        }
        $levelMaxValue = sr_admin_post_int_in_range('level_max_value', 1, 100);
        if ($levelMaxValue === null) {
            $errors[] = sr_t('community::action.admin.level_max_value_invalid');
            $levelMaxValue = (int) $settings['level_max_value'];
        }
        $levelMaxChanged = $levelMaxValue !== (int) $settings['level_max_value'];
        if ($levelMaxChanged && (
            sr_post_string('level_max_change_confirmed', 1) !== '1'
            || sr_post_string('level_max_change_confirm_text', 40) !== sr_t('community::ui.level_max_change_confirmation_text')
        )) {
            $errors[] = sr_t('community::action.admin.level_max_change_confirmation_required');
        }
        if ($errors === []) {
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
            $stmt->execute();
            $communityModule = $stmt->fetch();
            if (!is_array($communityModule)) {
                $errors[] = sr_t('community::action.admin.module_missing');
            }
        }
        if ($errors === [] && is_array($communityModule ?? null)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_module_settings
                        (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                     VALUES
                        (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        value_type = VALUES(value_type),
                        updated_at = VALUES(updated_at)'
                );
                $now = sr_now();
                $stmt->execute([
                    'module_id' => (int) $communityModule['id'],
                    'setting_key' => 'level_max_value',
                    'setting_value' => (string) $levelMaxValue,
                    'value_type' => 'int',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($levelSettingsSubmitted) {
                    foreach ([
                        ['level_enabled', $levelEnabled ? '1' : '0', 'bool'],
                        ['level_auto_recalculate', $levelAutoRecalculate ? '1' : '0', 'bool'],
                        ['level_post_score', (string) $levelPostScore, 'int'],
                        ['level_comment_score', (string) $levelCommentScore, 'int'],
                    ] as $row) {
                        $stmt->execute([
                            'module_id' => (int) $communityModule['id'],
                            'setting_key' => $row[0],
                            'setting_value' => $row[1],
                            'value_type' => $row[2],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
                $createdLevelCount = sr_community_ensure_level_definitions($pdo, (int) $levelMaxValue);
                $pdo->commit();
                sr_clear_module_settings_cache('community');
                $settings = sr_community_settings($pdo);
                $levels = sr_community_levels($pdo, $settings);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'community_level_max_save_failed');
                $errors[] = sr_t('community::action.admin.settings_save_failed');
            }
        }

        $rawMinScores = $_POST['level_min_score'] ?? [];
        if (!is_array($rawMinScores)) {
            $errors[] = sr_t('community::action.admin.level_min_score_input_invalid');
        }

        $minScoresById = [];
        foreach ($levels as $level) {
            $levelId = (int) ($level['id'] ?? 0);
            if ($levelId < 1) {
                continue;
            }

            $rawValue = is_array($rawMinScores) && array_key_exists((string) $levelId, $rawMinScores)
                ? $rawMinScores[(string) $levelId]
                : (string) ($level['min_score'] ?? '0');
            if (is_array($rawValue)) {
                $errors[] = sr_t('community::action.admin.level_min_score_input_invalid');
                continue;
            }

            $value = trim((string) $rawValue);
            if ($value === '' || strlen($value) > 10 || preg_match('/\A\d+\z/', $value) !== 1) {
                $errors[] = sr_t('community::action.admin.level_min_score_invalid', ['level' => (string) $level['level_value']]);
                continue;
            }

            $minScore = (int) $value;
            if ($minScore < 0 || $minScore > 1000000000) {
                $errors[] = sr_t('community::action.admin.level_min_score_invalid', ['level' => (string) $level['level_value']]);
                continue;
            }

            $minScoresById[$levelId] = $minScore;
        }

        if ($errors === []) {
            try {
                $updatedCount = sr_community_update_level_min_scores($pdo, $minScoresById, $settings);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.level_definitions.updated',
                    'target_type' => 'module',
                    'target_id' => 'community',
                    'result' => 'success',
                    'message' => 'Community level definitions updated.',
                    'metadata' => [
                        'updated_count' => $updatedCount,
                        'created_level_count' => (int) ($createdLevelCount ?? 0),
                        'level_max_value' => (int) $settings['level_max_value'],
                        'level_settings_submitted' => $levelSettingsSubmitted,
                        'level_enabled' => $levelEnabled,
                        'level_auto_recalculate' => $levelAutoRecalculate,
                        'level_post_score' => $levelPostScore,
                        'level_comment_score' => $levelCommentScore,
                    ],
                ]);
                if (($createdLevelCount ?? 0) > 0) {
                    $notice = sr_t('community::action.admin.level_definitions_saved_levels_created', ['count' => (string) $createdLevelCount]);
                } else {
                    $notice = ($updatedCount > 0 || $levelSettingsSubmitted) ? sr_t('community::action.admin.level_definitions_saved') : sr_t('community::action.admin.level_definitions_no_changes');
                }
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($intent === 'recalculate_levels') {
        if (empty($settings['level_enabled'])) {
            $errors[] = sr_t('community::action.admin.level_recalculate_disabled');
        } elseif (sr_post_string('recalculate_confirmed', 1) !== '1' || sr_post_string('recalculate_confirm_text', 40) !== sr_t('community::ui.level_recalculate_confirmation_text')) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.levels.recalculate_confirmation_failed',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'failure',
                'message' => 'Community level recalculation confirmation failed.',
                'metadata' => [
                    'confirmation_checked' => false,
                    'load_grade' => sr_admin_high_load_assessment([
                        'target_records' => sr_community_recalculate_target_account_count($pdo),
                        'table_count' => 4,
                        'batch_available' => true,
                    ])['grade'],
                ],
            ]);
            $errors[] = sr_t('community::action.admin.level_recalculate_confirmation_required');
        } else {
            $total = sr_community_recalculate_target_account_count($pdo);
            $job = sr_community_level_recalculate_job_create($pdo, (int) $account['id'], $total, 200);
            $jobId = (int) ($job['id'] ?? 0);
            $lockToken = (string) ($job['lock_token'] ?? '');
            $loadAssessment = sr_admin_high_load_assessment([
                'target_records' => $total,
                'table_count' => 4,
                'batch_available' => true,
            ]);
            try {
                $summary = sr_community_recalculate_recent_account_levels($pdo, 200);
                sr_community_level_recalculate_job_complete($pdo, $jobId, $lockToken, (int) ($summary['next_cursor'] ?? 0), (int) ($summary['accounts'] ?? 0), $total);
            } catch (Throwable $exception) {
                sr_community_level_recalculate_job_fail($pdo, $jobId, $lockToken, $exception);
                sr_log_exception($exception, 'community_level_recalculate_job_failed');
                $errors[] = $exception->getMessage();
                $summary = ['accounts' => 0, 'next_cursor' => 0, 'done' => false];
            }
            if ($errors !== []) {
                $levels = sr_community_levels($pdo, $settings);
                include SR_ROOT . '/modules/community/views/admin-settings.php';
                return;
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.levels.recalculated',
                'target_type' => 'module',
                'target_id' => 'community',
                'result' => 'success',
                'message' => 'Community levels recalculated.',
                'metadata' => array_merge($summary, [
                    'total' => $total,
                    'job_id' => $jobId,
                    'failed_count' => 0,
                    'batch' => false,
                    'load_grade' => (string) $loadAssessment['grade'],
                    'confirmation_checked' => true,
                ]),
            ]);
            $notice = sr_t('community::action.admin.levels_recalculated', ['accounts' => (string) ($summary['accounts'] ?? 0)]);
        }
    } else {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    $levels = sr_community_levels($pdo, $settings);
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), $communitySettingsPage === 'levels' ? '/admin/community/levels' : '/admin/community/settings');
}

$settings['layout_key'] = sr_community_layout_key($settings, $site ?? null, $pdo);

include SR_ROOT . '/modules/community/views/admin-settings.php';
