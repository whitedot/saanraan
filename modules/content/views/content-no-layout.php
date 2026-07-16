<?php

$contentNoLayoutTitle = (string) (($page['seo_title'] ?? '') ?: ($page['title'] ?? ''));
$contentNoLayoutDescription = (string) (($page['seo_description'] ?? '') ?: ($page['summary'] ?? ''));
$contentNoLayoutStylesheets = sr_content_body_embed_stylesheets($page, $contentLayoutSettings, $pdo ?? null);
$contentNoLayoutEditorKey = $pdo instanceof PDO
    ? sr_content_effective_editor_key($pdo, $page)
    : sr_content_item_editor_key((string) ($page['editor_key'] ?? 'textarea'));
$contentNoLayoutBodyFormat = $pdo instanceof PDO
    ? sr_content_effective_body_format($pdo, $page)
    : sr_body_format((string) ($page['body_format'] ?? 'plain'));
$contentNoLayoutUsesCkeditor = $contentNoLayoutEditorKey === 'ckeditor'
    && $contentNoLayoutBodyFormat === 'html';
$contentNoLayoutThemeKey = sr_content_theme_key((string) ($contentLayoutSettings['theme_key'] ?? ''));
$contentNoLayoutThemeReset = sr_public_layout_module_theme_asset_url('content', $contentNoLayoutThemeKey, 'reset.css');
array_unshift($contentNoLayoutStylesheets, $contentNoLayoutThemeReset);
if ($contentNoLayoutUsesCkeditor) {
    $contentNoLayoutStylesheets = array_values(array_filter(
        $contentNoLayoutStylesheets,
        static fn(string $stylesheet): bool => $stylesheet !== '/assets/editor-ck.css'
    ));
    $contentNoLayoutStylesheets[] = '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css';
    $contentNoLayoutStylesheets[] = '/modules/ckeditor/assets/saanraan-ckeditor.css';
}
$contentNoLayoutShowTitle = (int) ($page['show_title'] ?? 1) === 1;
$contentEditUrl = (string) ($contentEditUrl ?? '');
$contentNoLayoutColorScheme = sr_color_scheme(is_array($site ?? null) ? $site : null);
$contentNoLayoutNeedsConfirmation = empty($pageAccess['allowed'])
    && (string) ($pageAccess['error_key'] ?? '') === 'asset_confirmation_required';
$contentNoLayoutScripts = [];
if ($contentNoLayoutNeedsConfirmation) {
    $contentNoLayoutStylesheets[] = sr_public_layout_module_theme_asset_url('content', $contentNoLayoutThemeKey, 'common.css');
    $contentNoLayoutStylesheets[] = sr_public_layout_module_theme_asset_url('content', $contentNoLayoutThemeKey, 'module.css');
    $contentNoLayoutThemeStylesheet = sr_module_view_theme_stylesheet_url('content', $contentNoLayoutThemeKey);
    if ($contentNoLayoutThemeStylesheet !== '') {
        $contentNoLayoutStylesheets[] = $contentNoLayoutThemeStylesheet;
    }
    $contentNoLayoutScripts = [
        '/assets/common-ui.js',
        '/modules/content/assets/module.js',
    ];
}
$contentNoLayoutStylesheets = array_values(array_unique($contentNoLayoutStylesheets));
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e($contentNoLayoutColorScheme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?php echo sr_e($contentNoLayoutTitle); ?></title>
    <?php if ($contentNoLayoutDescription !== '') { ?>
        <meta name="description" content="<?php echo sr_e($contentNoLayoutDescription); ?>">
    <?php } ?>
    <?php foreach ($contentNoLayoutStylesheets as $contentNoLayoutStylesheet) { ?>
        <link rel="stylesheet" href="<?php echo sr_e(sr_asset_url((string) $contentNoLayoutStylesheet)); ?>">
    <?php } ?>
    <script>(function(){try{var s=localStorage.getItem("sr_public_color_scheme");if(s==="light"||s==="dark"||s==="system"){document.documentElement.setAttribute("data-color-scheme",s);}}catch(e){}})();</script>
</head>
<body>
<main>
    <?php if ($contentNoLayoutShowTitle) { ?>
        <h1><?php echo sr_e((string) $page['title']); ?></h1>
    <?php } ?>
    <?php include SR_ROOT . '/modules/content/views/content-edit-link.php'; ?>
    <?php if (!empty($pageAccess['allowed'])) { ?>
        <?php if ($contentNoLayoutUsesCkeditor) { ?>
            <div class="sr-ckeditor" data-sr-editor-body-theme="content.<?php echo sr_e($contentNoLayoutThemeKey); ?>">
                <div class="content-body ck-content" lang="<?php echo sr_e(sr_locale()); ?>" dir="ltr">
                    <?php echo sr_content_body_html($page, $contentLayoutSettings, $pdo); ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="content-body">
                <?php echo sr_content_body_html($page, $contentLayoutSettings, $pdo); ?>
            </div>
        <?php } ?>
    <?php } elseif ($contentNoLayoutNeedsConfirmation) { ?>
        <?php
        $assetConfirmationAssetLabel = (string) ($pageAccess['asset_label'] ?? '');
        $assetConfirmationAmount = (int) ($pageAccess['amount'] ?? 0);
        $assetConfirmationMessage = (string) (($pageAccess['message'] ?? '') ?: (trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 콘텐츠를 열람하시겠습니까?'));
        $assetConfirmationAction = sr_content_path((string) $page['slug']);
        $assetConfirmationId = 0;
        $assetConfirmationContentId = 0;
        $assetConfirmationRequestToken = (string) ($pageAccess['confirmation_request_token'] ?? '');
        $assetConfirmationTitle = '콘텐츠 열람 확인';
        $assetConfirmationSubmitLabel = sr_t('content::ui.text.ac5b575f');
        $assetConfirmationCouponIssues = is_array($pageAccess['coupon_issues'] ?? null) ? $pageAccess['coupon_issues'] : [];
        $assetConfirmationExchangeSuggestion = is_array($pageAccess['asset_exchange_suggestion'] ?? null) ? $pageAccess['asset_exchange_suggestion'] : [];
        $assetConfirmationModalId = 'content_no_layout_asset_confirmation';
        $assetConfirmationOpen = true;
        $assetConfirmationCancelUrl = '/content';
        $assetConfirmationCloseOnSubmit = false;
        include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';
        ?>
    <?php } else { ?>
        <p><?php echo sr_e((string) ($pageAccess['message'] ?? sr_t('content::ui.content.7d2dd480'))); ?></p>
    <?php } ?>
</main>
<?php foreach ($contentNoLayoutScripts as $contentNoLayoutScript) { ?>
    <script src="<?php echo sr_e(sr_asset_url($contentNoLayoutScript)); ?>" defer></script>
<?php } ?>
</body>
</html>
