<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/levels', 'edit');
sr_require_csrf();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$settings = sr_community_settings($pdo);
if (empty($settings['level_enabled'])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => sr_t('community::action.admin.level_recalculate_disabled'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    sr_finish_response();
}

if (sr_post_string('recalculate_confirmed', 1) !== '1') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => sr_t('community::action.admin.level_recalculate_confirmation_required'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    sr_finish_response();
}

$cursorInput = sr_post_string('cursor', 20);
$batchSizeInput = sr_post_string('batch_size', 20);
$processedTotalInput = sr_post_string('processed_total', 20);
$cursor = preg_match('/\A[0-9]+\z/', $cursorInput) === 1 ? (int) $cursorInput : 0;
$batchSize = preg_match('/\A[1-9][0-9]*\z/', $batchSizeInput) === 1 ? (int) $batchSizeInput : 50;
$processedTotal = preg_match('/\A[0-9]+\z/', $processedTotalInput) === 1 ? (int) $processedTotalInput : 0;
$batchSize = max(1, min(100, $batchSize));
$processedTotal = max(0, min(1000000000, $processedTotal));

$total = sr_community_recalculate_target_account_count($pdo);
$summary = sr_community_recalculate_account_levels_batch($pdo, $cursor, $batchSize, $settings);
$processed = (int) ($summary['accounts'] ?? 0);
$processedTotal += $processed;
$done = !empty($summary['done']);

if ($done) {
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'community.levels.recalculated',
        'target_type' => 'module',
        'target_id' => 'community',
        'result' => 'success',
        'message' => 'Community levels recalculated.',
        'metadata' => [
            'accounts' => $processedTotal,
            'total' => $total,
            'batch_size' => $batchSize,
            'next_cursor' => (int) ($summary['next_cursor'] ?? $cursor),
        ],
    ]);
}

echo json_encode([
    'ok' => true,
    'processed' => $processed,
    'processed_total' => $processedTotal,
    'total' => $total,
    'next_cursor' => (int) ($summary['next_cursor'] ?? $cursor),
    'done' => $done,
    'message' => $done
        ? sr_t('community::action.admin.levels_recalculated', ['accounts' => (string) $processedTotal])
        : sr_t('community::action.admin.levels_recalculating', [
            'processed' => (string) $processedTotal,
            'total' => (string) $total,
        ]),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
sr_finish_response();
