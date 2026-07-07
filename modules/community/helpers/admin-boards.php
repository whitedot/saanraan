<?php

declare(strict_types=1);

function sr_community_admin_handle_board_save_post(PDO $pdo, string $intent, array $account, array $context): array
{
    $errors = [];
    $notice = '';
    $allowedStatuses = is_array($context['allowed_statuses'] ?? null) ? $context['allowed_statuses'] : [];
    $allowedReadPolicies = is_array($context['allowed_read_policies'] ?? null) ? $context['allowed_read_policies'] : [];
    $allowedWritePolicies = is_array($context['allowed_write_policies'] ?? null) ? $context['allowed_write_policies'] : [];
    $allowedCommentPolicies = is_array($context['allowed_comment_policies'] ?? null) ? $context['allowed_comment_policies'] : [];
    $communitySkinOptions = is_array($context['community_skin_options'] ?? null) ? $context['community_skin_options'] : [];
    $editorOptions = is_array($context['editor_options'] ?? null) ? $context['editor_options'] : [];
    $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
    $maxLevel = (int) ($context['max_level'] ?? 0);
    $publicDisplaySettingLabels = is_array($context['public_display_setting_labels'] ?? null) ? $context['public_display_setting_labels'] : [];
    $publicBannerSettingLabels = is_array($context['public_banner_setting_labels'] ?? null) ? $context['public_banner_setting_labels'] : [];
    $publicPopupLayerSettingLabels = is_array($context['public_popup_layer_setting_labels'] ?? null) ? $context['public_popup_layer_setting_labels'] : [];
    $publicBannerIds = is_array($context['public_banner_ids'] ?? null) ? $context['public_banner_ids'] : [];
    $publicPopupLayerIds = is_array($context['public_popup_layer_ids'] ?? null) ? $context['public_popup_layer_ids'] : [];
    $enabledMemberGroupKeys = is_array($context['enabled_member_group_keys'] ?? null) ? $context['enabled_member_group_keys'] : [];
    $assetModuleOptions = is_array($context['asset_module_options'] ?? null) ? $context['asset_module_options'] : [];
    $reactionPresetOptions = is_array($context['reaction_preset_options'] ?? null) ? $context['reaction_preset_options'] : [];
    $reactionAvailable = sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php');
    $privacyConsentPolicyDocumentsAvailable = sr_community_privacy_consent_policy_documents_available($pdo);
        $boardKey = strtolower(trim(sr_post_string('board_key', 60)));
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $readPolicy = sr_post_string('read_policy', 30);
        $writePolicy = sr_post_string('write_policy', 30);
        $commentPolicy = sr_post_string('comment_policy', 30);
        $identityVerificationEnabled = ($_POST['identity_verification_enabled'] ?? '') === '1';
        $identityVerificationPurpose = sr_community_identity_verification_purpose(sr_post_string('identity_verification_purpose', 30));
        $identityVerificationRequiredActions = sr_community_identity_verification_required_actions_input($_POST['identity_verification_required_actions'] ?? []);
        $identityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
        if ($identityVerificationModuleAvailable) {
            require_once SR_ROOT . '/modules/identity_verification/helpers.php';
        }
        $identityVerificationPurposeKey = $identityVerificationPurpose === 'adult'
            ? 'community.adult_board'
            : 'community.restricted_board';
        $identityVerificationAvailable = $identityVerificationModuleAvailable
            && function_exists('sr_identity_verification_available')
            && sr_identity_verification_available($pdo, $identityVerificationPurposeKey);
        if ($identityVerificationEnabled) {
            if ($identityVerificationRequiredActions === []) {
                $errors[] = '본인확인을 사용할 행위를 1개 이상 선택하세요.';
            }
            if (!$identityVerificationAvailable) {
                $errors[] = '게시판 본인확인을 사용하려면 본인확인 사용을 켜고 선택한 게시판 본인확인 기준을 지원하는 제공자를 설정하세요.';
            }
        }
        $skinKey = sr_post_string('skin_key', 40);
        $postEditorInput = sr_post_string('post_editor', 30);
        $postEditor = sr_editor_effective_key($pdo, sr_community_post_editor_key($postEditorInput));
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
        $attachmentMaxBytes = sr_admin_post_int_in_range('attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = sr_admin_post_int_in_range('attachment_max_count', 0, 10);
        $thumbnailEnabled = ($_POST['thumbnail_enabled'] ?? '') === '1';
        $thumbnailCriterionInput = sr_post_string('thumbnail_criterion', 20);
        $thumbnailCriterion = sr_community_thumbnail_criterion($thumbnailCriterionInput);
        $thumbnailMinWidth = sr_admin_post_int_in_range('thumbnail_min_width', 1, 4000);
        $thumbnailMinBytes = sr_admin_post_int_in_range('thumbnail_min_bytes', 0, 20971520);
        $publicDisplaySettingValues = [];
        foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
            $publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range($displaySettingKey, 0, 999999999);
        }
        $imageUploadsEnabled = ($_POST['image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = sr_admin_post_int_in_range('file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = sr_admin_post_int_in_range('file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = sr_post_string_without_truncation('file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? sr_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = sr_admin_post_int_in_range('read_min_level', 0, $maxLevel);
        $writeMinLevel = sr_admin_post_int_in_range('write_min_level', 0, $maxLevel);
        $commentMinLevel = sr_admin_post_int_in_range('comment_min_level', 0, $maxLevel);
        $categoryEnabled = ($_POST['category_enabled'] ?? '') === '1';
        $categoryRequired = ($_POST['category_required'] ?? '') === '1';
        if ($categoryRequired) {
            $categoryEnabled = true;
        }
        $seriesEnabled = ($_POST['series_enabled'] ?? '') === '1';
        $secretPostsEnabled = ($_POST['secret_posts_enabled'] ?? '') === '1';
        $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
        $postEditLockCommentCount = sr_admin_post_int_in_range('post_edit_lock_comment_count', 0, 1000000);
        $postDeleteLockCommentCount = sr_admin_post_int_in_range('post_delete_lock_comment_count', 0, 1000000);
        $postBodyMaxSettingLength = sr_community_post_body_setting_max_length();
        $postBodyMinLength = sr_admin_post_int_in_range('post_body_min_length', 0, $postBodyMaxSettingLength);
        $postBodyMaxLength = sr_admin_post_int_in_range('post_body_max_length', 0, $postBodyMaxSettingLength);
        $commentBodyMinLength = sr_admin_post_int_in_range('comment_body_min_length', 0, 5000);
        $commentBodyMaxLength = sr_admin_post_int_in_range('comment_body_max_length', 0, 5000);
        $listExcerptEnabled = ($_POST['list_excerpt_enabled'] ?? '') === '1';
        $listExcerptLength = sr_admin_post_int_in_range('list_excerpt_length', 1, 1000);
        $listPerPage = sr_admin_post_int_in_range('list_per_page', 1, 100);
        $listDefaultSortInput = sr_post_string('list_default_sort', 20);
        $listDefaultSort = sr_community_board_list_sort_key($listDefaultSortInput);
        $summaryFeedEnabled = ($_POST['summary_feed_enabled'] ?? '') === '1';
        $reactionEnabledInput = ($_POST['reaction_enabled'] ?? '') === '1';
        $reactionPostPresetInput = sr_post_string('reaction_post_preset_key', 80);
        $reactionCommentPresetInput = sr_post_string('reaction_comment_preset_key', 80);
        $reactionEnabled = $reactionAvailable && $reactionEnabledInput;
        $reactionPostPresetKey = '';
        $reactionCommentPresetKey = '';
        if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key')) {
            $reactionPostPresetKey = sr_reaction_setting_preset_key($pdo, $reactionPostPresetInput);
            $reactionCommentPresetKey = sr_reaction_setting_preset_key($pdo, $reactionCommentPresetInput);
        }
        $privacyConsentEnabled = ($_POST['privacy_consent_enabled'] ?? '') === '1';
        $editingBoardId = 0;
        if ($intent === 'update') {
            $editingBoardIdValue = sr_post_string('board_id', 20);
            $editingBoardId = preg_match('/\A[1-9][0-9]*\z/', $editingBoardIdValue) === 1 ? (int) $editingBoardIdValue : 0;
        }
        $existingPrivacyConsentSettings = $settings;
        if ($editingBoardId > 0) {
            $existingBoard = sr_community_board_by_id($pdo, $editingBoardId);
            if (is_array($existingBoard)) {
                foreach (sr_community_privacy_consent_setting_keys() as $privacyConsentSettingKey) {
                    $existingPrivacyConsentSettings[$privacyConsentSettingKey] = sr_community_effective_board_setting(
                        $pdo,
                        $existingBoard,
                        $privacyConsentSettingKey,
                        (string) ($settings[$privacyConsentSettingKey] ?? '')
                    );
                }
            }
        }
        $privacyConsentDocumentKeys = [];
        $privacyConsentRequires = [];
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            $privacyConsentDocumentKeys[$privacyConsentTargetKey] = array_key_exists($privacyConsentDocumentSettingKey, $_POST)
                ? sr_community_privacy_consent_clean_document_key(sr_post_string($privacyConsentDocumentSettingKey, 80))
                : sr_community_privacy_consent_admin_document_key_from_settings($existingPrivacyConsentSettings, $privacyConsentTargetKey);
            $privacyConsentRequires[$privacyConsentTargetKey] = $privacyConsentDocumentKeys[$privacyConsentTargetKey] !== '';
        }
        $selectedPrivacyConsentDocumentKeys = array_filter($privacyConsentDocumentKeys, static fn (string $value): bool => $value !== '');
        $privacyConsentDocumentKey = (string) (reset($selectedPrivacyConsentDocumentKeys) ?: ($settings['privacy_consent_document_key'] ?? 'community_privacy_default'));
        $privacyConsentDocumentInheritPolicy = sr_post_string('privacy_consent_document_inherit_policy', 20);
        if (!in_array($privacyConsentDocumentInheritPolicy, ['inherit', 'override', 'disabled'], true)) {
            $privacyConsentDocumentInheritPolicy = 'override';
        }
        $privacyConsentRequirePost = !empty($privacyConsentRequires['post']);
        $privacyConsentRequireComment = !empty($privacyConsentRequires['comment']);
        $privacyConsentRequireAttachmentUpload = !empty($privacyConsentRequires['attachment_upload']);
        if (!$privacyConsentPolicyDocumentsAvailable) {
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
        $extraFieldsInput = sr_post_string_without_truncation('extra_fields_json', 20000);
        $extraFieldDefinitionErrors = sr_community_extra_field_definitions_input_errors($extraFieldsInput);
        $extraFieldsJson = $extraFieldDefinitionErrors === [] && is_string($extraFieldsInput) ? sr_community_extra_field_definitions_json_from_input($extraFieldsInput) : null;
        $boardSeoValues = [
            'seo_title' => sr_community_seo_text(sr_post_string('seo_title', 160), 160),
            'seo_description' => sr_community_seo_text(sr_post_string('seo_description', 255), 255),
            'og_title' => '',
            'og_description' => '',
            'og_image_url' => trim(sr_post_string('og_image_url', 255)),
        ];
        $boardOgImageUploadFile = $_FILES['og_image_upload'] ?? null;
        $boardOgImageUploadProvided = sr_site_og_image_upload_was_provided($boardOgImageUploadFile);
        $levelPostScore = sr_admin_post_int_in_range('level_post_score', 0, 10000);
        $levelCommentScore = sr_admin_post_int_in_range('level_comment_score', 0, 10000);
        $boardGroupId = sr_admin_post_int_in_range('board_group_id', 0, 999999999);
        $boardGroupId = is_int($boardGroupId) ? $boardGroupId : 0;
        $boardGroup = $boardGroupId > 0 ? sr_community_board_group_by_id($pdo, $boardGroupId) : null;
        $readGroupKeysInput = $_POST['read_group_keys'] ?? [];
        $writeGroupKeysInput = $_POST['write_group_keys'] ?? [];
        $commentGroupKeysInput = $_POST['comment_group_keys'] ?? [];
        $readGroupKeys = sr_community_board_group_keys_from_input_value($readGroupKeysInput);
        $writeGroupKeys = sr_community_board_group_keys_from_input_value($writeGroupKeysInput);
        $commentGroupKeys = sr_community_board_group_keys_from_input_value($commentGroupKeysInput);
        $assetSettings = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $policySetIds = sr_community_asset_policy_set_ids_from_value($_POST[$assetPrefix . '_policy_set_ids'] ?? []);
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
                ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($_POST[$assetPrefix . '_asset_module'] ?? '', true), true)
                : sr_community_asset_module_key_or_empty(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
            $assetSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_policy_set_selection_json_from_ids($policySetIds);
            $assetSettings[$assetPrefix . '_policy_set_id'] = sr_community_asset_policy_set_first_id($policySetIds);
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $existingSettlementCurrency = is_array($existingBoard ?? null)
                    ? sr_community_asset_board_setting($pdo, $existingBoard, $settings, $assetPrefix . '_settlement_currency', '')
                    : '';
                $assetSettings[$assetPrefix . '_settlement_currency'] = sr_community_asset_settlement_currency($pdo, [
                    'asset_settlement_currency' => (string) $existingSettlementCurrency,
                ]);
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
        $legacyAssetPolicySource = sr_community_asset_policy_source(sr_post_string('asset_policy_source', 20));
        $legacyAssetSettingSource = $legacyAssetPolicySource === 'global' ? 'all' : $legacyAssetPolicySource;
        $assetSettingSources = [];
        $assetPrefixSources = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $legacyPrefixSource = sr_post_string('source_' . $assetPrefix, 20);
            if ($legacyPrefixSource === '') {
                $legacyPrefixSource = $legacyAssetSettingSource;
            }
            $assetModuleSource = sr_post_string('source_' . $assetPrefix . '_asset_module', 20);
            foreach (sr_community_asset_prefix_setting_keys((string) $assetPrefix) as $settingKey) {
                $postedSettingSource = sr_post_string('source_' . $settingKey, 20);
                if ($postedSettingSource === '' && in_array($settingKey, [$assetPrefix . '_amount', $assetPrefix . '_settlement_currency', $assetPrefix . '_amounts_json'], true)) {
                    $postedSettingSource = $assetModuleSource;
                }
                $assetSettingSources[$settingKey] = sr_community_normalize_board_setting_source($postedSettingSource !== '' ? $postedSettingSource : $legacyPrefixSource);
            }
            $assetSettingSources[$assetPrefix . '_group_policies_json'] = $assetSettingSources[$assetPrefix . '_policy_set_id'] ?? $assetSettingSources[$assetPrefix . '_group_policies_json'];
            $assetPrefixSources[$assetPrefix] = $assetSettingSources[$assetPrefix . '_enabled'] ?? sr_community_normalize_board_setting_source($legacyPrefixSource);
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_publisher_reward_enabled'] = ($_POST['paid_attachment_download_publisher_reward_enabled'] ?? '') === '1';
        $assetSettings['paid_attachment_download_publisher_reward_rate'] = sr_admin_post_int_in_range('paid_attachment_download_publisher_reward_rate', 0, 100);
        $multiAssetPaymentEnabled = sr_community_multi_asset_payment_enabled($pdo);
        $assetSettingSources['paid_attachment_download_publisher_reward_enabled'] = sr_community_normalize_board_setting_source(sr_post_string('source_paid_attachment_download_publisher_reward_enabled', 20));
        $assetSettingSources['paid_attachment_download_publisher_reward_rate'] = sr_community_normalize_board_setting_source(sr_post_string('source_paid_attachment_download_publisher_reward_rate', 20));
        $assetSettingLabels = [];
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $assetSettingLabels[$assetPrefix] = sr_community_asset_setting_label($assetPrefix);
            if (
                !$multiAssetPaymentEnabled
                && in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)
                && !empty($assetSettings[$assetPrefix . '_enabled'])
                && count(sr_community_asset_module_keys_from_value((string) ($assetSettings[$assetPrefix . '_asset_module'] ?? ''), true)) > 1
            ) {
                $errors[] = $assetSettingLabels[$assetPrefix] . ' 항목은 포인트/금액 항목을 하나만 선택하세요.';
            }
        }
        $settingSources = [];
        foreach (sr_community_board_group_setting_keys() as $settingKey) {
            $settingSources[$settingKey] = sr_community_normalize_board_setting_source(sr_post_string('source_' . $settingKey, 20));
        }
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            $privacyConsentSettingKey = 'privacy_consent_require_' . $privacyConsentTargetKey;
            $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            $privacyConsentDocumentSource = (string) ($settingSources[$privacyConsentDocumentSettingKey] ?? 'board');
            $settingSources[$privacyConsentSettingKey] = $privacyConsentDocumentSource;
            $privacyConsentRequires[$privacyConsentTargetKey] = (string) ($privacyConsentDocumentKeys[$privacyConsentTargetKey] ?? '') !== '';
        }
        $privacyConsentRequirePost = !empty($privacyConsentRequires['post']);
        $privacyConsentRequireComment = !empty($privacyConsentRequires['comment']);
        $privacyConsentRequireAttachmentUpload = !empty($privacyConsentRequires['attachment_upload']);
        $boardSettingValues = [];
        if ($intent === 'create' && !sr_community_board_key_is_valid($boardKey)) {
            $errors[] = sr_t('community::action.admin.board_key_invalid');
        }

        if ($title === '') {
            $errors[] = sr_t('community::action.admin.board_title_required');
        }

        if ($description === null) {
            $errors[] = sr_t('community::action.admin.description_too_long');
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.board_status_invalid');
        }

        if (!in_array($readPolicy, $allowedReadPolicies, true)) {
            $errors[] = sr_t('community::action.admin.read_policy_invalid');
        }

        if (!in_array($writePolicy, $allowedWritePolicies, true)) {
            $errors[] = sr_t('community::action.admin.write_policy_invalid');
        }

        if (!in_array($commentPolicy, $allowedCommentPolicies, true)) {
            $errors[] = sr_t('community::action.admin.comment_policy_invalid');
        }

        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = sr_t('community::action.admin.board_skin_invalid');
            $skinKey = 'basic';
        }

        if ($boardGroupId > 0 && !is_array($boardGroup)) {
            $errors[] = sr_t('community::action.admin.board_group_invalid');
        }

        if ($postEditorInput !== $postEditor || !array_key_exists($postEditor, $editorOptions)) {
            $errors[] = '게시판 에디터 값이 올바르지 않습니다.';
            $postEditor = 'textarea';
        }

        if ($sortOrder === null) {
            $errors[] = sr_t('community::action.admin.sort_order_invalid');
            $sortOrder = 0;
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.image_max_bytes_invalid');
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.image_max_count_invalid');
            $attachmentMaxCount = 1;
        }

        foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
            $displaySettingLabel = (string) ($publicDisplaySettingLabels[$displaySettingKey] ?? $displaySettingKey);
            if ($displaySettingValue === null) {
                $errors[] = sr_t('community::action.admin.display_value_invalid', ['label' => $displaySettingLabel]);
                $publicDisplaySettingValues[$displaySettingKey] = 0;
                continue;
            }

            if (isset($publicBannerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicBannerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_banner_invalid', ['label' => $displaySettingLabel]);
            }

            if (isset($publicPopupLayerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicPopupLayerIds[$displaySettingValue])) {
                $errors[] = sr_t('community::action.admin.display_popup_invalid', ['label' => $displaySettingLabel]);
            }
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = sr_t('community::action.admin.file_max_bytes_invalid');
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = sr_t('community::action.admin.file_max_count_invalid');
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = sr_t('community::action.admin.file_extensions_too_long');
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = sr_t('community::action.admin.file_extensions_invalid', ['extensions' => implode(', ', $invalidFileExtensions)]);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = sr_t('community::action.admin.file_extensions_required');
        }

        if ($readMinLevel === null) {
            $errors[] = sr_t('community::action.admin.read_min_level_invalid', ['max' => (string) $maxLevel]);
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = sr_t('community::action.admin.write_min_level_invalid', ['max' => (string) $maxLevel]);
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = sr_t('community::action.admin.comment_min_level_invalid', ['max' => (string) $maxLevel]);
            $commentMinLevel = 0;
        }

        if ($levelPostScore === null) {
            $errors[] = sr_t('community::action.admin.post_score_invalid');
            $levelPostScore = (int) $settings['level_post_score'];
        }

        if ($levelCommentScore === null) {
            $errors[] = sr_t('community::action.admin.comment_score_invalid');
            $levelCommentScore = (int) $settings['level_comment_score'];
        }

        if ($categoryRequired) {
            $categoryBoardId = $intent === 'update' ? $editingBoardId : 0;
            if ($categoryBoardId < 1 || sr_community_categories($pdo, $categoryBoardId, true) === []) {
                $errors[] = '활성 카테고리가 1개 이상 있어야 카테고리 필수를 켤 수 있습니다.';
            }
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

        foreach ([
            'postEditLockCommentCount' => ['value' => $postEditLockCommentCount, 'message' => '게시글 수정 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postDeleteLockCommentCount' => ['value' => $postDeleteLockCommentCount, 'message' => '게시글 삭제 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMinLength' => ['value' => $postBodyMinLength, 'message' => '게시글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'postBodyMaxLength' => ['value' => $postBodyMaxLength, 'message' => '게시글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMinLength' => ['value' => $commentBodyMinLength, 'message' => '댓글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
            'commentBodyMaxLength' => ['value' => $commentBodyMaxLength, 'message' => '댓글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
            'listExcerptLength' => ['value' => $listExcerptLength, 'message' => '목록 본문 요약 길이가 올바르지 않습니다.', 'fallback' => 120],
            'listPerPage' => ['value' => $listPerPage, 'message' => '목록 페이지당 글 수가 올바르지 않습니다.', 'fallback' => 20],
        ] as $numericSettingKey => $numericSetting) {
            if ($numericSetting['value'] === null) {
                $errors[] = (string) $numericSetting['message'];
                ${$numericSettingKey} = (int) $numericSetting['fallback'];
            }
        }
        if ($postBodyMinLength > 0 && $postBodyMaxLength > 0 && $postBodyMinLength > $postBodyMaxLength) {
            $errors[] = '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($commentBodyMinLength > 0 && $commentBodyMaxLength > 0 && $commentBodyMinLength > $commentBodyMaxLength) {
            $errors[] = '댓글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
        }
        if ($listDefaultSortInput !== $listDefaultSort) {
            $errors[] = '목록 기본 정렬 값이 올바르지 않습니다.';
        }

        if (!$reactionAvailable) {
            if ($reactionEnabledInput || $reactionPostPresetInput !== '' || $reactionCommentPresetInput !== '') {
                $errors[] = '게시판 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
            }
        } else {
            foreach (['reaction_post_preset_key' => $reactionPostPresetKey, 'reaction_comment_preset_key' => $reactionCommentPresetKey] as $reactionSettingKey => $reactionPresetKey) {
                if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                    $errors[] = '게시판 리액션 프리셋 값이 올바르지 않습니다.';
                    break;
                }
            }
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
                $targetDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
                if (($settingSources[$targetDocumentSettingKey] ?? 'board') === 'board'
                    && ($targetDocumentKey === '' || !is_array(sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey)))) {
                    $errors[] = sr_community_privacy_consent_admin_label($privacyConsentTargetKey) . ' 정책 문서를 선택해 주세요.';
                }
            }
        }

        if (!$boardOgImageUploadProvided && $boardSeoValues['og_image_url'] !== '' && !sr_is_http_url($boardSeoValues['og_image_url']) && !sr_is_safe_relative_url($boardSeoValues['og_image_url'])) {
            $errors[] = '게시판 OG 이미지는 http(s) URL 또는 /로 시작하는 내부 경로만 입력해 주세요.';
        }

        foreach ($settingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $errors[] = sr_t('community::action.admin.setting_group_source_requires_group', ['setting' => $settingKey]);
            }
        }

        foreach ($assetSettingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $assetPrefix = sr_community_asset_prefix_from_setting_key((string) $settingKey);
                $assetLabel = (string) ($assetSettingLabels[$assetPrefix] ?? $settingKey);
                $errors[] = sr_t('community::action.admin.asset_group_source_requires_group', ['label' => $assetLabel]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeysInput],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeysInput],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeysInput = $groupKeyValidation['value'];
            if (sr_community_board_group_keys_input_too_long($groupKeysInput)) {
                $errors[] = sr_t('community::action.admin.group_list_too_long', ['label' => $label]);
                continue;
            }

            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($groupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_invalid', ['label' => $label, 'keys' => implode(', ', $invalidGroupKeys)]);
            }
        }

        foreach ([
            ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $readGroupKeys],
            ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $writeGroupKeys],
            ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $commentGroupKeys],
        ] as $groupKeyValidation) {
            $label = (string) $groupKeyValidation['label'];
            $groupKeys = $groupKeyValidation['value'];
            $unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys));
            if ($unknownGroupKeys !== []) {
                $errors[] = sr_t('community::action.admin.group_keys_inactive', ['label' => $label, 'keys' => implode(', ', $unknownGroupKeys)]);
            }
        }

        $writeGroupKeys = array_values(array_intersect($writeGroupKeys, $readGroupKeys));
        $commentGroupKeys = array_values(array_intersect($commentGroupKeys, $readGroupKeys));

        foreach ($assetSettingLabels as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            if (($assetPrefixSources[$assetPrefix] ?? 'board') === 'board' && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
                if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                    $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                        $errors[] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
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
        foreach ([$reactionPostPresetKey, $reactionCommentPresetKey] as $reactionPresetKey) {
            if ($reactionPresetKey !== '' && !isset($reactionPresetOptions[$reactionPresetKey])) {
                $errors[] = '게시판 리액션 프리셋 값이 올바르지 않습니다.';
                break;
            }
        }
        if ($extraFieldDefinitionErrors !== []) {
            $errors = array_merge($errors, $extraFieldDefinitionErrors);
            $extraFieldsJson = '[]';
        }

        if ($errors === [] && $intent === 'create' && sr_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = sr_t('community::action.admin.board_key_duplicate');
        }

        if ($errors === [] && $boardOgImageUploadProvided) {
            if (!is_array($boardOgImageUploadFile)) {
                $errors[] = '업로드할 게시판 OG 이미지를 확인할 수 없습니다.';
            } else {
                try {
                    $uploadedBoardOgImage = sr_site_upload_og_image($boardOgImageUploadFile);
                    $boardSeoValues['og_image_url'] = sr_community_seo_text((string) ($uploadedBoardOgImage['public_url'] ?? ''), 255);
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        if ($errors === []) {
            $boardSettingValues = [
                'status' => $status,
                'skin_key' => $skinKey,
                'post_editor' => $postEditor,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'identity_verification_enabled' => $identityVerificationEnabled ? '1' : '0',
                'identity_verification_purpose' => $identityVerificationPurpose,
                'identity_verification_required_actions' => sr_community_identity_verification_actions_setting_value($identityVerificationRequiredActions),
                'read_group_keys' => sr_community_board_group_keys_setting_value($readGroupKeys),
                'write_group_keys' => sr_community_board_group_keys_setting_value($writeGroupKeys),
                'comment_group_keys' => sr_community_board_group_keys_setting_value($commentGroupKeys),
                'read_min_level' => (string) $readMinLevel,
                'write_min_level' => (string) $writeMinLevel,
                'comment_min_level' => (string) $commentMinLevel,
                'category_enabled' => $categoryEnabled ? '1' : '0',
                'category_required' => $categoryRequired ? '1' : '0',
                'series_enabled' => $seriesEnabled ? '1' : '0',
                'secret_posts_enabled' => $secretPostsEnabled ? '1' : '0',
                'secret_comments_enabled' => $secretCommentsEnabled ? '1' : '0',
                'post_edit_lock_comment_count' => (string) $postEditLockCommentCount,
                'post_delete_lock_comment_count' => (string) $postDeleteLockCommentCount,
                'post_body_min_length' => (string) $postBodyMinLength,
                'post_body_max_length' => (string) $postBodyMaxLength,
                'comment_body_min_length' => (string) $commentBodyMinLength,
                'comment_body_max_length' => (string) $commentBodyMaxLength,
                'list_excerpt_enabled' => $listExcerptEnabled ? '1' : '0',
                'list_excerpt_length' => (string) $listExcerptLength,
                'list_per_page' => (string) $listPerPage,
                'list_default_sort' => $listDefaultSort,
                'summary_feed_enabled' => $summaryFeedEnabled ? '1' : '0',
                'reaction_enabled' => $reactionEnabled ? '1' : '0',
                'reaction_post_preset_key' => $reactionPostPresetKey,
                'reaction_comment_preset_key' => $reactionCommentPresetKey,
                'privacy_consent_enabled' => $privacyConsentEnabled ? '1' : '0',
                'privacy_consent_document_key' => $privacyConsentDocumentKey !== '' ? $privacyConsentDocumentKey : 'community_privacy_default',
                'privacy_consent_post_document_key' => (string) ($privacyConsentDocumentKeys['post'] ?? ''),
                'privacy_consent_comment_document_key' => (string) ($privacyConsentDocumentKeys['comment'] ?? ''),
                'privacy_consent_attachment_upload_document_key' => (string) ($privacyConsentDocumentKeys['attachment_upload'] ?? ''),
                'privacy_consent_document_inherit_policy' => $privacyConsentDocumentInheritPolicy,
                'privacy_consent_title' => '',
                'privacy_consent_body' => '',
                'privacy_consent_version' => '',
                'privacy_consent_require_post' => $privacyConsentRequirePost ? '1' : '0',
                'privacy_consent_require_comment' => $privacyConsentRequireComment ? '1' : '0',
                'privacy_consent_require_attachment_upload' => $privacyConsentRequireAttachmentUpload ? '1' : '0',
                'extra_fields_json' => $extraFieldsJson,
                'level_post_score' => (string) $levelPostScore,
                'level_comment_score' => (string) $levelCommentScore,
                'image_uploads_enabled' => $imageUploadsEnabled ? '1' : '0',
                'thumbnail_enabled' => $thumbnailEnabled ? '1' : '0',
                'thumbnail_criterion' => $thumbnailCriterion,
                'thumbnail_min_width' => (string) $thumbnailMinWidth,
                'thumbnail_min_bytes' => (string) $thumbnailMinBytes,
                'attachment_max_bytes' => (string) $attachmentMaxBytes,
                'attachment_max_count' => (string) $attachmentMaxCount,
                'file_uploads_enabled' => $fileUploadsEnabled ? '1' : '0',
                'file_attachment_max_bytes' => (string) $fileAttachmentMaxBytes,
                'file_attachment_max_count' => (string) $fileAttachmentMaxCount,
                'file_allowed_extensions' => implode(',', $fileAllowedExtensions),
            ];
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                $boardSettingValues[(string) $displaySettingKey] = (string) $displaySettingValue;
            }
            foreach ($boardSeoValues as $seoSettingKey => $seoSettingValue) {
                $boardSettingValues[(string) $seoSettingKey] = (string) $seoSettingValue;
            }
        }

        if ($intent === 'create' && $errors === []) {
            $boardId = sr_community_create_board($pdo, [
                'board_group_id' => $boardGroupId,
                'board_key' => $boardKey,
                'title' => $title,
                'description' => (string) $description,
                'status' => $status,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'image_uploads_enabled' => $imageUploadsEnabled,
                'sort_order' => (int) $sortOrder,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.created',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board created.',
                'metadata' => array_merge([
                    'board_key' => $boardKey,
                    'board_group_id' => $boardGroupId,
                    'status' => $status,
                    'summary_feed_enabled' => $summaryFeedEnabled,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'file_uploads_enabled' => $fileUploadsEnabled,
                    'attachment_max_bytes' => $attachmentMaxBytes,
                    'attachment_max_count' => $attachmentMaxCount,
                    'file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                    'file_attachment_max_count' => $fileAttachmentMaxCount,
                    'file_allowed_extensions' => $fileAllowedExtensions,
                    'read_group_keys' => $readGroupKeys,
                    'write_group_keys' => $writeGroupKeys,
                    'comment_group_keys' => $commentGroupKeys,
                    'read_min_level' => $readMinLevel,
                    'identity_verification_enabled' => $identityVerificationEnabled,
                    'identity_verification_purpose' => $identityVerificationPurpose,
                    'identity_verification_required_actions' => $identityVerificationRequiredActions,
                    'write_min_level' => $writeMinLevel,
                    'comment_min_level' => $commentMinLevel,
                    'category_enabled' => $categoryEnabled,
                    'category_required' => $categoryRequired,
                    'series_enabled' => $seriesEnabled,
                    'level_post_score' => $levelPostScore,
                    'level_comment_score' => $levelCommentScore,
                    'secret_posts_enabled' => $secretPostsEnabled,
                    'secret_comments_enabled' => $secretCommentsEnabled,
                    'reaction_enabled' => $reactionEnabled,
                    'reaction_post_preset_key' => $reactionPostPresetKey,
                    'reaction_comment_preset_key' => $reactionCommentPresetKey,
                    'skin_key' => $skinKey,
                    'asset_settings' => $assetSettings,
                    'asset_prefix_sources' => $assetPrefixSources,
                    'asset_setting_sources' => $assetSettingSources,
                    'setting_sources' => $settingSources,
                ], $publicDisplaySettingValues),
            ]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
                sr_community_set_board_setting($pdo, $boardId, $thumbnailSettingKey, (string) ($boardSettingValues[$thumbnailSettingKey] ?? ''), sr_community_board_setting_value_type($thumbnailSettingKey));
            }
            foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
            }
            sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'category_enabled', $categoryEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'series_enabled', $seriesEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'reaction_enabled', $reactionEnabled ? '1' : '0', 'bool');
            sr_community_set_board_setting($pdo, $boardId, 'reaction_post_preset_key', $reactionPostPresetKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'reaction_comment_preset_key', $reactionCommentPresetKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
            sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
            foreach ($boardSettingValues as $settingKey => $settingValue) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
            }
            foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) ($settingSources['extra_fields_json'] ?? 'board')) as $targetBoardId) {
                sr_community_sync_board_field_definitions($pdo, (int) $targetBoardId, sr_community_extra_field_definitions_from_json($extraFieldsJson));
            }
            foreach ($assetSettingSources as $settingKey => $source) {
                sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
            }
            if (function_exists('sr_community_feed_cache_mark_all_stale')) {
                sr_community_feed_cache_mark_all_stale($pdo, 'board_settings_changed');
            }

            $notice = sr_t('community::action.admin.board_created');
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/community/boards');
        } elseif ($intent === 'update' && $errors === []) {
            $boardIdValue = sr_post_string('board_id', 20);
            $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
            $board = sr_community_board_by_id($pdo, $boardId);
            if (!is_array($board)) {
                $errors[] = sr_t('community::action.error.board_not_found');
            }

            if ($errors === [] && is_array($board)) {
                $beforeAttachmentMaxBytes = sr_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = sr_community_board_attachment_max_count($pdo, $boardId);
                $beforeThumbnailSettings = [];
                foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
                    $beforeThumbnailSettings[$thumbnailSettingKey] = sr_community_board_thumbnail_setting($pdo, $boardId, $thumbnailSettingKey, $settings);
                }
                $beforePublicDisplaySettingValues = [];
                foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
                    $beforePublicDisplaySettingValues[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, $boardId, $displaySettingKey) ?? 0);
                }
                $beforeFileAttachmentMaxBytes = sr_community_board_file_attachment_max_bytes($pdo, $boardId);
                $beforeFileAttachmentMaxCount = sr_community_board_file_attachment_max_count($pdo, $boardId);
                $beforeFileAllowedExtensions = sr_community_board_file_allowed_extensions($pdo, $boardId);
                $beforeReadGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'read_group_keys');
                $beforeWriteGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'write_group_keys');
                $beforeCommentGroupKeys = sr_community_board_group_keys($pdo, $boardId, 'comment_group_keys');
                $beforeReadMinLevel = sr_community_board_min_level($pdo, $boardId, 'read_min_level');
                $beforeWriteMinLevel = sr_community_board_min_level($pdo, $boardId, 'write_min_level');
                $beforeCommentMinLevel = sr_community_board_min_level($pdo, $boardId, 'comment_min_level');
                $beforeCategoryEnabled = sr_community_board_category_enabled($pdo, $boardId);
                $beforeCategoryRequired = sr_community_board_category_required($pdo, $boardId);
                $beforeSeriesEnabled = sr_community_effective_board_series_enabled($pdo, $board, $settings);
                $beforeLevelPostScore = sr_community_board_level_score($pdo, $boardId, 'level_post_score', $settings);
                $beforeLevelCommentScore = sr_community_board_level_score($pdo, $boardId, 'level_comment_score', $settings);
                $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
                $beforeAssetSettingSources = [];
                foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
                    $beforeAssetSettingSources[$assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeAssetSettings = [];
                foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                    $beforeAssetSettings[$assetSettingKey] = sr_community_board_setting_value($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeCurrentBoardAssetSettingsForAudit = sr_community_board_asset_settings_for_audit($pdo, $boardId);
                sr_community_update_board($pdo, $boardId, [
                    'board_group_id' => $boardGroupId,
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'read_policy' => $readPolicy,
                    'write_policy' => $writePolicy,
                    'comment_policy' => $commentPolicy,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'sort_order' => (int) $sortOrder,
                ]);
                sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
                foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
                    sr_community_set_board_setting($pdo, $boardId, $thumbnailSettingKey, (string) ($boardSettingValues[$thumbnailSettingKey] ?? ''), sr_community_board_setting_value_type($thumbnailSettingKey));
                }
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, 'int');
                }
                sr_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
                sr_community_set_board_setting($pdo, $boardId, 'read_group_keys', sr_community_board_group_keys_setting_value($readGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'write_group_keys', sr_community_board_group_keys_setting_value($writeGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'comment_group_keys', sr_community_board_group_keys_setting_value($commentGroupKeys), 'json');
                sr_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'category_enabled', $categoryEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'category_required', $categoryRequired ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'series_enabled', $seriesEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'secret_posts_enabled', $secretPostsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'secret_comments_enabled', $secretCommentsEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'reaction_enabled', $reactionEnabled ? '1' : '0', 'bool');
                sr_community_set_board_setting($pdo, $boardId, 'reaction_post_preset_key', $reactionPostPresetKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'reaction_comment_preset_key', $reactionCommentPresetKey, 'string');
                sr_community_set_board_setting($pdo, $boardId, 'level_post_score', (string) $levelPostScore, 'int');
                sr_community_set_board_setting($pdo, $boardId, 'level_comment_score', (string) $levelCommentScore, 'int');
                foreach ($boardSettingValues as $settingKey => $settingValue) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, (string) ($settingSources[$settingKey] ?? 'board'), $settingValue);
                }
                foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) ($settingSources['extra_fields_json'] ?? 'board')) as $targetBoardId) {
                    sr_community_sync_board_field_definitions($pdo, (int) $targetBoardId, sr_community_extra_field_definitions_from_json($extraFieldsJson));
                }
                $boardAssetAudits = [];
                foreach ($assetSettingSources as $settingKey => $source) {
                    foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, (string) $source) as $targetBoardId) {
                        $targetBoardId = (int) $targetBoardId;
                        if ($targetBoardId < 1) {
                            continue;
                        }

                        if (!isset($boardAssetAudits[$targetBoardId])) {
                            $targetBoard = sr_community_board_by_id($pdo, $targetBoardId);
                            $boardAssetAudits[$targetBoardId] = [
                                'board_key' => is_array($targetBoard) ? (string) ($targetBoard['board_key'] ?? '') : '',
                                'before_asset_settings' => $targetBoardId === $boardId ? $beforeCurrentBoardAssetSettingsForAudit : sr_community_board_asset_settings_for_audit($pdo, $targetBoardId),
                                'applied_setting_keys' => [],
                            ];
                        }
                        $boardAssetAudits[$targetBoardId]['applied_setting_keys'][(string) $settingKey] = true;
                    }
                }
                sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
                foreach ($assetSettingSources as $settingKey => $source) {
                    sr_community_apply_board_setting_scope($pdo, $boardId, $boardGroupId, (string) $settingKey, $source, $assetSettings[$settingKey] ?? '');
                }
                if (function_exists('sr_community_feed_cache_mark_all_stale')) {
                    sr_community_feed_cache_mark_all_stale($pdo, 'board_settings_changed');
                }

                $publicDisplayMetadata = [];
                foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
                    $publicDisplayMetadata['before_' . $displaySettingKey] = (int) ($beforePublicDisplaySettingValues[$displaySettingKey] ?? 0);
                    $publicDisplayMetadata['after_' . $displaySettingKey] = (int) $displaySettingValue;
                }

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.updated',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board updated.',
                    'metadata' => array_merge([
                        'board_key' => (string) $board['board_key'],
                        'before_status' => (string) $board['status'],
                        'after_status' => $status,
                        'before_board_group_id' => (int) ($board['board_group_id'] ?? 0),
                        'after_board_group_id' => $boardGroupId,
                        'before_summary_feed_enabled' => sr_community_effective_board_summary_feed_enabled($pdo, $board),
                        'after_summary_feed_enabled' => $summaryFeedEnabled,
                        'before_image_uploads_enabled' => (int) $board['image_uploads_enabled'] === 1,
                        'after_image_uploads_enabled' => $imageUploadsEnabled,
                        'after_file_uploads_enabled' => $fileUploadsEnabled,
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $attachmentMaxBytes,
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $attachmentMaxCount,
                        'before_thumbnail_settings' => $beforeThumbnailSettings,
                        'after_thumbnail_settings' => [
                            'thumbnail_enabled' => $thumbnailEnabled ? '1' : '0',
                            'thumbnail_criterion' => $thumbnailCriterion,
                            'thumbnail_min_width' => (string) $thumbnailMinWidth,
                            'thumbnail_min_bytes' => (string) $thumbnailMinBytes,
                        ],
                        'before_file_attachment_max_bytes' => $beforeFileAttachmentMaxBytes,
                        'after_file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                        'before_file_attachment_max_count' => $beforeFileAttachmentMaxCount,
                        'after_file_attachment_max_count' => $fileAttachmentMaxCount,
                        'before_file_allowed_extensions' => $beforeFileAllowedExtensions,
                        'after_file_allowed_extensions' => $fileAllowedExtensions,
                        'before_read_group_keys' => $beforeReadGroupKeys,
                        'after_read_group_keys' => $readGroupKeys,
                        'before_write_group_keys' => $beforeWriteGroupKeys,
                        'after_write_group_keys' => $writeGroupKeys,
                        'before_comment_group_keys' => $beforeCommentGroupKeys,
                        'after_comment_group_keys' => $commentGroupKeys,
                        'before_read_min_level' => $beforeReadMinLevel,
                        'after_read_min_level' => $readMinLevel,
                        'before_write_min_level' => $beforeWriteMinLevel,
                        'after_write_min_level' => $writeMinLevel,
                        'before_comment_min_level' => $beforeCommentMinLevel,
                        'after_comment_min_level' => $commentMinLevel,
                        'before_category_enabled' => $beforeCategoryEnabled,
                        'after_category_enabled' => $categoryEnabled,
                        'before_category_required' => $beforeCategoryRequired,
                        'after_category_required' => $categoryRequired,
                        'before_series_enabled' => $beforeSeriesEnabled,
                        'after_series_enabled' => $seriesEnabled,
                        'before_level_post_score' => $beforeLevelPostScore,
                        'after_level_post_score' => $levelPostScore,
                        'before_level_comment_score' => $beforeLevelCommentScore,
                        'after_level_comment_score' => $levelCommentScore,
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $skinKey,
                        'after_reaction_enabled' => $reactionEnabled,
                        'after_reaction_post_preset_key' => $reactionPostPresetKey,
                        'after_reaction_comment_preset_key' => $reactionCommentPresetKey,
                        'before_asset_setting_sources' => $beforeAssetSettingSources,
                        'after_asset_prefix_sources' => $assetPrefixSources,
                        'after_asset_setting_sources' => $assetSettingSources,
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $assetSettings,
                        'setting_sources' => $settingSources,
                    ], $publicDisplayMetadata),
                ]);
                foreach ($boardAssetAudits as $targetBoardId => $boardAssetAudit) {
                    $appliedSettingKeys = array_keys(is_array($boardAssetAudit['applied_setting_keys'] ?? null) ? $boardAssetAudit['applied_setting_keys'] : []);
                    sort($appliedSettingKeys);
                    sr_admin_audit_asset_settings_update($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.board.asset_settings.updated',
                        'target_type' => 'community_board',
                        'target_id' => (string) $targetBoardId,
                        'asset_settings_scope' => 'community.board',
                        'before_asset_settings' => is_array($boardAssetAudit['before_asset_settings'] ?? null) ? $boardAssetAudit['before_asset_settings'] : [],
                        'after_asset_settings' => sr_community_board_asset_settings_for_audit($pdo, (int) $targetBoardId),
                        'message' => 'Community board asset settings updated.',
                        'metadata' => [
                            'board_key' => (string) ($boardAssetAudit['board_key'] ?? ''),
                            'source' => 'community_board',
                            'source_board_key' => (string) $board['board_key'],
                            'applied_setting_keys' => $appliedSettingKeys,
                        ],
                    ]);
                }

                $notice = sr_t('community::action.admin.board_updated');
            }
        }

    return [
        'errors' => $errors,
        'notice' => $notice,
    ];
}
