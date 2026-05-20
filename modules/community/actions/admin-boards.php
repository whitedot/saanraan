<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
if (!in_array($communityBoardsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardsPage = 'list';
}
$allowedStatuses = sr_community_board_statuses();
$allowedReadPolicies = sr_community_policy_values('read');
$allowedWritePolicies = sr_community_policy_values('write');
$allowedCommentPolicies = sr_community_policy_values('comment');
$communitySkinOptions = sr_community_skin_options();
$settings = sr_community_settings($pdo);
$assetModuleOptions = sr_community_asset_module_options($pdo);
$maxLevel = sr_community_max_level_value();
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$publicBannerSettingLabels = [
    'banner_before_list_id' => '목록 상단 배너',
    'banner_after_list_id' => '목록 하단 배너',
    'banner_before_view_id' => '글보기 상단 배너',
    'banner_after_view_id' => '글보기 하단 배너',
    'banner_before_form_id' => '글쓰기 폼 상단 배너',
    'banner_after_form_id' => '글쓰기 폼 하단 배너',
];
$publicPopupLayerSettingLabels = [
    'popup_layer_list_id' => '목록 팝업레이어',
    'popup_layer_view_id' => '글보기 팝업레이어',
    'popup_layer_form_id' => '글쓰기 폼 팝업레이어',
];
$publicDisplaySettingLabels = $publicBannerSettingLabels + $publicPopupLayerSettingLabels;
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

$boardGroups = sr_community_board_groups($pdo);
$boardGroupIds = [];
$boardGroupTitles = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupIds[(int) $boardGroup['id']] = true;
    $boardGroupTitles[(int) $boardGroup['id']] = (string) $boardGroup['title'];
}
$boardGroupFilterValue = sr_get_string('group_id', 20);
$boardGroupFilterId = preg_match('/\A[1-9][0-9]*\z/', $boardGroupFilterValue) === 1 ? (int) $boardGroupFilterValue : 0;
if ($boardGroupFilterId > 0 && !isset($boardGroupIds[$boardGroupFilterId])) {
    $boardGroupFilterId = 0;
}

if (sr_request_method() === 'POST') {
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);

    if ($intent === 'update_skin') {
        $boardIdValue = sr_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $skinKey = sr_post_string('skin_key', 40);
        $board = sr_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = '게시판을 찾을 수 없습니다.';
        }
        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = '게시판 스킨 값이 올바르지 않습니다.';
            $skinKey = 'basic';
        }

        if ($errors === [] && is_array($board)) {
            $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.skin_updated',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board skin updated.',
                'metadata' => [
                    'board_key' => (string) $board['board_key'],
                    'before_skin_key' => $beforeSkinKey,
                    'after_skin_key' => $skinKey,
                ],
            ]);

            $notice = '게시판 스킨을 저장했습니다.';
        }
    } elseif (in_array($intent, ['create', 'update'], true)) {
        $boardKey = sr_post_string('board_key', 60);
        $title = sr_post_string('title', 120);
        $description = sr_post_string_without_truncation('description', 2000);
        $status = sr_post_string('status', 30);
        $readPolicy = sr_post_string('read_policy', 30);
        $writePolicy = sr_post_string('write_policy', 30);
        $commentPolicy = sr_post_string('comment_policy', 30);
        $skinKey = sr_post_string('skin_key', 40);
        $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
        $attachmentMaxBytes = sr_admin_post_int_in_range('attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = sr_admin_post_int_in_range('attachment_max_count', 0, 10);
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
        $boardGroupId = sr_admin_post_int_in_range('board_group_id', 0, 999999999);
        $boardGroupId = is_int($boardGroupId) ? $boardGroupId : 0;
        $readGroupKeysInput = sr_post_string_without_truncation('read_group_keys', 1000);
        $writeGroupKeysInput = sr_post_string_without_truncation('write_group_keys', 1000);
        $commentGroupKeysInput = sr_post_string_without_truncation('comment_group_keys', 1000);
        $readGroupKeys = is_string($readGroupKeysInput) ? sr_community_board_group_keys_from_input($readGroupKeysInput) : [];
        $writeGroupKeys = is_string($writeGroupKeysInput) ? sr_community_board_group_keys_from_input($writeGroupKeysInput) : [];
        $commentGroupKeys = is_string($commentGroupKeysInput) ? sr_community_board_group_keys_from_input($commentGroupKeysInput) : [];
        $assetSettings = [];
        foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
            $assetSettings[$assetPrefix . '_enabled'] = ($_POST[$assetPrefix . '_enabled'] ?? '') === '1';
            $assetSettings[$assetPrefix . '_asset_module'] = sr_community_asset_module_key(sr_post_string($assetPrefix . '_asset_module', 20));
            $assetSettings[$assetPrefix . '_amount'] = sr_admin_post_int_in_range($assetPrefix . '_amount', 0, 999999999);
        }
        $assetSettings['paid_read_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_read_charge_policy', 20), 'once');
        $assetSettings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy(sr_post_string('paid_attachment_download_charge_policy', 20), 'once');
        $settingSources = [];
        foreach (sr_community_board_group_setting_keys() as $settingKey) {
            $settingSources[$settingKey] = sr_community_normalize_board_setting_source(sr_post_string('source_' . $settingKey, 20));
        }

        if ($intent === 'create' && !sr_community_board_key_is_valid($boardKey)) {
            $errors[] = '게시판 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
        }

        if ($title === '') {
            $errors[] = '게시판 이름을 입력하세요.';
        }

        if ($description === null) {
            $errors[] = '설명은 2000자 이하로 입력하세요.';
            $description = '';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '게시판 상태 값이 올바르지 않습니다.';
        }

        if (!in_array($readPolicy, $allowedReadPolicies, true)) {
            $errors[] = '읽기 정책 값이 올바르지 않습니다.';
        }

        if (!in_array($writePolicy, $allowedWritePolicies, true)) {
            $errors[] = '쓰기 정책 값이 올바르지 않습니다.';
        }

        if (!in_array($commentPolicy, $allowedCommentPolicies, true)) {
            $errors[] = '댓글 정책 값이 올바르지 않습니다.';
        }

        if (!isset($communitySkinOptions[$skinKey])) {
            $errors[] = '게시판 스킨 값이 올바르지 않습니다.';
            $skinKey = 'basic';
        }

        if ($sortOrder === null) {
            $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
            $sortOrder = 0;
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = '이미지 최대 용량은 1024 이상 10485760 이하의 정수여야 합니다.';
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = '이미지 최대 개수는 0 이상 10 이하의 정수여야 합니다.';
            $attachmentMaxCount = 1;
        }

        foreach ($publicDisplaySettingValues as $displaySettingKey => $displaySettingValue) {
            $displaySettingLabel = (string) ($publicDisplaySettingLabels[$displaySettingKey] ?? $displaySettingKey);
            if ($displaySettingValue === null) {
                $errors[] = $displaySettingLabel . ' 값이 올바르지 않습니다.';
                $publicDisplaySettingValues[$displaySettingKey] = 0;
                continue;
            }

            if (isset($publicBannerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicBannerIds[$displaySettingValue])) {
                $errors[] = $displaySettingLabel . '는 공용 배너 중에서 선택하세요.';
            }

            if (isset($publicPopupLayerSettingLabels[$displaySettingKey]) && $displaySettingValue > 0 && !isset($publicPopupLayerIds[$displaySettingValue])) {
                $errors[] = $displaySettingLabel . '는 공용 팝업레이어 중에서 선택하세요.';
            }
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = '파일 최대 용량은 1024 이상 20971520 이하의 정수여야 합니다.';
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = '파일 최대 개수는 0 이상 5 이하의 정수여야 합니다.';
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = '허용 파일 확장자는 1000자 이하로 입력하세요.';
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = '허용할 수 없는 파일 확장자입니다: ' . implode(', ', $invalidFileExtensions);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = '파일 첨부를 허용하려면 확장자를 하나 이상 입력하세요.';
        }

        if ($readMinLevel === null) {
            $errors[] = '읽기 최소 레벨은 0 이상 ' . (string) $maxLevel . ' 이하의 정수여야 합니다.';
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = '쓰기 최소 레벨은 0 이상 ' . (string) $maxLevel . ' 이하의 정수여야 합니다.';
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = '댓글 최소 레벨은 0 이상 ' . (string) $maxLevel . ' 이하의 정수여야 합니다.';
            $commentMinLevel = 0;
        }

        if ($boardGroupId > 0 && !isset($boardGroupIds[$boardGroupId])) {
            $errors[] = '게시판 그룹 값이 올바르지 않습니다.';
        }

        foreach ($settingSources as $settingKey => $source) {
            if ($source === 'group' && $boardGroupId < 1) {
                $errors[] = $settingKey . ' 설정은 게시판 그룹이 있어야 그룹 기본값을 따를 수 있습니다.';
            }
        }

        foreach ([
            '읽기 그룹' => $readGroupKeysInput,
            '쓰기 그룹' => $writeGroupKeysInput,
            '댓글 그룹' => $commentGroupKeysInput,
        ] as $label => $groupKeysInput) {
            if (!is_string($groupKeysInput)) {
                $errors[] = $label . ' key 목록은 1000자 이하로 입력하세요.';
                continue;
            }

            $invalidGroupKeys = sr_community_invalid_board_group_keys_from_input($groupKeysInput);
            if ($invalidGroupKeys !== []) {
                $errors[] = $label . ' key 형식이 올바르지 않습니다: ' . implode(', ', $invalidGroupKeys);
            }
        }

        foreach ([
            '읽기 그룹' => $readGroupKeys,
            '쓰기 그룹' => $writeGroupKeys,
            '댓글 그룹' => $commentGroupKeys,
        ] as $label => $groupKeys) {
            $unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys));
            if ($unknownGroupKeys !== []) {
                $errors[] = $label . ' key는 활성 회원 그룹이어야 합니다: ' . implode(', ', $unknownGroupKeys);
            }
        }

        foreach ([
            '읽기' => [$readPolicy, $readGroupKeys, $settingSources['read_policy']],
            '쓰기' => [$writePolicy, $writeGroupKeys, $settingSources['write_policy']],
            '댓글' => [$commentPolicy, $commentGroupKeys, $settingSources['comment_policy']],
        ] as $label => $policyGroupKeys) {
            if ((string) $policyGroupKeys[2] === 'board' && (string) $policyGroupKeys[0] === 'group' && $policyGroupKeys[1] === []) {
                $errors[] = $label . ' 정책을 group으로 선택하려면 그룹 key를 하나 이상 입력하세요.';
            }
        }

        foreach (['post_reward' => '게시글 적립', 'comment_reward' => '댓글 적립', 'write_charge' => '글쓰기 차감', 'comment_charge' => '댓글 차감', 'paid_read' => '유료 열람', 'paid_attachment_download' => '첨부 다운로드 차감'] as $assetPrefix => $assetLabel) {
            if ($assetSettings[$assetPrefix . '_amount'] === null) {
                $errors[] = $assetLabel . ' 금액은 0 이상 999999999 이하의 정수여야 합니다.';
                $assetSettings[$assetPrefix . '_amount'] = 0;
            }

            $assetModule = (string) $assetSettings[$assetPrefix . '_asset_module'];
            if (!isset($assetModuleOptions[$assetModule]) && !empty($assetSettings[$assetPrefix . '_enabled']) && (int) $assetSettings[$assetPrefix . '_amount'] > 0) {
                $errors[] = $assetLabel . '에 사용할 ' . sr_community_asset_module_label($assetModule) . ' 모듈이 활성 상태가 아닙니다.';
            }
        }

        if ($errors === [] && $intent === 'create' && sr_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = '이미 사용 중인 게시판 key입니다.';
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
                    'write_min_level' => $writeMinLevel,
                    'comment_min_level' => $commentMinLevel,
                    'skin_key' => $skinKey,
                    'asset_settings' => $assetSettings,
                    'setting_sources' => $settingSources,
                ], $publicDisplaySettingValues),
            ]);
            sr_community_set_board_setting($pdo, $boardId, 'skin_key', $skinKey, 'string');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            sr_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
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
            sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
            foreach ($settingSources as $settingKey => $source) {
                sr_community_set_board_setting_source($pdo, $boardId, $settingKey, $source);
            }

            $notice = '게시판을 만들었습니다.';
        } elseif ($intent === 'update' && $errors === []) {
            $boardIdValue = sr_post_string('board_id', 20);
            $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
            $board = sr_community_board_by_id($pdo, $boardId);
            if (!is_array($board)) {
                $errors[] = '게시판을 찾을 수 없습니다.';
            }

            if ($errors === [] && is_array($board)) {
                $beforeAttachmentMaxBytes = sr_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = sr_community_board_attachment_max_count($pdo, $boardId);
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
                $beforeSkinKey = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, $boardId, 'skin_key') ?? 'basic')]);
                $beforeAssetSettings = [];
                foreach ($assetSettings as $assetSettingKey => $assetSettingValue) {
                    $beforeAssetSettings[$assetSettingKey] = sr_community_board_setting_value($pdo, $boardId, (string) $assetSettingKey);
                }
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
                sr_community_save_board_asset_settings($pdo, $boardId, $assetSettings);
                foreach ($settingSources as $settingKey => $source) {
                    sr_community_set_board_setting_source($pdo, $boardId, $settingKey, $source);
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
                        'before_image_uploads_enabled' => (int) $board['image_uploads_enabled'] === 1,
                        'after_image_uploads_enabled' => $imageUploadsEnabled,
                        'after_file_uploads_enabled' => $fileUploadsEnabled,
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $attachmentMaxBytes,
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $attachmentMaxCount,
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
                        'before_skin_key' => $beforeSkinKey,
                        'after_skin_key' => $skinKey,
                        'before_asset_settings' => $beforeAssetSettings,
                        'after_asset_settings' => $assetSettings,
                        'setting_sources' => $settingSources,
                    ], $publicDisplayMetadata),
                ]);

                $notice = '게시판 설정을 변경했습니다.';
            }
        }
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$boardGroups = sr_community_board_groups($pdo);
$boards = sr_community_boards($pdo);
foreach ($boards as &$board) {
    $board['setting_sources'] = sr_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = sr_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = sr_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    foreach ($publicDisplaySettingLabels as $displaySettingKey => $displaySettingLabel) {
        $board[$displaySettingKey] = (int) (sr_community_board_setting_value($pdo, (int) $board['id'], $displaySettingKey) ?? 0);
    }
    $board['effective_attachment_max_bytes'] = sr_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_count'] = sr_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_uploads_enabled'] = sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0');
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['file_attachment_max_bytes'] = sr_community_board_own_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['file_attachment_max_count'] = sr_community_board_own_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_bytes'] = sr_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_count'] = sr_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_allowed_extensions'] = sr_community_board_own_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['effective_file_allowed_extensions'] = sr_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['read_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['effective_write_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['effective_comment_group_keys'] = sr_community_board_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['read_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['write_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['comment_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['effective_read_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['effective_write_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['effective_comment_min_level'] = sr_community_board_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['skin_key'] = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'skin_key') ?? 'basic')]);
    foreach (['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $assetPrefix) {
        $board[$assetPrefix . '_enabled'] = sr_community_board_setting_value($pdo, (int) $board['id'], $assetPrefix . '_enabled') ?? (!empty($settings[$assetPrefix . '_enabled']) ? '1' : '0');
        $board[$assetPrefix . '_asset_module'] = sr_community_board_setting_value($pdo, (int) $board['id'], $assetPrefix . '_asset_module') ?? (string) ($settings[$assetPrefix . '_asset_module'] ?? 'point');
        $board[$assetPrefix . '_amount'] = sr_community_board_setting_value($pdo, (int) $board['id'], $assetPrefix . '_amount') ?? (string) ($settings[$assetPrefix . '_amount'] ?? 0);
    }
    $board['paid_read_charge_policy'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'paid_read_charge_policy') ?? (string) ($settings['paid_read_charge_policy'] ?? 'once');
    $board['paid_attachment_download_charge_policy'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'paid_attachment_download_charge_policy') ?? (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once');
}
unset($board);

if ($communityBoardsPage === 'list' && $boardGroupFilterId > 0) {
    $boards = array_values(array_filter($boards, static function (array $board) use ($boardGroupFilterId): bool {
        return (int) ($board['board_group_id'] ?? 0) === $boardGroupFilterId;
    }));
}

$editBoard = null;
if ($communityBoardsPage === 'edit') {
    $editBoardIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editBoardId = preg_match('/\A[1-9][0-9]*\z/', $editBoardIdValue) === 1 ? (int) $editBoardIdValue : 0;
    foreach ($boards as $board) {
        if ((int) $board['id'] === $editBoardId) {
            $editBoard = $board;
            break;
        }
    }

    if (!is_array($editBoard)) {
        sr_render_error(404, '게시판을 찾을 수 없습니다.');
    }
}

include SR_ROOT . '/modules/community/views/admin-boards.php';
