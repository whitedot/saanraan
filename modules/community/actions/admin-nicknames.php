<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'view');
$canResetNicknames = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');
$nicknameNotificationAvailable = sr_community_notification_available($pdo);
$settings = sr_community_settings($pdo);
$memberMessageSendingEnabled = (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled';
$canSendMemberMessages = $memberMessageSendingEnabled && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');
$communityLevelEnabled = !empty($settings['level_enabled']) && sr_community_level_tables_exist($pdo);
$communityLevelManualEditable = $communityLevelEnabled && empty($settings['level_auto_recalculate']);
$canEditMemberLevels = $communityLevelManualEditable && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');
$communityLevels = $communityLevelEnabled ? sr_community_levels($pdo, $settings) : [];

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/nicknames', 'edit');

    $returnPathInput = sr_post_string_without_truncation('return_path', 1000);
    $returnPath = is_string($returnPathInput) ? $returnPathInput : '';
    $returnPathInfo = parse_url($returnPath);
    $returnPathValue = is_array($returnPathInfo)
        && !isset($returnPathInfo['scheme'])
        && !isset($returnPathInfo['host'])
        && (string) ($returnPathInfo['path'] ?? '') === '/admin/community/nicknames'
        && !str_contains($returnPath, "\r")
        && !str_contains($returnPath, "\n")
        ? $returnPath
        : '/admin/community/nicknames';
    $intent = sr_post_string('intent', 40);
    if ($intent === 'send_message') {
        $targetAccountIdValue = sr_post_string('account_id', 20);
        $targetAccountId = preg_match('/\A[1-9][0-9]*\z/', $targetAccountIdValue) === 1 ? (int) $targetAccountIdValue : 0;
        $bodyText = sr_post_string_without_truncation('body_text', 5000);
        $postResult = [
            'errors' => [],
            'notice' => '',
        ];
        $targetAccount = $targetAccountId > 0 ? sr_community_public_account_summary($pdo, $targetAccountId) : null;
        if (!$memberMessageSendingEnabled) {
            $postResult['errors'][] = sr_t('community::action.error.message_send_forbidden');
        } elseif (!is_array($targetAccount)) {
            $postResult['errors'][] = sr_t('community::action.error.recipient_not_found');
        } elseif ((string) ($targetAccount['status'] ?? '') !== 'active') {
            $postResult['errors'][] = sr_t('community::action.error.recipient_not_found');
        } elseif ((int) ($targetAccount['id'] ?? 0) === (int) $account['id']) {
            $postResult['errors'][] = sr_t('community::action.error.message_self_forbidden');
        } elseif (sr_community_member_nickname($pdo, $targetAccountId) === '') {
            $postResult['errors'][] = sr_t('community::action.admin.member_message_requires_nickname');
        }
        if ($bodyText === null) {
            $postResult['errors'][] = sr_t('community::action.error.message_body_too_long');
            $bodyText = '';
        } elseif (trim($bodyText) === '') {
            $postResult['errors'][] = sr_t('community::action.error.message_body_required');
        }

        if ($postResult['errors'] === [] && is_array($targetAccount)) {
            $messageId = sr_community_create_message($pdo, (int) $account['id'], $targetAccountId, $bodyText);
            $senderLabel = sr_community_message_account_label(
                (string) ($account['display_name'] ?? ''),
                (int) $account['id'],
                false,
                null,
                (string) ($account['status'] ?? ''),
                sr_community_member_nickname($pdo, (int) $account['id']),
                $settings
            );
            $notificationCreated = sr_community_create_account_notification(
                $pdo,
                $targetAccountId,
                sr_t('community::notification.message.title'),
                sr_t('community::notification.message.body', ['account' => $senderLabel]),
                '/community/message?id=' . (string) $messageId,
                (int) $account['id']
            );
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.member.message_sent',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Community member message sent by admin.',
                'metadata' => [
                    'community_message_id' => $messageId,
                    'notification_created' => $notificationCreated,
                ],
            ]);
            $postResult['notice'] = sr_t('community::action.admin.member_message_sent');
        }
    } elseif ($intent === 'update_level') {
        $accountIdInputs = $_POST['account_ids'] ?? [];
        $accountIdInputs = is_array($accountIdInputs) ? $accountIdInputs : [];
        $targetAccountIds = [];
        foreach ($accountIdInputs as $accountIdInput) {
            $accountIdValue = is_scalar($accountIdInput) ? (string) $accountIdInput : '';
            if (preg_match('/\A[1-9][0-9]*\z/', $accountIdValue) === 1) {
                $targetAccountIds[] = (int) $accountIdValue;
            }
        }
        $targetAccountIds = array_values(array_unique($targetAccountIds));
        $levelValueInput = sr_post_string('level_value', 20);
        $levelValue = preg_match('/\A[0-9]+\z/', $levelValueInput) === 1 ? (int) $levelValueInput : -1;
        $postResult = [
            'errors' => [],
            'notice' => '',
        ];
        if (!$communityLevelEnabled) {
            $postResult['errors'][] = sr_t('community::action.admin.member_level_disabled');
        } elseif (!$communityLevelManualEditable) {
            $postResult['errors'][] = sr_t('community::action.admin.member_level_auto_recalculate_enabled');
        } elseif ($targetAccountIds === []) {
            $postResult['errors'][] = sr_t('community::action.admin.member_level_targets_required');
        } elseif (count($targetAccountIds) > 500) {
            $postResult['errors'][] = sr_t('community::action.admin.member_level_targets_too_many');
        } elseif ($levelValue < 0 || $levelValue > sr_community_max_level_value($settings)) {
            $postResult['errors'][] = sr_t('community::action.admin.member_level_invalid');
        }

        $targetAccounts = [];
        if ($postResult['errors'] === []) {
            foreach ($targetAccountIds as $targetAccountId) {
                $targetAccount = sr_community_public_account_summary($pdo, $targetAccountId);
                if (!is_array($targetAccount) || sr_community_member_nickname($pdo, $targetAccountId) === '') {
                    $postResult['errors'][] = sr_t('community::action.admin.member_level_target_not_found');
                    break;
                }
                if ((string) ($targetAccount['status'] ?? '') !== 'active') {
                    $postResult['errors'][] = sr_t('community::action.admin.member_level_active_required');
                    break;
                }
                $targetAccounts[$targetAccountId] = $targetAccount;
            }
        }

        if ($postResult['errors'] === []) {
            $updatedCount = 0;
            $pdo->beginTransaction();
            try {
                foreach (array_keys($targetAccounts) as $targetAccountId) {
                    $beforeLevel = sr_community_account_level_snapshot($pdo, $targetAccountId);
                    $afterLevel = sr_community_set_account_level($pdo, $targetAccountId, $levelValue, 'admin_manual_level_update', $settings);
                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.member.level_updated',
                        'target_type' => 'member_account',
                        'target_id' => (string) $targetAccountId,
                        'result' => 'success',
                        'message' => 'Community member level updated by admin.',
                        'metadata' => [
                            'before_level_value' => (int) ($beforeLevel['level_value'] ?? 0),
                            'after_level_value' => (int) ($afterLevel['level_value'] ?? 0),
                            'score_value' => (int) ($afterLevel['score_value'] ?? 0),
                            'bulk_update' => true,
                            'bulk_target_count' => count($targetAccounts),
                        ],
                    ]);
                    $updatedCount++;
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $postResult['notice'] = sr_t('community::action.admin.member_level_bulk_updated', ['count' => (string) $updatedCount]);
        }
    } elseif ($intent === 'reset_nickname') {
        $postResult = sr_community_handle_nickname_reset_post($pdo, $account);
    } else {
        $postResult = [
            'errors' => [sr_t('community::action.admin.invalid_intent')],
            'notice' => '',
        ];
    }
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    if ($errors === []) {
        sr_admin_flash_result(sr_admin_action_result([], $notice));
        sr_redirect($returnPathValue);
    }
    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect($returnPathValue);
}

$nicknameFilter = sr_community_nickname_filter($pdo, $runtimeConfig, $communityLevelEnabled, $settings);
$nicknameSearchSubmitted = array_key_exists('field', $_GET)
    || array_key_exists('q', $_GET)
    || ($communityLevelEnabled && array_key_exists('level', $_GET));
$nicknameTotal = $nicknameSearchSubmitted ? sr_community_nickname_count($pdo, $nicknameFilter) : 0;
$nicknamePagination = $nicknameSearchSubmitted
    ? sr_admin_pagination_from_total($pdo, $nicknameTotal)
    : sr_admin_pagination_meta(0, 50, 1);
$nicknameSortOptions = sr_community_admin_nickname_sort_options($communityLevelEnabled && sr_community_level_tables_exist($pdo));
$nicknameDefaultSort = sr_community_admin_nickname_default_sort();
$nicknameSort = sr_admin_sort_from_request($nicknameSortOptions, $nicknameDefaultSort);
$nicknameRows = $nicknameSearchSubmitted
    ? sr_admin_member_rows_with_public_hash(
        $runtimeConfig,
        sr_community_nickname_rows($pdo, $nicknameFilter, (int) $nicknamePagination['per_page'], sr_admin_pagination_offset($nicknamePagination), $nicknameSort)
    )
    : [];

include SR_ROOT . '/modules/community/views/admin-nicknames.php';
