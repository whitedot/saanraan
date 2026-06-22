#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/modules/logo_manager/helpers.php';
require_once $root . '/modules/site_menu/helpers.php';
require_once $root . '/modules/seo/helpers.php';

$errors = [];

function sr_output_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_output_helper_php_string(string $value): string
{
    return var_export($value, true);
}

function sr_output_helper_run_fixture(string $name, string $body): array
{
    $root = dirname(__DIR__, 2);
    $script = tempnam(sys_get_temp_dir(), 'sr-output-helper-');
    if (!is_string($script)) {
        sr_output_helper_assert(false, 'Cannot create temporary fixture script: ' . $name);
        return ['exit_code' => 1, 'stdout' => '', 'stderr' => '', 'status' => null, 'contract' => null];
    }

    $source = "<?php\n"
        . "declare(strict_types=1);\n"
        . "define('SR_ROOT', " . sr_output_helper_php_string($root) . ");\n"
        . "chdir(SR_ROOT);\n"
        . "require_once SR_ROOT . '/core/helpers.php';\n"
        . "register_shutdown_function(static function (): void {\n"
        . "    echo \"\\n__SR_STATUS__\" . json_encode(http_response_code(), JSON_UNESCAPED_SLASHES) . \"__END_STATUS__\\n\";\n"
        . "    echo \"__SR_CONTRACT__\" . json_encode(\$GLOBALS['sr_request_contract'] ?? null, JSON_UNESCAPED_SLASHES) . \"__END_CONTRACT__\\n\";\n"
        . "});\n"
        . $body
        . "\n";

    if (file_put_contents($script, $source) === false) {
        unlink($script);
        sr_output_helper_assert(false, 'Cannot write temporary fixture script: ' . $name);
        return ['exit_code' => 1, 'stdout' => '', 'stderr' => '', 'status' => null, 'contract' => null];
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, $script], $descriptorSpec, $pipes, $root);
    if (!is_resource($process)) {
        unlink($script);
        sr_output_helper_assert(false, 'Cannot start fixture process: ' . $name);
        return ['exit_code' => 1, 'stdout' => '', 'stderr' => '', 'status' => null, 'contract' => null];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    unlink($script);

    $stdout = is_string($stdout) ? $stdout : '';
    $stderr = is_string($stderr) ? $stderr : '';
    $status = null;
    if (preg_match('/__SR_STATUS__(.*?)__END_STATUS__/s', $stdout, $matches) === 1) {
        $decodedStatus = json_decode($matches[1], true);
        $status = is_int($decodedStatus) ? $decodedStatus : null;
    }
    $contract = null;
    if (preg_match('/__SR_CONTRACT__(.*?)__END_CONTRACT__/s', $stdout, $matches) === 1) {
        $decodedContract = json_decode($matches[1], true);
        $contract = is_array($decodedContract) ? $decodedContract : null;
    }

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'status' => $status,
        'contract' => $contract,
    ];
}

class SrOutputHelperCheckPdo extends PDO
{
    public int $moduleKeyQueryCount = 0;

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'SELECT module_key FROM sr_modules')) {
            $this->moduleKeyQueryCount++;
        }

        if ($fetchMode === null) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

sr_output_helper_assert(
    sr_is_safe_relative_url('/account'),
    'Normal absolute relative path should be allowed.'
);
sr_output_helper_assert(
    sr_is_safe_relative_url('/login?next=%2Fadmin'),
    'Relative path with query should be allowed.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url('//example.com'),
    'Protocol-relative URL should be rejected.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url('/\\example.com'),
    'Backslash URL should be rejected.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url("/account\nSet-Cookie: bad=1"),
    'Control characters should be rejected.'
);
sr_output_helper_assert(
    sr_url('/\\example.com') === '/',
    'Unsafe relative URL should fall back to the site root.'
);
$_SERVER['SCRIPT_NAME'] = '/saanraan/index.php';
sr_output_helper_assert(
    sr_url('/') === '/saanraan/',
    'Root URL should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('/') === '/saanraan/',
    'Site menu root item should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('/login') === '/saanraan/login',
    'Site menu internal item should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('https://example.com/') === 'https://example.com/',
    'Site menu external item should keep the original URL.'
);
$_SERVER['SCRIPT_NAME'] = '/index.php';
sr_output_helper_assert(
    sr_absolute_url(['base_url' => 'https://example.com/base?bad=1'], '/login') === '/login',
    'Absolute URL should reject site base URLs with query strings.'
);
sr_output_helper_assert(
    sr_absolute_url(['base_url' => 'https://example.com/base'], '/\\evil.test') === 'https://example.com/base/',
    'Absolute URL should replace unsafe paths with the site root path.'
);
$seoTags = sr_seo_tags(
    [
        'canonical' => '/community',
        'og' => [
            'image' => '/assets/card.png',
        ],
    ],
    ['base_url' => 'https://example.com/base']
);
sr_output_helper_assert(
    strpos($seoTags, '<link rel="canonical" href="https://example.com/base/community">') !== false,
    'Relative canonical URLs should use the configured site base URL.'
);
sr_output_helper_assert(
    strpos($seoTags, '<meta property="og:url" content="https://example.com/base/community">') !== false,
    'Open Graph URLs should use the normalized canonical URL.'
);
sr_output_helper_assert(
    strpos($seoTags, '<meta property="og:image" content="https://example.com/base/assets/card.png">') !== false,
    'Relative Open Graph image URLs should use the configured site base URL.'
);
sr_output_helper_assert(
    sr_load_translations('ko', '0module') === [],
    'Translation loader should reject module keys outside the shared module key policy.'
);
sr_output_helper_assert(
    sr_download_content_type("application/json; charset=UTF-8\r\nX-Bad: 1") === 'application/octet-stream',
    'Download content type should reject header control characters.'
);
sr_output_helper_assert(
    sr_download_content_type('application/json; charset=UTF-8') === 'application/json; charset=UTF-8',
    'Download content type should allow normal MIME values with charset.'
);
sr_output_helper_assert(
    sr_download_filename("../report\r\nInjected: yes.json") === 'report-Injected-yes.json',
    'Download filename should remove path and header separator characters.'
);
sr_output_helper_assert(
    sr_download_filename("\r\n") === 'download.bin',
    'Download filename should fall back when no safe characters remain.'
);
sr_output_helper_assert(
    sr_download_content_disposition("../report\r\nInjected: yes.json") === 'attachment; filename="report-Injected-yes.json"',
    'Download content disposition should sanitize attachment filenames.'
);
sr_output_helper_assert(
    sr_download_content_disposition('image.png', 'inline') === 'inline; filename="image.png"',
    'Download content disposition should allow inline image delivery.'
);
sr_output_helper_assert(
    sr_download_content_disposition('file.bin', "inline\r\nX-Bad: 1") === 'attachment; filename="file.bin"',
    'Download content disposition should reject injected disposition values.'
);
sr_output_helper_assert(
    sr_download_cache_control('private, no-store, no-cache, must-revalidate') === 'private, no-store, no-cache, must-revalidate',
    'Download cache-control should allow private no-store policy.'
);
sr_output_helper_assert(
    sr_download_cache_control("private\r\nSet-Cookie: bad=1") === 'no-store, no-cache, must-revalidate',
    'Download cache-control should reject injected cache headers.'
);
sr_output_helper_assert(
    sr_download_cache_control('public, max-age=31536000, immutable') === 'public, max-age=31536000, immutable',
    'File response cache-control should allow immutable public image cache policy.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Cache-Control: no-store'),
    'JSON response helper should allow explicit cache-control response headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Content-Disposition: attachment; filename="export.json"'),
    'JSON response helper should allow explicit content-disposition response headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox"),
    'File response helper should allow explicit CSP response headers with a constrained value grammar.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Content-Type: text/plain; charset=UTF-8'),
    'Response header allowlist should allow normalized content-type headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Content-Length: 120'),
    'Response header allowlist should allow numeric content-length headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('ETag: "' . str_repeat('a', 64) . '"'),
    'File response helper should allow quoted hash ETag headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Last-Modified: Wed, 21 Oct 2015 07:28:00 GMT'),
    'File response helper should allow IMF-fixdate last-modified headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('X-Content-Type-Options: nosniff'),
    'Response header allowlist should allow nosniff response headers.'
);
sr_output_helper_assert(
    sr_response_header_is_allowed('Pragma: no-cache'),
    'Response header allowlist should allow no-cache pragma headers.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Location: /admin'),
    'JSON response helper should reject redirect headers.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Cache-Control: public, max-age=300()'),
    'Response header allowlist should reject malformed cache-control values.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Content-Disposition: attachment; filename=export.json'),
    'Response header allowlist should reject content-disposition values outside the download helper shape.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Content-Length: abc'),
    'Response header allowlist should reject non-numeric content-length values.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Content-Type: text/plain; boundary=abc'),
    'Response header allowlist should reject content-type values outside the normalized helper shape.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('ETag: abc'),
    'Response header allowlist should reject unquoted ETag values.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Last-Modified: next Thursday'),
    'Response header allowlist should reject non-HTTP-date last-modified values.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('X-Content-Type-Options: sniff'),
    'Response header allowlist should reject x-content-type-options values other than nosniff.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed('Pragma: cache'),
    'Response header allowlist should reject pragma values other than no-cache.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed("X-Test: ok\r\nSet-Cookie: bad=1"),
    'JSON response helper should reject non-allowlisted or injected response headers.'
);
sr_output_helper_assert(
    !sr_response_header_is_allowed("Cache-Control: no-store\r\nSet-Cookie: bad=1"),
    'JSON response helper should reject injected headers even when the first header name is allowlisted.'
);
sr_output_helper_assert(
    sr_http_date(0) === 'Thu, 01 Jan 1970 00:00:00 GMT',
    'HTTP date helper should emit GMT IMF-fixdate strings.'
);
sr_output_helper_assert(
    sr_etag_matches('W/"' . str_repeat('a', 64) . '", "' . str_repeat('b', 64) . '"', '"' . str_repeat('a', 64) . '"'),
    'ETag matcher should use weak comparison for conditional GET validators.'
);
sr_output_helper_assert(
    !sr_etag_matches('"' . str_repeat('b', 64) . '"', '"' . str_repeat('a', 64) . '"'),
    'ETag matcher should reject non-matching validators.'
);

$publicExternalRedirect = sr_output_helper_run_fixture('public external redirect', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-external-redirect.php');
sr_redirect_external('https://93.184.216.34/public-object');
PHP);
sr_output_helper_assert($publicExternalRedirect['exit_code'] === 0, 'Public external redirect fixture should exit cleanly.');
sr_output_helper_assert(
    is_array($publicExternalRedirect['contract'] ?? null)
        && (($publicExternalRedirect['contract']['exit_reason'] ?? null) === 'completed')
        && (($publicExternalRedirect['contract']['resolved_stage'] ?? null) === 'before_response_end'),
    'Public external redirect should complete the request contract.'
);

$privateExternalRedirect = sr_output_helper_run_fixture('private external redirect blocked', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-external-redirect.php');
sr_redirect_external('https://127.0.0.1/internal');
PHP);
sr_output_helper_assert($privateExternalRedirect['exit_code'] === 0, 'Private external redirect rejection should render a controlled error response.');
sr_output_helper_assert($privateExternalRedirect['status'] === 500, 'Private external redirect should be rejected with a 500 response.');
sr_output_helper_assert(
    is_array($privateExternalRedirect['contract'] ?? null)
        && (($privateExternalRedirect['contract']['exit_reason'] ?? null) === 'completed')
        && (($privateExternalRedirect['contract']['resolved_stage'] ?? null) === 'before_response_end'),
    'Private external redirect rejection should still finish through the response contract.'
);

$trustedExternalRedirect = sr_output_helper_run_fixture('trusted external redirect', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-trusted-redirect.php');
sr_redirect_trusted_external('http://127.0.0.1/storage-object');
PHP);
sr_output_helper_assert($trustedExternalRedirect['exit_code'] === 0, 'Trusted external redirect fixture should allow server-generated local storage URLs.');
sr_output_helper_assert(
    is_array($trustedExternalRedirect['contract'] ?? null)
        && (($trustedExternalRedirect['contract']['exit_reason'] ?? null) === 'completed')
        && (($trustedExternalRedirect['contract']['resolved_stage'] ?? null) === 'before_response_end'),
    'Trusted external redirect should complete the request contract.'
);

$badTrustedExternalRedirect = sr_output_helper_run_fixture('trusted external redirect rejects non-http', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-trusted-redirect.php');
sr_redirect_trusted_external('javascript:alert(1)');
PHP);
sr_output_helper_assert($badTrustedExternalRedirect['exit_code'] === 0, 'Trusted external redirect invalid URL rejection should render a controlled error response.');
sr_output_helper_assert($badTrustedExternalRedirect['status'] === 500, 'Trusted external redirect should reject non-HTTP URLs.');

$encodedScriptValue = sr_js_json_encode([
    'tag' => '</script><script>alert(1)</script>',
    'quote' => '"\'&',
    'bad_utf8' => "\xC3\x28",
]);
sr_output_helper_assert(
    !str_contains($encodedScriptValue, '</script>')
        && !str_contains($encodedScriptValue, '<script>')
        && str_contains($encodedScriptValue, '\\u003C/script\\u003E')
        && str_contains($encodedScriptValue, '\\u0022')
        && str_contains($encodedScriptValue, '\\u0027')
        && str_contains($encodedScriptValue, '\\u0026'),
    'JS JSON helper should hex-escape script-breaking characters and substitute invalid UTF-8.'
);
sr_output_helper_assert(
    sr_plain_text_html('See https://example.com/path?x=1, then <b>', true)
        === 'See <a href="https://example.com/path?x=1" rel="nofollow noopener noreferrer">https://example.com/path?x=1</a>, then &lt;b&gt;',
    'Plain text URL linkification should link safe HTTP URLs and escape surrounding text.'
);
sr_output_helper_assert(
    sr_plain_text_html('See https://example.com/path?x=1, then <b>', false)
        === 'See https://example.com/path?x=1, then &lt;b&gt;',
    'Plain text URL linkification should stay disabled by default.'
);
sr_output_helper_assert(
    sr_plain_text_html("Line 1\nhttps://example.com/a.", true)
        === 'Line 1<br>' . "\n" . '<a href="https://example.com/a" rel="nofollow noopener noreferrer">https://example.com/a</a>.',
    'Plain text URL linkification should preserve line breaks and trailing punctuation.'
);
sr_output_helper_assert(
    sr_public_layout_key(['public_layout_key' => 'basic']) === 'common.basic',
    'Legacy public layout key should normalize to the namespaced common layout key.'
);
sr_output_helper_assert(
    sr_public_layout_key(['public_layout_key' => '../basic']) === 'common.basic',
    'Unknown public layout key should fall back to the common layout.'
);
sr_output_helper_assert(
    sr_public_layout_file('common.basic') === $root . '/layouts/public/basic/layout.php'
        && sr_public_layout_file('basic') === $root . '/layouts/public/basic/layout.php',
    'Common public layout and legacy key should resolve to the layouts directory.'
);

foreach ([
    '/modules/admin/helpers/forms.php',
    '/modules/asset_exchange/helpers.php',
    '/modules/content/helpers.php',
    '/modules/coupon/helpers.php',
    '/modules/point/helpers.php',
] as $timeHelperPath) {
    $timeHelper = file_get_contents($root . $timeHelperPath);
    sr_output_helper_assert(is_string($timeHelper), 'Time helper should be readable: ' . $timeHelperPath);
    if (is_string($timeHelper) && str_contains($timeHelper, 'data-sr-time-tooltip')) {
        preg_match_all('/<time\b[^>]*>/i', $timeHelper, $timeTagMatches);
        $hasDuplicateTooltip = false;
        foreach ($timeTagMatches[0] ?? [] as $timeTag) {
            if (str_contains($timeTag, 'data-sr-time-tooltip') && preg_match('/\btitle\s*=/', $timeTag) === 1) {
                $hasDuplicateTooltip = true;
                break;
            }
        }
        sr_output_helper_assert(
            !$hasDuplicateTooltip,
            'Custom time tooltip should not also render a native title tooltip: ' . $timeHelperPath
        );
    }
}

$commonUiScript = file_get_contents($root . '/assets/common-ui.js');
sr_output_helper_assert(is_string($commonUiScript), 'Common UI script should be readable.');
if (is_string($commonUiScript)) {
    sr_output_helper_assert(
        str_contains($commonUiScript, "trigger.removeAttribute('title')"),
        'Custom time tooltip script should remove native title attributes to prevent duplicate tooltips.'
    );
}
$layoutPdo = new SrOutputHelperCheckPdo('sqlite::memory:');
$layoutPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$layoutPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$layoutPdo->exec("CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)");
$layoutPdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('community', 'enabled'), ('site_menu', 'enabled'), ('content', 'enabled')");
sr_output_helper_assert(
    sr_public_layout_optional_view_file('community.basic', 'community_home', $layoutPdo) === $root . '/modules/community/layouts/basic/home.php',
    'Optional public layout view lookup should include enabled module layout contracts when PDO is provided.'
);
$GLOBALS['sr_public_layout_options_runtime_cache'] = [];
$layoutPdo->moduleKeyQueryCount = 0;
sr_public_layout_options($layoutPdo);
sr_public_layout_options($layoutPdo);
sr_output_helper_assert(
    $layoutPdo->moduleKeyQueryCount === 1,
    'Public layout options should reuse request runtime cache for repeated enabled module contract lookups.'
);
$layoutPdo->moduleKeyQueryCount = 0;
sr_logo_manager_position_options($layoutPdo);
sr_logo_manager_position_options($layoutPdo);
sr_output_helper_assert(
    $layoutPdo->moduleKeyQueryCount === 1,
    'Logo position options should reuse request runtime cache for repeated contract lookups.'
);
$layoutPdo->moduleKeyQueryCount = 0;
$outputSlotContracts = sr_output_slot_renderer_contracts($layoutPdo, 'core');
sr_output_slot_renderer_contracts($layoutPdo, 'core');
sr_output_helper_assert(
    $layoutPdo->moduleKeyQueryCount === 1,
    'Output slot renderer metadata should reuse request runtime cache for repeated contract lookups.'
);
sr_output_helper_assert(
    $outputSlotContracts !== []
        && isset($outputSlotContracts[0]['module_key'], $outputSlotContracts[0]['contract_name'], $outputSlotContracts[0]['contract_version'], $outputSlotContracts[0]['contract_file'])
        && $outputSlotContracts[0]['contract_name'] === 'output-slots.php'
        && $outputSlotContracts[0]['contract_version'] === SR_MODULE_CONTRACT_VERSION
        && !isset($outputSlotContracts[0]['html'], $outputSlotContracts[0]['callable']),
    'Output slot renderer cache should store serializable contract metadata only.'
);
$siteMetaPdo = new PDO('sqlite::memory:');
$siteMetaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$siteMetaPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$siteMetaPdo->exec("CREATE TABLE sr_site_settings (setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL)");
$siteMetaPdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.title_suffix', 'Site Suffix', 'string')");
$siteMetaPdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.meta_description', 'Default description', 'string')");
$siteMetaPdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.og_image', '/uploads/og.png', 'string')");
$appliedSeo = sr_site_apply_public_meta_defaults($siteMetaPdo, [
    'title' => 'Page',
    'og' => ['type' => 'article', 'description' => ''],
]);
sr_output_helper_assert(
    ($appliedSeo['title'] ?? '') === 'Page - Site Suffix'
        && (($appliedSeo['og']['title'] ?? '') === 'Page - Site Suffix')
        && ($appliedSeo['description'] ?? '') === 'Default description'
        && (($appliedSeo['og']['description'] ?? '') === 'Default description')
        && (($appliedSeo['og']['image'] ?? '') === '/uploads/og.png'),
    'Site public meta defaults should apply title suffix, OG title, default description, OG description, and default OG image.'
);
$appliedSeoAgain = sr_site_apply_public_meta_defaults($siteMetaPdo, $appliedSeo);
sr_output_helper_assert(
    ($appliedSeoAgain['title'] ?? '') === 'Page - Site Suffix',
    'Site title suffix should not be appended more than once.'
);
$explicitSeo = sr_site_apply_public_meta_defaults($siteMetaPdo, [
    'title' => 'Page',
    'description' => 'Custom description',
    'og' => ['image' => '/custom.png'],
]);
sr_output_helper_assert(
    ($explicitSeo['description'] ?? '') === 'Custom description'
        && (($explicitSeo['og']['description'] ?? '') === 'Custom description')
        && (($explicitSeo['og']['image'] ?? '') === '/custom.png'),
    'Site public meta defaults should preserve explicit description for SEO and OG while preserving explicit OG image values.'
);
sr_output_helper_assert(
    sr_color_scheme(['ui_color_scheme' => 'dark']) === 'dark',
    'Known color scheme should be accepted.'
);
sr_output_helper_assert(
    sr_color_scheme(['ui_color_scheme' => 'unknown']) === 'light',
    'Unknown color scheme should fall back to light.'
);
$_POST = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 256),
    'array_value' => ['abc'],
];
sr_output_helper_assert(
    sr_post_string_without_truncation('short_value', 255) === 'abc',
    'Untruncated POST helper should return values within the limit.'
);
sr_output_helper_assert(
    sr_post_string_without_truncation('long_value', 255) === null,
    'Untruncated POST helper should reject overlong values.'
);
sr_output_helper_assert(
    sr_post_string_without_truncation('array_value', 255) === null,
    'Untruncated POST helper should reject array values.'
);
$_GET = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 65),
    'array_value' => ['abc'],
];
sr_output_helper_assert(
    sr_get_string_without_truncation('short_value', 64) === 'abc',
    'Untruncated GET helper should return values within the limit.'
);
sr_output_helper_assert(
    sr_get_string_without_truncation('long_value', 64) === null,
    'Untruncated GET helper should reject overlong values.'
);
sr_output_helper_assert(
    sr_get_string_without_truncation('array_value', 64) === null,
    'Untruncated GET helper should reject array values.'
);

$outputHelper = file_get_contents($root . '/core/helpers/output.php');
if (is_string($outputHelper)) {
    sr_output_helper_assert(
        strpos($outputHelper, 'function sr_json_response(mixed $payload') !== false
            && strpos($outputHelper, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false
            && strpos($outputHelper, "header('Content-Type: application/json; charset=utf-8')") !== false
            && strpos($outputHelper, 'sr_response_header_is_allowed($header)') !== false
            && strpos($outputHelper, 'sr_finish_response();') !== false,
        'JSON response helper should centralize content type, UTF-8 substitution, header allowlist, and response termination.'
    );
    sr_output_helper_assert(
        strpos($outputHelper, 'function sr_redirect_external(string $url): void') !== false
            && strpos($outputHelper, 'if (!sr_is_public_http_url($url))') !== false
            && strpos($outputHelper, 'function sr_redirect_trusted_external(string $url): void') !== false
            && strpos($outputHelper, 'if (!sr_is_http_url($url))') !== false,
        'External redirect helpers should split public user URLs from trusted server-generated URLs.'
    );
    sr_output_helper_assert(
        strpos($outputHelper, "function sr_send_download_headers(string \$contentType, string \$filename, string \$disposition = 'attachment', ?int \$contentLength = null") !== false
            && strpos($outputHelper, 'sr_download_content_disposition($filename, $disposition)') !== false
            && strpos($outputHelper, 'header(\'Content-Length: \' . (string) $contentLength)') !== false
            && strpos($outputHelper, 'function sr_download_content_disposition(string $filename, string $disposition = \'attachment\'): string') !== false
            && strpos($outputHelper, 'function sr_download_cache_control(string $cacheControl): string') !== false,
        'Download header helper should centralize content disposition, optional length, and cache-control sanitization.'
    );
    sr_output_helper_assert(
        strpos($outputHelper, "function sr_send_file_headers(string \$contentType, ?int \$contentLength = null, string \$cacheControl = 'private, max-age=300', array \$headers = []): void") !== false
            && strpos($outputHelper, "header('Content-Type: ' . sr_download_content_type(\$contentType))") !== false
            && strpos($outputHelper, "header('Content-Length: ' . (string) \$contentLength)") !== false
            && strpos($outputHelper, "header('X-Content-Type-Options: nosniff')") !== false
            && strpos($outputHelper, "header('Cache-Control: ' . sr_download_cache_control(\$cacheControl))") !== false
            && strpos($outputHelper, 'sr_response_header_is_allowed($header)') !== false,
        'File response helper should centralize content type, optional length, nosniff, cache-control, and extra header allowlist.'
    );
    sr_output_helper_assert(
        strpos($outputHelper, 'function sr_js_json_encode(mixed $value): string') !== false
            && strpos($outputHelper, 'JSON_HEX_TAG') !== false
            && strpos($outputHelper, 'JSON_HEX_AMP') !== false
            && strpos($outputHelper, 'JSON_HEX_APOS') !== false
            && strpos($outputHelper, 'JSON_HEX_QUOT') !== false
            && strpos($outputHelper, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false,
        'JS JSON helper should expose a script-safe JSON encoding policy.'
    );
    sr_output_helper_assert(
        strpos($outputHelper, 'sr_load_module_contract_file($rendererModuleKey, $file)') !== false
            && strpos($outputHelper, 'catch (Throwable $exception)') !== false
            && strpos($outputHelper, "sr_log_exception(\$exception, 'module_output_slot_failed_' . \$rendererModuleKey)") !== false,
        'Output slot rendering should fail closed for broken module contract files and renderers.'
    );
}

foreach ([
    '/core/views',
    '/modules',
] as $scriptContextDir) {
    $directory = $root . $scriptContextDir;
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $path = $fileInfo->getPathname();
        $normalizedPath = str_replace('\\', '/', $path);
        if (
            !str_contains($normalizedPath, '/views/')
            && !str_ends_with($normalizedPath, '/core/views/install.php')
            && !str_ends_with($normalizedPath, '/modules/ckeditor/helpers.php')
        ) {
            continue;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            sr_output_helper_assert(false, 'Script context PHP file should be readable: ' . $path);
            continue;
        }

        if (preg_match('/(?<!sr_js_)json_encode\s*\(/', $content) === 1) {
            sr_output_helper_assert(false, 'Script context JSON should use sr_js_json_encode(): ' . $path);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "output helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "output helper checks completed.\n";
