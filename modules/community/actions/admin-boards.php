<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedStatuses = toy_community_board_statuses();
$allowedGroupStatuses = toy_community_board_group_statuses();
$allowedReadPolicies = toy_community_policy_values('read');
$allowedWritePolicies = toy_community_policy_values('write');
$allowedCommentPolicies = toy_community_policy_values('comment');
$settings = toy_module_settings($pdo, 'community');
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
            toy_community_set_board_group_setting($pdo, $groupId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
            toy_community_set_board_group_setting($pdo, $groupId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            toy_community_set_board_group_setting($pdo, $groupId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');

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
    } elseif (in_array($intent, ['create', 'update'], true)) {
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
        $imageUploadsEnabled = ($_POST['image_uploads_enabled'] ?? '') === '1';
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
                    'attachment_max_bytes' => $attachmentMaxBytes,
                    'attachment_max_count' => $attachmentMaxCount,
                    'read_group_keys' => $readGroupKeys,
                    'write_group_keys' => $writeGroupKeys,
                    'comment_group_keys' => $commentGroupKeys,
                    'setting_sources' => $settingSources,
                ],
            ]);
            toy_community_set_board_setting($pdo, $boardId, 'attachment_max_bytes', (string) $attachmentMaxBytes, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'attachment_max_count', (string) $attachmentMaxCount, 'int');
            toy_community_set_board_setting($pdo, $boardId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
            toy_community_set_board_setting($pdo, $boardId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
            toy_community_set_board_setting($pdo, $boardId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');
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
                $beforeReadGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'read_group_keys');
                $beforeWriteGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'write_group_keys');
                $beforeCommentGroupKeys = toy_community_board_group_keys($pdo, $boardId, 'comment_group_keys');
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
                toy_community_set_board_setting($pdo, $boardId, 'read_group_keys', toy_community_board_group_keys_setting_value($readGroupKeys), 'json');
                toy_community_set_board_setting($pdo, $boardId, 'write_group_keys', toy_community_board_group_keys_setting_value($writeGroupKeys), 'json');
                toy_community_set_board_setting($pdo, $boardId, 'comment_group_keys', toy_community_board_group_keys_setting_value($commentGroupKeys), 'json');
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
                        'before_attachment_max_bytes' => $beforeAttachmentMaxBytes,
                        'after_attachment_max_bytes' => $attachmentMaxBytes,
                        'before_attachment_max_count' => $beforeAttachmentMaxCount,
                        'after_attachment_max_count' => $attachmentMaxCount,
                        'before_read_group_keys' => $beforeReadGroupKeys,
                        'after_read_group_keys' => $readGroupKeys,
                        'before_write_group_keys' => $beforeWriteGroupKeys,
                        'after_write_group_keys' => $writeGroupKeys,
                        'before_comment_group_keys' => $beforeCommentGroupKeys,
                        'after_comment_group_keys' => $commentGroupKeys,
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
$boardGroupSettings = [];
foreach ($boardGroups as $boardGroup) {
    $boardGroupSettings[(int) $boardGroup['id']] = toy_community_board_group_settings($pdo, (int) $boardGroup['id']);
}

$boards = toy_community_boards($pdo);
foreach ($boards as &$board) {
    $board['setting_sources'] = toy_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = toy_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = toy_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_bytes'] = toy_community_board_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['effective_attachment_max_count'] = toy_community_board_attachment_max_count($pdo, (int) $board['id'], $settings);
    $board['read_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = toy_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['effective_write_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['effective_comment_group_keys'] = toy_community_board_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
}
unset($board);

include TOY_ROOT . '/modules/community/views/admin-boards.php';
