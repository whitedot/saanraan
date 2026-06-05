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
$contentPublisherName = trim((string) (($site ?? [])['name'] ?? ($site ?? [])['site_name'] ?? 'Saanraan'));
$contentPublisherName = $contentPublisherName !== '' ? $contentPublisherName : 'Saanraan';
$contentHomeContents = isset($contentHomeContents) && is_array($contentHomeContents) ? $contentHomeContents : [];
$contentHomeGroups = isset($contentHomeGroups) && is_array($contentHomeGroups) ? $contentHomeGroups : [];
$contentHomeComments = isset($contentHomeComments) && is_array($contentHomeComments) ? $contentHomeComments : [];
$contentHomeFeatured = $contentHomeContents[0] ?? null;
$contentHomeLatestList = array_slice($contentHomeContents, is_array($contentHomeFeatured) ? 1 : 0, 8);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'layout_key' => (string) ($contentHomeLayoutKey ?? ''),
]));
?>

<main class="content-home content-home-magazine">
    <div class="content-home-magazine-shell">
        <?php if ($contentHomeGroups !== []) { ?>
            <section class="content-home-topline" aria-label="<?php echo sr_e('콘텐츠 그룹'); ?>">
                <div class="content-home-topline-heading">
                    <p><?php echo sr_e('Latest'); ?></p>
                    <h1><?php echo sr_e('NEUIGKEITEN'); ?></h1>
                </div>
                <div class="content-home-group-grid">
                    <?php foreach (array_slice($contentHomeGroups, 0, 4) as $contentHomeGroup) { ?>
                        <?php $contentHomeGroupKey = (string) ($contentHomeGroup['group_key'] ?? ''); ?>
                        <?php if (!sr_content_group_key_is_valid($contentHomeGroupKey)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <article class="content-home-group-card">
                            <p>
                                <span><?php echo sr_e((string) ($contentHomeGroup['title'] ?? $contentHomeGroupKey)); ?></span>
                                <span><?php echo sr_e('View group'); ?></span>
                            </p>
                            <h2><a href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeGroupKey))); ?>"><?php echo sr_e((string) ($contentHomeGroup['description'] ?? '') !== '' ? (string) $contentHomeGroup['description'] : (string) ($contentHomeGroup['title'] ?? $contentHomeGroupKey)); ?></a></h2>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>

        <section class="content-home-feature" aria-label="<?php echo sr_e('최신 콘텐츠'); ?>">
            <div class="content-home-feature-title">
                <p><?php echo sr_e('NEWS AUS'); ?></p>
                <p><?php echo sr_e('UNSER'); ?></p>
                <p><?php echo sr_e('INDUSTRY'); ?></p>
            </div>
            <?php if (is_array($contentHomeFeatured)) { ?>
                <?php $contentHomeFeaturedSlug = (string) ($contentHomeFeatured['slug'] ?? ''); ?>
                <article class="content-home-feature-story">
                    <a class="content-home-feature-media" href="<?php echo sr_e(sr_url(sr_content_path($contentHomeFeaturedSlug))); ?>" aria-label="<?php echo sr_e((string) ($contentHomeFeatured['title'] ?? $contentHomeFeaturedSlug)); ?>">
                        <span></span>
                    </a>
                    <p class="content-home-eyebrow">
                        <span><?php echo sr_e((string) ($contentHomeFeatured['content_group_title'] ?? '') !== '' ? (string) $contentHomeFeatured['content_group_title'] : $contentPublisherName); ?></span>
                        <span><?php echo sr_e((string) ($contentHomeFeatured['published_at'] ?? '') !== '' ? substr((string) $contentHomeFeatured['published_at'], 0, 10) : substr((string) ($contentHomeFeatured['updated_at'] ?? ''), 0, 10)); ?></span>
                    </p>
                    <h2><a href="<?php echo sr_e(sr_url(sr_content_path($contentHomeFeaturedSlug))); ?>"><?php echo sr_e((string) ($contentHomeFeatured['title'] ?? $contentHomeFeaturedSlug)); ?></a></h2>
                    <?php if ((string) ($contentHomeFeatured['summary'] ?? '') !== '') { ?>
                        <p class="content-home-feature-summary"><?php echo sr_e((string) $contentHomeFeatured['summary']); ?></p>
                    <?php } ?>
                </article>
            <?php } else { ?>
                <article class="content-home-feature-story content-home-feature-empty">
                    <h2><?php echo sr_e('공개된 콘텐츠가 없습니다.'); ?></h2>
                </article>
            <?php } ?>
        </section>

        <section class="content-home-lower-grid" aria-label="<?php echo sr_e('콘텐츠 최신 흐름'); ?>">
            <aside class="content-home-comments-panel">
                <h2><?php echo sr_e('Latest comments'); ?></h2>
                <?php if ($contentHomeComments === []) { ?>
                    <p class="content-home-empty"><?php echo sr_e('아직 등록된 댓글이 없습니다.'); ?></p>
                <?php } else { ?>
                    <?php foreach (array_slice($contentHomeComments, 0, 4) as $contentHomeComment) { ?>
                        <?php $commentSlug = (string) ($contentHomeComment['content_slug'] ?? ''); ?>
                        <article class="content-home-comment">
                            <p><?php echo sr_e((string) ($contentHomeComment['author_public_name'] ?? '회원')); ?></p>
                            <a href="<?php echo sr_e(sr_url(sr_content_path($commentSlug)) . '#content-comments'); ?>"><?php echo sr_e((string) ($contentHomeComment['body_text'] ?? '')); ?></a>
                        </article>
                    <?php } ?>
                <?php } ?>
            </aside>

            <section class="content-home-latest-panel">
                <h2><?php echo sr_e('Latest stories'); ?></h2>
                <?php if ($contentHomeLatestList === []) { ?>
                    <p class="content-home-empty"><?php echo sr_e('더 표시할 콘텐츠가 없습니다.'); ?></p>
                <?php } else { ?>
                    <?php foreach ($contentHomeLatestList as $contentHomeItem) { ?>
                        <?php $contentHomeSlug = (string) ($contentHomeItem['slug'] ?? ''); ?>
                        <?php if (!sr_content_slug_is_valid($contentHomeSlug)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <article class="content-home-latest-item">
                            <p class="content-home-eyebrow">
                                <span><?php echo sr_e((string) ($contentHomeItem['content_group_title'] ?? '') !== '' ? (string) $contentHomeItem['content_group_title'] : $contentPublisherName); ?></span>
                                <span><?php echo sr_e((string) ($contentHomeItem['published_at'] ?? '') !== '' ? substr((string) $contentHomeItem['published_at'], 0, 10) : substr((string) ($contentHomeItem['updated_at'] ?? ''), 0, 10)); ?></span>
                            </p>
                            <h3><a href="<?php echo sr_e(sr_url(sr_content_path($contentHomeSlug))); ?>"><?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?></a></h3>
                            <?php if ((string) ($contentHomeItem['summary'] ?? '') !== '') { ?>
                                <p><?php echo sr_e((string) $contentHomeItem['summary']); ?></p>
                            <?php } ?>
                        </article>
                    <?php } ?>
                <?php } ?>
            </section>

            <aside class="content-home-notes-panel">
                <h2><?php echo sr_e('Briefing'); ?></h2>
                <article>
                    <p><?php echo sr_e('We collect signals from design, technology, product work, and the small operational decisions that shape better digital services.'); ?></p>
                    <p><?php echo sr_e('Expect sharp notes, practical patterns, and a slower look at what changed this week.'); ?></p>
                </article>
            </aside>
        </section>
    </div>
</main>
<?php sr_public_layout_end(); ?>
