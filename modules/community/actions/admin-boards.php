<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';
if (is_file(TOY_ROOT . '/modules/banner/helpers.php')) {
    require_once TOY_ROOT . '/modules/banner/helpers.php';
}
if (is_file(TOY_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once TOY_ROOT . '/modules/popup_layer/helpers.php';
}

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$communityBoardsPage = isset($communityBoardsPage) ? (string) $communityBoardsPage : 'list';
if (!in_array($communityBoardsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardsPage = 'list';
}
$allowedStatuses = toy_community_board_statuses();
$allowedReadPolicies = toy_community_policy_values('read');
$allowedWritePolicies = toy_community_policy_values('write');
$allowedCommentPolicies = toy_community_policy_values('comment');
$settings = toy_community_settings($pdo);
$publicBanners = function_exists('toy_banner_public_banners') && toy_module_enabled($pdo, 'banner')
    ? toy_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('toy_popup_layer_public_layers') && toy_module_enabled($pdo, 'popup_layer')
    ? toy_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$memberGroups = toy_member_groups($pdo);
$enabledMemberGroups = [];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') !== 'enabled') {
        continue;
    }

    $enabledMemberGroups[] = $memberGroup;
    $enabledMemberGroupKeys[] = (string) $memberGroup['group_key'];
}

$boardGroups = toy_community_board_groups($pdo);
$boardGroupIds = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupIds[(int) $boardGroup['id']] = true;
}

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);

    if (in_array($intent, ['create', 'update'], true)) {
        $boardKey = toy_post_string('board_key', 60);
        $title = toy_post_string('title', 120);
        $description = toy_post_string_without_truncation('description', 2000);
        $status = toy_post_string('status', 30);
        $readPolicy = toy_post_string('read_policy', 30);
        $writePolicy = toy_post_string('write_policy', 30);
        $commentPolicy = toy_post_string('comment_policy', 30);
        $sortOrder = toy_admin_post_int_in_range('sort_order', 0, 1000000);
        $attachmentMaxBytes = toy_admin_post_int_in_range('attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = toy_admin_post_int_in_range('attachment_max_count', 0, 10);
        $bannerBeforeListId = toy_admin_post_int_in_range('banner_before_list_id', 0, 999999999);
        $bannerAfterListId = toy_admin_post_int_in_range('banner_after_list_id', 0, 999999999);
        $popupLayerListId = toy_admin_post_int_in_range('popup_layer_list_id', 0, 999999999);
        $imageUploadsEnabled = ($_POST['image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = toy_admin_post_int_in_range('file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = toy_admin_post_int_in_range('file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = toy_post_string_without_truncation('file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? toy_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = toy_admin_post_int_in_range('read_min_level', 0, 1000000);
        $writeMinLevel = toy_admin_post_int_in_range('write_min_level', 0, 1000000);
        $commentMinLevel = toy_admin_post_int_in_range('comment_min_level', 0, 1000000);
        $boardGroupId = toy_admin_post_int_in_range('board_group_id', 0, 999999999);
        $boardGroupId = is_int($boardGroupId) ? $boardGroupId : 0;
        $readGroupKeysInput = toy_post_string_without_truncation('read_group_keys', 1000);
        $writeGroupKeysInput = toy_post_string_without_truncation('write_group_keys', 1000);
        $commentGroupKeysInput = toy_post_string_without_truncation('comment_group_keys', 1000);
        $readGroupKeys = is_string($readGroupKeysInput) ? toy_community_board_group_keys_from_input($readGroupKeysInput) : [];
        $writeGroupKeys = is_string($writeGroupKeysInput) ? toy_community_board_group_keys_from_input($writeGroupKeysInput) : [];
        $commentGroupKeys = is_string($commentGroupKeysInput) ? toy_community_board_group_keys_from_input($commentGroupKeysInput) : [];
        $settingSources = [];
        foreach (toy_community_board_group_setting_keys() as $settingKey) {
            $settingSources[$settingKey] = toy_community_normalize_board_setting_source(toy_post_string('source_' . $settingKey, 20));
        }

        if ($intent === 'create' && !toy_community_board_key_is_valid($boardKey)) {
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

        if ($bannerBeforeListId === null) {
            $errors[] = '목록 상단 배너 값이 올바르지 않습니다.';
            $bannerBeforeListId = 0;
        }

        if ($bannerAfterListId === null) {
            $errors[] = '목록 하단 배너 값이 올바르지 않습니다.';
            $bannerAfterListId = 0;
        }

        if ($bannerBeforeListId > 0 && !isset($publicBannerIds[$bannerBeforeListId])) {
            $errors[] = '목록 상단 배너는 공용 배너 중에서 선택하세요.';
        }

        if ($bannerAfterListId > 0 && !isset($publicBannerIds[$bannerAfterListId])) {
            $errors[] = '목록 하단 배너는 공용 배너 중에서 선택하세요.';
        }

        if ($popupLayerListId === null) {
            $errors[] = '목록 팝업레이어 값이 올바르지 않습니다.';
            $popupLayerListId = 0;
        }

        if ($popupLayerListId > 0 && !isset($publicPopupLayerIds[$popupLayerListId])) {
            $errors[] = '목록 팝업레이어는 공용 팝업레이어 중에서 선택하세요.';
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
            $invalidFileExtensions = toy_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = '허용할 수 없는 파일 확장자입니다: ' . implode(', ', $invalidFileExtensions);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = '파일 첨부를 허용하려면 확장자를 하나 이상 입력하세요.';
        }

        if ($readMinLevel === null) {
            $errors[] = '읽기 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = '쓰기 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = '댓글 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
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

            $invalidGroupKeys = toy_community_invalid_board_group_keys_from_input($groupKeysInput);
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

        if ($errors === [] && $intent === 'create' && toy_community_board_by_key($pdo, $boardKey) !== null) {
            $errors[] = '이미 사용 중인 게시판 key입니다.';
        }

        if ($intent === 'create' && $errors === []) {
            $boardId = toy_community_create_board($pdo, [
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

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.created',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board created.',
                'metadata' => [
                    'board_key' => $boardKey,
                    'board_group_id' => $boardGroupId,
                    'status' => $status,
                    'image_uploads_enabled' => $imageUploadsEnabled,
                    'file_uploads_enabled' => $fileUploadsEnabled,
                    'attachment_max_bytes' => $attachmentMaxBytes,
                    'attachment_max_count' => $attachmentMaxCount,
                    'banner_before_list_id' => $bannerBeforeListId,
                    'banner_after_list_id' => $bannerAfterListId,
                    'popup_layer_list_id' => $popupLayerListId,
                    'file_attachment_max_bytes' => $fileAttachmentMaxBytes,
                    'file_attachment_max_count' => $fileAttachmentMaxCount,
                    'file_allowed_extensions' => $fileAllowedExtensions,
                    'read_group_keys' => $readGroupKeys,
                    'write_group_keys' => $writeGroupKeys,
                    'comment_group_keys' => $commentGroupKeys,
                    'read_min_level' => $readMinLevel,
                    'write_min_level' => $writeMinLevel,
                    'comment_min_level' => $commentMinLevel,
                    'setting_sources' => $settingSources,
                ],
            ]);
            toy_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'banner_before_list_id', (string) $bannerBeforeListId, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'banner_after_list_id', (string) $bannerAfterListId, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'popup_layer_list_id', (string) $popupLayerListId, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            toy_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            toy_community_set_board_setting($pdo, $boardId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
            toy_community_set_board_setting($pdo, $boardId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            toy_community_set_board_setting($pdo, $boardId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            toy_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
            foreach ($settingSources as $settingKey => $source) {
                toy_community_set_board_setting_source($pdo, $boardId, $settingKey, $source);
            }

            $notice = '게시판을 만들었습니다.';
        } elseif ($intent === 'update' && $errors === []) {
            $boardIdValue = toy_post_string('board_id', 20);
            $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
            $board = toy_community_board_by_id($pdo, $boardId);
            if (!is_array($board)) {
                $errors[] = '게시판을 찾을 수 없습니다.';
            }

            if ($errors === [] && is_array($board)) {
                $beforeAttachmentMaxBytes = toy_community_board_attachment_max_bytes($pdo, $boardId);
                $beforeAttachmentMaxCount = toy_community_board_attachment_max_count($pdo, $boardId);
                $beforeBannerBeforeListId = (int) (toy_community_board_setting_value($pdo, $boardId, 'banner_before_list_id') ?? 0);
                $beforeBannerAfterListId = (int) (toy_community_board_setting_value($pdo, $boardId, 'banner_after_list_id') ?? 0);
                $beforePopupLayerListId = (int) (toy_community_board_setting_value($pdo, $boardId, 'popup_layer_list_id') ?? 0);
                $beforeFileAttachmentMaxBytes = toy_community_board_file_attachment_max_bytes($pdo, $boardId);
                $beforeFileAttachmentMaxCount = toy_community_board_file_attachment_max_count($pdo, $boardId);
                $beforeFileAllowedExtensions = toy_community_board_file_allowed_extensions($pdo, $boardId);
                $beforeReadGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'read_group_keys');
                $beforeWriteGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'write_group_keys');
                $beforeCommentGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'comment_group_keys');
                $beforeReadMinLevel = toy_community_board_min_level($pdo, $boardId, 'read_min_level');
                $beforeWriteMinLevel = toy_community_board_min_level($pdo, $boardId, 'write_min_level');
                $beforeCommentMinLevel = toy_community_board_min_level($pdo, $boardId, 'comment_min_level');
                toy_community_update_board($pdo, $boardId, [
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
                toy_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'banner_before_list_id', (string) $bannerBeforeListId, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'banner_after_list_id', (string) $bannerAfterListId, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'popup_layer_list_id', (string) $popupLayerListId, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
                toy_community_set_board_setting($pdo, $boardId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
                toy_community_set_board_setting($pdo, $boardId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
                toy_community_set_board_setting($pdo, $boardId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
                toy_community_set_board_setting($pdo, $boardId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');
                toy_community_set_board_setting($pdo, $boardId, 'read_min_level', (string) $readMinLevel, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'write_min_level', (string) $writeMinLevel, 'int');
                toy_community_set_board_setting($pdo, $boardId, 'comment_min_level', (string) $commentMinLevel, 'int');
                foreach ($settingSources as $settingKey => $source) {
                    toy_community_set_board_setting_source($pdo, $boardId, $settingKey, $source);
                }

                toy_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.updated',
                    'target_type' => 'community_board',
                    'target_id' => (string) $boardId,
                    'result' => 'success',
                    'message' => 'Community board updated.',
                    'metadata' => [
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
                        'before_banner_before_list_id' => $beforeBannerBeforeListId,
                        'after_banner_before_list_id' => $bannerBeforeListId,
                        'before_banner_after_list_id' => $beforeBannerAfterListId,
                        'after_banner_after_list_id' => $bannerAfterListId,
                        'before_popup_layer_list_id' => $beforePopupLayerListId,
                        'after_popup_layer_list_id' => $popupLayerListId,
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
                        'setting_sources' => $settingSources,
                    ],
                ]);

                $notice = '게시판 설정을 변경했습니다.';
            }
        }
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$boardGroups = toy_community_board_groups($pdo);
$boards = toy_community_boards($pdo);
foreach ($boards as &$board) {
    $board['setting_sources'] = toy_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = toy_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = toy_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['banner_before_list_id'] = (int) (toy_community_board_setting_value($pdo, (int) $board['id'], 'banner_before_list_id') ?? 0);
    $board['banner_after_list_id'] = (int) (toy_community_board_setting_value($pdo, (int) $board['id'], 'banner_after_list_id') ?? 0);
    $board['popup_layer_list_id'] = (int) (toy_community_board_setting_value($pdo, (int) $board['id'], 'popup_layer_list_id') ?? 0);
    $board['effective_attachment_max_bytes'] = toy_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_count'] = toy_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_uploads_enabled'] = toy_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0');
    $board['effective_file_uploads_enabled'] = toy_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['file_attachment_max_bytes'] = toy_community_board_own_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['file_attachment_max_count'] = toy_community_board_own_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_bytes'] = toy_community_board_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_file_attachment_max_count'] = toy_community_board_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['file_allowed_extensions'] = toy_community_board_own_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['effective_file_allowed_extensions'] = toy_community_board_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $board['read_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['effective_write_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['effective_comment_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['read_min_level'] = toy_community_board_own_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['write_min_level'] = toy_community_board_own_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['comment_min_level'] = toy_community_board_own_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['effective_read_min_level'] = toy_community_board_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['effective_write_min_level'] = toy_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['effective_comment_min_level'] = toy_community_board_min_level($pdo, (int) $board['id'], 'comment_min_level');
}
unset($board);

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
        toy_render_error(404, '게시판을 찾을 수 없습니다.');
    }
}

include TOY_ROOT . '/modules/community/views/admin-boards.php';
