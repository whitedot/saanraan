<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/logs', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$assets = sr_asset_exchange_assets($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/logs', 'edit');

    $intent = sr_post_string('intent', 40);
    $returnTo = sr_admin_post_return_url('/admin/asset-exchange/logs');

    try {
        if ($intent !== 'correct_completed_group') {
            throw new InvalidArgumentException('지원하지 않는 환전 로그 작업입니다.');
        }

        $exchangeGroupId = sr_asset_exchange_clean_reference_id(sr_post_string('exchange_group_id', 80), 80);
        $reason = sr_asset_exchange_clean_text(sr_post_string('correction_reason', 255), 255);
        if ($reason === '') {
            $reason = '관리자 환전 정정';
        }

        $correctionLogId = sr_asset_exchange_correct_completed_group($pdo, $exchangeGroupId, (int) $account['id'], $reason);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.log.corrected',
            'target_type' => 'asset_exchange_log',
            'target_id' => $exchangeGroupId,
            'result' => 'success',
            'message' => 'Asset exchange completed group corrected.',
            'metadata' => [
                'exchange_group_id' => $exchangeGroupId,
                'correction_log_id' => $correctionLogId,
                'reason' => $reason,
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([], '환전 묶음을 정정했습니다.'));
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
            ? $exception->getMessage()
            : '환전 묶음 정정에 실패했습니다.';
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.log.corrected',
            'target_type' => 'asset_exchange_log',
            'target_id' => sr_asset_exchange_clean_reference_id(sr_post_string('exchange_group_id', 80), 80),
            'result' => 'failure',
            'message' => 'Asset exchange completed group correction failed.',
            'metadata' => [
                'reason' => sr_asset_exchange_clean_text($message, 255),
            ],
        ]);
        sr_admin_flash_result(sr_admin_action_result([$message], ''));
    }

    sr_redirect($returnTo);
}

$logFilters = [
    'status' => sr_admin_get_allowed_single_array('status', ['completed', 'failed'], 30),
    'asset' => sr_admin_get_allowed_single_array('asset', array_keys($assets), 40),
    'field' => sr_get_string('field', 30),
    'q' => trim(sr_get_string('q', 120)),
];
if (!in_array($logFilters['field'], ['all', 'member', 'group_id'], true)) {
    $logFilters['field'] = 'all';
}

$where = [];
$params = [];
if ($logFilters['status'] !== []) {
    [$condition, $conditionParams] = sr_admin_sql_in_condition('l.status', 'status', $logFilters['status']);
    $where[] = $condition;
    $params = array_merge($params, $conditionParams);
}
if ($logFilters['asset'] !== []) {
    [$fromCondition, $fromParams] = sr_admin_sql_in_condition('l.from_module_key', 'from_asset', $logFilters['asset']);
    [$toCondition, $toParams] = sr_admin_sql_in_condition('l.to_module_key', 'to_asset', $logFilters['asset']);
    $where[] = '(' . $fromCondition . ' OR ' . $toCondition . ')';
    $params = array_merge($params, $fromParams, $toParams);
}
if ($logFilters['q'] !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $logFilters['q']) . '%';
    if ($logFilters['field'] === 'member') {
        $where[] = "(a.email LIKE :keyword ESCAPE '\\\\' OR a.display_name LIKE :keyword ESCAPE '\\\\')";
        $params['keyword'] = $like;
    } elseif ($logFilters['field'] === 'group_id') {
        $where[] = "l.exchange_group_id LIKE :keyword ESCAPE '\\\\'";
        $params['keyword'] = $like;
    } else {
        $where[] = "(a.email LIKE :member_keyword ESCAPE '\\\\' OR a.display_name LIKE :member_keyword ESCAPE '\\\\' OR l.exchange_group_id LIKE :group_keyword ESCAPE '\\\\')";
        $params['member_keyword'] = $like;
        $params['group_keyword'] = $like;
    }
}
$whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) AS count_value
     FROM sr_asset_exchange_logs l
     INNER JOIN sr_member_accounts a ON a.id = l.account_id' . $whereSql
);
$countStmt->execute($params);
$countRow = $countStmt->fetch();
$pagination = sr_admin_pagination_from_total($pdo, is_array($countRow) ? (int) ($countRow['count_value'] ?? 0) : 0);
$stmt = $pdo->prepare(
    'SELECT l.*, a.email, a.display_name, a.status AS account_status
     FROM sr_asset_exchange_logs l
     INNER JOIN sr_member_accounts a ON a.id = l.account_id' . $whereSql . '
     ORDER BY l.id DESC
     LIMIT :limit_value OFFSET :offset_value'
);
foreach ($params as $paramKey => $paramValue) {
    $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
}
$stmt->bindValue('limit_value', (int) $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset_value', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange-logs.php';
