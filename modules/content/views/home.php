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
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'layout_key' => (string) ($contentHomeLayoutKey ?? ''),
]));
?>

<main class="content-home">
    <div class="content-home-shell">
        <section class="content-home-feed" aria-label="<?php echo sr_e('최신 콘텐츠'); ?>">
            <nav class="content-group-tabs" aria-label="<?php echo sr_e('콘텐츠 보기'); ?>">
                <span aria-current="page"><?php echo sr_e('For you'); ?></span>
                <span><?php echo sr_e('Featured'); ?></span>
            </nav>

            <div class="content-group-list">
                <?php if (($contentHomeContents ?? []) === []) { ?>
                    <p class="content-group-empty">공개된 콘텐츠가 없습니다.</p>
                <?php } else { ?>
                    <?php foreach ($contentHomeContents as $contentHomeItem) { ?>
                        <?php $contentHomeSlug = (string) ($contentHomeItem['slug'] ?? ''); ?>
                        <?php if (!sr_content_slug_is_valid($contentHomeSlug)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <?php
                        $contentHomeDateText = (string) ($contentHomeItem['published_at'] ?? '') !== ''
                            ? substr((string) $contentHomeItem['published_at'], 0, 10)
                            : substr((string) ($contentHomeItem['updated_at'] ?? ''), 0, 10);
                        $contentHomeGroupTitle = (string) ($contentHomeItem['content_group_title'] ?? '');
                        $contentHomeThumbText = '';
                        ?>
                        <article class="content-group-item">
                            <div class="content-group-item-main">
                                <p class="content-group-item-meta">
                                    <span><?php echo sr_e($contentHomeGroupTitle !== '' ? $contentHomeGroupTitle : $contentPublisherName); ?></span>
                                    <?php if ($contentHomeDateText !== '') { ?>
                                        <span><?php echo sr_e($contentHomeDateText); ?></span>
                                    <?php } ?>
                                </p>
                                <h2><a href="<?php echo sr_e(sr_url(sr_content_path($contentHomeSlug))); ?>"><?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?></a></h2>
                                <?php if ((string) ($contentHomeItem['summary'] ?? '') !== '') { ?>
                                    <p class="content-group-item-summary"><?php echo sr_e((string) $contentHomeItem['summary']); ?></p>
                                <?php } ?>
                                <div class="content-group-item-actions" aria-label="<?php echo sr_e('콘텐츠 반응'); ?>">
                                    <span aria-hidden="true">✦</span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>waving_hand</span>
                                    <span><?php echo sr_e('17.2K'); ?></span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>mode_comment</span>
                                    <span><?php echo sr_e('800'); ?></span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>repeat</span>
                                    <span><?php echo sr_e('126'); ?></span>
                                    <span class="content-group-item-action-spacer"></span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chat_bubble</span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>bookmark_add</span>
                                    <span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>more_horiz</span>
                                </div>
                            </div>
                            <a class="content-group-item-thumb" href="<?php echo sr_e(sr_url(sr_content_path($contentHomeSlug))); ?>" aria-label="<?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?>">
                                <span><?php echo sr_e($contentHomeThumbText); ?></span>
                            </a>
                        </article>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <aside class="content-home-aside" aria-label="<?php echo sr_e('콘텐츠 탐색'); ?>">
            <section>
                <h2><?php echo sr_e('Topics'); ?></h2>
                <?php if (($contentHomeGroups ?? []) === []) { ?>
                    <p class="content-group-empty">사용 중인 콘텐츠 그룹이 없습니다.</p>
                <?php } else { ?>
                    <div class="content-home-topic-list">
                        <?php foreach ($contentHomeGroups as $contentHomeGroup) { ?>
                            <?php $contentHomeGroupKey = (string) ($contentHomeGroup['group_key'] ?? ''); ?>
                            <?php if (!sr_content_group_key_is_valid($contentHomeGroupKey)) { ?>
                                <?php continue; ?>
                            <?php } ?>
                            <a href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeGroupKey))); ?>"><?php echo sr_e((string) ($contentHomeGroup['title'] ?? $contentHomeGroupKey)); ?></a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>

            <section>
                <h2><?php echo sr_e('Staff Picks'); ?></h2>
                <?php foreach (array_slice($contentHomeContents ?? [], 0, 3) as $pickedContent) { ?>
                    <?php $pickedSlug = (string) ($pickedContent['slug'] ?? ''); ?>
                    <?php if (!sr_content_slug_is_valid($pickedSlug)) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <article class="content-group-pick">
                        <p><?php echo sr_e('In ' . ((string) ($pickedContent['content_group_title'] ?? '') !== '' ? (string) $pickedContent['content_group_title'] : $contentPublisherName)); ?></p>
                        <h3><a href="<?php echo sr_e(sr_url(sr_content_path($pickedSlug))); ?>"><?php echo sr_e((string) ($pickedContent['title'] ?? $pickedSlug)); ?></a></h3>
                    </article>
                <?php } ?>
            </section>

            <section>
                <h2><?php echo sr_e('Who to follow'); ?></h2>
                <?php foreach (array_slice($contentHomeContents ?? [], 0, 3) as $followContent) { ?>
                    <?php $followTitle = (string) ($followContent['title'] ?? $contentPublisherName); ?>
                    <article class="content-home-follow">
                        <span><?php echo sr_e(function_exists('mb_substr') ? mb_substr($followTitle, 0, 1) : substr($followTitle, 0, 1)); ?></span>
                        <div>
                            <h3><?php echo sr_e($followTitle); ?></h3>
                            <p><?php echo sr_e((string) ($followContent['summary'] ?? '')); ?></p>
                        </div>
                        <a href="<?php echo sr_e(sr_url('/content')); ?>"><?php echo sr_e('Follow'); ?></a>
                    </article>
                <?php } ?>
            </section>
        </aside>
    </div>
</main>
<?php sr_public_layout_end(); ?>
