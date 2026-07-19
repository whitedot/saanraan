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
$contentAuthorFallbackName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$contentHomeAccount = sr_member_current_account($pdo);
$contentHomeSections = isset($contentHomeSections) && is_array($contentHomeSections) ? $contentHomeSections : [];
$contentHomeMemberSettings = sr_member_settings($pdo);
$contentHomeProfileImageSizePixels = sr_member_profile_image_size_pixels('small', $contentHomeMemberSettings);
$contentHomeAuthorAccountIds = [];
foreach ($contentHomeSections as $contentHomeAuthorSection) {
    foreach ((array) ($contentHomeAuthorSection['contents'] ?? []) as $contentHomeAuthorItem) {
        $contentHomeAuthorAccountIds[] = (int) ($contentHomeAuthorItem['author_account_id'] ?? $contentHomeAuthorItem['created_by'] ?? 0);
    }
}
$contentHomeProfileImageSources = sr_member_public_profile_image_sources($pdo, $contentHomeAuthorAccountIds);
$contentHomeFollowStatuses = sr_member_follow_statuses($pdo, (int) ($contentHomeAccount['id'] ?? 0), $contentHomeAuthorAccountIds);
$contentHomeDateText = static function (array $content): string {
    return (string) ($content['published_at'] ?? '') !== ''
        ? (string) $content['published_at']
        : (string) ($content['updated_at'] ?? '');
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
$contentSidebarSubject = [];
$contentSidebarContext = sr_content_sidebar_context($pdo, $contentLayoutSettings, $contentSidebarSubject);
$contentHomeLayoutClass = 'content-home-layout' . (!empty($contentSidebarContext['enabled']) ? '' : ' content-home-layout-main-only');
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.home',
    'layout_key' => (string) ($contentHomeLayoutKey ?? ''),
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
    'scripts' => ['/modules/member/assets/profile-menu.js'],
    'output_slots' => [
        ['module_key' => 'content', 'point_key' => 'content.home', 'slot_key' => 'screen'],
        ['module_key' => 'content', 'point_key' => 'content.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
]));
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'content',
    'point_key' => 'content.home',
    'slot_key' => 'screen',
]); ?>

<main class="content-home content-home-screen">
    <div class="<?php echo sr_e($contentHomeLayoutClass); ?>">
        <section class="content-home-main" aria-label="최근 콘텐츠">
            <h1 class="sr-only"><?php echo sr_e($contentHomeTitle); ?></h1>
            <?php if ($contentHomeSections === []) { ?>
                <section class="card content-home-empty" aria-labelledby="content_home_empty_title">
                    <div class="card-body">
                        <h2 id="content_home_empty_title" class="card-title">공개된 콘텐츠가 없습니다.</h2>
                        <p>콘텐츠를 공개하면 이곳에 최신 글이 표시됩니다.</p>
                    </div>
                </section>
            <?php } else { ?>
                <div class="content-home-sections">
                    <?php foreach ($contentHomeSections as $contentHomeSectionIndex => $contentHomeSection) { ?>
                        <?php
                        $contentHomeSectionGrouped = !empty($contentHomeSection['is_grouped']);
                        $contentHomeSectionGroupKey = (string) ($contentHomeSection['group_key'] ?? '');
                        $contentHomeSectionGroupTitle = trim((string) ($contentHomeSection['group_title'] ?? ''));
                        $contentHomeSectionContents = is_array($contentHomeSection['contents'] ?? null) ? $contentHomeSection['contents'] : [];
                        $contentHomeSectionTitleId = 'content_home_section_' . (string) (int) $contentHomeSectionIndex;
                        ?>
                        <section class="content-home-section<?php echo $contentHomeSectionGrouped ? ' content-home-section-grouped' : ' content-home-section-ungrouped'; ?>" aria-labelledby="<?php echo sr_e($contentHomeSectionTitleId); ?>">
                            <header class="content-home-section-header">
                                <h2 id="<?php echo sr_e($contentHomeSectionTitleId); ?>" class="content-home-section-title">
                                    <?php if ($contentHomeSectionGrouped && sr_content_group_key_is_valid($contentHomeSectionGroupKey)) { ?>
                                        <a href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeSectionGroupKey))); ?>">
                                            <?php echo sr_e($contentHomeSectionGroupTitle !== '' ? $contentHomeSectionGroupTitle : $contentHomeSectionGroupKey); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo sr_e($contentHomeSectionGroupTitle !== '' ? $contentHomeSectionGroupTitle : '최근 콘텐츠'); ?>
                                    <?php } ?>
                                </h2>
                                <?php if ($contentHomeSectionGrouped && sr_content_group_key_is_valid($contentHomeSectionGroupKey)) { ?>
                                    <a class="content-home-section-more" href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeSectionGroupKey))); ?>">더 보기</a>
                                <?php } ?>
                            </header>

                            <div class="content-home-latest">
                                <?php foreach ($contentHomeSectionContents as $contentHomeItem) { ?>
                                    <?php $contentHomeSlug = (string) ($contentHomeItem['slug'] ?? ''); ?>
                                    <?php if (!sr_content_slug_is_valid($contentHomeSlug)) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php $contentHomeAccess = sr_content_entry_access_context($pdo, $contentHomeItem, is_array($contentHomeAccount) ? $contentHomeAccount : null, 'home_section_' . (string) (int) $contentHomeSectionIndex . '_' . (string) (int) ($contentHomeItem['id'] ?? 0)); ?>
                                    <?php $contentHomeExcerpt = $contentHomeExcerptText($contentHomeItem); ?>
                                    <?php $contentHomeAuthorAccountId = (int) ($contentHomeItem['author_account_id'] ?? $contentHomeItem['created_by'] ?? 0); ?>
                                    <?php $contentHomeAuthorName = sr_content_public_author_name($contentHomeItem, $contentHomeMemberSettings, $contentAuthorFallbackName); ?>
                                    <?php $contentHomeAuthorProfileImageHtml = sr_member_public_profile_image_html((string) ($contentHomeProfileImageSources[$contentHomeAuthorAccountId] ?? ''), 'content-list-author-profile-image', 'small', $contentHomeAuthorName, $contentHomeProfileImageSizePixels); ?>
                                    <article class="content-home-latest-item card">
                                        <a<?php echo sr_content_entry_link_attributes($contentHomeAccess, 'content-home-latest-media', (string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?>>
                                            <?php echo sr_content_cover_image_html($contentHomeItem, 'content-home-latest-image card-img-top', (string) ($contentHomeItem['title'] ?? '')); ?>
                                        </a>
                                        <div class="content-home-latest-copy card-body">
                                            <div class="content-home-latest-meta">
                                                <span class="content-list-author">
                                                    <?php echo $contentHomeAuthorProfileImageHtml; ?>
                                                    <?php echo sr_member_public_name_menu_html($pdo, is_array($contentHomeAccount) ? $contentHomeAccount : null, $contentHomeAuthorAccountId, $contentHomeAuthorName, [
                                                        'is_following' => (string) ($contentHomeFollowStatuses[$contentHomeAuthorAccountId] ?? '') === 'active',
                                                        'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/content'),
                                                    ]); ?>
                                                </span>
                                            </div>
                                            <h3><a<?php echo sr_content_entry_link_attributes($contentHomeAccess); ?>><?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?></a></h3>
                                            <?php if ($contentHomeExcerpt !== '') { ?>
                                                <p class="content-home-latest-excerpt"><?php echo sr_e($contentHomeExcerpt); ?></p>
                                            <?php } ?>
                                            <div class="content-home-latest-footer">
                                                <?php if ($contentHomeSectionGrouped && sr_content_group_key_is_valid($contentHomeSectionGroupKey)) { ?>
                                                    <a class="content-home-latest-group-link" href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeSectionGroupKey))); ?>"><?php echo sr_e($contentHomeSectionGroupTitle !== '' ? $contentHomeSectionGroupTitle : $contentHomeSectionGroupKey); ?></a>
                                                <?php } else { ?>
                                                    <span class="content-home-latest-group-link">그룹 없음</span>
                                                <?php } ?>
                                                <?php if ($contentHomeDateText($contentHomeItem) !== '') { ?>
                                                    <?php echo sr_content_time_html($contentHomeDateText($contentHomeItem)); ?>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <?php echo sr_content_entry_access_modal($pdo, $contentHomeItem, $contentHomeAccess); ?>
                                    </article>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <?php include SR_ROOT . '/modules/content/theme/basic/sidebar.php'; ?>
    </div>
</main>
<?php sr_public_layout_end(); ?>
