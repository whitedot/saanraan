<?php

$contentHomeTitle = sr_t('content::ui.content.6c84a1b3');
$contentHomeDescription = '새로 공개된 콘텐츠를 한곳에서 읽습니다.';
$seo = [
    'title' => $contentHomeTitle,
    'description' => $contentHomeDescription,
    'canonical' => '/content',
    'og' => [
        'title' => $contentHomeTitle,
        'description' => $contentHomeDescription,
        'type' => 'website',
    ],
];
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
$contentPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$contentHomeAccount = sr_member_current_account($pdo);
$contentHomeContents = isset($contentHomeContents) && is_array($contentHomeContents) ? $contentHomeContents : [];
$contentHomeFeatured = $contentHomeContents[0] ?? null;
$contentHomeLatestList = array_slice($contentHomeContents, is_array($contentHomeFeatured) ? 1 : 0, 12);
$contentHomeDateText = static function (array $content): string {
    return (string) ($content['published_at'] ?? '') !== ''
        ? (string) $content['published_at']
        : (string) ($content['updated_at'] ?? '');
};
$contentHomeGroupText = static function (array $content) use ($contentPublisherName): string {
    $groupTitle = trim((string) ($content['content_group_title'] ?? ''));
    return $groupTitle !== '' ? $groupTitle : $contentPublisherName;
};
$contentHomeExcerptText = static function (array $content): string {
    $summary = trim(preg_replace('/\s+/u', ' ', (string) ($content['summary'] ?? '')) ?? '');
    if ($summary === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($summary, 'UTF-8') > 128 ? mb_substr($summary, 0, 128, 'UTF-8') . '...' : $summary;
    }

    return strlen($summary) > 180 ? substr($summary, 0, 180) . '...' : $summary;
};
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.home',
    'layout_key' => (string) ($contentHomeLayoutKey ?? ''),
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'content', 'point_key' => 'content.home', 'slot_key' => 'screen'],
    ],
]));
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'content',
    'point_key' => 'content.home',
    'slot_key' => 'screen',
]); ?>

<main class="content-home content-home-studio">
    <div class="content-home-studio-shell">
        <?php if (is_array($contentHomeFeatured)) { ?>
            <?php $contentHomeFeaturedSlug = (string) ($contentHomeFeatured['slug'] ?? ''); ?>
            <?php $contentHomeFeaturedAccess = sr_content_entry_access_context($pdo, $contentHomeFeatured, is_array($contentHomeAccount) ? $contentHomeAccount : null, 'home_featured'); ?>
            <article class="content-home-hero">
                <a<?php echo sr_content_entry_link_attributes($contentHomeFeaturedAccess, 'content-home-hero-media', (string) ($contentHomeFeatured['title'] ?? $contentHomeFeaturedSlug)); ?>>
                    <?php echo sr_content_cover_image_html($contentHomeFeatured, 'content-home-hero-image', (string) ($contentHomeFeatured['title'] ?? '')); ?>
                </a>
                <div class="content-home-hero-copy">
                    <p class="content-home-hero-meta">
                        <span><?php echo sr_e('By ' . $contentPublisherName); ?></span>
                        <?php if ($contentHomeDateText($contentHomeFeatured) !== '') { ?>
                            <span><?php echo sr_content_time_html($contentHomeDateText($contentHomeFeatured)); ?></span>
                        <?php } ?>
                    </p>
                    <p class="content-home-hero-group"><?php echo sr_e($contentHomeGroupText($contentHomeFeatured)); ?></p>
                    <h1><a<?php echo sr_content_entry_link_attributes($contentHomeFeaturedAccess); ?>><?php echo sr_e((string) ($contentHomeFeatured['title'] ?? $contentHomeFeaturedSlug)); ?></a></h1>
                    <?php if ((string) ($contentHomeFeatured['summary'] ?? '') !== '') { ?>
                        <p class="content-home-hero-summary"><?php echo sr_e((string) $contentHomeFeatured['summary']); ?></p>
                    <?php } ?>
                </div>
                <?php echo sr_content_entry_access_modal($pdo, $contentHomeFeatured, $contentHomeFeaturedAccess); ?>
            </article>
        <?php } else { ?>
            <section class="content-home-empty-hero">
                <h1><?php echo sr_e('공개된 콘텐츠가 없습니다.'); ?></h1>
                <p><?php echo sr_e('콘텐츠를 공개하면 이곳에 최신 글이 표시됩니다.'); ?></p>
            </section>
        <?php } ?>

        <section class="content-home-latest" aria-label="<?php echo sr_e('최근 콘텐츠'); ?>">
            <?php if ($contentHomeLatestList === []) { ?>
                <p class="content-home-empty"><?php echo sr_e('더 표시할 콘텐츠가 없습니다.'); ?></p>
            <?php } else { ?>
                <?php foreach ($contentHomeLatestList as $contentHomeItem) { ?>
                    <?php $contentHomeSlug = (string) ($contentHomeItem['slug'] ?? ''); ?>
                    <?php if (!sr_content_slug_is_valid($contentHomeSlug)) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <?php $contentHomeUrl = sr_url(sr_content_path($contentHomeSlug)); ?>
                    <?php $contentHomeAccess = sr_content_entry_access_context($pdo, $contentHomeItem, is_array($contentHomeAccount) ? $contentHomeAccount : null, 'home_latest_' . (string) (int) ($contentHomeItem['id'] ?? 0)); ?>
                    <?php $contentHomeExcerpt = $contentHomeExcerptText($contentHomeItem); ?>
                    <article class="content-home-latest-item card">
                        <a<?php echo sr_content_entry_link_attributes($contentHomeAccess, 'content-home-latest-media', (string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?>>
                            <?php echo sr_content_cover_image_html($contentHomeItem, 'content-home-latest-image card-img-top', (string) ($contentHomeItem['title'] ?? '')); ?>
                        </a>
                        <div class="content-home-latest-copy card-body">
                            <p class="content-home-latest-meta">
                                <span><?php echo sr_e($contentPublisherName); ?></span>
                            </p>
                            <h2>
                                <a<?php echo sr_content_entry_link_attributes($contentHomeAccess); ?>>
                                    <span><?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?></span>
                                </a>
                            </h2>
                            <?php if ($contentHomeExcerpt !== '') { ?>
                                <p class="content-home-latest-excerpt"><?php echo sr_e($contentHomeExcerpt); ?></p>
                            <?php } ?>
                            <div class="content-home-latest-footer">
                                <span><?php echo sr_e($contentHomeGroupText($contentHomeItem)); ?></span>
                                <?php if ($contentHomeDateText($contentHomeItem) !== '') { ?>
                                    <?php echo sr_content_time_html($contentHomeDateText($contentHomeItem)); ?>
                                <?php } ?>
                            </div>
                        </div>
                        <?php echo sr_content_entry_access_modal($pdo, $contentHomeItem, $contentHomeAccess); ?>
                    </article>
                <?php } ?>
            <?php } ?>
        </section>
    </div>
</main>
<?php sr_public_layout_end(); ?>
