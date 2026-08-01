<?php

declare(strict_types=1);

function sr_community_admin_apply_board_settings(
    PDO $pdo,
    int $boardId,
    int $boardGroupId,
    array $boardSettingValues,
    array $settingSources,
    string $extraFieldsJson,
    array $assetSettings,
    array $assetSettingSources
): void {
    foreach ($boardSettingValues as $settingKey => $settingValue) {
        sr_community_apply_board_setting_scope(
            $pdo,
            $boardId,
            $boardGroupId,
            (string) $settingKey,
            (string) ($settingSources[$settingKey] ?? 'board'),
            $settingValue
        );
    }

    $extraFieldSource = (string) ($settingSources['extra_fields_json'] ?? 'board');
    $extraFieldDefinitions = sr_community_extra_field_definitions_from_json($extraFieldsJson);
    foreach (sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, $extraFieldSource) as $targetBoardId) {
        sr_community_sync_board_field_definitions($pdo, (int) $targetBoardId, $extraFieldDefinitions);
    }

    foreach ($assetSettingSources as $settingKey => $source) {
        sr_community_apply_board_setting_scope(
            $pdo,
            $boardId,
            $boardGroupId,
            (string) $settingKey,
            (string) $source,
            $assetSettings[$settingKey] ?? ''
        );
    }

    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'board_settings_changed');
    }
}

function sr_community_admin_generate_category_key(array $usedKeys): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $categoryKey = 'category_' . bin2hex(random_bytes(8));
        if (!isset($usedKeys[$categoryKey])) {
            return $categoryKey;
        }
    }

    throw new RuntimeException('카테고리 Key를 생성하지 못했습니다. 다시 시도해 주세요.');
}

function sr_community_admin_board_categories_from_json(?string $json): array
{
    if ($json === null) {
        return ['items' => [], 'errors' => ['등록할 카테고리 데이터가 너무 큽니다.']];
    }

    $decoded = json_decode($json === '' ? '[]' : $json, true);
    if (!is_array($decoded) || !array_is_list($decoded)) {
        return ['items' => [], 'errors' => ['등록할 카테고리 데이터 형식이 올바르지 않습니다.']];
    }
    if (count($decoded) > 100) {
        return ['items' => [], 'errors' => ['게시판을 등록할 때 카테고리는 최대 100개까지 지정할 수 있습니다.']];
    }

    $items = [];
    $errors = [];
    $seenKeys = [];
    foreach ($decoded as $index => $item) {
        if (!is_array($item)) {
            $errors[] = '등록할 카테고리 ' . (string) ($index + 1) . '번 데이터가 올바르지 않습니다.';
            continue;
        }

        $categoryIdValue = $item['id'] ?? 0;
        $categoryId = filter_var($categoryIdValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $categoryKey = $categoryId === false
            ? sr_community_admin_generate_category_key($seenKeys)
            : strtolower(trim((string) ($item['category_key'] ?? '')));
        $title = trim((string) ($item['title'] ?? ''));
        $description = trim((string) ($item['description'] ?? ''));
        $status = (string) ($item['status'] ?? 'enabled');
        $sortOrderValue = $item['sort_order'] ?? null;
        $sortOrder = filter_var($sortOrderValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1000000],
        ]);

        if ($categoryId !== false && !sr_community_category_key_is_valid($categoryKey)) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번 Key가 올바르지 않습니다.';
        } elseif (isset($seenKeys[$categoryKey])) {
            $errors[] = '카테고리 Key는 게시판 안에서 중복될 수 없습니다: ' . $categoryKey;
        }
        if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 120) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번 이름을 120자 이내로 입력해 주세요.';
        }
        if (strlen($description) > 2000) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번 설명이 너무 깁니다.';
        }
        if (!in_array($status, sr_community_category_statuses(), true)) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번 상태가 올바르지 않습니다.';
        }
        if ($sortOrder === false) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번 정렬값이 올바르지 않습니다.';
            $sortOrder = 0;
        }

        $seenKeys[$categoryKey] = true;
        $items[] = [
            'id' => $categoryId === false ? 0 : (int) $categoryId,
            'category_key' => $categoryKey,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'sort_order' => (int) $sortOrder,
        ];
    }

    return ['items' => $items, 'errors' => array_values(array_unique($errors))];
}

function sr_community_admin_validate_board_category_sync(PDO $pdo, int $boardId, array $categories): array
{
    if ($boardId < 1) {
        return [];
    }

    $errors = [];
    $existingById = [];
    foreach (sr_community_categories($pdo, $boardId) as $existingCategory) {
        $existingById[(int) $existingCategory['id']] = $existingCategory;
    }
    $submittedIds = [];
    foreach ($categories as $index => $category) {
        $categoryId = (int) ($category['id'] ?? 0);
        if ($categoryId < 1) {
            continue;
        }
        if (!isset($existingById[$categoryId])) {
            $errors[] = '카테고리 ' . (string) ($index + 1) . '번은 현재 게시판의 카테고리가 아닙니다.';
            continue;
        }
        if (isset($submittedIds[$categoryId])) {
            $errors[] = '같은 카테고리 행을 두 번 제출할 수 없습니다.';
        }
        if ((string) $existingById[$categoryId]['category_key'] !== (string) ($category['category_key'] ?? '')) {
            $errors[] = '기존 카테고리 Key는 변경할 수 없습니다.';
        }
        $submittedIds[$categoryId] = true;
    }

    $referenceStmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_posts WHERE category_id = :category_id');
    foreach ($existingById as $categoryId => $existingCategory) {
        if (isset($submittedIds[$categoryId])) {
            continue;
        }
        $referenceStmt->execute(['category_id' => $categoryId]);
        if ((int) $referenceStmt->fetchColumn() > 0) {
            $errors[] = '게시글에서 사용 중인 카테고리는 목록에서 삭제할 수 없습니다: ' . (string) $existingCategory['title'];
        }
    }

    return array_values(array_unique($errors));
}

function sr_community_admin_sync_board_children(PDO $pdo, int $boardId, string $boardKey, array $categories, array $managers, int $actorAccountId): void
{
    $existingCategories = sr_community_categories($pdo, $boardId);
    $existingCategoriesById = [];
    $submittedCategoryIds = [];
    foreach ($existingCategories as $existingCategory) {
        $existingCategoriesById[(int) $existingCategory['id']] = $existingCategory;
    }
    foreach ($categories as $category) {
        $categoryId = (int) ($category['id'] ?? 0);
        if ($categoryId > 0) {
            $submittedCategoryIds[$categoryId] = true;
        }
    }
    foreach ($existingCategories as $existingCategory) {
        $categoryId = (int) $existingCategory['id'];
        if (isset($submittedCategoryIds[$categoryId])) {
            continue;
        }
        if (!sr_community_delete_category($pdo, $categoryId)) {
            throw new RuntimeException('삭제할 수 없는 카테고리가 포함되어 있습니다.');
        }
        sr_audit_log($pdo, [
            'actor_account_id' => $actorAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.category.deleted',
            'target_type' => 'community_category',
            'target_id' => (string) $categoryId,
            'result' => 'success',
            'message' => 'Community category deleted with board save.',
            'metadata' => ['board_key' => $boardKey, 'category_key' => (string) $existingCategory['category_key']],
        ]);
    }
    foreach ($categories as $category) {
        $categoryId = (int) ($category['id'] ?? 0);
        if ($categoryId > 0) {
            $existingCategory = $existingCategoriesById[$categoryId] ?? [];
            $categoryChanged = (string) ($existingCategory['title'] ?? '') !== (string) $category['title']
                || (string) ($existingCategory['description'] ?? '') !== (string) $category['description']
                || (string) ($existingCategory['status'] ?? '') !== (string) $category['status']
                || (int) ($existingCategory['sort_order'] ?? 0) !== (int) $category['sort_order'];
            if (!$categoryChanged) {
                continue;
            }
            sr_community_update_category($pdo, $categoryId, $category);
            $eventType = 'community.category.updated';
        } else {
            $categoryId = sr_community_create_category($pdo, $boardId, $category);
            $eventType = 'community.category.created';
        }
        sr_audit_log($pdo, [
            'actor_account_id' => $actorAccountId,
            'actor_type' => 'admin',
            'event_type' => $eventType,
            'target_type' => 'community_category',
            'target_id' => (string) $categoryId,
            'result' => 'success',
            'message' => 'Community category synchronized with board save.',
            'metadata' => [
                'board_key' => $boardKey,
                'category_key' => (string) $category['category_key'],
                'status' => (string) $category['status'],
            ],
        ]);
    }

    $desiredPermissions = [];
    foreach ($managers as $manager) {
        $desiredPermissions[(int) $manager['account_id']] = array_fill_keys(
            is_array($manager['permission_keys'] ?? null) ? $manager['permission_keys'] : [],
            true
        );
    }
    $existingPermissions = [];
    foreach (sr_community_board_managers($pdo, $boardId) as $existingManager) {
        $accountId = (int) $existingManager['account_id'];
        $permissionKey = (string) $existingManager['permission_key'];
        $existingPermissions[$accountId][$permissionKey] = true;
        if (isset($desiredPermissions[$accountId][$permissionKey])) {
            continue;
        }
        sr_community_revoke_board_management_permission($pdo, (int) $existingManager['id'], $boardId, $actorAccountId);
        sr_audit_log($pdo, [
            'actor_account_id' => $actorAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.board_manager.revoked',
            'target_type' => 'community_board',
            'target_id' => (string) $boardId,
            'result' => 'success',
            'message' => 'Community board manager permission revoked with board save.',
            'metadata' => ['board_key' => $boardKey, 'account_id' => $accountId, 'permission_key' => $permissionKey],
        ]);
    }
    foreach ($desiredPermissions as $accountId => $permissionMap) {
        $newPermissionKeys = array_values(array_diff(array_keys($permissionMap), array_keys($existingPermissions[$accountId] ?? [])));
        if ($newPermissionKeys === []) {
            continue;
        }
        $grantedPermissionKeys = sr_community_grant_board_management_permissions($pdo, $boardId, $accountId, $newPermissionKeys, $actorAccountId);
        sr_audit_log($pdo, [
            'actor_account_id' => $actorAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.board_manager.granted',
            'target_type' => 'community_board',
            'target_id' => (string) $boardId,
            'result' => 'success',
            'message' => 'Community board manager permissions granted with board save.',
            'metadata' => ['board_key' => $boardKey, 'account_id' => $accountId, 'permission_keys' => $grantedPermissionKeys],
        ]);
    }
}

function sr_community_admin_board_managers_from_json(PDO $pdo, ?string $json): array
{
    if ($json === null) {
        return ['items' => [], 'errors' => ['등록할 운영 스탭 데이터가 너무 큽니다.']];
    }

    $decoded = json_decode($json === '' ? '[]' : $json, true);
    if (!is_array($decoded) || !array_is_list($decoded)) {
        return ['items' => [], 'errors' => ['등록할 운영 스탭 데이터 형식이 올바르지 않습니다.']];
    }
    if (count($decoded) > 50) {
        return ['items' => [], 'errors' => ['게시판을 등록할 때 운영 스탭은 최대 50명까지 지정할 수 있습니다.']];
    }

    $items = [];
    $errors = [];
    $seenAccountIds = [];
    $accountStmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
    foreach ($decoded as $index => $item) {
        if (!is_array($item)) {
            $errors[] = '등록할 운영 스탭 ' . (string) ($index + 1) . '번 데이터가 올바르지 않습니다.';
            continue;
        }

        $accountIdValue = $item['account_id'] ?? null;
        $accountId = filter_var($accountIdValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $permissionInput = is_array($item['permission_keys'] ?? null) ? $item['permission_keys'] : [];
        $permissionKeys = [];
        foreach ($permissionInput as $permissionKey) {
            $permissionKey = (string) $permissionKey;
            if (!sr_community_board_manager_permission_is_valid($permissionKey)) {
                $errors[] = '운영 스탭 ' . (string) ($index + 1) . '번 권한 값이 올바르지 않습니다.';
                continue;
            }
            $permissionKeys[] = $permissionKey;
        }
        $permissionKeys = array_values(array_unique($permissionKeys));

        $accountExists = false;
        if ($accountId !== false) {
            $accountStmt->execute(['id' => (int) $accountId]);
            $accountExists = (bool) $accountStmt->fetchColumn();
        }
        if ($accountId === false || !$accountExists) {
            $errors[] = '운영 스탭 ' . (string) ($index + 1) . '번 회원을 찾을 수 없습니다.';
            $accountId = 0;
        } elseif (isset($seenAccountIds[(int) $accountId])) {
            $errors[] = '같은 회원을 운영 스탭에 두 번 지정할 수 없습니다.';
        }
        if ($permissionKeys === []) {
            $errors[] = '운영 스탭 ' . (string) ($index + 1) . '번 권한을 하나 이상 선택해 주세요.';
        }

        if ((int) $accountId > 0) {
            $seenAccountIds[(int) $accountId] = true;
        }
        $items[] = [
            'account_id' => (int) $accountId,
            'permission_keys' => $permissionKeys,
        ];
    }

    return ['items' => $items, 'errors' => array_values(array_unique($errors))];
}

function sr_community_admin_prepare_board_save_post(PDO $pdo, string $intent, array $context): array
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
    $siteMenuAvailable = !empty($context['site_menu_available']);
    $siteMenuOptions = is_array($context['site_menu_options'] ?? null) ? $context['site_menu_options'] : [];
    $afterSave = is_callable($context['after_save'] ?? null) ? $context['after_save'] : null;
    $reactionAvailable = sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/public-reaction.php');
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
    $commentEditorInput = sr_post_string('comment_editor', 30);
    $commentEditor = sr_editor_effective_key($pdo, sr_community_comment_editor_key($commentEditorInput));
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
    $initialCategories = [];
    $initialBoardManagers = [];
    if (in_array($intent, ['create', 'update'], true)) {
        $initialCategoryResult = sr_community_admin_board_categories_from_json(
            sr_post_string_without_truncation('categories_json', 100000)
        );
        $initialBoardManagerResult = sr_community_admin_board_managers_from_json(
            $pdo,
            sr_post_string_without_truncation('board_managers_json', 100000)
        );
        $initialCategories = is_array($initialCategoryResult['items'] ?? null) ? $initialCategoryResult['items'] : [];
        $initialBoardManagers = is_array($initialBoardManagerResult['items'] ?? null) ? $initialBoardManagerResult['items'] : [];
        $errors = array_merge(
            $errors,
            is_array($initialCategoryResult['errors'] ?? null) ? $initialCategoryResult['errors'] : [],
            is_array($initialBoardManagerResult['errors'] ?? null) ? $initialBoardManagerResult['errors'] : []
        );
        if ($initialCategories !== [] && !sr_community_categories_supported($pdo)) {
            $errors[] = '카테고리 스키마 업데이트가 아직 적용되지 않았습니다.';
        }
    }
    $seriesEnabled = ($_POST['series_enabled'] ?? '') === '1';
    $secretPostsEnabled = ($_POST['secret_posts_enabled'] ?? '') === '1';
    $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
    $antispamPostModeInput = sr_post_string('antispam_post_mode', 20);
    $antispamCommentModeInput = sr_post_string('antispam_comment_mode', 20);
    $antispamPostMode = sr_community_antispam_mode($antispamPostModeInput);
    $antispamCommentMode = sr_community_antispam_mode($antispamCommentModeInput);
    $postEditLockCommentCount = sr_admin_post_int_in_range('post_edit_lock_comment_count', 0, 1000000);
    $postDeleteLockCommentCount = sr_admin_post_int_in_range('post_delete_lock_comment_count', 0, 1000000);
    $postBodyMaxSettingLength = sr_community_post_body_setting_max_length();
    $postBodyMinLength = sr_admin_post_int_in_range('post_body_min_length', 0, $postBodyMaxSettingLength);
    $postBodyMaxLength = sr_admin_post_int_in_range('post_body_max_length', 0, $postBodyMaxSettingLength);
    $commentBodyMinLength = sr_admin_post_int_in_range('comment_body_min_length', 0, 5000);
    $commentBodyMaxLength = sr_admin_post_int_in_range('comment_body_max_length', 0, 5000);
    $commentsPerPage = sr_admin_post_int_in_range('comments_per_page', 0, 100);
    $listExcerptEnabled = ($_POST['list_excerpt_enabled'] ?? '') === '1';
    $listExcerptLength = sr_admin_post_int_in_range('list_excerpt_length', 1, 1000);
    $listPerPage = sr_admin_post_int_in_range('list_per_page', 1, 100);
    $listDefaultSortInput = sr_post_string('list_default_sort', 20);
    $listDefaultSort = sr_community_board_list_sort_key($listDefaultSortInput);
    $summaryFeedEnabled = ($_POST['summary_feed_enabled'] ?? '') === '1';
    $boardSidebarMenuTypeInput = sr_post_string('board_sidebar_menu_type', 30);
    $boardSidebarMenuType = sr_community_board_sidebar_menu_type($boardSidebarMenuTypeInput);
    $boardSidebarSiteMenuKey = sr_community_board_sidebar_site_menu_key(sr_post_string('board_sidebar_site_menu_key', 60));
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
            $errors = array_merge(
                $errors,
                sr_community_admin_validate_board_category_sync($pdo, $editingBoardId, $initialCategories)
            );
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
    $commentExtraFieldsInput = sr_post_string_without_truncation('comment_extra_fields_json', 20000);
    $commentExtraFieldDefinitionErrors = sr_comment_extra_field_definition_errors($commentExtraFieldsInput);
    $commentExtraFieldsJson = $commentExtraFieldDefinitionErrors === [] && is_string($commentExtraFieldsInput) ? sr_comment_extra_field_definitions_json($commentExtraFieldsInput) : '[]';
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
    $settingSources['board_sidebar_site_menu_key'] = (string) ($settingSources['board_sidebar_menu_type'] ?? 'board');
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

    return compact(
        'errors',
        'notice',
        'allowedStatuses',
        'allowedReadPolicies',
        'allowedWritePolicies',
        'allowedCommentPolicies',
        'communitySkinOptions',
        'editorOptions',
        'settings',
        'maxLevel',
        'publicDisplaySettingLabels',
        'publicBannerSettingLabels',
        'publicPopupLayerSettingLabels',
        'publicBannerIds',
        'publicPopupLayerIds',
        'enabledMemberGroupKeys',
        'assetModuleOptions',
        'reactionPresetOptions',
        'siteMenuAvailable',
        'siteMenuOptions',
        'afterSave',
        'reactionAvailable',
        'boardKey',
        'title',
        'description',
        'status',
        'readPolicy',
        'writePolicy',
        'commentPolicy',
        'identityVerificationEnabled',
        'identityVerificationPurpose',
        'identityVerificationRequiredActions',
        'skinKey',
        'postEditorInput',
        'postEditor',
        'commentEditorInput',
        'commentEditor',
        'sortOrder',
        'attachmentMaxBytes',
        'attachmentMaxCount',
        'thumbnailEnabled',
        'thumbnailCriterionInput',
        'thumbnailCriterion',
        'thumbnailMinWidth',
        'thumbnailMinBytes',
        'publicDisplaySettingValues',
        'imageUploadsEnabled',
        'fileUploadsEnabled',
        'fileAttachmentMaxBytes',
        'fileAttachmentMaxCount',
        'fileAllowedExtensionsInput',
        'fileAllowedExtensions',
        'readMinLevel',
        'writeMinLevel',
        'commentMinLevel',
        'categoryEnabled',
        'categoryRequired',
        'initialCategories',
        'initialBoardManagers',
        'seriesEnabled',
        'secretPostsEnabled',
        'secretCommentsEnabled',
        'antispamPostModeInput',
        'antispamCommentModeInput',
        'antispamPostMode',
        'antispamCommentMode',
        'postEditLockCommentCount',
        'postDeleteLockCommentCount',
        'postBodyMinLength',
        'postBodyMaxLength',
        'commentBodyMinLength',
        'commentBodyMaxLength',
        'commentsPerPage',
        'listExcerptEnabled',
        'listExcerptLength',
        'listPerPage',
        'listDefaultSortInput',
        'listDefaultSort',
        'summaryFeedEnabled',
        'boardSidebarMenuTypeInput',
        'boardSidebarMenuType',
        'boardSidebarSiteMenuKey',
        'reactionEnabledInput',
        'reactionPostPresetInput',
        'reactionCommentPresetInput',
        'reactionEnabled',
        'reactionPostPresetKey',
        'reactionCommentPresetKey',
        'privacyConsentEnabled',
        'privacyConsentDocumentKeys',
        'privacyConsentRequires',
        'privacyConsentDocumentKey',
        'privacyConsentDocumentInheritPolicy',
        'privacyConsentRequirePost',
        'privacyConsentRequireComment',
        'privacyConsentRequireAttachmentUpload',
        'extraFieldDefinitionErrors',
        'extraFieldsJson',
        'commentExtraFieldDefinitionErrors',
        'commentExtraFieldsJson',
        'boardSeoValues',
        'boardOgImageUploadFile',
        'boardOgImageUploadProvided',
        'levelPostScore',
        'levelCommentScore',
        'boardGroupId',
        'boardGroup',
        'readGroupKeysInput',
        'writeGroupKeysInput',
        'commentGroupKeysInput',
        'readGroupKeys',
        'writeGroupKeys',
        'commentGroupKeys',
        'assetSettings',
        'assetSettingSources',
        'assetPrefixSources',
        'assetSettingLabels',
        'settingSources'
    );
}

function sr_community_admin_validate_board_save_post(PDO $pdo, string $intent, array $save): array
{
    $save['boardSettingValues'] = [];
    if ($intent === 'create' && !sr_community_board_key_is_valid($save['boardKey'])) {
        $save['errors'][] = sr_t('community::action.admin.board_key_invalid');
    }

    if ($save['title'] === '') {
        $save['errors'][] = sr_t('community::action.admin.board_title_required');
    }

    if ($save['description'] === null) {
        $save['errors'][] = sr_t('community::action.admin.description_too_long');
        $save['description'] = '';
    }
    if ($save['boardSidebarMenuTypeInput'] !== $save['boardSidebarMenuType'] || !array_key_exists($save['boardSidebarMenuType'], sr_community_board_sidebar_menu_type_options($save['siteMenuAvailable']))) {
        $save['errors'][] = '게시판 사이드 메뉴 유형이 올바르지 않습니다.';
    } elseif ($save['boardSidebarMenuType'] === 'site_menu' && ($save['boardSidebarSiteMenuKey'] === '' || !isset($save['siteMenuOptions'][$save['boardSidebarSiteMenuKey']]))) {
        $save['errors'][] = '게시판 사이드에 표시할 사이트 메뉴를 선택하세요.';
    }

    if (!in_array($save['status'], $save['allowedStatuses'], true)) {
        $save['errors'][] = sr_t('community::action.admin.board_status_invalid');
    }

    if (!in_array($save['readPolicy'], $save['allowedReadPolicies'], true)) {
        $save['errors'][] = sr_t('community::action.admin.read_policy_invalid');
    }

    if (!in_array($save['writePolicy'], $save['allowedWritePolicies'], true)) {
        $save['errors'][] = sr_t('community::action.admin.write_policy_invalid');
    }

    if (!in_array($save['commentPolicy'], $save['allowedCommentPolicies'], true)) {
        $save['errors'][] = sr_t('community::action.admin.comment_policy_invalid');
    }

    if (!isset($save['communitySkinOptions'][$save['skinKey']])) {
        $save['errors'][] = sr_t('community::action.admin.board_skin_invalid');
        $save['skinKey'] = 'basic';
    }

    if ($save['boardGroupId'] > 0 && !is_array($save['boardGroup'])) {
        $save['errors'][] = sr_t('community::action.admin.board_group_invalid');
    }

    if ($save['postEditorInput'] !== $save['postEditor'] || !array_key_exists($save['postEditor'], $save['editorOptions'])) {
        $save['errors'][] = '게시판 에디터 값이 올바르지 않습니다.';
        $save['postEditor'] = 'textarea';
    }
    if ($save['commentEditorInput'] !== $save['commentEditor'] || !array_key_exists($save['commentEditor'], $save['editorOptions'])) {
        $save['errors'][] = '댓글 에디터 값이 올바르지 않습니다.';
        $save['commentEditor'] = 'textarea';
    }
    if ($save['antispamPostModeInput'] !== $save['antispamPostMode'] || $save['antispamCommentModeInput'] !== $save['antispamCommentMode']) {
        $save['errors'][] = '게시판 자동등록방지 적용 모드를 확인해 주세요.';
    }

    if ($save['sortOrder'] === null) {
        $save['errors'][] = sr_t('community::action.admin.sort_order_invalid');
        $save['sortOrder'] = 0;
    }

    if ($save['attachmentMaxBytes'] === null) {
        $save['errors'][] = sr_t('community::action.admin.image_max_bytes_invalid');
        $save['attachmentMaxBytes'] = 2097152;
    }

    if ($save['attachmentMaxCount'] === null) {
        $save['errors'][] = sr_t('community::action.admin.image_max_count_invalid');
        $save['attachmentMaxCount'] = 1;
    }

    foreach ($save['publicDisplaySettingValues'] as $displaySettingKey => $displaySettingValue) {
        $displaySettingLabel = (string) ($save['publicDisplaySettingLabels'][$displaySettingKey] ?? $displaySettingKey);
        if ($displaySettingValue === null) {
            $save['errors'][] = sr_t('community::action.admin.display_value_invalid', ['label' => $displaySettingLabel]);
            $save['publicDisplaySettingValues'][$displaySettingKey] = 0;
            continue;
        }

        if (isset($save['publicBannerSettingLabels'][$displaySettingKey]) && $displaySettingValue > 0 && !isset($save['publicBannerIds'][$displaySettingValue])) {
            $save['errors'][] = sr_t('community::action.admin.display_banner_invalid', ['label' => $displaySettingLabel]);
        }

        if (isset($save['publicPopupLayerSettingLabels'][$displaySettingKey]) && $displaySettingValue > 0 && !isset($save['publicPopupLayerIds'][$displaySettingValue])) {
            $save['errors'][] = sr_t('community::action.admin.display_popup_invalid', ['label' => $displaySettingLabel]);
        }
    }

    if ($save['fileAttachmentMaxBytes'] === null) {
        $save['errors'][] = sr_t('community::action.admin.file_max_bytes_invalid');
        $save['fileAttachmentMaxBytes'] = 5242880;
    }

    if ($save['fileAttachmentMaxCount'] === null) {
        $save['errors'][] = sr_t('community::action.admin.file_max_count_invalid');
        $save['fileAttachmentMaxCount'] = 3;
    }

    if (!is_string($save['fileAllowedExtensionsInput'])) {
        $save['errors'][] = sr_t('community::action.admin.file_extensions_too_long');
        $save['fileAllowedExtensions'] = [];
    } else {
        $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($save['fileAllowedExtensionsInput']);
        if ($invalidFileExtensions !== []) {
            $save['errors'][] = sr_t('community::action.admin.file_extensions_invalid', ['extensions' => implode(', ', $invalidFileExtensions)]);
        }
    }

    if ($save['fileUploadsEnabled'] && $save['fileAllowedExtensions'] === []) {
        $save['errors'][] = sr_t('community::action.admin.file_extensions_required');
    }

    if ($save['readMinLevel'] === null) {
        $save['errors'][] = sr_t('community::action.admin.read_min_level_invalid', ['max' => (string) $save['maxLevel']]);
        $save['readMinLevel'] = 0;
    }

    if ($save['writeMinLevel'] === null) {
        $save['errors'][] = sr_t('community::action.admin.write_min_level_invalid', ['max' => (string) $save['maxLevel']]);
        $save['writeMinLevel'] = 0;
    }

    if ($save['commentMinLevel'] === null) {
        $save['errors'][] = sr_t('community::action.admin.comment_min_level_invalid', ['max' => (string) $save['maxLevel']]);
        $save['commentMinLevel'] = 0;
    }

    if ($save['levelPostScore'] === null) {
        $save['errors'][] = sr_t('community::action.admin.post_score_invalid');
        $save['levelPostScore'] = (int) $save['settings']['level_post_score'];
    }

    if ($save['levelCommentScore'] === null) {
        $save['errors'][] = sr_t('community::action.admin.comment_score_invalid');
        $save['levelCommentScore'] = (int) $save['settings']['level_comment_score'];
    }

    if ($save['categoryRequired']) {
        $hasEnabledCategory = false;
        foreach ($save['initialCategories'] as $initialCategory) {
            if ((string) ($initialCategory['status'] ?? '') === 'enabled') {
                $hasEnabledCategory = true;
                break;
            }
        }
        if (!$hasEnabledCategory) {
            $save['errors'][] = '활성 카테고리가 1개 이상 있어야 카테고리 필수를 켤 수 있습니다.';
        }
    }
    if ($save['thumbnailCriterionInput'] !== $save['thumbnailCriterion']) {
        $save['errors'][] = '썸네일 생성 기준 선택이 올바르지 않습니다.';
    }
    if ($save['thumbnailCriterion'] === 'width' && $save['thumbnailMinWidth'] === null) {
        $save['errors'][] = '썸네일 생성 기준 너비가 올바르지 않습니다.';
        $save['thumbnailMinWidth'] = 320;
    }
    if ($save['thumbnailCriterion'] === 'bytes' && $save['thumbnailMinBytes'] === null) {
        $save['errors'][] = '썸네일 생성 기준 용량이 올바르지 않습니다.';
        $save['thumbnailMinBytes'] = 102400;
    }
    if ($save['thumbnailMinWidth'] === null) {
        $save['thumbnailMinWidth'] = 320;
    }
    if ($save['thumbnailMinBytes'] === null) {
        $save['thumbnailMinBytes'] = 102400;
    }

    foreach ([
        'postEditLockCommentCount' => ['value' => $save['postEditLockCommentCount'], 'message' => '게시글 수정 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
        'postDeleteLockCommentCount' => ['value' => $save['postDeleteLockCommentCount'], 'message' => '게시글 삭제 잠금 댓글 수가 올바르지 않습니다.', 'fallback' => 0],
        'postBodyMinLength' => ['value' => $save['postBodyMinLength'], 'message' => '게시글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
        'postBodyMaxLength' => ['value' => $save['postBodyMaxLength'], 'message' => '게시글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
        'commentBodyMinLength' => ['value' => $save['commentBodyMinLength'], 'message' => '댓글 본문 최소 길이가 올바르지 않습니다.', 'fallback' => 0],
        'commentBodyMaxLength' => ['value' => $save['commentBodyMaxLength'], 'message' => '댓글 본문 최대 길이가 올바르지 않습니다.', 'fallback' => 0],
        'commentsPerPage' => ['value' => $save['commentsPerPage'], 'message' => '댓글 페이지당 수가 올바르지 않습니다.', 'fallback' => 0],
        'listExcerptLength' => ['value' => $save['listExcerptLength'], 'message' => '목록 본문 요약 길이가 올바르지 않습니다.', 'fallback' => 120],
        'listPerPage' => ['value' => $save['listPerPage'], 'message' => '목록 페이지당 글 수가 올바르지 않습니다.', 'fallback' => 20],
    ] as $numericSettingKey => $numericSetting) {
        if ($numericSetting['value'] === null) {
            $save['errors'][] = (string) $numericSetting['message'];
            $save[$numericSettingKey] = (int) $numericSetting['fallback'];
        }
    }
    if ($save['postBodyMinLength'] > 0 && $save['postBodyMaxLength'] > 0 && $save['postBodyMinLength'] > $save['postBodyMaxLength']) {
        $save['errors'][] = '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
    }
    if ($save['commentBodyMinLength'] > 0 && $save['commentBodyMaxLength'] > 0 && $save['commentBodyMinLength'] > $save['commentBodyMaxLength']) {
        $save['errors'][] = '댓글 본문 최소 길이는 최대 길이보다 클 수 없습니다.';
    }
    if ($save['listDefaultSortInput'] !== $save['listDefaultSort']) {
        $save['errors'][] = '목록 기본 정렬 값이 올바르지 않습니다.';
    }

    if (!$save['reactionAvailable']) {
        if ($save['reactionEnabledInput'] || $save['reactionPostPresetInput'] !== '' || $save['reactionCommentPresetInput'] !== '') {
            $save['errors'][] = '게시판 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
        }
    } else {
        foreach (['reaction_post_preset_key' => $save['reactionPostPresetKey'], 'reaction_comment_preset_key' => $save['reactionCommentPresetKey']] as $reactionSettingKey => $reactionPresetKey) {
            if ($reactionPresetKey !== '' && !isset($save['reactionPresetOptions'][$reactionPresetKey])) {
                $save['errors'][] = '게시판 리액션 프리셋 값이 올바르지 않습니다.';
                break;
            }
        }
    }

    if ($save['privacyConsentEnabled']) {
        if (!sr_community_submission_consents_table_exists($pdo)) {
            $save['errors'][] = '개인정보 수집 및 이용동의 스키마 업데이트가 아직 적용되지 않았습니다.';
        }
        if (!$save['privacyConsentRequirePost'] && !$save['privacyConsentRequireComment'] && !$save['privacyConsentRequireAttachmentUpload']) {
            $save['errors'][] = '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.';
        }
        foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
            if (empty($save['privacyConsentRequires'][$privacyConsentTargetKey])) {
                continue;
            }
            $targetDocumentKey = (string) ($save['privacyConsentDocumentKeys'][$privacyConsentTargetKey] ?? '');
            $targetDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
            if (($save['settingSources'][$targetDocumentSettingKey] ?? 'board') === 'board'
                && ($targetDocumentKey === '' || !is_array(sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey)))) {
                $save['errors'][] = sr_community_privacy_consent_admin_label($privacyConsentTargetKey) . ' 정책 문서를 선택해 주세요.';
            }
        }
    }

    if (!$save['boardOgImageUploadProvided'] && $save['boardSeoValues']['og_image_url'] !== '' && !sr_is_http_url($save['boardSeoValues']['og_image_url']) && !sr_is_safe_relative_url($save['boardSeoValues']['og_image_url'])) {
        $save['errors'][] = '게시판 OG 이미지는 http(s) URL 또는 /로 시작하는 내부 경로만 입력해 주세요.';
    }

    foreach ($save['settingSources'] as $settingKey => $source) {
        if ($source === 'group' && $save['boardGroupId'] < 1) {
            $save['errors'][] = sr_t('community::action.admin.setting_group_source_requires_group', ['setting' => $settingKey]);
        }
    }

    foreach ($save['assetSettingSources'] as $settingKey => $source) {
        if ($source === 'group' && $save['boardGroupId'] < 1) {
            $assetPrefix = sr_community_asset_prefix_from_setting_key((string) $settingKey);
            $assetLabel = (string) ($save['assetSettingLabels'][$assetPrefix] ?? $settingKey);
            $save['errors'][] = sr_t('community::action.admin.asset_group_source_requires_group', ['label' => $assetLabel]);
        }
    }

    foreach ([
        ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $save['readGroupKeysInput']],
        ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $save['writeGroupKeysInput']],
        ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $save['commentGroupKeysInput']],
    ] as $groupKeyValidation) {
        $label = (string) $groupKeyValidation['label'];
        $groupKeysInput = $groupKeyValidation['value'];
        if (sr_community_board_group_keys_input_too_long($groupKeysInput)) {
            $save['errors'][] = sr_t('community::action.admin.group_list_too_long', ['label' => $label]);
            continue;
        }

        $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input_value($groupKeysInput);
        if ($invalidGroupKeys !== []) {
            $save['errors'][] = sr_t('community::action.admin.group_keys_invalid', ['label' => $label, 'keys' => implode(', ', $invalidGroupKeys)]);
        }
    }

    foreach ([
        ['label' => sr_t('community::action.admin.label.read_group'), 'value' => $save['readGroupKeys']],
        ['label' => sr_t('community::action.admin.label.write_group'), 'value' => $save['writeGroupKeys']],
        ['label' => sr_t('community::action.admin.label.comment_group'), 'value' => $save['commentGroupKeys']],
    ] as $groupKeyValidation) {
        $label = (string) $groupKeyValidation['label'];
        $groupKeys = $groupKeyValidation['value'];
        $unknownGroupKeys = array_values(array_diff($groupKeys, $save['enabledMemberGroupKeys']));
        if ($unknownGroupKeys !== []) {
            $save['errors'][] = sr_t('community::action.admin.group_keys_inactive', ['label' => $label, 'keys' => implode(', ', $unknownGroupKeys)]);
        }
    }

    $save['writeGroupKeys'] = array_values(array_intersect($save['writeGroupKeys'], $save['readGroupKeys']));
    $save['commentGroupKeys'] = array_values(array_intersect($save['commentGroupKeys'], $save['readGroupKeys']));

    foreach ($save['assetSettingLabels'] as $assetPrefix => $assetLabel) {
        if ($save['assetSettings'][$assetPrefix . '_amount'] === null) {
            $save['errors'][] = sr_t('community::action.admin.asset_amount_invalid', ['label' => $assetLabel]);
            $save['assetSettings'][$assetPrefix . '_amount'] = 0;
        }

        if (($save['assetPrefixSources'][$assetPrefix] ?? 'board') === 'board' && !empty($save['assetSettings'][$assetPrefix . '_enabled']) && (int) $save['assetSettings'][$assetPrefix . '_amount'] > 0) {
            $assetModule = (string) $save['assetSettings'][$assetPrefix . '_asset_module'];
            if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
                $assetModules = sr_community_asset_module_keys_from_value($assetModule, true);
                if (!sr_community_asset_modules_available($pdo, $assetModules)) {
                    $save['errors'][] = sr_t('community::action.admin.asset_modules_required_active', ['label' => $assetLabel]);
                }
                $amounts = sr_community_asset_amounts_from_value($save['assetSettings'][$assetPrefix . '_amounts_json'] ?? '', $assetModules);
                if (count($amounts) < count($assetModules)) {
                    $save['errors'][] = sr_t('community::action.admin.asset_amounts_required', ['label' => $assetLabel]);
                }
            } elseif (!isset($save['assetModuleOptions'][$assetModule])) {
                $save['errors'][] = sr_t('community::action.admin.asset_module_inactive', [
                    'label' => $assetLabel,
                    'module' => sr_community_asset_module_label($assetModule, $pdo),
                ]);
            }
        }
        $save['errors'] = array_merge($save['errors'], sr_admin_asset_group_policy_validation_errors($pdo, sr_community_asset_group_policies_from_value($save['assetSettings'][$assetPrefix . '_group_policies_json'] ?? ''), $assetLabel));
        $assetPolicySetIds = sr_community_asset_policy_set_ids_with_legacy($save['assetSettings'][$assetPrefix . '_group_policies_json'] ?? '', (int) ($save['assetSettings'][$assetPrefix . '_policy_set_id'] ?? 0));
        $assetModulesForPolicy = sr_community_asset_module_keys_from_value((string) ($save['assetSettings'][$assetPrefix . '_asset_module'] ?? ''), true);
        $save['errors'] = array_merge($save['errors'], sr_community_asset_policy_set_ids_validation_errors($pdo, $assetPolicySetIds, $assetLabel));
        $save['errors'] = array_merge($save['errors'], sr_community_asset_policy_set_asset_match_errors($pdo, $assetPolicySetIds, $assetModulesForPolicy, $assetLabel));
    }
    if ($save['assetSettings']['paid_attachment_download_publisher_reward_rate'] === null) {
        $save['errors'][] = '첨부 다운로드 게시자 보상 지급률이 올바르지 않습니다.';
        $save['assetSettings']['paid_attachment_download_publisher_reward_rate'] = 0;
    }
    if ($save['extraFieldDefinitionErrors'] !== []) {
        $save['errors'] = array_merge($save['errors'], $save['extraFieldDefinitionErrors']);
        $save['extraFieldsJson'] = '[]';
    }
    if ($save['commentExtraFieldDefinitionErrors'] !== []) {
        $save['errors'] = array_merge($save['errors'], $save['commentExtraFieldDefinitionErrors']);
    }

    if ($save['errors'] === [] && $intent === 'create' && sr_community_board_by_key($pdo, $save['boardKey']) !== null) {
        $save['errors'][] = sr_t('community::action.admin.board_key_duplicate');
    }

    if ($save['errors'] === [] && $save['boardOgImageUploadProvided']) {
        if (!is_array($save['boardOgImageUploadFile'])) {
            $save['errors'][] = '업로드할 게시판 OG 이미지를 확인할 수 없습니다.';
        } else {
            try {
                $uploadedBoardOgImage = sr_site_upload_og_image($save['boardOgImageUploadFile']);
                $save['boardSeoValues']['og_image_url'] = sr_community_seo_text((string) ($uploadedBoardOgImage['public_url'] ?? ''), 255);
            } catch (Throwable $exception) {
                $save['errors'][] = $exception->getMessage();
            }
        }
    }

    if ($save['errors'] === []) {
        $save['boardSettingValues'] = [
            'status' => $save['status'],
            'skin_key' => $save['skinKey'],
            'post_editor' => $save['postEditor'],
            'comment_editor' => $save['commentEditor'],
            'read_policy' => $save['readPolicy'],
            'write_policy' => $save['writePolicy'],
            'comment_policy' => $save['commentPolicy'],
            'identity_verification_enabled' => $save['identityVerificationEnabled'] ? '1' : '0',
            'identity_verification_purpose' => $save['identityVerificationPurpose'],
            'identity_verification_required_actions' => sr_community_identity_verification_actions_setting_value($save['identityVerificationRequiredActions']),
            'read_group_keys' => sr_community_board_group_keys_setting_value($save['readGroupKeys']),
            'write_group_keys' => sr_community_board_group_keys_setting_value($save['writeGroupKeys']),
            'comment_group_keys' => sr_community_board_group_keys_setting_value($save['commentGroupKeys']),
            'read_min_level' => (string) $save['readMinLevel'],
            'write_min_level' => (string) $save['writeMinLevel'],
            'comment_min_level' => (string) $save['commentMinLevel'],
            'category_enabled' => $save['categoryEnabled'] ? '1' : '0',
            'category_required' => $save['categoryRequired'] ? '1' : '0',
            'series_enabled' => $save['seriesEnabled'] ? '1' : '0',
            'secret_posts_enabled' => $save['secretPostsEnabled'] ? '1' : '0',
            'secret_comments_enabled' => $save['secretCommentsEnabled'] ? '1' : '0',
            'antispam_post_mode' => $save['antispamPostMode'],
            'antispam_comment_mode' => $save['antispamCommentMode'],
            'post_edit_lock_comment_count' => (string) $save['postEditLockCommentCount'],
            'post_delete_lock_comment_count' => (string) $save['postDeleteLockCommentCount'],
            'post_body_min_length' => (string) $save['postBodyMinLength'],
            'post_body_max_length' => (string) $save['postBodyMaxLength'],
            'comment_body_min_length' => (string) $save['commentBodyMinLength'],
            'comment_body_max_length' => (string) $save['commentBodyMaxLength'],
            'comments_per_page' => (string) $save['commentsPerPage'],
            'list_excerpt_enabled' => $save['listExcerptEnabled'] ? '1' : '0',
            'list_excerpt_length' => (string) $save['listExcerptLength'],
            'list_per_page' => (string) $save['listPerPage'],
            'list_default_sort' => $save['listDefaultSort'],
            'summary_feed_enabled' => $save['summaryFeedEnabled'] ? '1' : '0',
            'board_sidebar_menu_type' => $save['boardSidebarMenuType'],
            'board_sidebar_site_menu_key' => $save['boardSidebarSiteMenuKey'],
            'reaction_enabled' => $save['reactionEnabled'] ? '1' : '0',
            'reaction_post_preset_key' => $save['reactionPostPresetKey'],
            'reaction_comment_preset_key' => $save['reactionCommentPresetKey'],
            'privacy_consent_enabled' => $save['privacyConsentEnabled'] ? '1' : '0',
            'privacy_consent_document_key' => $save['privacyConsentDocumentKey'] !== '' ? $save['privacyConsentDocumentKey'] : 'community_privacy_default',
            'privacy_consent_post_document_key' => (string) ($save['privacyConsentDocumentKeys']['post'] ?? ''),
            'privacy_consent_comment_document_key' => (string) ($save['privacyConsentDocumentKeys']['comment'] ?? ''),
            'privacy_consent_attachment_upload_document_key' => (string) ($save['privacyConsentDocumentKeys']['attachment_upload'] ?? ''),
            'privacy_consent_document_inherit_policy' => $save['privacyConsentDocumentInheritPolicy'],
            'privacy_consent_title' => '',
            'privacy_consent_body' => '',
            'privacy_consent_version' => '',
            'privacy_consent_require_post' => $save['privacyConsentRequirePost'] ? '1' : '0',
            'privacy_consent_require_comment' => $save['privacyConsentRequireComment'] ? '1' : '0',
            'privacy_consent_require_attachment_upload' => $save['privacyConsentRequireAttachmentUpload'] ? '1' : '0',
            'extra_fields_json' => $save['extraFieldsJson'],
            'comment_extra_fields_json' => $save['commentExtraFieldsJson'],
            'level_post_score' => (string) $save['levelPostScore'],
            'level_comment_score' => (string) $save['levelCommentScore'],
            'image_uploads_enabled' => $save['imageUploadsEnabled'] ? '1' : '0',
            'thumbnail_enabled' => $save['thumbnailEnabled'] ? '1' : '0',
            'thumbnail_criterion' => $save['thumbnailCriterion'],
            'thumbnail_min_width' => (string) $save['thumbnailMinWidth'],
            'thumbnail_min_bytes' => (string) $save['thumbnailMinBytes'],
            'attachment_max_bytes' => (string) $save['attachmentMaxBytes'],
            'attachment_max_count' => (string) $save['attachmentMaxCount'],
            'file_uploads_enabled' => $save['fileUploadsEnabled'] ? '1' : '0',
            'file_attachment_max_bytes' => (string) $save['fileAttachmentMaxBytes'],
            'file_attachment_max_count' => (string) $save['fileAttachmentMaxCount'],
            'file_allowed_extensions' => implode(',', $save['fileAllowedExtensions']),
        ];
        foreach ($save['publicDisplaySettingValues'] as $displaySettingKey => $displaySettingValue) {
            $save['boardSettingValues'][(string) $displaySettingKey] = (string) $displaySettingValue;
        }
        foreach ($save['boardSeoValues'] as $seoSettingKey => $seoSettingValue) {
            $save['boardSettingValues'][(string) $seoSettingKey] = (string) $seoSettingValue;
        }
    }

    return $save;
}

function sr_community_admin_create_board_from_save(PDO $pdo, array $account, array $save): array
{
    if ($save['errors'] === []) {
        $pdo->beginTransaction();
        try {
            $boardId = sr_community_create_board($pdo, [
                'board_key' => $save['boardKey'],
                'board_group_id' => $save['boardGroupId'],
                'title' => $save['title'],
                'description' => (string) $save['description'],
                'status' => $save['status'],
                'read_policy' => $save['readPolicy'],
                'write_policy' => $save['writePolicy'],
                'comment_policy' => $save['commentPolicy'],
                'image_uploads_enabled' => $save['imageUploadsEnabled'],
                'sort_order' => (int) $save['sortOrder'],
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
                    'board_key' => $save['boardKey'],
                    'board_group_id' => $save['boardGroupId'],
                    'status' => $save['status'],
                    'summary_feed_enabled' => $save['summaryFeedEnabled'],
                    'image_uploads_enabled' => $save['imageUploadsEnabled'],
                    'file_uploads_enabled' => $save['fileUploadsEnabled'],
                    'attachment_max_bytes' => $save['attachmentMaxBytes'],
                    'attachment_max_count' => $save['attachmentMaxCount'],
                    'file_attachment_max_bytes' => $save['fileAttachmentMaxBytes'],
                    'file_attachment_max_count' => $save['fileAttachmentMaxCount'],
                    'file_allowed_extensions' => $save['fileAllowedExtensions'],
                    'read_group_keys' => $save['readGroupKeys'],
                    'write_group_keys' => $save['writeGroupKeys'],
                    'comment_group_keys' => $save['commentGroupKeys'],
                    'read_min_level' => $save['readMinLevel'],
                    'identity_verification_enabled' => $save['identityVerificationEnabled'],
                    'identity_verification_purpose' => $save['identityVerificationPurpose'],
                    'identity_verification_required_actions' => $save['identityVerificationRequiredActions'],
                    'write_min_level' => $save['writeMinLevel'],
                    'comment_min_level' => $save['commentMinLevel'],
                    'category_enabled' => $save['categoryEnabled'],
                    'category_required' => $save['categoryRequired'],
                    'series_enabled' => $save['seriesEnabled'],
                    'level_post_score' => $save['levelPostScore'],
                    'level_comment_score' => $save['levelCommentScore'],
                    'secret_posts_enabled' => $save['secretPostsEnabled'],
                    'secret_comments_enabled' => $save['secretCommentsEnabled'],
                    'antispam_post_mode' => $save['antispamPostMode'],
                    'antispam_comment_mode' => $save['antispamCommentMode'],
                    'reaction_enabled' => $save['reactionEnabled'],
                    'reaction_post_preset_key' => $save['reactionPostPresetKey'],
                    'reaction_comment_preset_key' => $save['reactionCommentPresetKey'],
                    'skin_key' => $save['skinKey'],
                    'asset_settings' => $save['assetSettings'],
                    'asset_prefix_sources' => $save['assetPrefixSources'],
                    'asset_setting_sources' => $save['assetSettingSources'],
                    'setting_sources' => $save['settingSources'],
                ], $save['publicDisplaySettingValues']),
            ]);
            sr_community_admin_apply_board_settings(
                $pdo,
                $boardId,
                $save['boardGroupId'],
                $save['boardSettingValues'],
                $save['settingSources'],
                $save['extraFieldsJson'],
                $save['assetSettings'],
                $save['assetSettingSources']
            );

            sr_community_admin_sync_board_children(
                $pdo,
                $boardId,
                $save['boardKey'],
                $save['initialCategories'],
                $save['initialBoardManagers'],
                (int) $account['id']
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($save['afterSave'] !== null) {
            try {
                $save['afterSave']($boardId, true);
            } catch (Throwable $exception) {
                if (function_exists('sr_log_exception')) {
                    sr_log_exception($exception, 'community_board_admin_draft_cleanup_failed');
                }
            }
        }

        $save['notice'] = sr_t('community::action.admin.board_created');
        sr_admin_flash_result(sr_admin_action_result([], $save['notice']));
        sr_redirect('/admin/community/boards');
    }

    return [
        'errors' => $save['errors'],
        'notice' => $save['notice'],
    ];
}

function sr_community_admin_update_board_from_save(PDO $pdo, array $account, array $save): array
{
    if ($save['errors'] === []) {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $save['errors'][] = sr_t('community::action.error.board_not_found');
        }

        if ($save['errors'] === [] && is_array($board)) {
            $pdo->beginTransaction();
            try {
                $beforeAttachmentMaxBytes = sr_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = sr_community_board_attachment_max_count($pdo, $boardId);
                $beforeThumbnailSettings = [];
                foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
                    $beforeThumbnailSettings[$thumbnailSettingKey] = sr_community_board_thumbnail_setting($pdo, $boardId, $thumbnailSettingKey, $save['settings']);
                }
                $beforePublicDisplaySettingValues = [];
                foreach ($save['publicDisplaySettingLabels'] as $displaySettingKey => $displaySettingLabel) {
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
                $beforeSeriesEnabled = sr_community_effective_board_series_enabled($pdo, $board, $save['settings']);
                $beforeLevelPostScore = sr_community_board_level_score($pdo, $boardId, 'level_post_score', $save['settings']);
                $beforeLevelCommentScore = sr_community_board_level_score($pdo, $boardId, 'level_comment_score', $save['settings']);
                $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
                $beforeAssetSettingSources = [];
                foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
                    $beforeAssetSettingSources[$assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeAssetSettings = [];
                foreach ($save['assetSettings'] as $assetSettingKey => $assetSettingValue) {
                    $beforeAssetSettings[$assetSettingKey] = sr_community_board_setting_value($pdo, $boardId, (string) $assetSettingKey);
                }
                $beforeCurrentBoardAssetSettingsForAudit = sr_community_board_asset_settings_for_audit($pdo, $boardId);
                sr_community_update_board($pdo, $boardId, [
                    'board_group_id' => $save['boardGroupId'],
                    'title' => $save['title'],
                    'description' => (string) $save['description'],
                    'status' => $save['status'],
                    'read_policy' => $save['readPolicy'],
                    'write_policy' => $save['writePolicy'],
                    'comment_policy' => $save['commentPolicy'],
                    'image_uploads_enabled' => $save['imageUploadsEnabled'],
                    'sort_order' => (int) $save['sortOrder'],
                ]);
                $boardAssetAudits = [];
                foreach ($save['assetSettingSources'] as $settingKey => $source) {
                    foreach (sr_community_board_scope_target_ids($pdo, $boardId, $save['boardGroupId'], (string) $source) as $targetBoardId) {
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
                sr_community_admin_apply_board_settings(
                    $pdo,
                    $boardId,
                    $save['boardGroupId'],
                    $save['boardSettingValues'],
                    $save['settingSources'],
                    $save['extraFieldsJson'],
                    $save['assetSettings'],
                    $save['assetSettingSources']
                );

                $publicDisplayMetadata = [];
                foreach ($save['publicDisplaySettingValues'] as $displaySettingKey => $displaySettingValue) {
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
                        'after_status' => $save['status'],
                        'before_board_group_id' => (int) ($board['board_group_id'] ?? 0),
                        'after_board_group_id' => $save['boardGroupId'],
                        'before_summary_feed_enabled' => sr_community_effective_board_summary_feed_enabled($pdo, $board),
                        'after_summary_feed_enabled' => $save['summaryFeedEnabled'],
                        'before_image_uploads_enabled' => (int) $board['image_uploads_enabled'] === 1,
                        'after_image_uploads_enabled' => $save['imageUploadsEnabled'],
                        'after_file_uploads_enabled' => $save['fileUploadsEnabled'],
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $save['attachmentMaxBytes'],
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $save['attachmentMaxCount'],
                        'before_thumbnail_settings' => $beforeThumbnailSettings,
                        'after_thumbnail_settings' => [
                            'thumbnail_enabled' => $save['thumbnailEnabled'] ? '1' : '0',
                            'thumbnail_criterion' => $save['thumbnailCriterion'],
                            'thumbnail_min_width' => (string) $save['thumbnailMinWidth'],
                            'thumbnail_min_bytes' => (string) $save['thumbnailMinBytes'],
                        ],
                        'before_file_attachment_max_bytes' => $beforeFileAttachmentMaxBytes,
                        'after_file_attachment_max_bytes' => $save['fileAttachmentMaxBytes'],
                        'before_file_attachment_max_count' => $beforeFileAttachmentMaxCount,
                        'after_file_attachment_max_count' => $save['fileAttachmentMaxCount'],
                        'before_file_allowed_extensions' => $beforeFileAllowedExtensions,
                        'after_file_allowed_extensions' => $save['fileAllowedExtensions'],
                        'before_read_group_keys' => $beforeReadGroupKeys,
                        'after_read_group_keys' => $save['readGroupKeys'],
                        'before_write_group_keys' => $beforeWriteGroupKeys,
                        'after_write_group_keys' => $save['writeGroupKeys'],
                        'before_comment_group_keys' => $beforeCommentGroupKeys,
                        'after_comment_group_keys' => $save['commentGroupKeys'],
                        'before_read_min_level' => $beforeReadMinLevel,
                        'after_read_min_level' => $save['readMinLevel'],
                        'before_write_min_level' => $beforeWriteMinLevel,
                        'after_write_min_level' => $save['writeMinLevel'],
                        'before_comment_min_level' => $beforeCommentMinLevel,
                        'after_comment_min_level' => $save['commentMinLevel'],
                        'before_category_enabled' => $beforeCategoryEnabled,
                        'after_category_enabled' => $save['categoryEnabled'],
                        'before_category_required' => $beforeCategoryRequired,
                        'after_category_required' => $save['categoryRequired'],
                        'before_series_enabled' => $beforeSeriesEnabled,
                        'after_series_enabled' => $save['seriesEnabled'],
                        'before_level_post_score' => $beforeLevelPostScore,
                        'after_level_post_score' => $save['levelPostScore'],
                        'before_level_comment_score' => $beforeLevelCommentScore,
                        'after_level_comment_score' => $save['levelCommentScore'],
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $save['skinKey'],
                        'after_reaction_enabled' => $save['reactionEnabled'],
                        'after_reaction_post_preset_key' => $save['reactionPostPresetKey'],
                        'after_reaction_comment_preset_key' => $save['reactionCommentPresetKey'],
                        'before_asset_setting_sources' => $beforeAssetSettingSources,
                        'after_asset_prefix_sources' => $save['assetPrefixSources'],
                        'after_asset_setting_sources' => $save['assetSettingSources'],
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $save['assetSettings'],
                        'setting_sources' => $save['settingSources'],
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

                sr_community_admin_sync_board_children(
                    $pdo,
                    $boardId,
                    (string) $board['board_key'],
                    $save['initialCategories'],
                    $save['initialBoardManagers'],
                    (int) $account['id']
                );
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            if ($save['afterSave'] !== null) {
                try {
                    $save['afterSave']($boardId, false);
                } catch (Throwable $exception) {
                    if (function_exists('sr_log_exception')) {
                        sr_log_exception($exception, 'community_board_admin_draft_cleanup_failed');
                    }
                }
            }

            $save['notice'] = sr_t('community::action.admin.board_updated');
        }
    }

    return [
        'errors' => $save['errors'],
        'notice' => $save['notice'],
    ];
}

function sr_community_admin_persist_board_save_post(PDO $pdo, string $intent, array $account, array $save): array
{
    if ($intent === 'create') {
        return sr_community_admin_create_board_from_save($pdo, $account, $save);
    }
    if ($intent === 'update') {
        return sr_community_admin_update_board_from_save($pdo, $account, $save);
    }

    return [
        'errors' => $save['errors'],
        'notice' => $save['notice'],
    ];
}

function sr_community_admin_handle_board_save_post(PDO $pdo, string $intent, array $account, array $context): array
{
    $save = sr_community_admin_prepare_board_save_post($pdo, $intent, $context);
    $save = sr_community_admin_validate_board_save_post($pdo, $intent, $save);

    return sr_community_admin_persist_board_save_post($pdo, $intent, $account, $save);
}
