<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$couponAdminPage = 'definitions';
$requestPath = sr_request_path();
if ($requestPath === '/admin/coupons/issues') {
    $couponAdminPage = 'issues';
} elseif ($requestPath === '/admin/coupons/redemptions') {
    $couponAdminPage = 'redemptions';
}
$account = sr_member_require_login($pdo);
$couponPermissionPath = '/admin/coupons';
if ($couponAdminPage === 'issues') {
    $couponPermissionPath = '/admin/coupons/issues';
} elseif ($couponAdminPage === 'redemptions') {
    $couponPermissionPath = '/admin/coupons/redemptions';
}
sr_admin_require_permission($pdo, (int) $account['id'], $couponPermissionPath, 'view');
$couponCreateModalOpen = false;
$couponIssueModalOpenDefinitionId = 0;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $couponPermissionPath, 'edit');

    $intent = sr_post_string('intent', 40);
    try {
        if ($intent === 'create_definition' && $couponAdminPage === 'definitions') {
            $definitionId = sr_coupon_create_definition($pdo, [
                'coupon_key' => sr_post_string('coupon_key', 60),
                'title' => sr_post_string('title', 120),
                'description' => sr_post_string('description', 1000),
                'status' => sr_post_string('status', 30),
                'coupon_type' => sr_post_string('coupon_type', 40),
                'target_type' => sr_post_string('target_type', 60),
                'target_id' => sr_post_string('target_id', 80),
                'refundable_policy' => sr_post_string('refundable_policy', 30),
                'max_uses_per_issue' => sr_post_string('max_uses_per_issue', 10),
            ]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.definition.created',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'result' => 'success',
                'message' => 'Coupon definition created.',
            ]);
            $notice = '쿠폰 종류를 만들었습니다.';
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/coupons');
        } elseif ($intent === 'issue_coupon' && $couponAdminPage === 'definitions') {
            $definitionId = (int) sr_post_string('coupon_definition_id', 20);
            $targetMode = sr_post_string('issue_target_mode', 20);
            $targetAccountIds = sr_coupon_issue_target_account_ids(
                $pdo,
                $runtimeConfig,
                $targetMode,
                sr_post_string('account_identifier', 80),
                sr_post_string('group_key', 60)
            );
            $issuedCount = 0;
            $firstIssueId = 0;
            foreach ($targetAccountIds as $targetAccountId) {
                $issueId = sr_coupon_issue_to_account(
                    $pdo,
                    $definitionId,
                    $targetAccountId,
                    sr_post_string('issued_reason', 255),
                    (int) $account['id'],
                    null
                );
                $issuedCount++;
                if ($firstIssueId === 0) {
                    $firstIssueId = $issueId;
                }
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.issue.created',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'result' => 'success',
                'message' => 'Coupon issued.',
                'metadata' => [
                    'coupon_issue_id' => $firstIssueId,
                    'issued_count' => $issuedCount,
                    'issue_target_mode' => $targetMode,
                ],
            ]);
            $notice = $issuedCount . '명에게 쿠폰을 지급했습니다.';
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/coupons');
        } elseif ($intent === 'set_definition_status' && $couponAdminPage === 'definitions') {
            $definitionId = (int) sr_post_string('definition_id', 20);
            $status = sr_post_string('status', 30);
            sr_coupon_update_definition_status($pdo, $definitionId, $status);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.definition.status_updated',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'result' => 'success',
                'message' => 'Coupon definition status updated.',
                'metadata' => ['status' => $status],
            ]);
            $notice = '쿠폰 종류의 사용 상태를 변경했습니다.';
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/coupons');
        } elseif ($intent === 'set_issue_status' && $couponAdminPage === 'issues') {
            $issueId = (int) sr_post_string('issue_id', 20);
            $status = sr_post_string('status', 30);
            sr_coupon_update_issue_status($pdo, $issueId, $status, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.issue.status_updated',
                'target_type' => 'coupon_issue',
                'target_id' => (string) $issueId,
                'result' => 'success',
                'message' => 'Coupon issue status updated.',
                'metadata' => ['status' => $status],
            ]);
            $notice = '지급한 쿠폰 상태를 변경했습니다.';
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/coupons/issues');
        } elseif ($intent === 'refund_redemption' && $couponAdminPage === 'redemptions') {
            $redemptionId = (int) sr_post_string('redemption_id', 20);
            $refundNote = sr_post_string('refund_note', 255);
            $refundResult = sr_coupon_refund_redemption($pdo, $redemptionId, (int) $account['id'], $refundNote);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.redemption.refunded',
                'target_type' => 'coupon_redemption',
                'target_id' => (string) $redemptionId,
                'result' => 'success',
                'message' => 'Coupon redemption refunded.',
                'metadata' => $refundResult + [
                    'refund_note' => $refundNote,
                ],
            ]);
            $notice = '쿠폰 사용 내역을 수동 환불했습니다.';
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/coupons/redemptions');
        } else {
            $errors[] = '요청한 작업을 처리할 수 없습니다.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '쿠폰 작업을 처리하지 못했습니다.';
        $couponCreateModalOpen = $intent === 'create_definition';
        $couponIssueModalOpenDefinitionId = $intent === 'issue_coupon' ? (int) sr_post_string('coupon_definition_id', 20) : 0;
    }
}

$definitions = $couponAdminPage === 'definitions' ? sr_coupon_definitions($pdo, 100) : [];
$memberGroups = $couponAdminPage === 'definitions' ? sr_coupon_issue_member_groups($pdo) : [];
$issues = [];
$redemptions = [];
if ($couponAdminPage === 'issues') {
    $stmt = $pdo->query(
        'SELECT i.id, i.account_id, i.status, i.used_count, i.issued_at, i.expires_at, d.title, d.coupon_key
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         ORDER BY i.id DESC
         LIMIT 100'
    );
    foreach ($stmt->fetchAll() as $row) {
        $row['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
        $issues[] = $row;
    }
} elseif ($couponAdminPage === 'redemptions') {
    $redemptions = sr_coupon_admin_redemptions($pdo, $runtimeConfig, 100);
}

include SR_ROOT . '/modules/coupon/views/admin-coupons.php';
