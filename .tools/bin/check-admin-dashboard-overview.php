#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
require_once SR_ROOT . '/modules/admin/helpers/dashboard.php';
function sr_t(string $key, array $parameters = []): string { return $key; }
function sr_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function sr_url(string $path): string { return $path; }
function sr_material_icon_html(string $icon, string $class = ''): string { return '<span aria-hidden="true" class="' . sr_e($class) . '">' . sr_e($icon) . '</span>'; }

class SrDashboardOverviewStatement extends PDOStatement
{
    public function __construct() {}
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return ['value' => '12', 'detail' => '8'];
    }
}
class SrDashboardOverviewPdo extends PDO
{
    public int $queries = 0;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries++;
        if (str_contains($query, 'fixture_failure')) {
            throw new PDOException('Fixture query failure');
        }
        return new SrDashboardOverviewStatement();
    }
}
function sr_dashboard_overview_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new SrDashboardOverviewPdo();
$sections = [];
$expectedQueries = 0;
foreach (['member', 'content', 'community', 'point', 'deposit', 'reward', 'coupon', 'asset_exchange'] as $module) {
    $definitions = require SR_ROOT . '/modules/' . $module . '/dashboard.php';
    $routes = require SR_ROOT . '/modules/' . $module . '/paths.php';
    foreach ($definitions as $definition) {
        foreach ($definition['items'] as $row) {
            if (isset($row['overview'])) {
                $descriptor = sr_admin_dashboard_overview_descriptor($row['overview']);
                sr_dashboard_overview_assert($descriptor !== [], 'Provider overview metadata must be valid: ' . $module);
                $path = (string) parse_url($descriptor['path'], PHP_URL_PATH);
                sr_dashboard_overview_assert(isset($routes['GET ' . $path]), 'Overview must link to a module-owned GET route: ' . $module);
            }
            $expectedQueries += isset($row['value_sql']) ? 1 : 0;
            $expectedQueries += isset($row['detail_sql']) ? 1 : 0;
        }
        $definition['rows'] = sr_admin_dashboard_metric_rows($pdo, $definition['items']);
        $definition['module_key'] = $module;
        $definition['default_visible'] = true;
        $sections[] = $definition;
    }
}
$dashboardOverview = sr_admin_dashboard_overview($sections);
sr_dashboard_overview_assert(array_map('count', $dashboardOverview) === ['service' => 3, 'task' => 4, 'activity' => 3, 'asset' => 4], 'Provider roles must not turn activity or assets into pending tasks.');
sr_dashboard_overview_assert(array_column($dashboardOverview['asset'], 'title') === ['포인트 총 잔액', '예치금 총 잔액', '적립금 총 잔액', '쿠폰 지급'], 'Asset titles must come from their providers.');
sr_dashboard_overview_assert($pdo->queries === $expectedQueries, 'Overview must reuse queried metrics without extra SQL.');
foreach (['https://example.org', '//example.org', '/admin/../install', '/admin#fragment'] as $path) {
    sr_dashboard_overview_assert(sr_admin_dashboard_overview_descriptor(['role' => 'task', 'path' => $path]) === [], 'Reject unsupported overview destination.');
}
sr_dashboard_overview_assert(sr_admin_dashboard_overview_descriptor(['role' => 'arbitrary', 'path' => '/admin']) === [], 'Reject undeclared overview roles.');

$failureRows = sr_admin_dashboard_metric_rows($pdo, [['label' => '<Pending>', 'value_sql' => 'SELECT fixture_failure AS value', 'overview' => ['role' => 'task', 'path' => '/admin']]]);
$dashboardOverview = sr_admin_dashboard_overview([['key' => 'fixture', 'title' => '<Module>', 'rows' => $failureRows, 'default_visible' => true]]);
sr_dashboard_overview_assert($dashboardOverview['task'][0]['count'] === null, 'Query failure must not become a zero task count.');
$recoveryMarkers = [];
ob_start();
include SR_ROOT . '/modules/admin/views/dashboard-overview.php';
$html = (string) ob_get_clean();
sr_dashboard_overview_assert(str_contains($html, '확인 필요') && !str_contains($html, '>대기 업무 없음<'), 'Unknown counts must not imply a cleared queue.');
sr_dashboard_overview_assert(str_contains($html, '&lt;Pending&gt;') && !str_contains($html, '<Pending>'), 'Overview labels must be escaped.');
$sections[0]['default_visible'] = false;
sr_dashboard_overview_assert(sr_admin_dashboard_overview($sections)['service'][0]['visible'] === false, 'Default hidden module state must reach the overview.');
sr_dashboard_overview_assert(sr_admin_dashboard_overview([]) === ['service' => [], 'task' => [], 'activity' => [], 'asset' => []], 'Empty module data must stay empty.');
echo "admin dashboard overview checks completed.\n";
