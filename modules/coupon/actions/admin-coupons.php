<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons', 'view');

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons', 'edit');

    $intent = sr_post_string('intent', 40);
    try {
        if ($intent === 'create_definition') {
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
            $notice = '쿠폰 정의를 등록했습니다.';
        } elseif ($intent === 'issue_coupon') {
            $targetAccountIdentifier = sr_post_string('account_identifier', 80);
            $targetAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $targetAccountIdentifier);
            if ($targetAccountId <= 0) {
                throw new InvalidArgumentException('발급 대상 회원을 선택해 주세요.');
            }
            $issueId = sr_coupon_issue_to_account(
                $pdo,
                (int) sr_post_string('coupon_definition_id', 20),
                $targetAccountId,
                sr_post_string('issued_reason', 255),
                (int) $account['id'],
                null
            );
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.issue.created',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Coupon issued.',
                'metadata' => [
                    'coupon_issue_id' => $issueId,
                ],
            ]);
            $notice = '쿠폰을 발급했습니다.';
        } elseif ($intent === 'set_definition_status') {
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
            $notice = '쿠폰 정의 상태를 변경했습니다.';
        } elseif ($intent === 'set_issue_status') {
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
            $notice = '발급 쿠폰 상태를 변경했습니다.';
        } else {
            $errors[] = '요청한 작업을 처리할 수 없습니다.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '쿠폰 작업을 처리하지 못했습니다.';
    }
}

$definitions = sr_coupon_definitions($pdo, 100);
$issues = [];
$stmt = $pdo->query(
    'SELECT i.id, i.account_id, i.status, i.used_count, i.issued_at, i.expires_at, d.title, d.coupon_key
     FROM sr_coupon_issues i
     INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
     ORDER BY i.id DESC
     LIMIT 100'
);
foreach ($stmt->fetchAll() as $row) {
    $issues[] = $row;
}

include SR_ROOT . '/modules/coupon/views/admin-coupons.php';
