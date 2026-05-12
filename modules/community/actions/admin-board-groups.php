<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$communityBoardGroupsPage = isset($communityBoardGroupsPage) ? (string) $communityBoardGroupsPage : 'list';
if (!in_array($communityBoardGroupsPage, ['list', 'new', 'edit'], true)) {
    $communityBoardGroupsPage = 'list';
}
$allowedGroupStatuses = toy_community_board_group_statuses();
$allowedReadPolicies = toy_community_policy_values('read');
$allowedWritePolicies = toy_community_policy_values('write');
$allowedCommentPolicies = toy_community_policy_values('comment');
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

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);

    if (in_array($intent, ['create_group', 'update_group'], true)) {
        $groupId = 0;
        if ($intent === 'update_group') {
            $groupIdValue = toy_post_string('group_id', 20);
            $groupId = preg_match('/\A[1-9][0-9]*\z/', $groupIdValue) === 1 ? (int) $groupIdValue : 0;
            if (!is_array(toy_community_board_group_by_id($pdo, $groupId))) {
                $errors[] = '게시판 그룹을 찾을 수 없습니다.';
            }
        }

        $groupKey = toy_post_string('group_key', 60);
        $title = toy_post_string('title', 120);
        $description = toy_post_string_without_truncation('description', 2000);
        $status = toy_post_string('status', 30);
        $sortOrder = toy_admin_post_int_in_range('sort_order', 0, 1000000);
        $readPolicy = toy_post_string('group_read_policy', 30);
        $writePolicy = toy_post_string('group_write_policy', 30);
        $commentPolicy = toy_post_string('group_comment_policy', 30);
        $attachmentMaxBytes = toy_admin_post_int_in_range('group_attachment_max_bytes', 1024, 10485760);
        $attachmentMaxCount = toy_admin_post_int_in_range('group_attachment_max_count', 0, 10);
        $imageUploadsEnabled = ($_POST['group_image_uploads_enabled'] ?? '') === '1';
        $fileUploadsEnabled = ($_POST['group_file_uploads_enabled'] ?? '') === '1';
        $fileAttachmentMaxBytes = toy_admin_post_int_in_range('group_file_attachment_max_bytes', 1024, 20971520);
        $fileAttachmentMaxCount = toy_admin_post_int_in_range('group_file_attachment_max_count', 0, 5);
        $fileAllowedExtensionsInput = toy_post_string_without_truncation('group_file_allowed_extensions', 1000);
        $fileAllowedExtensions = is_string($fileAllowedExtensionsInput) ? toy_community_file_extensions_from_input($fileAllowedExtensionsInput) : [];
        $readMinLevel = toy_admin_post_int_in_range('group_read_min_level', 0, 1000000);
        $writeMinLevel = toy_admin_post_int_in_range('group_write_min_level', 0, 1000000);
        $commentMinLevel = toy_admin_post_int_in_range('group_comment_min_level', 0, 1000000);
        $readGroupKeysInput = toy_post_string_without_truncation('group_read_group_keys', 1000);
        $writeGroupKeysInput = toy_post_string_without_truncation('group_write_group_keys', 1000);
        $commentGroupKeysInput = toy_post_string_without_truncation('group_comment_group_keys', 1000);
        $readGroupKeys = is_string($readGroupKeysInput) ? toy_community_board_group_keys_from_input($readGroupKeysInput) : [];
        $writeGroupKeys = is_string($writeGroupKeysInput) ? toy_community_board_group_keys_from_input($writeGroupKeysInput) : [];
        $commentGroupKeys = is_string($commentGroupKeysInput) ? toy_community_board_group_keys_from_input($commentGroupKeysInput) : [];

        if ($intent === 'create_group' && !toy_community_board_group_key_is_valid($groupKey)) {
            $errors[] = '그룹 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
        }

        if ($title === '') {
            $errors[] = '그룹 이름을 입력하세요.';
        }

        if ($description === null) {
            $errors[] = '그룹 설명은 2000자 이하로 입력하세요.';
            $description = '';
        }

        if (!in_array($status, $allowedGroupStatuses, true)) {
            $errors[] = '그룹 상태 값이 올바르지 않습니다.';
        }

        if ($sortOrder === null) {
            $errors[] = '그룹 정렬 순서는 0 이상의 정수여야 합니다.';
            $sortOrder = 0;
        }

        foreach ([
            '읽기' => [$readPolicy, $allowedReadPolicies],
            '쓰기' => [$writePolicy, $allowedWritePolicies],
            '댓글' => [$commentPolicy, $allowedCommentPolicies],
        ] as $label => $policyPair) {
            if (!in_array((string) $policyPair[0], $policyPair[1], true)) {
                $errors[] = '그룹 ' . $label . ' 정책 값이 올바르지 않습니다.';
            }
        }

        if ($attachmentMaxBytes === null) {
            $errors[] = '그룹 이미지 최대 용량은 1024 이상 10485760 이하의 정수여야 합니다.';
            $attachmentMaxBytes = 2097152;
        }

        if ($attachmentMaxCount === null) {
            $errors[] = '그룹 이미지 최대 개수는 0 이상 10 이하의 정수여야 합니다.';
            $attachmentMaxCount = 1;
        }

        if ($fileAttachmentMaxBytes === null) {
            $errors[] = '그룹 파일 최대 용량은 1024 이상 20971520 이하의 정수여야 합니다.';
            $fileAttachmentMaxBytes = 5242880;
        }

        if ($fileAttachmentMaxCount === null) {
            $errors[] = '그룹 파일 최대 개수는 0 이상 5 이하의 정수여야 합니다.';
            $fileAttachmentMaxCount = 3;
        }

        if (!is_string($fileAllowedExtensionsInput)) {
            $errors[] = '그룹 파일 허용 확장자는 1000자 이하로 입력하세요.';
            $fileAllowedExtensions = [];
        } else {
            $invalidFileExtensions = toy_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput);
            if ($invalidFileExtensions !== []) {
                $errors[] = '그룹 파일 허용 확장자 값이 올바르지 않습니다: ' . implode(', ', $invalidFileExtensions);
            }
        }

        if ($fileUploadsEnabled && $fileAllowedExtensions === []) {
            $errors[] = '그룹 파일 첨부를 허용하려면 확장자를 하나 이상 입력하세요.';
        }

        if ($readMinLevel === null) {
            $errors[] = '그룹 읽기 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
            $readMinLevel = 0;
        }

        if ($writeMinLevel === null) {
            $errors[] = '그룹 쓰기 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
            $writeMinLevel = 0;
        }

        if ($commentMinLevel === null) {
            $errors[] = '그룹 댓글 최소 레벨은 0 이상 1000000 이하의 정수여야 합니다.';
            $commentMinLevel = 0;
        }

        foreach ([
            '그룹 읽기 회원 그룹' => $readGroupKeysInput,
            '그룹 쓰기 회원 그룹' => $writeGroupKeysInput,
            '그룹 댓글 회원 그룹' => $commentGroupKeysInput,
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
            '그룹 읽기 회원 그룹' => $readGroupKeys,
            '그룹 쓰기 회원 그룹' => $writeGroupKeys,
            '그룹 댓글 회원 그룹' => $commentGroupKeys,
        ] as $label => $groupKeys) {
            $unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys));
            if ($unknownGroupKeys !== []) {
                $errors[] = $label . ' key는 활성 회원 그룹이어야 합니다: ' . implode(', ', $unknownGroupKeys);
            }
        }

        foreach ([
            '읽기' => [$readPolicy, $readGroupKeys],
            '쓰기' => [$writePolicy, $writeGroupKeys],
            '댓글' => [$commentPolicy, $commentGroupKeys],
        ] as $label => $policyGroupKeys) {
            if ((string) $policyGroupKeys[0] === 'group' && $policyGroupKeys[1] === []) {
                $errors[] = '그룹 ' . $label . ' 정책을 group으로 선택하려면 회원 그룹 key를 하나 이상 입력하세요.';
            }
        }

        if ($errors === [] && $intent === 'create_group' && toy_community_board_group_by_key($pdo, $groupKey) !== null) {
            $errors[] = '이미 사용 중인 그룹 key입니다.';
        }

        if ($errors === []) {
            if ($intent === 'create_group') {
                $groupId = toy_community_create_board_group($pdo, [
                    'group_key' => $groupKey,
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'sort_order' => (int) $sortOrder,
                ]);
                $eventType = 'community.board_group.created';
                $notice = '게시판 그룹을 만들었습니다.';
            } else {
                toy_community_update_board_group($pdo, $groupId, [
                    'title' => $title,
                    'description' => (string) $description,
                    'status' => $status,
                    'sort_order' => (int) $sortOrder,
                ]);
                $eventType = 'community.board_group.updated';
                $notice = '게시판 그룹을 변경했습니다.';
            }

            toy_community_set_board_group_setting($pdo, $groupId, 'read_policy', $readPolicy);
            toy_community_set_board_group_setting($pdo, $groupId, 'write_policy', $writePolicy);
            toy_community_set_board_group_setting($pdo, $groupId, 'comment_policy', $commentPolicy);
            toy_community_set_board_group_setting($pdo, $groupId, 'image_uploads_enabled', $imageUploadsEnabled ? '1' : '0', 'bool');
            toy_community_set_board_group_setting($pdo, $groupId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'file_uploads_enabled', $fileUploadsEnabled ? '1' : '0', 'bool');
            toy_community_set_board_group_setting($pdo, $groupId, 'file_attachment_max_bytes', (string) $fileAttachmentMaxBytes, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'file_attachment_max_count', (string) $fileAttachmentMaxCount, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'file_allowed_extensions', implode(',', $fileAllowedExtensions), 'string');
            toy_community_set_board_group_setting($pdo, $groupId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
            toy_community_set_board_group_setting($pdo, $groupId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            toy_community_set_board_group_setting($pdo, $groupId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');
            toy_community_set_board_group_setting($pdo, $groupId, 'read_min_level', (string) $readMinLevel, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'write_min_level', (string) $writeMinLevel, 'int');
            toy_community_set_board_group_setting($pdo, $groupId, 'comment_min_level', (string) $commentMinLevel, 'int');

            $applySettingKeys = [];
            if (isset($_POST['apply_setting_keys']) && is_array($_POST['apply_setting_keys'])) {
                foreach ($_POST['apply_setting_keys'] as $settingKey) {
                    $settingKey = (string) $settingKey;
                    if (in_array($settingKey, toy_community_board_group_setting_keys(), true)) {
                        $applySettingKeys[] = $settingKey;
                    }
                }
            }
            $applySettingKeys = array_values(array_unique($applySettingKeys));
            $appliedBoardCount = 0;
            if ($applySettingKeys !== []) {
                $appliedBoardCount = toy_community_apply_board_group_settings_to_boards($pdo, $groupId, $applySettingKeys);
                $notice .= ' 선택한 설정을 ' . (string) $appliedBoardCount . '개 게시판에 적용했습니다.';
            }

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $eventType,
                'target_type' => 'community_board_group',
                'target_id' => (string) $groupId,
                'result' => 'success',
                'message' => 'Community board group saved.',
                'metadata' => [
                    'status' => $status,
                    'applied_setting_keys' => $applySettingKeys,
                    'applied_board_count' => $appliedBoardCount,
                ],
            ]);
        }
    } else {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$boardGroups = toy_community_board_groups($pdo);
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupSettings[(int) $boardGroup['id']] = toy_community_board_group_settings($pdo, (int) $boardGroup['id']);
}

$editBoardGroup = null;
if ($communityBoardGroupsPage === 'edit') {
    $editGroupIdValue = isset($_GET['edit_id']) ? (string) $_GET['edit_id'] : '';
    $editGroupId = preg_match('/\A[1-9][0-9]*\z/', $editGroupIdValue) === 1 ? (int) $editGroupIdValue : 0;
    foreach ($boardGroups as $boardGroup) {
        if ((int) $boardGroup['id'] === $editGroupId) {
            $editBoardGroup = $boardGroup;
            break;
        }
    }

    if (!is_array($editBoardGroup)) {
        toy_render_error(404, '게시판 그룹을 찾을 수 없습니다.');
    }
}

include TOY_ROOT . '/modules/community/views/admin-board-groups.php';
