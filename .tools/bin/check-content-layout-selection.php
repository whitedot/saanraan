#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$read = static function (string $relativePath) use ($root, &$errors): string {
    $body = file_get_contents($root . '/' . $relativePath);
    if (!is_string($body)) {
        $errors[] = 'Cannot read ' . $relativePath . '.';
        return '';
    }

    return $body;
};

$assert(sr_content_no_layout_key() === 'content.none', 'Content no-layout key must remain explicit and stable.');
$assert(sr_content_layout_disabled('content.none'), 'Content no-layout helper must recognize the explicit key.');
$assert(!sr_content_layout_disabled(''), 'An empty legacy layout key must keep its default-layout meaning.');
$assert(!sr_content_layout_disabled('content.basic'), 'A registered content layout must not be treated as disabled.');

$orderedLayoutContext = sr_content_public_layout_context([
    'layout_key' => 'content.basic',
    'theme_key' => 'basic',
], [
    'stylesheets' => [
        '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
        '/modules/ckeditor/assets/saanraan-ckeditor.css',
    ],
]);
$orderedStylesheets = is_array($orderedLayoutContext['stylesheets'] ?? null) ? $orderedLayoutContext['stylesheets'] : [];
$moduleStylesheetIndex = array_search('/modules/content/theme/basic/assets/module.css', $orderedStylesheets, true);
$ckeditorStylesheetIndex = array_search('/modules/ckeditor/vendor/ckeditor5/ckeditor5.css', $orderedStylesheets, true);
$ckeditorThemeStylesheetIndex = array_search('/modules/ckeditor/assets/saanraan-ckeditor.css', $orderedStylesheets, true);
$assert(is_int($moduleStylesheetIndex) && is_int($ckeditorStylesheetIndex) && $moduleStylesheetIndex < $ckeditorStylesheetIndex, 'Content module styles must load before CKEditor content styles.');
$assert(is_int($ckeditorStylesheetIndex) && is_int($ckeditorThemeStylesheetIndex) && $ckeditorStylesheetIndex < $ckeditorThemeStylesheetIndex, 'Bundled CKEditor styles must load before the project CKEditor theme.');

$adminView = $read('modules/content/views/admin-contents.php');
$assert(str_contains($adminView, 'sr_content_no_layout_key()'), 'Content admin layout select must expose the no-layout option.');
$assert(str_contains($adminView, 'name="show_title"'), 'Content admin form must expose the no-layout title option.');
$assert(str_contains($adminView, 'data-content-series-detail'), 'Content series episode fields must use independent conditional rows.');
$assert(!str_contains($adminView, '<div class="admin-form-inline">'), 'Content series fields must not return to the compressed inline layout.');

$records = $read('modules/content/helpers/records.php');
$assert(str_contains($records, "'show_title' => sr_post_string('show_title'"), 'Content input parsing must normalize the title display option.');
$assert(str_contains($records, '!sr_content_layout_disabled($layoutKey)'), 'Content validation must allow only the explicit no-layout key outside registered layouts.');
$assert(substr_count($records, 'show_title = :show_title') === 1, 'Content updates must persist show_title exactly once.');
$assert(substr_count($records, ':show_title') >= 4, 'Content create, revision, and copy writes must persist show_title.');

$viewAction = $read('modules/content/actions/view.php');
$assert(str_contains($viewAction, "include SR_ROOT . '/modules/content/views/content-no-layout.php';"), 'Content view action must branch to the no-layout renderer.');
$assert(str_contains($viewAction, "\$contentLayoutSettings['layout_key'] = \$contentPageLayoutKey;"), 'Content view action must honor the selected per-content layout.');
$assert(str_contains($viewAction, "sr_admin_has_permission(\$pdo, \$contentAccountId, '/admin/content', 'edit')"), 'Content view action must expose the admin editor only to content editors.');
$assert(str_contains($viewAction, 'sr_content_editable_submission_for_content_author('), 'Content view action must resolve the author submission editor.');
$assert(str_contains($viewAction, "'/admin/content/edit?id='"), 'Content view action must build the admin content edit URL.');
$assert(str_contains($viewAction, "'/account/content?id='"), 'Content view action must build the author submission edit URL.');

foreach ([
    'modules/content/views/content.php',
    'modules/content/theme/basic/content.php',
] as $contentPublicViewFile) {
    $contentPublicView = $read($contentPublicViewFile);
    $assert(str_contains($contentPublicView, "include SR_ROOT . '/modules/content/views/content-edit-link.php';"), $contentPublicViewFile . ' must render the shared content edit link.');
    $assert(str_contains($contentPublicView, 'sr_content_public_body_html('), $contentPublicViewFile . ' must use the shared editor-aware public body renderer.');
}

$noLayoutView = $read('modules/content/views/content-no-layout.php');
$assert(str_contains($noLayoutView, '$contentNoLayoutShowTitle'), 'No-layout renderer must conditionally render the content title.');
$assert(str_contains($noLayoutView, 'sr_content_public_body_html('), 'No-layout renderer must use the shared editor-aware public body renderer.');
$assert(str_contains($noLayoutView, '<div class="content-body">'), 'No-layout renderer must retain the shared body wrapper for non-CKEditor content.');
$assert(!str_contains($noLayoutView, 'ck-editor__editable_inline'), 'No-layout CKEditor content must not render the editor input surface.');
$assert(!str_contains($noLayoutView, 'ck-editor__main'), 'No-layout CKEditor content must not render editor UI containers.');
$assert(str_contains($noLayoutView, "sr_public_layout_module_theme_asset_url('content', \$contentNoLayoutThemeKey, 'reset.css')"), 'No-layout content must load the selected content theme reset before editor styles.');
$assert(str_contains($noLayoutView, "include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';"), 'No-layout renderer must reuse the original content confirmation modal.');
$assert(str_contains($noLayoutView, "sr_public_layout_module_theme_asset_url('content', \$contentNoLayoutThemeKey, 'common.css')"), 'No-layout confirmation must load the selected content theme common styles.');
$assert(str_contains($noLayoutView, "sr_public_layout_module_theme_asset_url('content', \$contentNoLayoutThemeKey, 'module.css')"), 'No-layout confirmation must load the selected content theme module styles.');
$assert(str_contains($noLayoutView, "'/assets/common-ui.js'"), 'No-layout confirmation must load the original overlay behavior.');
$assert(!str_contains($noLayoutView, 'sr_public_layout_begin'), 'No-layout renderer must not invoke a public layout shell.');
$assert(!str_contains($noLayoutView, 'contentSeriesContext'), 'No-layout renderer must not expose series navigation.');
$assert(str_contains($noLayoutView, "include SR_ROOT . '/modules/content/views/content-edit-link.php';"), 'No-layout renderer must render the shared content edit link.');

$memberSubmissionHelpers = $read('modules/content/helpers/member-submissions.php');
$assert(str_contains($memberSubmissionHelpers, 'function sr_content_editable_submission_for_content_author('), 'Content author edit lookup helper must remain available.');
$assert(str_contains($memberSubmissionHelpers, "['approved', 'member_draft', 'revision_requested', 'rejected']"), 'Approved author submissions must be editable as a new revision.');
$accountContentView = $read('modules/content/views/account-content.php');
$assert(str_contains($accountContentView, "['approved', 'member_draft', 'revision_requested', 'rejected']"), 'Author submission history must link approved content to its revision form.');

$submissionPdo = new PDO('sqlite::memory:');
$submissionPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$submissionPdo->exec(
    'CREATE TABLE sr_content_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        author_account_id INTEGER NOT NULL,
        review_status TEXT NOT NULL
    )'
);
$submissionPdo->exec("INSERT INTO sr_content_submissions (content_id, author_account_id, review_status) VALUES (10, 20, 'approved')");
$authorSubmission = sr_content_editable_submission_for_content_author($submissionPdo, 10, 20);
$assert(is_array($authorSubmission) && (int) $authorSubmission['id'] === 1, 'Approved content authors must resolve their own submission editor.');
$assert(sr_content_editable_submission_for_content_author($submissionPdo, 10, 21) === null, 'Content authors must not resolve another account submission editor.');
$submissionPdo->exec("UPDATE sr_content_submissions SET review_status = 'pending_review' WHERE id = 1");
$assert(sr_content_editable_submission_for_content_author($submissionPdo, 10, 20) === null, 'Pending author submissions must not expose a concurrent edit link.');

$renderNoLayout = static function (int $showTitle, string $editUrl = '', string $editorKey = 'html') use ($root): string {
    $page = [
        'id' => 1,
        'slug' => 'layout-fixture',
        'title' => '레이아웃 제목',
        'summary' => '화면에 나오지 않을 요약',
        'body_text' => '<p>레이아웃 본문</p>',
        'body_format' => 'html',
        'editor_key' => $editorKey,
        'seo_title' => '',
        'seo_description' => '',
        'show_title' => $showTitle,
    ];
    $pageAccess = ['allowed' => true];
    $contentLayoutSettings = [
        'external_embed_enabled' => false,
        'internal_embed_enabled' => false,
        'plain_text_auto_link_urls' => false,
        'plain_text_auto_link_new_tab' => false,
    ];
    $pdo = null;
    $site = null;
    $contentEditUrl = $editUrl;
    ob_start();
    include $root . '/modules/content/views/content-no-layout.php';
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
};
$visibleTitleHtml = $renderNoLayout(1);
$hiddenTitleHtml = $renderNoLayout(0);
$editableHtml = $renderNoLayout(1, '/admin/content/edit?id=1');
$ckeditorHtml = $renderNoLayout(1, '', 'ckeditor');
$assert(str_contains($visibleTitleHtml, '<h1>레이아웃 제목</h1>'), 'No-layout renderer must show the title when show_title is enabled.');
$assert(!str_contains($hiddenTitleHtml, '<h1>'), 'No-layout renderer must hide the visible title when show_title is disabled.');
$assert(str_contains($hiddenTitleHtml, '<title>레이아웃 제목</title>'), 'No-layout renderer must retain the document title when the visible title is hidden.');
$assert(str_contains($hiddenTitleHtml, '<p>레이아웃 본문</p>'), 'No-layout renderer must retain the sanitized body when the visible title is hidden.');
$assert(str_contains($hiddenTitleHtml, '<div class="content-body">'), 'No-layout renderer must apply editor body selectors to the rendered content.');
$assert(str_contains($ckeditorHtml, '<div class="sr-ckeditor" data-sr-editor-output data-sr-editor-body-theme="content.basic">'), 'No-layout CKEditor renderer must preserve the shared transparent output and editor body theme context.');
$assert(str_contains($ckeditorHtml, '<div class="ck-content"'), 'No-layout CKEditor renderer must activate CKEditor content presentation rules.');
$assert(!str_contains($ckeditorHtml, 'ck-editor__editable_inline'), 'No-layout CKEditor renderer must not expose editor input chrome.');
$assert(!str_contains($ckeditorHtml, 'aria-readonly='), 'No-layout CKEditor body is ordinary public content, not an editor widget.');
$assert(str_contains($ckeditorHtml, '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'), 'No-layout CKEditor renderer must load the bundled editor stylesheet.');
$assert(str_contains($ckeditorHtml, '/modules/ckeditor/assets/saanraan-ckeditor.css'), 'No-layout CKEditor renderer must load the project editor theme stylesheet.');
$assert(str_contains($ckeditorHtml, '/modules/content/theme/basic/assets/reset.css'), 'No-layout CKEditor renderer must load the same reset and token baseline as the editor input screen.');
$assert(!str_contains($ckeditorHtml, '/assets/editor-ck.css'), 'No-layout CKEditor renderer must not let the reduced public reset override original editor content rules.');
$contentHelpers = $read('modules/content/helpers.php');
$assert(str_contains($contentHelpers, 'function sr_content_public_body_html('), 'Content helpers must expose one shared public body renderer for every layout mode.');
$assert(str_contains($contentHelpers, 'data-sr-editor-output'), 'Shared content body renderer must identify public output separately from editor input UI.');
$assert(str_contains($contentHelpers, "'/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'"), 'Shared content body assets must load the bundled CKEditor stylesheet.');
$assert(str_contains($contentHelpers, "'/modules/ckeditor/assets/saanraan-ckeditor.css'"), 'Shared content body assets must load the project CKEditor theme stylesheet.');
$ckeditorThemeStyles = $read('modules/ckeditor/assets/saanraan-ckeditor.css');
$ckeditorVendorStyles = $read('modules/ckeditor/vendor/ckeditor5/ckeditor5.css');
$assert(str_contains($ckeditorThemeStyles, '.sr-ckeditor[data-sr-editor-output] .ck-content'), 'Public CKEditor output must have a scoped background override.');
$assert(str_contains($ckeditorThemeStyles, 'background: transparent;'), 'Public CKEditor output background must remain transparent.');
$assert(str_contains($ckeditorVendorStyles, '--ck-content-line-height:1.5'), 'Bundled CKEditor content line-height token must retain its expected source value.');
$assert(str_contains($ckeditorVendorStyles, 'line-height:var(--ck-content-line-height)'), 'Bundled CKEditor content rules must apply their line-height token.');
$assert(str_contains($ckeditorThemeStyles, 'font-size: var(--ck-content-font-size);'), 'Public CKEditor output must use the original CKEditor content font-size token.');
$assert(str_contains($ckeditorThemeStyles, 'line-height: var(--ck-content-line-height);'), 'Public CKEditor output must use the original CKEditor content line-height token.');
$assert(str_contains($ckeditorThemeStyles, 'padding-inline: 0;'), 'Public CKEditor output must not add horizontal content padding.');
$assert(!str_contains($ckeditorThemeStyles, 'padding: 0 var(--ck-spacing-standard);'), 'Public CKEditor output must not copy the editable widget horizontal padding.');
$assert(str_contains($ckeditorThemeStyles, '.ck-content > :first-child'), 'Public CKEditor output must retain the editable surface first-block spacing.');
$assert(str_contains($ckeditorThemeStyles, 'margin-top: var(--ck-spacing-large);'), 'Public CKEditor output first-block spacing must use the original CKEditor token.');
$assert(str_contains($ckeditorThemeStyles, '.ck-content > :last-child'), 'Public CKEditor output must retain the editable surface last-block spacing.');
$assert(str_contains($ckeditorThemeStyles, 'margin-bottom: var(--ck-spacing-large);'), 'Public CKEditor output last-block spacing must use the original CKEditor token.');
$assert(str_contains($ckeditorThemeStyles, '.ck-content p > img:only-child'), 'CKEditor image-only paragraphs must retain their editor line box in public output.');
$assert(str_contains($ckeditorThemeStyles, '.ck-content p > a:only-child > img:only-child'), 'Linked CKEditor image-only paragraphs must retain their editor line box in public output.');
$hiddenTitleBody = strstr($hiddenTitleHtml, '<body>');
$assert(is_string($hiddenTitleBody) && !str_contains($hiddenTitleBody, '화면에 나오지 않을 요약'), 'No-layout renderer must not expose the content summary in the document body.');
$assert(str_contains($editableHtml, 'admin/content/edit?id=1'), 'No-layout renderer must expose the resolved content edit URL.');
$assert(!str_contains($hiddenTitleHtml, 'class="btn btn-sm btn-outline-default content-edit-link"'), 'No-layout renderer must hide the edit link when no edit URL was resolved.');
$assert(sr_body_editor_stylesheets('html', 'ckeditor') === ['/assets/editor-ck.css'], 'CKEditor content must load the editor body stylesheet.');
$assert(sr_body_editor_stylesheets('markdown', 'markdown_editor') === ['/assets/editor-md.css'], 'Markdown content must load the Markdown editor stylesheet.');

$renderNoLayoutConfirmation = static function (string $themeKey = 'basic') use ($root): string {
    $_SESSION = [];
    $page = [
        'id' => 1,
        'slug' => 'paid-layout-fixture',
        'title' => '유료 콘텐츠',
        'summary' => '',
        'body_text' => '<p>확인 전 비공개 본문</p>',
        'body_format' => 'html',
        'editor_key' => 'html',
        'seo_title' => '',
        'seo_description' => '',
        'show_title' => 1,
    ];
    $pageAccess = [
        'allowed' => false,
        'error_key' => 'asset_confirmation_required',
        'asset_label' => '포인트',
        'amount' => 100,
        'confirmation_request_token' => 'request-token',
        'asset_exchange_suggestion' => ['required' => true],
        'coupon_issues' => [
            ['id' => 7, 'title' => '열람 쿠폰', 'expires_at' => ''],
        ],
    ];
    $contentLayoutSettings = [
        'theme_key' => $themeKey,
        'external_embed_enabled' => false,
        'internal_embed_enabled' => false,
        'plain_text_auto_link_urls' => false,
        'plain_text_auto_link_new_tab' => false,
    ];
    $pdo = null;
    $site = ['ui_color_scheme' => 'dark'];
    ob_start();
    include $root . '/modules/content/views/content-no-layout.php';
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
};
$confirmationHtml = $renderNoLayoutConfirmation();
$assert(str_contains($confirmationHtml, '/modules/content/theme/basic/assets/common.css'), 'No-layout paid confirmation must load the original content common stylesheet.');
$assert(str_contains($confirmationHtml, '/modules/content/theme/basic/assets/module.css'), 'No-layout paid confirmation must load the original content module stylesheet.');
$assert(str_contains($confirmationHtml, '/assets/common-ui.js'), 'No-layout paid confirmation must load the original overlay script.');
$assert(str_contains($confirmationHtml, 'class="modal-overlay overlay content-asset-confirmation-modal overlay-open open"'), 'No-layout paid confirmation must render the original open modal.');
$assert(substr_count($confirmationHtml, 'name="asset_confirm" value="1"') === 2, 'No-layout coupon and default forms must both confirm the asset request.');
$assert(substr_count($confirmationHtml, 'name="asset_exchange_confirm" value="1"') === 2, 'No-layout coupon and default forms must both preserve exchange confirmation.');
$assert(substr_count($confirmationHtml, 'name="asset_request_token" value="request-token"') === 2, 'No-layout coupon and default forms must both preserve the request token.');
$assert(str_contains($confirmationHtml, 'name="coupon_issue_id" value="7"'), 'No-layout paid confirmation must preserve coupon selection.');
$assert(!str_contains($confirmationHtml, '확인 전 비공개 본문'), 'No-layout paid confirmation must not expose the protected body before access is granted.');
$missingThemeConfirmationHtml = $renderNoLayoutConfirmation('missing_theme');
$assert(str_contains($missingThemeConfirmationHtml, '/modules/content/theme/basic/assets/common.css'), 'No-layout paid confirmation must fall back to the basic common stylesheet when the selected theme does not exist.');
$assert(str_contains($missingThemeConfirmationHtml, '/modules/content/theme/basic/assets/module.css'), 'No-layout paid confirmation must fall back to the basic module stylesheet when the selected theme does not exist.');
$assert(!str_contains($missingThemeConfirmationHtml, '/theme/missing_theme/'), 'No-layout paid confirmation must not expose assets for a theme that does not exist.');

$installSql = $read('modules/content/install.sql');
$updateSql = $read('modules/content/updates/2026.07.006.sql');
$assert(substr_count($installSql, 'show_title TINYINT(1) NOT NULL DEFAULT 1') === 2, 'Fresh install schema must add show_title to items and revisions.');
$assert(str_contains($updateSql, 'ALTER TABLE {{SR_TABLE_PREFIX}}content_items'), 'Content update must add the item show_title column.');
$assert(str_contains($updateSql, 'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions'), 'Content update must add the revision show_title column.');

if ($errors !== []) {
    fwrite(STDERR, "Content layout selection check failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Content layout selection check passed.\n");
