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
$contentAuthorFallbackName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$contentGroupItems = isset($groupContents) && is_array($groupContents) ? $groupContents : [];
$contentGroupMemberSettings = sr_member_settings($pdo);
$contentGroupDateText = static function (array $content): string {
    return (string) ($content['published_at'] ?? '') !== ''
        ? (string) $content['published_at']
        : (string) ($content['updated_at'] ?? '');
};
$contentGroupExcerptText = static function (array $content): string {
    $summary = trim(preg_replace('/\s+/u', ' ', (string) ($content['summary'] ?? '')) ?? '');
    if ($summary === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($summary, 'UTF-8') > 128 ? mb_substr($summary, 0, 128, 'UTF-8') . '...' : $summary;
    }

    return strlen($summary) > 180 ? substr($summary, 0, 180) . '...' : $summary;
};
$contentSidebarSubject = ['group_key' => (string) ($pageGroup['group_key'] ?? '')];
$contentSidebarContext = sr_content_sidebar_context($pdo, $contentLayoutSettings, $contentSidebarSubject);
$contentGroupLayoutClass = 'content-home-layout' . (!empty($contentSidebarContext['enabled']) ? '' : ' content-home-layout-main-only');
$contentGroupLayoutContext = [
    'consumer_target' => 'content.group',
    'stylesheets' => (array) ($contentGroupPublicIdentityAssets['stylesheets'] ?? []),
    'scripts' => (array) ($contentGroupPublicIdentityAssets['scripts'] ?? []),
    'output_slots' => [
        ['module_key' => 'content', 'point_key' => 'content.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
];
if (isset($pageGroupLayoutKey) && is_string($pageGroupLayoutKey) && $pageGroupLayoutKey !== '') {
    $contentGroupLayoutContext['layout_key'] = $pageGroupLayoutKey;
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, $contentGroupLayoutContext));
?>

<main class="content-group content-home-screen">
    <div class="<?php echo sr_e($contentGroupLayoutClass); ?>">
        <section class="content-home-main content-group-main" aria-labelledby="content_group_title">
            <header class="content-group-card-header">
                <p class="content-group-kicker"><?php echo sr_e(sr_t('content::ui.content.5875c5b3')); ?></p>
                <h1 id="content_group_title"><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></h1>
                <?php if ((string) ($pageGroup['description'] ?? '') !== '') { ?>
                    <p><?php echo nl2br(sr_e((string) $pageGroup['description'])); ?></p>
                <?php } ?>
            </header>

            <?php if ($contentGroupItems === []) { ?>
                <section class="card content-home-empty" aria-label="콘텐츠 없음">
                    <div class="card-body">
                        <p><?php echo sr_e(sr_t('content::ui.content.e4d2d4f4')); ?></p>
                    </div>
                </section>
            <?php } else { ?>
                <div class="content-home-latest content-group-card-grid">
                    <?php foreach ($contentGroupItems as $groupContent) { ?>
                        <?php $groupContentSlug = (string) ($groupContent['slug'] ?? ''); ?>
                        <?php if (!sr_content_slug_is_valid($groupContentSlug)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <?php
                        $groupContentAccess = sr_content_entry_access_context($pdo, $groupContent, is_array($contentGroupAccount) ? $contentGroupAccount : null, 'group_item_' . (string) (int) ($groupContent['id'] ?? 0));
                        $groupContentExcerpt = $contentGroupExcerptText($groupContent);
                        $groupContentAuthorAccountId = (int) ($groupContent['author_account_id'] ?? $groupContent['created_by'] ?? 0);
                        $groupContentAuthorName = sr_content_public_author_name($groupContent, $contentGroupMemberSettings, $contentAuthorFallbackName);
                        $groupContentAuthorIdentity = sr_member_public_identity_parts($pdo, $contentGroupPublicIdentityContext, $groupContentAuthorAccountId, $groupContentAuthorName, [
                            'size' => 'small',
                            'image_class' => 'content-list-author-profile-image',
                            'menu_options' => [
                                'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? sr_content_group_path((string) ($pageGroup['group_key'] ?? ''))),
                            ],
                        ]);
                        ?>
                        <article class="content-home-latest-item card">
                            <a<?php echo sr_content_entry_link_attributes($groupContentAccess, 'content-home-latest-media', (string) ($groupContent['title'] ?? $groupContentSlug)); ?>>
                                <?php echo sr_content_cover_image_html($groupContent, 'content-home-latest-image card-img-top', (string) ($groupContent['title'] ?? '')); ?>
                            </a>
                            <div class="content-home-latest-copy card-body">
                                <div class="content-home-latest-meta">
                                    <span class="content-list-author">
                                        <?php echo $groupContentAuthorIdentity['profile_image_html']; ?>
                                        <?php echo $groupContentAuthorIdentity['name_html']; ?>
                                    </span>
                                </div>
                                <h2><a<?php echo sr_content_entry_link_attributes($groupContentAccess); ?>><?php echo sr_e((string) ($groupContent['title'] ?? $groupContentSlug)); ?></a></h2>
                                <?php if ($groupContentExcerpt !== '') { ?>
                                    <p class="content-home-latest-excerpt"><?php echo sr_e($groupContentExcerpt); ?></p>
                                <?php } ?>
                                <div class="content-home-latest-footer">
                                    <a class="content-home-latest-group-link" href="<?php echo sr_e(sr_url(sr_content_group_path((string) ($pageGroup['group_key'] ?? '')))); ?>"><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></a>
                                    <?php if ($contentGroupDateText($groupContent) !== '') { ?>
                                        <?php echo sr_content_time_html($contentGroupDateText($groupContent)); ?>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php echo sr_content_entry_access_modal($pdo, $groupContent, $groupContentAccess); ?>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <?php include SR_ROOT . '/modules/content/theme/basic/sidebar.php'; ?>
    </div>
</main>
<?php sr_public_layout_end(); ?>
