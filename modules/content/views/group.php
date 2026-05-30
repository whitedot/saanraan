<?php

$seo = [
    'title' => (string) ($pageGroup['title'] ?? sr_t('content::ui.content.5875c5b3')),
    'description' => (string) ($pageGroup['description'] ?? ''),
    'canonical' => sr_content_group_path((string) ($pageGroup['group_key'] ?? '')),
    'og' => [
        'title' => (string) ($pageGroup['title'] ?? sr_t('content::ui.content.5875c5b3')),
        'description' => (string) ($pageGroup['description'] ?? ''),
        'type' => 'website',
    ],
];
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings));
?>

<main class="content-group">
    <section class="content-group-header">
        <p class="content-group-kicker"><?php echo sr_e(sr_t('content::ui.content.5875c5b3')); ?></p>
        <h1><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></h1>
        <?php if ((string) ($pageGroup['description'] ?? '') !== '') { ?>
            <p><?php echo nl2br(sr_e((string) $pageGroup['description'])); ?></p>
        <?php } ?>
    </section>

    <section class="content-group-list" aria-label="<?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?>">
        <?php if (($groupContents ?? []) === []) { ?>
            <p class="content-group-empty"><?php echo sr_e(sr_t('content::ui.content.e4d2d4f4')); ?></p>
        <?php } else { ?>
            <?php foreach ($groupContents as $groupContent) { ?>
                <?php $groupContentSlug = (string) ($groupContent['slug'] ?? ''); ?>
                <?php if (!sr_content_slug_is_valid($groupContentSlug)) { ?>
                    <?php continue; ?>
                <?php } ?>
                <article class="content-group-item">
                    <h2><a href="<?php echo sr_e(sr_url(sr_content_path($groupContentSlug))); ?>"><?php echo sr_e((string) ($groupContent['title'] ?? $groupContentSlug)); ?></a></h2>
                    <?php if ((string) ($groupContent['summary'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $groupContent['summary']); ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
</main>
<?php sr_public_layout_end(); ?>
