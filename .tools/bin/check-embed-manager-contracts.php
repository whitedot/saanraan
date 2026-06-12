<?php

declare(strict_types=1);

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-11 12:00:00';
    }
}

if (!function_exists('sr_e')) {
    function sr_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sr_is_safe_relative_url')) {
    function sr_is_safe_relative_url(string $url): bool
    {
        return $url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//');
    }
}

if (!function_exists('sr_is_http_url')) {
    function sr_is_http_url(string $url): bool
    {
        return preg_match('#\Ahttps?://#i', $url) === 1;
    }
}

if (!function_exists('sr_log_exception')) {
    function sr_log_exception(Throwable $exception, string $context = ''): void
    {
    }
}

if (!function_exists('sr_enabled_module_contract_files')) {
    function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludeModuleKeys = []): array
    {
        if ($contractFile !== 'embed-manager-targets.php') {
            return [];
        }

        return ['fixture' => 'embed-manager-targets.php'];
    }
}

if (!function_exists('sr_load_module_contract_file')) {
    function sr_load_module_contract_file(string $moduleKey, string $file): mixed
    {
        if ($moduleKey !== 'fixture' || $file !== 'embed-manager-targets.php') {
            return null;
        }

        return [
            'targets' => [
                [
                    'target_module' => 'fixture',
                    'target_type' => 'item',
                    'allowed_variants' => ['card', 'button'],
                    'default_variant' => 'card',
                    'search' => static function (PDO $pdo, string $keyword, int $limit, array $context = []): array {
                        return [];
                    },
                    'resolve' => static function (PDO $pdo, array $context): ?array {
                        $stmt = $pdo->prepare('SELECT * FROM sr_fixture_embed_targets WHERE id = :id LIMIT 1');
                        $stmt->execute(['id' => (int) ($context['target_id'] ?? 0)]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!is_array($row)) {
                            return null;
                        }

                        return [
                            'label_snapshot' => (string) ($row['label_snapshot'] ?? ''),
                            'summary' => (string) ($row['summary'] ?? ''),
                            'public_url' => (string) ($row['public_url'] ?? ''),
                            'admin_url' => (string) ($row['admin_url'] ?? ''),
                            'status' => (string) ($row['status'] ?? 'active'),
                        ];
                    },
                ],
            ],
        ];
    }
}

require_once dirname(__DIR__, 2) . '/modules/embed_manager/helpers.php';

$errors = [];

function sr_embed_contract_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_embed_contract_read(string $path): string
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_embed_contract_error('file cannot be read: ' . $path);
        return '';
    }

    return $contents;
}

function sr_embed_contract_contains(string $path, string $needle): void
{
    $contents = sr_embed_contract_read($path);
    if ($contents !== '' && !str_contains($contents, $needle)) {
        sr_embed_contract_error($path . ' missing marker: ' . $needle);
    }
}

function sr_embed_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_embed_contract_error($message);
    }
}

function sr_embed_contract_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_embed_manager_refs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ref_key TEXT NOT NULL UNIQUE,
            owner_module TEXT NOT NULL,
            owner_type TEXT NOT NULL,
            owner_id INTEGER NOT NULL,
            owner_field TEXT NOT NULL DEFAULT \'body\',
            target_module TEXT NOT NULL,
            target_type TEXT NOT NULL,
            target_id TEXT NOT NULL,
            variant TEXT NOT NULL DEFAULT \'card\',
            label_snapshot TEXT NOT NULL DEFAULT \'\',
            image_snapshot TEXT NOT NULL DEFAULT \'\',
            sort_order INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT \'active\',
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_fixture_embed_targets (
            id INTEGER PRIMARY KEY,
            label_snapshot TEXT NOT NULL,
            summary TEXT NOT NULL,
            public_url TEXT NOT NULL,
            admin_url TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_fixture_embed_targets
            (id, label_snapshot, summary, public_url, admin_url, status)
         VALUES
            (1, '공개 항목', '공개 요약', '/fixture/1', '/admin/fixture/1', 'active'),
            (2, '비공개 항목', '비공개 요약', '/fixture/2', '/admin/fixture/2', 'private')"
    );

    return $pdo;
}

function sr_embed_contract_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function sr_embed_contract_marker(string $refKey, int $targetId, string $variant = 'card', string $label = ''): string
{
    return '<span class="sr-embed-manager-marker"'
        . ' data-sr-embed-manager-ref="' . sr_e($refKey) . '"'
        . ' data-sr-embed-manager-target-module="fixture"'
        . ' data-sr-embed-manager-target-type="item"'
        . ' data-sr-embed-manager-target-id="' . sr_e((string) $targetId) . '"'
        . ' data-sr-embed-manager-variant="' . sr_e($variant) . '"'
        . ' data-sr-embed-manager-label="' . sr_e($label) . '"></span>';
}

function sr_embed_contract_runtime_fixture(): void
{
    $pdo = sr_embed_contract_pdo();
    $body = '<p>앞</p>' . sr_embed_contract_marker('em_active1', 1, 'card', '본문 라벨') . '<p>뒤</p>';
    sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 10, 'body', $body, 7);
    sr_embed_contract_assert((int) sr_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_embed_manager_refs') === 1, 'embed runtime fixture must create one ref.');
    sr_embed_contract_assert((string) sr_embed_contract_scalar($pdo, 'SELECT status FROM sr_embed_manager_refs WHERE ref_key = :ref_key', ['ref_key' => 'em_active1']) === 'active', 'embed runtime fixture must store active status.');
    sr_embed_contract_assert((string) sr_embed_contract_scalar($pdo, 'SELECT created_by_account_id FROM sr_embed_manager_refs WHERE ref_key = :ref_key', ['ref_key' => 'em_active1']) === '7', 'embed runtime fixture must store creator account id.');

    $rendered = sr_embed_manager_render_body_html($pdo, $body, 'fixture', 'doc', 10);
    sr_embed_contract_assert(str_contains($rendered, 'data-sr-embed-manager-rendered="1"'), 'embed runtime fixture must render active marker as a card.');
    sr_embed_contract_assert(str_contains($rendered, 'href="/fixture/1"'), 'embed runtime fixture must render active target URL.');
    sr_embed_contract_assert(str_contains($rendered, '공개 항목'), 'embed runtime fixture must render resolved target label.');

    $privateBody = sr_embed_contract_marker('em_private1', 2, 'button', '');
    sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 11, 'body', $privateBody, 7);
    $publicPrivate = sr_embed_manager_render_body_html($pdo, $privateBody, 'fixture', 'doc', 11);
    sr_embed_contract_assert(!str_contains($publicPrivate, '비공개 항목'), 'embed runtime fixture must hide private targets in public mode.');
    $adminPrivate = sr_embed_manager_render_body_html($pdo, $privateBody, 'fixture', 'doc', 11, 'body', ['mode' => 'admin']);
    sr_embed_contract_assert(str_contains($adminPrivate, '비공개 항목 (private)'), 'embed runtime fixture must show private status in admin mode.');
    sr_embed_contract_assert(str_contains($adminPrivate, 'href="/admin/fixture/2"'), 'embed runtime fixture must show admin URL for private targets in admin mode.');

    sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 10, 'body', '<p>marker removed</p>', 7);
    sr_embed_contract_assert((string) sr_embed_contract_scalar($pdo, 'SELECT status FROM sr_embed_manager_refs WHERE ref_key = :ref_key', ['ref_key' => 'em_active1']) === 'removed', 'embed runtime fixture must mark missing refs as removed.');
    $removedRendered = sr_embed_manager_render_body_html($pdo, $body, 'fixture', 'doc', 10);
    sr_embed_contract_assert(!str_contains($removedRendered, 'data-sr-embed-manager-rendered="1"'), 'embed runtime fixture must not render removed refs.');

    try {
        sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 12, 'body', sr_embed_contract_marker('em_dupkey1', 1) . sr_embed_contract_marker('em_dupkey1', 1), 7);
        sr_embed_contract_error('embed runtime fixture must reject duplicate ref keys in one body.');
    } catch (InvalidArgumentException $exception) {
        sr_embed_contract_assert(str_contains($exception->getMessage(), '중복'), 'embed runtime fixture duplicate ref error message changed unexpectedly.');
    }

    sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 13, 'body', sr_embed_contract_marker('em_shared1', 1), 7);
    try {
        sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 14, 'body', sr_embed_contract_marker('em_shared1', 1), 7);
        sr_embed_contract_error('embed runtime fixture must reject ref keys already owned by another document.');
    } catch (InvalidArgumentException $exception) {
        sr_embed_contract_assert(str_contains($exception->getMessage(), '이미 사용 중'), 'embed runtime fixture cross-owner ref error message changed unexpectedly.');
    }

    $sourceBody = sr_embed_contract_marker('em_copyme1', 1);
    sr_embed_manager_sync_body_refs($pdo, 'fixture', 'doc', 20, 'body', $sourceBody, 7);
    $rewritten = sr_embed_manager_rewrite_body_refs_for_copy($pdo, 'fixture', 'doc', 20, 'body', 'fixture', 'doc', 21, 'body', $sourceBody, 8);
    sr_embed_contract_assert(!str_contains($rewritten, 'em_copyme1'), 'embed runtime fixture must rewrite copied ref keys.');
    sr_embed_contract_assert((int) sr_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_embed_manager_refs WHERE owner_module = :owner_module AND owner_type = :owner_type AND owner_id = :owner_id', [
        'owner_module' => 'fixture',
        'owner_type' => 'doc',
        'owner_id' => 21,
    ]) === 1, 'embed runtime fixture must create copied owner refs.');
    sr_embed_contract_assert(str_contains(sr_embed_manager_render_body_html($pdo, $rewritten, 'fixture', 'doc', 21), '공개 항목'), 'embed runtime fixture must render copied owner refs.');

    $pdo->exec('DELETE FROM sr_fixture_embed_targets WHERE id = 1');
    $brokenPublic = sr_embed_manager_render_body_html($pdo, $rewritten, 'fixture', 'doc', 21);
    sr_embed_contract_assert(!str_contains($brokenPublic, '공개 항목'), 'embed runtime fixture must hide broken refs in public mode.');
    $brokenAdmin = sr_embed_manager_render_body_html($pdo, $rewritten, 'fixture', 'doc', 21, 'body', ['mode' => 'admin']);
    sr_embed_contract_assert(str_contains($brokenAdmin, '공개 항목 (broken)'), 'embed runtime fixture must expose broken refs in admin mode.');
}

foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    $contractPath = 'modules/' . $moduleKey . '/embed-manager-targets.php';
    $modulePath = 'modules/' . $moduleKey . '/module.php';
    if (!is_file($contractPath)) {
        sr_embed_contract_error('embed manager contract is missing: ' . $contractPath);
        continue;
    }

    sr_embed_contract_contains($modulePath, "'embed-manager-targets.php'");
    foreach (["'target_module' => '" . $moduleKey . "'", "'allowed_variants'", "'search'", "'resolve'", "'public_url'", "'admin_url'", "'status'"] as $needle) {
        sr_embed_contract_contains($contractPath, $needle);
    }
}

sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_contract_targets');
sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_search_targets');
sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_render_body_html');
sr_embed_contract_contains('modules/embed_manager/helpers.php', 'ON CONFLICT(ref_key) DO UPDATE SET');
sr_embed_contract_contains('modules/embed_manager/helpers.php', '본문 임베드 표시 방식이 지원되지 않습니다.');
sr_embed_contract_contains('modules/content/helpers.php', 'sr_embed_manager_render_body_html($pdo');
sr_embed_contract_contains('modules/community/helpers/posts.php', 'sr_embed_manager_render_body_html($pdo');
sr_embed_contract_contains('modules/survey/skins/basic/view.php', 'return_to');
sr_embed_contract_runtime_fixture();

if ($errors !== []) {
    fwrite(STDERR, "embed manager contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'embed manager contract checks completed.' . PHP_EOL;
