#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/popup_layer/helpers/body-files.php';

$errors = [];

function sr_ckeditor_assets_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_ckeditor_assets_read(string $file): string
{
    if (!is_file($file)) {
        sr_ckeditor_assets_error('CKEditor asset file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_ckeditor_assets_error('CKEditor asset file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_ckeditor_assets_require_markers(string $file, array $markers): void
{
    $contents = sr_ckeditor_assets_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_ckeditor_assets_error('CKEditor asset marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_ckeditor_assets_forbid_markers(string $file, array $markers): void
{
    $contents = sr_ckeditor_assets_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (str_contains($contents, $marker)) {
            sr_ckeditor_assets_error('CKEditor asset forbidden marker found in ' . $file . ': ' . $marker);
        }
    }
}

function sr_ckeditor_assets_node_syntax_check(string $file): void
{
    if (!function_exists('exec')) {
        return;
    }

    $nodePathOutput = [];
    exec('command -v node 2>/dev/null', $nodePathOutput, $nodePathStatus);
    if ($nodePathStatus !== 0 || $nodePathOutput === []) {
        return;
    }

    $nodePath = trim((string) $nodePathOutput[0]);
    if ($nodePath === '') {
        return;
    }

    $output = [];
    $command = escapeshellarg($nodePath) . ' --check ' . escapeshellarg($file) . ' 2>&1';
    exec($command, $output, $status);
    if ($status !== 0) {
        sr_ckeditor_assets_error('CKEditor loader JavaScript syntax check failed: ' . implode("\n", $output));
    }
}

function sr_ckeditor_assets_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_ckeditor_assets_error($message);
    }
}

function sr_ckeditor_assets_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..') {
            continue;
        }
        sr_ckeditor_assets_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function sr_ckeditor_assets_check_popup_layer_tmp_cleanup_fixture(): void
{
    $token = bin2hex(random_bytes(16));
    $freshToken = bin2hex(random_bytes(16));
    $base = SR_ROOT . '/storage/popup_layer/body/tmp';
    $oldDir = $base . '/' . $token;
    $freshDir = $base . '/' . $freshToken;
    $oldFile = $oldDir . '/old.png';
    $freshFile = $freshDir . '/fresh.png';
    $invalidDir = $base . '/invalid-token';
    $invalidFile = $invalidDir . '/ignored.png';

    try {
        foreach ([$oldDir, $freshDir, $invalidDir] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                sr_ckeditor_assets_error('CKEditor popup layer cleanup fixture cannot create directory: ' . $dir);
                return;
            }
        }

        file_put_contents($oldFile, 'old');
        file_put_contents($freshFile, 'fresh');
        file_put_contents($invalidFile, 'ignored');
        $expiredTime = time() - sr_popup_layer_body_file_temporary_ttl_seconds() - 60;
        @touch($oldFile, $expiredTime);
        @touch($oldDir, $expiredTime);

        $result = sr_popup_layer_cleanup_expired_body_files(new PDO('sqlite::memory:'), 10);
        sr_ckeditor_assets_assert((int) ($result['deleted'] ?? -1) === 1, 'Popup layer expired body file cleanup fixture should delete exactly one old file.');
        sr_ckeditor_assets_assert((int) ($result['failed'] ?? -1) === 0, 'Popup layer expired body file cleanup fixture should not report failures.');
        sr_ckeditor_assets_assert(!is_file($oldFile), 'Popup layer expired body file cleanup fixture should delete old file.');
        sr_ckeditor_assets_assert(is_file($freshFile), 'Popup layer expired body file cleanup fixture should keep fresh file.');
        sr_ckeditor_assets_assert(is_file($invalidFile), 'Popup layer expired body file cleanup fixture should ignore invalid token directories.');
    } finally {
        sr_ckeditor_assets_remove_tree($oldDir);
        sr_ckeditor_assets_remove_tree($freshDir);
        sr_ckeditor_assets_remove_tree($invalidDir);
    }
}

function sr_ckeditor_assets_check_content_body_file_access_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_admin_account_roles (account_id INTEGER NOT NULL, role_key TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_admin_account_permissions (account_id INTEGER NOT NULL, menu_path TEXT NOT NULL, action_key TEXT NOT NULL)');

    $publishedFree = [
        'id' => 1,
        'status' => 'published',
        'asset_access_enabled' => 0,
        'asset_access_amount' => 0,
    ];
    $publishedPaid = [
        'id' => 2,
        'status' => 'published',
        'asset_access_enabled' => 1,
        'asset_access_amount' => 100,
    ];
    $draft = [
        'id' => 3,
        'status' => 'draft',
        'asset_access_enabled' => 0,
        'asset_access_amount' => 0,
    ];

    sr_ckeditor_assets_assert(
        sr_content_can_access_body_file($pdo, $publishedFree, null),
        'Content body file access fixture should allow public free published content.'
    );
    sr_ckeditor_assets_assert(
        !sr_content_can_access_body_file($pdo, $publishedPaid, null),
        'Content body file access fixture should block anonymous access to paid published content body files.'
    );
    sr_ckeditor_assets_assert(
        !sr_content_can_access_body_file($pdo, $draft, null),
        'Content body file access fixture should block anonymous access to draft content body files.'
    );
    sr_ckeditor_assets_assert(
        !sr_content_can_access_body_file($pdo, $draft, ['id' => 7]),
        'Content body file access fixture should block non-admin member access to draft content body files.'
    );

    $pdo->exec("INSERT INTO sr_admin_account_roles (account_id, role_key) VALUES (7, 'owner')");
    sr_ckeditor_assets_assert(
        sr_content_can_access_body_file($pdo, $draft, ['id' => 7]),
        'Content body file access fixture should allow content view admins to access draft content body files.'
    );
}

function sr_ckeditor_assets_editor_contract_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('ckeditor', 'enabled')");

    return $pdo;
}

foreach ([
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    'modules/ckeditor/vendor/ckeditor5/LICENSE.md',
    'modules/ckeditor/vendor/ckeditor5/COPYING.GPL',
    'modules/ckeditor/vendor/ckeditor5/README.md',
    'assets/editor-ck.css',
    'modules/ckeditor/assets/saanraan-ckeditor.js',
    'modules/ckeditor/assets/saanraan-ckeditor.css',
] as $file) {
    if (!is_file($file) || filesize($file) <= 0) {
        sr_ckeditor_assets_error('CKEditor required asset is missing or empty: ' . $file);
    }
}

sr_ckeditor_assets_node_syntax_check('modules/ckeditor/assets/saanraan-ckeditor.js');
sr_ckeditor_assets_check_content_body_file_access_fixture();
$ckeditorContractPdo = sr_ckeditor_assets_editor_contract_pdo();
sr_ckeditor_assets_assert(
    sr_content_body_format_for_editor($ckeditorContractPdo, 'ckeditor', '') === 'html',
    'Content CKEditor selection should save html body_format without relying on a posted hidden field.'
);
sr_ckeditor_assets_assert(
    sr_content_effective_body_format($ckeditorContractPdo, ['body_format' => 'plain', 'editor_key' => 'ckeditor']) === 'html',
    'Content CKEditor rows previously saved as plain should render through the html sanitizer path.'
);

sr_ckeditor_assets_require_markers('modules/ckeditor/vendor/ckeditor5/README.md', [
    'CKEditor 5',
    '48.1.0',
    'ckeditor5.umd.js',
    'ckeditor5.css',
]);

sr_ckeditor_assets_require_markers('modules/ckeditor/vendor/ckeditor5/LICENSE.md', [
    'CKEditor&nbsp;5',
    'GPL',
]);

sr_ckeditor_assets_require_markers('modules/ckeditor/helpers.php', [
    "'asset_mode' => 'self_hosted'",
    "'cdn_version' => '48.1.0'",
    "'license_key' => 'GPL'",
    "'selfHostedScriptUrl' => sr_asset_url('/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js')",
    "'selfHostedStylesheetUrl' => sr_asset_url('/modules/ckeditor/vendor/ckeditor5/ckeditor5.css')",
    "'pluginStylesheetUrl' => sr_asset_url('/modules/ckeditor/assets/saanraan-ckeditor.css')",
    'function sr_ckeditor_public_assets_html',
    'saanraan-ckeditor.js',
]);

sr_ckeditor_assets_require_markers('modules/ckeditor/assets/saanraan-ckeditor.js', [
    "window.srCkeditorInstances",
    "config.assetMode === 'self_hosted' ? config.selfHostedScriptUrl : config.cdnScriptUrl",
    "config.assetMode === 'self_hosted' ? config.selfHostedStylesheetUrl : config.cdnStylesheetUrl",
    "loadStylesheet(config.pluginStylesheetUrl)",
    "ckeditor.ClassicEditor.create(textarea, editorConfig(ckeditor, textarea))",
    "function applyBodyTheme(textarea, editorElement)",
    "window.srCkeditorEnhance = function ()",
    "window.srCkeditorDestroyTextarea = function (textarea)",
    "textarea.dataset.srEditorInitializing === '1'",
    "editorForTextarea(textarea)",
    "textarea.dataset.srEditorInitializing = '1'",
    "textarea.dataset.srEditor !== 'ckeditor'",
    "textarea.dataset.srEditorBodyTheme",
    "editorElement.style.setProperty(item[1], value)",
    "existing = form.querySelector('input[type=\"hidden\"][name=\"' + formatName + '\"]')",
    "input.name = formatName",
    "input.value = 'html'",
    "input.setAttribute('data-sr-editor-format', 'ckeditor')",
    "textarea._srCkeditorDestroyPromise",
    "textarea.dataset.srEditorReady = '0'",
    "document.documentElement.classList.add('sr-ckeditor-unavailable')",
    "data.append('csrf_token', upload.csrfToken)",
    "data.append('upload_token', upload.uploadToken)",
]);

sr_ckeditor_assets_require_markers('modules/content/views/admin-contents.php', [
    '$contentEditorFallbackKey = \'textarea\';',
    'sr_editor_options($pdo)',
    'sr_editor_effective_key($pdo, $contentEditorStoredKey)',
    'sr_admin_radio_toggle_group_html(\'content_admin_contents_editor_key\', \'editor_key\'',
    '기본 textarea가 기본 선택됩니다.',
    'data-sr-editor-upload-url',
    'data-sr-editor-body-theme="content.',
    'content-admin-editor-config',
    'data-content-body-format-input',
    'editorTransitionToken',
    'destroyPromise.then(function ()',
    'window.srCkeditorDestroyTextarea(textarea)',
    'window.srCkeditorEnhance',
    'window.srMarkdownEditorEnhance',
    'sr_content_body_file_upload_url()',
    'data-sr-editor-upload-csrf',
    'sr_csrf_token()',
    'data-sr-editor-upload-token',
    'sr_content_body_file_upload_token()',
]);

sr_ckeditor_assets_require_markers('modules/content/actions/admin-contents.php', [
    "sr_get_string('editor_key', 40)",
    'sr_content_item_editor_key',
    'sr_editor_options($pdo)[$newContentEditorKey]',
]);

sr_ckeditor_assets_require_markers('.tools/bin/smoke-ckeditor-upload-save.php', [
    '/admin/content/new?editor_key=ckeditor',
]);

sr_ckeditor_assets_require_markers('modules/content/actions/admin-body-file-upload.php', [
    "sr_request_method() !== 'POST'",
    'sr_member_require_login($pdo)',
    'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin/content\', \'edit\')',
    'sr_require_csrf()',
    "sr_post_string_without_truncation('upload_token', 32) ?? ''",
    'sr_content_upload_body_file($pdo',
    'sr_content_cleanup_expired_body_files($pdo, 5)',
]);

sr_ckeditor_assets_require_markers('modules/content/helpers/body-files.php', [
    'function sr_content_send_body_file(PDO $pdo, int $contentId, string $fileName, string $tmpToken = \'\', bool $thumbnail = false): void',
    'sr_member_current_account($pdo)',
    'sr_admin_has_permission($pdo, (int) $account[\'id\'], \'/admin/content\', \'edit\')',
    '!sr_content_body_file_token_is_valid($tmpToken)',
    'sr_content_body_file_tmp_key($tmpToken, $fileName)',
    'sr_content_can_access_body_file($pdo, $page, is_array($account) ? $account : null)',
    'sr_content_body_file_content_key($contentId, $fileName)',
    'function sr_content_can_access_body_file(PDO $pdo, array $page, ?array $account): bool',
    '(string) ($page[\'status\'] ?? \'\') !== \'published\'',
    'sr_admin_has_permission($pdo, (int) $account[\'id\'], \'/admin/content\', \'view\')',
    '!sr_content_asset_access_required($page)',
    'if (!is_array($account))',
    'sr_content_charge_view_access($pdo, $page, (int) $account[\'id\'], false, \'\', 0, false)',
    'return !empty($access[\'allowed\']);',
]);

sr_ckeditor_assets_require_markers('modules/community/skins/basic/form.php', [
    'data-sr-editor-upload-url',
    'data-sr-editor-body-theme="community.',
    'sr_community_body_file_upload_url($board, $editorPostId)',
    'data-sr-editor-upload-csrf',
    'sr_csrf_token()',
    'data-sr-editor-upload-token',
    'sr_community_body_file_upload_token()',
]);

foreach ([
    'modules/community/theme/basic/form.php',
    'modules/community/theme/sample/form.php',
] as $communityThemeForm) {
    sr_ckeditor_assets_require_markers($communityThemeForm, [
        'data-sr-editor-body-theme="community.',
        'sr_community_theme_key((string) ($settings[\'theme_key\'] ?? \'basic\'))',
    ]);
}

sr_ckeditor_assets_require_markers('modules/community/actions/body-file-upload.php', [
    "sr_request_method() !== 'POST'",
    'sr_member_require_login($pdo)',
    'sr_require_csrf()',
    'sr_community_board_by_key($pdo, $boardKey)',
    'sr_community_privacy_consent_validation_errors($pdo, $board, [',
    "sr_post_string_without_truncation('upload_token', 32) ?? ''",
    'sr_community_upload_body_file($pdo',
    'sr_community_cleanup_expired_body_files($pdo, 5)',
]);

sr_ckeditor_assets_require_markers('modules/popup_layer/actions/admin-popup-layers.php', [
    'sr_popup_layer_body_file_upload_url()',
    'data-sr-editor-upload-url',
    'data-sr-editor-upload-csrf',
    'sr_csrf_token()',
    'data-sr-editor-upload-token',
    'sr_popup_layer_body_file_upload_token()',
]);

sr_ckeditor_assets_require_markers('modules/popup_layer/actions/admin-body-file-upload.php', [
    "sr_request_method() !== 'POST'",
    'sr_member_require_login($pdo)',
    'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin/popup-layers\', \'edit\')',
    'sr_require_csrf()',
    "sr_post_string_without_truncation('upload_token', 32) ?? ''",
    'sr_popup_layer_upload_body_file($pdo',
    'sr_popup_layer_cleanup_expired_body_files($pdo, 5)',
]);

foreach ([
    'modules/content/actions/admin-body-file-upload.php',
    'modules/community/actions/body-file-upload.php',
    'modules/popup_layer/actions/admin-body-file-upload.php',
] as $uploadAction) {
    sr_ckeditor_assets_forbid_markers($uploadAction, [
        "sr_post_string('upload_token'",
    ]);
}

sr_ckeditor_assets_require_markers('modules/popup_layer/helpers/body-files.php', [
    'function sr_popup_layer_body_file_temporary_ttl_seconds(): int',
    'function sr_popup_layer_cleanup_expired_body_files(PDO $pdo, int $limit = 10): array',
    "SR_ROOT . '/storage/popup_layer/body/tmp'",
    'sr_popup_layer_body_file_temporary_ttl_seconds()',
]);

sr_ckeditor_assets_check_popup_layer_tmp_cleanup_fixture();

sr_ckeditor_assets_require_markers('modules/ckeditor/assets/saanraan-ckeditor.css', [
    '.sr-ckeditor',
    '--sr-ckeditor-ui-surface',
    '--sr-ckeditor-body-surface: var(--sr-editor-body-surface',
    '--ck-color-base-background: var(--sr-ckeditor-ui-surface)',
    '--ck-color-base-text: var(--sr-ckeditor-ui-text)',
    '--ck-color-button-default-hover-background: var(--sr-ckeditor-hover)',
    ':is([data-theme="dark"], [data-color-scheme="dark"]) .sr-ckeditor',
    'data-sr-editor-body-theme="content.basic"',
    'data-sr-editor-body-theme="community.sample"',
    '--sr-editor-body-text',
    '.ck-editor__editable_inline',
    '.sr-ckeditor :is(.ck-toolbar, .ck-editor__top .ck-sticky-panel .ck-sticky-panel__content, .ck-dropdown__panel, .ck-list)',
    '.sr-ckeditor :is(.ck-button.ck-on, .ck-list__item .ck-button.ck-on)',
    'background: var(--sr-ckeditor-body-surface)',
    '.sr-ckeditor .ck-content :where(ul)',
    'list-style: disc outside',
    '.sr-ckeditor .ck-content :where(table)',
    'display: table',
]);

sr_ckeditor_assets_require_markers('assets/editor-ck.css', [
    '.content-body',
    '.community-post-body',
    'background: transparent',
    'padding: 0',
    '.content-body :where(ul)',
    '.community-post-body :where(ul)',
    'list-style: disc outside',
    '.content-body :where(table)',
    '.community-post-body :where(table)',
    'display: table',
    '.content-body h1',
    'font-size: inherit',
]);

sr_ckeditor_assets_require_markers('core/helpers/output.php', [
    'function sr_body_editor_stylesheets',
    'sr_editor_normalize_key($bodyEditorKey) === \'ckeditor\'',
    "'/assets/editor-ck.css'",
]);

sr_ckeditor_assets_require_markers('modules/content/helpers.php', [
    'sr_content_effective_editor_key($pdo, $page)',
    'function sr_content_effective_body_format(PDO $pdo, array $page): string',
    '$editorKey === \'html\' || $editorKey === \'ckeditor\'',
]);

sr_ckeditor_assets_require_markers('modules/markdown_editor/assets/editor.js', [
    'window.srMarkdownEditorEnhance = enhance',
]);

sr_ckeditor_assets_require_markers('modules/community/helpers/posts.php', [
    '$bodyFormat = sr_community_post_body_format($pdo, $post, $settings)',
    'sr_body_editor_stylesheets($bodyFormat, $postEditorKey)',
]);

sr_ckeditor_assets_assert(sr_body_editor_stylesheets('html', 'html') === [], 'Direct HTML bodies should not load editor-ck.css.');
sr_ckeditor_assets_assert(sr_body_editor_stylesheets('html', 'ckeditor') === ['/assets/editor-ck.css'], 'CKEditor HTML bodies should load editor-ck.css.');

foreach ([
    'modules/content/theme/basic/assets/module.css',
    'modules/content/theme/sample/assets/module.css',
    'modules/community/theme/basic/assets/module.css',
    'modules/community/theme/sample/assets/module.css',
] as $bodyStylesheet) {
    sr_ckeditor_assets_forbid_markers($bodyStylesheet, [
        ':where(ul)',
        ':where(table)',
        'font-size: inherit',
    ]);
}

sr_ckeditor_assets_require_markers('modules/content/theme/sample/content.php', [
    'class="example-content-body content-body"',
]);

sr_ckeditor_assets_require_markers('modules/community/theme/sample/post.php', [
    'class="example-community-body community-post-body"',
]);

sr_ckeditor_assets_require_markers('docs/module-guide.md', [
    '--sr-editor-body-surface',
    'data-sr-editor-body-theme="content.{theme_key}"',
]);

sr_ckeditor_assets_require_markers('docs/admin-ui-guide.md', [
    '읽기 본문 색과 편집 본문 색',
    '--sr-editor-body-*',
]);

sr_ckeditor_assets_require_markers('modules/ckeditor/module.php', [
    "'asset_mode' => 'self_hosted'",
    "'cdn_version' => '48.1.0'",
    "'license_key' => 'GPL'",
]);

sr_ckeditor_assets_require_markers('modules/ckeditor/README.md', [
    'CKEditor 5 `48.1.0`',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    '에셋 로딩에 실패하거나 플러그인이 비활성화되면 기존 textarea가 그대로 제출',
]);

sr_ckeditor_assets_require_markers('.tools/bin/smoke-http.php', [
    'ckeditor plugin script',
    'ckeditor self-hosted script',
    'ckeditor self-hosted stylesheet',
]);

sr_ckeditor_assets_require_markers('.tools/browser-qa/tests/ckeditor-browser-smoke.spec.js', [
    'CKEditor browser smoke',
    '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    'sr-ckeditor-unavailable',
    'body_format',
]);

sr_ckeditor_assets_require_markers('.tools/bin/dev-router.php', [
    "/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js",
    "/modules/ckeditor/vendor/ckeditor5/ckeditor5.css",
]);

sr_ckeditor_assets_require_markers('docs/records/improvement-hardening-verification-2026-06-11.md', [
    'check-ckeditor-assets.php',
    'CKEditor self-hosted asset',
    'node --check',
    '콘텐츠/커뮤니티/팝업레이어 업로드 action 보안 계약 marker',
]);

if ($errors !== []) {
    fwrite(STDERR, "CKEditor asset checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "CKEditor asset checks completed.\n";
