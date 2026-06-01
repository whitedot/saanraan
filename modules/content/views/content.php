<?php

$seoTitle = (string) ($page['seo_title'] ?: $page['title']);
$seoDescription = (string) ($page['seo_description'] ?: $page['summary']);
$seo = [
    'title' => $seoTitle,
    'description' => $seoDescription,
    'canonical' => sr_content_path((string) $page['slug']),
    'og' => [
        'title' => $seoTitle,
        'description' => $seoDescription,
        'type' => 'article',
    ],
];
$pageLayoutKey = sr_public_layout_normalize_key((string) ($page['layout_key'] ?? ''));
if ($pageLayoutKey === '' || !isset(sr_public_layout_options($pdo ?? null)[$pageLayoutKey])) {
    $pageLayoutKey = sr_public_layout_key($site ?? null, $pdo ?? null);
}
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'layout_key' => $pageLayoutKey,
]));
?>
<main class="content-public content-public-basic">
    <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
        <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($page['popup_layer_id'] ?? 0)); ?>
    <?php } ?>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_before_content_id'] ?? 0)); ?>
    <?php } ?>

    <article class="content-article">
        <header class="content-header">
            <h1><?php echo sr_e((string) $page['title']); ?></h1>
            <?php if ((string) $page['summary'] !== '') { ?>
                <p><?php echo sr_e((string) $page['summary']); ?></p>
            <?php } ?>
        </header>
        <?php if (empty($pageAccess['allowed'])) { ?>
            <div class="content-body">
                <p><?php echo sr_e((string) ($pageAccess['message'] ?? sr_t('content::ui.content.7d2dd480'))); ?></p>
                <?php if ((string) ($pageAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                    <form method="post" action="<?php echo sr_e(sr_url(sr_content_path((string) $page['slug']))); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <button type="submit" class="btn btn-solid-primary">
                            <?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>
                        </button>
                    </form>
                <?php } ?>
            </div>
        <?php } else { ?>
            <?php if ((string) ($pageActionNotice ?? '') !== '') { ?>
                <p class="content-access-notice"><?php echo sr_e((string) $pageActionNotice); ?></p>
            <?php } ?>
            <?php if (is_array($pageActionErrors ?? null)) { ?>
                <?php foreach ($pageActionErrors as $pageActionError) { ?>
                    <p class="content-access-notice"><?php echo sr_e((string) $pageActionError); ?></p>
                <?php } ?>
            <?php } ?>
            <?php if (!empty($pageAccess['charged'])) { ?>
                <p class="content-access-notice">
                    <?php echo sr_e((string) ($pageAccess['asset_label'] ?? sr_t('content::ui.member.415a098e'))); ?>
                    <?php echo sr_e(number_format((int) ($pageAccess['amount'] ?? 0))); ?> <?php echo sr_e(sr_t('content::ui.text.375151be')); ?>
                </p>
            <?php } ?>
            <?php if (!empty($pageAccess['coupon_used'])) { ?>
                <p class="content-access-notice">쿠폰으로 열람했습니다.</p>
            <?php } ?>
            <div class="content-body">
                <?php echo sr_content_body_html($page); ?>
            </div>
            <?php if (is_array($contentSeriesContext ?? null) && is_array($contentSeriesContext['items'] ?? null) && $contentSeriesContext['items'] !== []) { ?>
                <nav class="content-series-nav" aria-label="<?php echo sr_e('시리즈 콘텐츠'); ?>">
                    <h2><?php echo sr_e((string) $contentSeriesContext['title']); ?></h2>
                    <?php if ((string) ($contentSeriesContext['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $contentSeriesContext['description']); ?></p>
                    <?php } ?>
                    <ol>
                        <?php foreach ($contentSeriesContext['items'] as $seriesItem) { ?>
                            <li>
                                <?php if ((int) ($seriesItem['content_id'] ?? 0) === (int) $page['id']) { ?>
                                    <strong>
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['content_title']); ?>
                                    </strong>
                                <?php } else { ?>
                                    <a href="<?php echo sr_e(sr_url(sr_content_path((string) $seriesItem['slug']))); ?>">
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['content_title']); ?>
                                    </a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                </nav>
            <?php } ?>
            <?php if (is_array($contentFiles ?? null) && $contentFiles !== []) { ?>
                <section class="content-downloads">
                    <h2><?php echo sr_e(sr_t('content::ui.text.0a4ca9bc')); ?></h2>
                    <ul>
                        <?php foreach ($contentFiles as $contentFile) { ?>
                            <li>
                                <?php if (empty($contentAdminPreview)) { ?>
                                    <?php $contentFileNeedsConfirmation = (int) ($contentFile['asset_download_enabled'] ?? 0) === 1 && sr_content_asset_policy_requires_confirmation((string) ($contentFile['asset_charge_policy'] ?? 'once')); ?>
                                    <?php if ($contentFileNeedsConfirmation) { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/content/download')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo sr_e((string) $contentFile['id']); ?>">
                                            <input type="hidden" name="content_id" value="<?php echo sr_e((string) (int) ($page['id'] ?? 0)); ?>">
                                            <button type="submit" class="btn btn-solid-light">
                                                <?php echo sr_e((string) $contentFile['title']); ?>
                                            </button>
                                        </form>
                                    <?php } else { ?>
                                        <a href="<?php echo sr_e(sr_url('/content/download?id=' . rawurlencode((string) $contentFile['id']) . '&content_id=' . rawurlencode((string) (int) ($page['id'] ?? 0)))); ?>">
                                            <?php echo sr_e((string) $contentFile['title']); ?>
                                        </a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span>
                                        <?php echo sr_e((string) $contentFile['title']); ?>
                                    </span>
                                <?php } ?>
                                <small>
                                    <?php echo sr_e((string) $contentFile['original_name']); ?>
                                    · <?php echo sr_e(sr_content_format_bytes((int) $contentFile['size_bytes'])); ?>
                                    <?php if ((int) ($contentFile['asset_download_enabled'] ?? 0) === 1) { ?>
                                        · <?php echo sr_e(sr_content_asset_module_labels((string) ($contentFile['asset_module'] ?? ''), $pdo)); ?>
                                        <?php echo sr_e(number_format((int) ($contentFile['asset_download_amount'] ?? 0))); ?>
                                    <?php } ?>
                                </small>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
            <?php if (empty($contentAdminPreview) && sr_content_asset_action_required($page)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/content/action')); ?>" class="content-action-form">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                    <button type="submit" class="btn btn-solid-primary">
                        <?php echo sr_e((string) ($page['asset_action_label'] ?? sr_t('content::ui.text.727333ab'))); ?>
                    </button>
                </form>
            <?php } ?>
        <?php } ?>
    </article>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'after_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_after_content_id'] ?? 0)); ?>
    <?php } ?>
</main>
<?php sr_public_layout_end(); ?>
