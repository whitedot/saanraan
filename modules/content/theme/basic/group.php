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
$contentPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$contentGroupAccount = sr_member_current_account($pdo);
$contentGroupLayoutContext = ['consumer_target' => 'content.group'];
if (isset($pageGroupLayoutKey) && is_string($pageGroupLayoutKey) && $pageGroupLayoutKey !== '') {
    $contentGroupLayoutContext['layout_key'] = $pageGroupLayoutKey;
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, $contentGroupLayoutContext));
?>

<main class="content-group">
    <div class="content-group-shell">
        <section class="content-group-feed" aria-label="<?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?>">
            <header class="content-group-header">
                <p class="content-group-kicker"><?php echo sr_e(sr_t('content::ui.content.5875c5b3')); ?></p>
                <h1><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></h1>
                <?php if ((string) ($pageGroup['description'] ?? '') !== '') { ?>
                    <p><?php echo nl2br(sr_e((string) $pageGroup['description'])); ?></p>
                <?php } ?>
            </header>
            <nav class="content-group-tabs" aria-label="<?php echo sr_e('콘텐츠 보기'); ?>">
                <span aria-current="page"><?php echo sr_e('For you'); ?></span>
                <span><?php echo sr_e('Featured'); ?></span>
            </nav>

            <div class="content-group-list">
                <?php if (($groupContents ?? []) === []) { ?>
                    <p class="content-group-empty"><?php echo sr_e(sr_t('content::ui.content.e4d2d4f4')); ?></p>
                <?php } else { ?>
                    <?php foreach ($groupContents as $groupContent) { ?>
                        <?php $groupContentSlug = (string) ($groupContent['slug'] ?? ''); ?>
                        <?php if (!sr_content_slug_is_valid($groupContentSlug)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <?php
                        $groupContentDateText = (string) ($groupContent['published_at'] ?? '') !== ''
                            ? (string) $groupContent['published_at']
                            : (string) ($groupContent['updated_at'] ?? '');
                        $groupContentAccess = sr_content_entry_access_context($pdo, $groupContent, is_array($contentGroupAccount) ? $contentGroupAccount : null, 'group_item_' . (string) (int) ($groupContent['id'] ?? 0));
                        ?>
                        <article class="content-group-item">
                            <div class="content-group-item-main">
                                <p class="content-group-item-meta">
                                    <span><?php echo sr_e($contentPublisherName); ?></span>
                                    <?php if ($groupContentDateText !== '') { ?>
                                        <span><?php echo sr_content_time_html($groupContentDateText); ?></span>
                                    <?php } ?>
                                </p>
                                <h2><a<?php echo sr_content_entry_link_attributes($groupContentAccess); ?>><?php echo sr_e((string) ($groupContent['title'] ?? $groupContentSlug)); ?></a></h2>
                                <?php if ((string) ($groupContent['summary'] ?? '') !== '') { ?>
                                    <p class="content-group-item-summary"><?php echo sr_e((string) $groupContent['summary']); ?></p>
                                <?php } ?>
                            </div>
                            <a<?php echo sr_content_entry_link_attributes($groupContentAccess, 'content-group-item-thumb', (string) ($groupContent['title'] ?? $groupContentSlug)); ?>>
                                <?php echo sr_content_cover_image_html($groupContent, 'content-group-item-image', (string) ($groupContent['title'] ?? $groupContentSlug)); ?>
                            </a>
                            <?php echo sr_content_entry_access_modal($pdo, $groupContent, $groupContentAccess); ?>
                        </article>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <aside class="content-group-aside" aria-label="<?php echo sr_e('콘텐츠 추천'); ?>">
            <section>
                <h2><?php echo sr_e('Staff Picks'); ?></h2>
                <?php foreach (array_slice($groupContents ?? [], 0, 3) as $pickedContent) { ?>
                    <?php $pickedSlug = (string) ($pickedContent['slug'] ?? ''); ?>
                    <?php if (!sr_content_slug_is_valid($pickedSlug)) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <?php $pickedContentAccess = sr_content_entry_access_context($pdo, $pickedContent, is_array($contentGroupAccount) ? $contentGroupAccount : null, 'group_pick_' . (string) (int) ($pickedContent['id'] ?? 0)); ?>
                    <article class="content-group-pick">
                        <p><?php echo sr_e('In ' . (string) ($pageGroup['title'] ?? 'Content')); ?></p>
                        <h3><a<?php echo sr_content_entry_link_attributes($pickedContentAccess); ?>><?php echo sr_e((string) ($pickedContent['title'] ?? $pickedSlug)); ?></a></h3>
                        <?php echo sr_content_entry_access_modal($pdo, $pickedContent, $pickedContentAccess); ?>
                    </article>
                <?php } ?>
            </section>
        </aside>
    </div>
</main>
<?php sr_public_layout_end(); ?>
