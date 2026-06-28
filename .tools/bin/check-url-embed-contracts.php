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

if (!function_exists('sr_absolute_url')) {
    function sr_absolute_url(?array $site, string $path): string
    {
        $baseUrl = rtrim((string) ($site['base_url'] ?? ''), '/');
        return $baseUrl !== '' ? $baseUrl . '/' . ltrim($path, '/') : $path;
    }
}

if (!function_exists('sr_log_exception')) {
    function sr_log_exception(Throwable $exception, string $context = ''): void
    {
    }
}

if (!defined('SR_URL_EMBED_FRAGMENT_CACHE_ROOT')) {
    define('SR_URL_EMBED_FRAGMENT_CACHE_ROOT', sys_get_temp_dir() . '/saanraan-embed-fragment-cache-test-' . getmypid());
}

if (!function_exists('sr_module_settings')) {
    function sr_module_settings(PDO $pdo, string $moduleKey): array
    {
        $defaultSettings = [
            'url_embed_enabled' => true,
            'internal_url_embed_enabled' => true,
            'external_url_embed_enabled' => true,
            'embed_scope' => 'standalone_url_only',
        ];

        if ($moduleKey === 'url_embed') {
            return array_merge($defaultSettings, isset($GLOBALS['sr_url_embed_contract_settings']) && is_array($GLOBALS['sr_url_embed_contract_settings']) ? $GLOBALS['sr_url_embed_contract_settings'] : []);
        }

        return isset($GLOBALS['sr_url_embed_contract_module_settings'][$moduleKey]) && is_array($GLOBALS['sr_url_embed_contract_module_settings'][$moduleKey])
            ? $GLOBALS['sr_url_embed_contract_module_settings'][$moduleKey]
            : [];
    }
}

if (!function_exists('sr_module_metadata')) {
    function sr_module_metadata(string $moduleKey): array
    {
        return isset($GLOBALS['sr_url_embed_contract_module_metadata'][$moduleKey]) && is_array($GLOBALS['sr_url_embed_contract_module_metadata'][$moduleKey])
            ? $GLOBALS['sr_url_embed_contract_module_metadata'][$moduleKey]
            : [];
    }
}

if (!function_exists('sr_site_setting')) {
    function sr_site_setting(PDO $pdo, string $key, mixed $default = null): mixed
    {
        return $key === 'site.base_url' ? 'https://example.test' : $default;
    }
}

if (!function_exists('sr_enabled_module_contract_files')) {
    function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludeModuleKeys = []): array
    {
        if ($contractFile !== 'url-embed-targets.php') {
            return [];
        }

        return ['fixture' => $contractFile];
    }
}

if (!function_exists('sr_load_module_contract_file')) {
    function sr_load_module_contract_file(string $moduleKey, string $file): mixed
    {
        if ($moduleKey !== 'fixture') {
            return null;
        }

        if ($file !== 'url-embed-targets.php') {
            return null;
        }

        return [
            'targets' => [
                [
                    'target_module' => 'fixture',
                    'target_type' => 'item',
                    'allowed_variants' => ['summary'],
                    'default_variant' => 'summary',
                    'embed_stylesheet' => '/modules/fixture/assets/embed.css',
                    'fragment_cache_public' => !empty($GLOBALS['sr_url_embed_contract_fragment_cache_public']),
                    'resolve_url' => static function (PDO $pdo, array $context): ?array {
                        $GLOBALS['sr_url_embed_contract_resolve_count'] = (int) ($GLOBALS['sr_url_embed_contract_resolve_count'] ?? 0) + 1;
                        $path = (string) parse_url((string) ($context['url'] ?? ''), PHP_URL_PATH);
                        if (!preg_match('#\A/fixture/([1-9][0-9]*)\z#', $path, $matches)) {
                            return null;
                        }
                        $stmt = $pdo->prepare('SELECT * FROM sr_fixture_embed_targets WHERE id = :id LIMIT 1');
                        $stmt->execute(['id' => (int) $matches[1]]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!is_array($row)) {
                            return [
                                'target_id' => (string) (int) $matches[1],
                                'canonical_url' => '/fixture/' . (string) (int) $matches[1],
                                'label_snapshot' => '삭제된 항목',
                                'target_state' => 'deleted',
                                'cache_status' => 'deleted',
                            ];
                        }

                        return [
                            'target_id' => (string) (int) ($row['id'] ?? 0),
                            'canonical_url' => '/fixture/' . (string) (int) ($row['id'] ?? 0),
                            'label_snapshot' => (string) ($row['label_snapshot'] ?? ''),
                            'summary_snapshot' => (string) ($row['summary'] ?? ''),
                            'image_snapshot' => (string) ($row['image_snapshot'] ?? ''),
                            'image_snapshot_policy' => (string) ($row['image_snapshot'] ?? '') !== '' ? 'public_url_ok' : 'none',
                            'target_state' => (string) ($row['status'] ?? '') === 'active' ? 'public' : 'private',
                            'cache_status' => (string) ($row['status'] ?? '') === 'active' ? 'fresh' : 'broken',
                            'target_cache_version' => (string) ($row['updated_at'] ?? ''),
                        ];
                    },
                    'render_embed' => static function (PDO $pdo, array $embed, array $context): array {
                        $GLOBALS['sr_url_embed_contract_render_count'] = (int) ($GLOBALS['sr_url_embed_contract_render_count'] ?? 0) + 1;
                        $stmt = $pdo->prepare('SELECT * FROM sr_fixture_embed_targets WHERE id = :id LIMIT 1');
                        $stmt->execute(['id' => (int) ($embed['target_id'] ?? 0)]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!is_array($row)) {
                            return ['html' => '', 'cache_status' => 'deleted'];
                        }
                        if ((string) ($row['status'] ?? '') !== 'active') {
                            return ['html' => '', 'cache_status' => 'broken', 'target_cache_version' => (string) ($row['updated_at'] ?? '')];
                        }
                        $image = (string) ($row['image_snapshot'] ?? '');
                        $html = '<sr-content-embed class="fixture-embed-summary" data-content-embed="summary">';
                        if ($image !== '') {
                            $html .= '<img src="' . sr_e($image) . '" alt="" loading="lazy" decoding="async" />';
                        }
                        $html .= '<strong><a href="/fixture/' . sr_e((string) (int) ($row['id'] ?? 0)) . '">' . sr_e((string) ($row['label_snapshot'] ?? '')) . '</a></strong>';
                        $html .= '<p>' . sr_e((string) ($row['summary'] ?? '')) . '</p></sr-content-embed>';
                        return ['html' => $html, 'cache_status' => 'fresh', 'target_cache_version' => (string) ($row['updated_at'] ?? '')];
                    },
                ],
            ],
        ];
    }
}

require_once dirname(__DIR__, 2) . '/core/helpers/url-embed.php';

$errors = [];

function sr_url_embed_contract_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_url_embed_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_url_embed_contract_error($message);
    }
}

function sr_url_embed_contract_contains(string $path, string $needle): void
{
    $contents = file_get_contents($path);
    if (!is_string($contents) || !str_contains($contents, $needle)) {
        sr_url_embed_contract_error($path . ' missing marker: ' . $needle);
    }
}

function sr_url_embed_contract_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_url_embed_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_module TEXT NOT NULL,
            owner_type TEXT NOT NULL,
            owner_id INTEGER NOT NULL,
            owner_field TEXT NOT NULL DEFAULT \'body\',
            source_url TEXT NOT NULL,
            canonical_url TEXT NOT NULL,
            canonical_url_hash TEXT NOT NULL,
            embed_kind TEXT NOT NULL DEFAULT \'internal_url\',
            provider_key TEXT NOT NULL DEFAULT \'\',
            render_variant TEXT NOT NULL DEFAULT \'summary\',
            target_module TEXT NOT NULL DEFAULT \'\',
            target_type TEXT NOT NULL DEFAULT \'\',
            target_id TEXT NOT NULL DEFAULT \'\',
            target_cache_version TEXT NOT NULL DEFAULT \'\',
            label_snapshot TEXT NOT NULL DEFAULT \'\',
            summary_snapshot TEXT NULL,
            image_snapshot TEXT NOT NULL DEFAULT \'\',
            image_snapshot_policy TEXT NOT NULL DEFAULT \'none\',
            target_state TEXT NOT NULL DEFAULT \'\',
            resolver_state TEXT NOT NULL DEFAULT \'\',
            cache_status TEXT NOT NULL DEFAULT \'fresh\',
            resolved_payload_json TEXT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            last_resolved_at TEXT NULL,
            last_render_checked_at TEXT NULL,
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(owner_module, owner_type, owner_id, owner_field, canonical_url_hash)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_fixture_embed_targets (
            id INTEGER PRIMARY KEY,
            label_snapshot TEXT NOT NULL,
            summary TEXT NOT NULL,
            image_snapshot TEXT NOT NULL,
            status TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_fixture_embed_targets
            (id, label_snapshot, summary, image_snapshot, status, updated_at)
         VALUES
            (1, '공개 항목', '공개 요약', '/fixture/image.webp', 'active', '2026-06-11 12:00:00'),
            (2, '비공개 항목', '비공개 요약', '', 'private', '2026-06-11 12:00:00')"
    );

    return $pdo;
}

function sr_url_embed_contract_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function sr_url_embed_contract_runtime_fixture(): void
{
    $pdo = sr_url_embed_contract_pdo();
    $emptyPdo = new PDO('sqlite::memory:');
    $emptyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    sr_url_embed_contract_assert(sr_url_embed_cache_table_exists($pdo), 'URL cache table must be detected on the fixture connection.');
    sr_url_embed_contract_assert(!sr_url_embed_cache_table_exists($emptyPdo), 'URL cache table detection must be scoped per PDO connection.');
    $emptyPdo->exec('CREATE TABLE sr_url_embed_cache (id INTEGER PRIMARY KEY)');
    sr_url_embed_contract_assert(sr_url_embed_cache_table_exists($emptyPdo), 'URL cache table detection must notice a table created later in the same process.');

    $body = '<p><a href="/fixture/1">/fixture/1</a></p><p>문장 안의 <a href="/fixture/2">/fixture/2</a> 링크</p>';
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 10, 'body', $body, 7);
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache') === 1, 'URL cache sync must create one canonical cache row.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache LIMIT 1') === 'fresh', 'URL cache row must be fresh.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT created_by_account_id FROM sr_url_embed_cache LIMIT 1') === '7', 'URL cache row must store creator account id.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT canonical_url FROM sr_url_embed_cache LIMIT 1') === '/fixture/1', 'Standalone URL cache must ignore inline URL links.');
    $summary = sr_url_embed_admin_cache_summary($pdo);
    sr_url_embed_contract_assert((int) ($summary['row_count'] ?? 0) === 1, 'URL cache admin summary must count all rows.');
    sr_url_embed_contract_assert((int) ($summary['fresh_count'] ?? 0) === 1, 'URL cache admin summary must count fresh rows.');
    sr_url_embed_contract_assert((string) ($summary['latest_updated_at'] ?? '') !== '', 'URL cache admin summary must expose latest updated time.');

    $GLOBALS['sr_url_embed_contract_resolve_count'] = 0;
    $rendered = sr_url_embed_render_body_html($pdo, $body, 'fixture', 'doc', 10);
    $stylesheets = sr_url_embed_stylesheets_for_body($pdo, $body, 'fixture', 'doc', 10);
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_resolve_count'] ?? 0) === 0, 'Fresh URL cache hit must render without re-running the resolver.');
    sr_url_embed_contract_assert($stylesheets === ['/modules/fixture/assets/embed.css'], 'Fresh internal URL cache hit must expose the target module embed stylesheet.');
    sr_url_embed_contract_assert(str_contains($rendered, 'fixture-embed-summary'), 'URL render must use target module renderer HTML.');
    sr_url_embed_contract_assert(str_contains($rendered, '공개 항목'), 'URL render must include target label.');
    sr_url_embed_contract_assert(str_contains($rendered, '/fixture/image.webp'), 'URL render must include public image snapshot.');
    sr_url_embed_contract_assert(str_contains($rendered, '문장 안의'), 'Inline URL link paragraph must remain in body.');
    sr_url_embed_contract_assert(str_contains($rendered, '/fixture/2'), 'Inline URL link must remain a link in standalone-only scope.');

    $selfBody = '<p>/fixture/1</p>';
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'item', 1, 'body', $selfBody, 7);
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_module = "fixture" AND owner_type = "item" AND owner_id = 1') === 0, 'Self URL embed must not create a derived cache row.');
    $selfRendered = sr_url_embed_render_body_html($pdo, $selfBody, 'fixture', 'item', 1);
    sr_url_embed_contract_assert(sr_url_embed_stylesheets_for_body($pdo, $selfBody, 'fixture', 'item', 1) === [], 'Self URL embed must not expose an embed stylesheet.');
    sr_url_embed_contract_assert(!str_contains($selfRendered, 'fixture-embed-summary'), 'Self URL embed must not render an embed card.');
    sr_url_embed_contract_assert(str_contains($selfRendered, '/fixture/1'), 'Self URL embed must leave the original URL in place.');

    $fragmentBody = '<p>/fixture/1</p>';
    $GLOBALS['sr_url_embed_contract_fragment_cache_public'] = true;
    $GLOBALS['sr_url_embed_contract_render_count'] = 0;
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 17, 'body', $fragmentBody, 7);
    sr_url_embed_contract_assert(str_contains(sr_url_embed_render_body_html($pdo, $fragmentBody, 'fixture', 'doc', 17), 'fixture-embed-summary'), 'Public fragment cache fixture must render on first miss.');
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_render_count'] ?? 0) === 1, 'Public fragment cache first miss must call the target renderer.');
    $GLOBALS['sr_url_embed_contract_render_count'] = 0;
    sr_url_embed_contract_assert(str_contains(sr_url_embed_render_body_html($pdo, $fragmentBody, 'fixture', 'doc', 17), 'fixture-embed-summary'), 'Public fragment cache hit must render from the file cache.');
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_render_count'] ?? 0) === 0, 'Public fragment cache hit must skip the target renderer.');
    sr_url_embed_mark_target_url_cache_stale($pdo, 'fixture', 'item', 1);
    $pdo->exec("UPDATE sr_fixture_embed_targets SET status = 'private', updated_at = '2026-06-11 12:03:00' WHERE id = 1");
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $fragmentBody, 'fixture', 'doc', 17), 'fixture-embed-summary'), 'Stale target URL cache must prevent serving an old public fragment after target becomes private.');
    unset($GLOBALS['sr_url_embed_contract_fragment_cache_public']);
    $pdo->exec("UPDATE sr_fixture_embed_targets SET status = 'active', updated_at = '2026-06-11 12:04:00' WHERE id = 1");
    sr_url_embed_mark_target_url_cache_stale($pdo, 'fixture', 'item', 1);

    $pdo->exec("UPDATE sr_fixture_embed_targets SET status = 'private', updated_at = '2026-06-11 12:05:00' WHERE id = 1");
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $body, 'fixture', 'doc', 10), 'fixture-embed-summary'), 'Target renderer must live-gate a fresh cache hit after target becomes private.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_id = 10 LIMIT 1') === 'broken', 'Target renderer cache signal must refresh cache status after target becomes private.');
    $pdo->exec("UPDATE sr_fixture_embed_targets SET status = 'active', updated_at = '2026-06-11 12:10:00' WHERE id = 1");
    sr_url_embed_contract_assert(str_contains(sr_url_embed_render_body_html($pdo, $body, 'fixture', 'doc', 10), 'fixture-embed-summary'), 'Broken cache row must recover through resolver when target becomes public again.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_id = 10 LIMIT 1') === 'fresh', 'Recovered target must refresh cache status to fresh.');
    $pdo->exec("UPDATE sr_fixture_embed_targets SET updated_at = '2026-06-11 12:20:00' WHERE id = 1");
    $GLOBALS['sr_url_embed_contract_render_count'] = 0;
    sr_url_embed_contract_assert(str_contains(sr_url_embed_render_body_html($pdo, $body, 'fixture', 'doc', 10), 'fixture-embed-summary'), 'Fresh cache version mismatch must still render after refresh.');
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_render_count'] ?? 0) === 2, 'Fresh cache version mismatch must rerender after resolver refresh.');
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT target_cache_version FROM sr_url_embed_cache WHERE owner_id = 10 LIMIT 1') === '2026-06-11 12:20:00', 'Fresh cache version mismatch must update target cache version.');

    $bareBody = '<p>/fixture/1</p>';
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 12, 'body', $bareBody, 7);
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = 12') === 1, 'Standalone bare relative URL must create one cache row.');
    $GLOBALS['sr_url_embed_contract_resolve_count'] = 0;
    $bareRendered = sr_url_embed_render_body_html($pdo, $bareBody, 'fixture', 'doc', 12);
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_resolve_count'] ?? 0) === 0, 'Bare URL fresh cache hit must render without re-running the resolver.');
    sr_url_embed_contract_assert(str_contains($bareRendered, 'fixture-embed-summary'), 'Standalone bare relative URL must render through target module renderer.');

    $missBody = '<p>/fixture/1</p>';
    $GLOBALS['sr_url_embed_contract_resolve_count'] = 0;
    $missRendered = sr_url_embed_render_body_html($pdo, $missBody, 'fixture', 'doc', 13);
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_resolve_count'] ?? 0) === 1, 'URL cache miss must resolve once during render.');
    sr_url_embed_contract_assert(str_contains($missRendered, 'fixture-embed-summary'), 'URL cache miss must still render when resolver succeeds.');
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = 13') === 1, 'URL cache miss render must write a derived cache row.');
    sr_url_embed_contract_assert(sr_url_embed_contract_scalar($pdo, 'SELECT created_by_account_id FROM sr_url_embed_cache WHERE owner_id = 13 LIMIT 1') === null, 'URL cache miss render must not attribute the derived row to the viewer.');

    $GLOBALS['sr_url_embed_contract_settings'] = ['internal_url_embed_enabled' => false];
    $GLOBALS['sr_url_embed_contract_resolve_count'] = 0;
    $disabledRendered = sr_url_embed_render_body_html($pdo, $body, 'fixture', 'doc', 10);
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_resolve_count'] ?? 0) === 0, 'Disabled internal URL rendering must not re-run resolver on a cache hit.');
    sr_url_embed_contract_assert(!str_contains($disabledRendered, 'fixture-embed-summary'), 'Disabled internal URL rendering must leave the original link in place.');
    unset($GLOBALS['sr_url_embed_contract_settings']);

    $allLinksBody = '<p>문장 안의 <a href="/fixture/1">/fixture/1</a> 링크</p><p>/fixture/1</p>';
    $GLOBALS['sr_url_embed_contract_settings'] = ['embed_scope' => 'all_supported_links'];
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 14, 'body', $allLinksBody, 7);
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = 14') === 1, 'All-supported scope must dedupe inline URL links and standalone bare URLs by canonical URL.');
    $allLinksRendered = sr_url_embed_render_body_html($pdo, $allLinksBody, 'fixture', 'doc', 14);
    sr_url_embed_contract_assert(substr_count($allLinksRendered, 'fixture-embed-summary') === 2, 'All-supported scope must render URL-label links and standalone bare URLs consistently.');
    unset($GLOBALS['sr_url_embed_contract_settings']);

    $dedupeBody = '<p><a href="/fixture/1">/fixture/1</a></p><p><a href="/fixture/1?tracking=1">/fixture/1?tracking=1</a></p>';
    $GLOBALS['sr_url_embed_contract_settings'] = ['embed_scope' => 'all_supported_links'];
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 15, 'body', $dedupeBody, 7);
    sr_url_embed_contract_assert((int) sr_url_embed_contract_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = 15') === 1, 'Request cache dedupe fixture must store one canonical row for multiple source URLs.');
    $GLOBALS['sr_url_embed_contract_render_count'] = 0;
    $dedupeRendered = sr_url_embed_render_body_html($pdo, $dedupeBody, 'fixture', 'doc', 15);
    sr_url_embed_contract_assert(substr_count($dedupeRendered, 'fixture-embed-summary') === 2, 'Multiple source URLs for one canonical URL must still replace each occurrence.');
    sr_url_embed_contract_assert((int) ($GLOBALS['sr_url_embed_contract_render_count'] ?? 0) === 1, 'Multiple source URLs for one canonical cache row must render once per request.');
    unset($GLOBALS['sr_url_embed_contract_settings']);

    sr_url_embed_contract_assert(sr_url_embed_target_label('quiz', 'quiz_set', '3') === '퀴즈 #3', 'Quiz embed target label must use Korean target type label.');
    sr_url_embed_contract_assert(sr_url_embed_target_label('survey', 'survey_form', '4') === '설문 #4', 'Survey embed target label must use Korean target type label.');

    $privateBody = '<p><a href="/fixture/2">/fixture/2</a></p>';
    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 11, 'body', $privateBody, 7);
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_id = 11 LIMIT 1') === 'broken', 'Private target cache must not be stored as fresh.');
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $privateBody, 'fixture', 'doc', 11), '비공개 항목'), 'Private target render must fall back to original link.');

    sr_url_embed_sync_body_url_cache($pdo, 'fixture', 'doc', 10, 'body', '<p>removed</p>', 7);
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_id = 10 LIMIT 1') === 'stale', 'Removed owner URL must become stale.');

    $youtubeBody = '<p>https://youtu.be/dQw4w9WgXcQ</p>';
    sr_url_embed_contract_assert(sr_url_embed_stylesheets_for_body($pdo, $youtubeBody, 'fixture', 'doc', 18) === ['/assets/url-embed.css'], 'External URL embeds must expose the common external embed stylesheet.');
    $youtubeRendered = sr_url_embed_render_body_html($pdo, $youtubeBody, 'fixture', 'doc', 18);
    sr_url_embed_contract_assert(str_contains($youtubeRendered, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'YouTube standalone URL must render as a safe iframe.');
    $xRendered = sr_url_embed_render_body_html($pdo, '<p>https://x.com/example/status/1234567890</p>', 'fixture', 'doc', 19);
    sr_url_embed_contract_assert(str_contains($xRendered, 'data-sr-url-embed="x"'), 'X standalone URL must render as an external URL card.');
    $instagramRendered = sr_url_embed_render_body_html($pdo, '<p>https://www.instagram.com/p/ABC123def/</p>', 'fixture', 'doc', 20);
    sr_url_embed_contract_assert(str_contains($instagramRendered, 'data-sr-url-embed="instagram"'), 'Instagram standalone URL must render as an external URL card.');

    $providerBody = '<p>/fixture/1</p>';
    sr_url_embed_sync_body_url_cache($pdo, 'owner', 'doc', 16, 'body', $providerBody, 7);
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_module = \'owner\' AND owner_id = 16 LIMIT 1') === 'fresh', 'Provider disable fixture must start from a fresh owner cache row.');
    sr_url_embed_contract_assert(str_contains(sr_url_embed_render_body_html($pdo, $providerBody, 'owner', 'doc', 16), 'fixture-embed-summary'), 'Provider disable fixture must render before target module is disabled.');

    $GLOBALS['sr_url_embed_contract_module_settings'] = ['fixture' => ['embed_enabled' => false]];
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $providerBody, 'owner', 'doc', 16), 'fixture-embed-summary'), 'Disabled target module embed setting must leave cached target URLs unrendered.');
    unset($GLOBALS['sr_url_embed_contract_module_settings']);

    $GLOBALS['sr_url_embed_contract_module_settings'] = ['owner' => ['embed_enabled' => false]];
    sr_url_embed_sync_body_url_cache($pdo, 'owner', 'doc', 16, 'body', $providerBody, 7);
    sr_url_embed_contract_assert((string) sr_url_embed_contract_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_module = \'owner\' AND owner_id = 16 LIMIT 1') === 'stale', 'Disabled owner module embed setting must stale existing owner cache rows.');
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $providerBody, 'owner', 'doc', 16), 'fixture-embed-summary'), 'Disabled owner module embed setting must leave body URLs unrendered.');
    unset($GLOBALS['sr_url_embed_contract_module_settings']);

    $GLOBALS['sr_url_embed_contract_module_metadata'] = ['fixture' => ['settings' => ['embed_enabled' => false]]];
    sr_url_embed_contract_assert(!str_contains(sr_url_embed_render_body_html($pdo, $providerBody, 'owner', 'doc', 16), 'fixture-embed-summary'), 'Disabled target module metadata default must leave cached target URLs unrendered when no saved setting exists.');
    unset($GLOBALS['sr_url_embed_contract_module_metadata']);
}

foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    $contractPath = 'modules/' . $moduleKey . '/url-embed-targets.php';
    $modulePath = 'modules/' . $moduleKey . '/module.php';
    $adminMenuPath = 'modules/' . $moduleKey . '/admin-menu.php';
    $pathsPath = 'modules/' . $moduleKey . '/paths.php';
    if (!is_file($contractPath)) {
        sr_url_embed_contract_error('URL embed contract is missing: ' . $contractPath);
        continue;
    }

    sr_url_embed_contract_contains($modulePath, "'url-embed-targets.php'");
    foreach (["'target_module' => '" . $moduleKey . "'", "'resolve_url'", "'render_embed'", "'canonical_url'", "'target_state'", "'cache_status'", "'image_snapshot_policy'", "'embed_stylesheet' => '/modules/" . $moduleKey . "/assets/embed.css'", "'fragment_cache_public' => true", "'fragment_cache_schema' => 'custom_tag_v1'"] as $needle) {
        sr_url_embed_contract_contains($contractPath, $needle);
    }
    $adminPath = $moduleKey === 'survey' ? '/admin/surveys/embed-cache' : '/admin/' . $moduleKey . '/embed-cache';
    sr_url_embed_contract_contains($adminMenuPath, "'" . $adminPath . "'");
    sr_url_embed_contract_contains($pathsPath, "'GET " . $adminPath . "'");
    sr_url_embed_contract_contains($pathsPath, "'POST " . $adminPath . "'");
    sr_url_embed_contract_contains('modules/' . $moduleKey . '/actions/admin-embed-cache.php', "include SR_ROOT . '/core/actions/admin-url-embed-fragment-cache.php'");
}

sr_url_embed_contract_contains('modules/coupon/url-embed-targets.php', "'embed_stylesheet' => '/modules/coupon/assets/embed.css'");
sr_url_embed_contract_contains('modules/coupon/admin-menu.php', "'/admin/coupons/embed-cache'");
sr_url_embed_contract_contains('modules/coupon/paths.php', "'GET /admin/coupons/embed-cache'");
sr_url_embed_contract_contains('modules/coupon/paths.php', "'POST /admin/coupons/embed-cache'");
sr_url_embed_contract_contains('modules/coupon/actions/admin-embed-cache.php', "include SR_ROOT . '/core/actions/admin-url-embed-fragment-cache.php'");
sr_url_embed_contract_contains('core/actions/admin-url-embed-fragment-cache.php', "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$urlEmbedCacheAdminPath, 'delete')");
sr_url_embed_contract_contains('core/actions/admin-url-embed-fragment-cache.php', 'sr_require_csrf()');
sr_url_embed_contract_contains('core/actions/admin-url-embed-fragment-cache.php', 'admin.url_embed_fragment_cache.cleaned');
sr_url_embed_contract_contains('core/helpers/url-embed.php', 'function sr_url_embed_fragment_cache_admin_scan');
sr_url_embed_contract_contains('core/helpers/url-embed.php', 'function sr_url_embed_fragment_cache_admin_cleanup');

sr_url_embed_contract_runtime_fixture();

sr_url_embed_contract_contains('core/helpers/url-embed.php', 'function sr_url_embed_admin_cache_summary');
sr_url_embed_contract_contains('core/helpers/url-embed.php', 'function sr_url_embed_resolve_external_url');
sr_url_embed_contract_contains('core/helpers/url-embed.php', 'function sr_url_embed_render_external_url');
sr_url_embed_contract_contains('modules/community/helpers/posts-writing.php', 'CAST(sr_url_embed_cache.target_id AS UNSIGNED)');

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'URL embed contracts ok' . PHP_EOL;
