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
    $returnTo = sr_post_string('return_to', 500);
    if (!sr_is_safe_relative_url($returnTo)) {
        $returnTo = $couponAdminPage === 'definitions' ? '/admin/coupons' : $requestPath;
    }
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
        } elseif ($intent === 'batch_definition_status' && $couponAdminPage === 'definitions') {
            $operationKey = sr_post_string('operation_key', 80);
            $targetStatus = sr_post_string('target_status', 30);
            $rawSelectedIds = $_POST['selected_definition_ids'] ?? [];
            $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);

            if ($operationKey !== 'coupon.definition_set_status') {
                $errors[] = '허용되지 않은 일괄 작업입니다.';
            }
            if (!in_array($targetStatus, sr_coupon_statuses(), true)) {
                $errors[] = '변경할 쿠폰 종류 상태가 올바르지 않습니다.';
            }
            if ($selectedIds === []) {
                $errors[] = '상태를 변경할 쿠폰 종류를 선택하세요.';
            }
            if ($hasInvalidSelectedId) {
                $errors[] = '선택한 쿠폰 종류 ID 값이 올바르지 않습니다.';
            }
            if (count($selectedIds) > 100) {
                $errors[] = '쿠폰 종류 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
            }

            $selectedDefinitions = [];
            if ($errors === []) {
                $placeholders = [];
                $params = [];
                foreach ($selectedIds as $index => $selectedId) {
                    $paramKey = 'definition_id_' . (string) $index;
                    $placeholders[] = ':' . $paramKey;
                    $params[$paramKey] = $selectedId;
                }
                $stmt = $pdo->prepare(
                    'SELECT id, coupon_key, title, status
                     FROM sr_coupon_definitions
                     WHERE id IN (' . implode(', ', $placeholders) . ')
                     ORDER BY id ASC'
                );
                foreach ($params as $paramKey => $selectedId) {
                    $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
                }
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    $selectedDefinitions[(int) ($row['id'] ?? 0)] = $row;
                }
                if (count($selectedDefinitions) !== count($selectedIds)) {
                    $errors[] = '선택한 쿠폰 종류 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                }
            }

            $blockedReferenceIds = [];
            if ($errors === [] && $targetStatus === 'disabled') {
                foreach ($selectedDefinitions as $selectedDefinition) {
                    $selectedId = (int) ($selectedDefinition['id'] ?? 0);
                    if ($selectedId < 1 || (string) ($selectedDefinition['status'] ?? '') !== 'active') {
                        continue;
                    }
                    $referenceResult = sr_read_reference_collect($pdo, 'coupon-references.php', [
                        'owner_module_key' => 'coupon',
                        'target_type' => 'coupon_definition',
                        'target_id' => $selectedId,
                        'target_key' => (string) ($selectedDefinition['coupon_key'] ?? ''),
                    ], [
                        'definition' => $selectedDefinition,
                        'coupon_key' => (string) ($selectedDefinition['coupon_key'] ?? ''),
                    ]);
                    if (($referenceResult['errors'] ?? []) !== []) {
                        $errors[] = '쿠폰 정의 참조 계약 오류가 있어 일괄 비활성화할 수 없습니다.';
                        break;
                    }
                    if (($referenceResult['rows'] ?? []) !== []) {
                        $blockedReferenceIds[] = $selectedId;
                    }
                }
                if ($errors === [] && $blockedReferenceIds !== []) {
                    $errors[] = '발급/사용 이력 또는 운영 참조가 있는 쿠폰 종류가 있어 비활성화하지 않았습니다: ' . implode(', ', array_map('strval', $blockedReferenceIds));
                }
            }

            if ($errors === []) {
                $changedCount = 0;
                $skippedCount = 0;
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE sr_coupon_definitions
                         SET status = :status,
                             updated_at = :updated_at
                         WHERE id = :id
                           AND status <> :status'
                    );
                    $now = sr_now();
                    foreach ($selectedIds as $selectedId) {
                        $stmt->execute([
                            'status' => $targetStatus,
                            'updated_at' => $now,
                            'id' => $selectedId,
                        ]);
                        if ($stmt->rowCount() > 0) {
                            $changedCount++;
                        } else {
                            $skippedCount++;
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'coupon.definition.batch_status_updated',
                    'target_type' => 'coupon_definition',
                    'target_id' => 'batch',
                    'result' => 'success',
                    'message' => 'Coupon definition statuses updated.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'selected_ids' => $selectedIds,
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                    ],
                ]);

                $notice = '쿠폰 종류 ' . (string) $changedCount . '건의 상태를 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . (string) $skippedCount . '건은 건너뛰었습니다.';
                }
                sr_admin_flash_result(sr_admin_action_result([], $notice));
                sr_redirect($returnTo);
            }
        } elseif ($intent === 'set_definition_status' && $couponAdminPage === 'definitions') {
            $definitionId = (int) sr_post_string('definition_id', 20);
            $status = sr_post_string('status', 30);
            if ($status !== 'active') {
                $definition = sr_coupon_definition_by_id($pdo, $definitionId);
                $referenceResult = sr_read_reference_collect($pdo, 'coupon-references.php', [
                    'owner_module_key' => 'coupon',
                    'target_type' => 'coupon_definition',
                    'target_id' => $definitionId,
                    'target_key' => is_array($definition) ? (string) ($definition['coupon_key'] ?? '') : '',
                ], [
                    'definition' => is_array($definition) ? $definition : [],
                    'coupon_key' => is_array($definition) ? (string) ($definition['coupon_key'] ?? '') : '',
                ]);
                if (($referenceResult['errors'] ?? []) !== []) {
                    throw new RuntimeException('쿠폰 정의 참조 계약 오류가 있어 상태를 변경할 수 없습니다.');
                }
                if (($referenceResult['rows'] ?? []) !== []) {
                    throw new RuntimeException('발급/사용 이력이 있는 쿠폰 정의는 비활성화할 수 없습니다. 참조 현황을 먼저 확인하세요.');
                }
            }
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
        $errors[] = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException ? $exception->getMessage() : '쿠폰 작업을 처리하지 못했습니다.';
        $couponCreateModalOpen = $intent === 'create_definition';
        $couponIssueModalOpenDefinitionId = $intent === 'issue_coupon' ? (int) sr_post_string('coupon_definition_id', 20) : 0;
    }
}

$definitionFilters = $couponAdminPage === 'definitions' ? sr_coupon_admin_definition_filters($pdo) : [];
$issueFilters = $couponAdminPage === 'issues' ? sr_coupon_admin_issue_filters($pdo, $runtimeConfig) : [];
$redemptionFilters = $couponAdminPage === 'redemptions' ? sr_coupon_admin_redemption_filters($pdo, $runtimeConfig) : [];
$definitionSort = $couponAdminPage === 'definitions' ? sr_admin_sort_from_request(sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort()) : sr_coupon_admin_definition_default_sort();
$issueSort = $couponAdminPage === 'issues' ? sr_admin_sort_from_request(sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()) : sr_coupon_admin_issue_default_sort();
$redemptionSort = $couponAdminPage === 'redemptions' ? sr_admin_sort_from_request(sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()) : sr_coupon_admin_redemption_default_sort();
$definitions = $couponAdminPage === 'definitions' ? sr_coupon_admin_definitions($pdo, $definitionFilters, 100, $definitionSort) : [];
$couponDefinitionReadReferencesById = [];
foreach ($definitions as $definition) {
    $definitionId = (int) ($definition['id'] ?? 0);
    if ($definitionId < 1) {
        continue;
    }
    $couponDefinitionReadReferencesById[$definitionId] = sr_read_reference_collect($pdo, 'coupon-references.php', [
        'owner_module_key' => 'coupon',
        'target_type' => 'coupon_definition',
        'target_id' => $definitionId,
        'target_key' => (string) ($definition['coupon_key'] ?? ''),
    ], [
        'definition' => $definition,
        'coupon_key' => (string) ($definition['coupon_key'] ?? ''),
    ]);
}
$memberGroups = $couponAdminPage === 'definitions' ? sr_coupon_issue_member_groups($pdo) : [];
$issues = [];
$redemptions = [];
if ($couponAdminPage === 'issues') {
    $issues = sr_coupon_admin_issues($pdo, $runtimeConfig, $issueFilters, 100, $issueSort);
} elseif ($couponAdminPage === 'redemptions') {
    $redemptions = sr_coupon_admin_redemptions($pdo, $runtimeConfig, 100, $redemptionFilters, $redemptionSort);
}

include SR_ROOT . '/modules/coupon/views/admin-coupons.php';
