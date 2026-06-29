<?php

$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
$contentHomeTitle = 'Sample View Theme Content';
$seo = [
    'title' => $contentHomeTitle,
    'description' => '콘텐츠 모듈 내부 sample view theme이 제공하는 메인 화면입니다.',
    'canonical' => '/content',
    'og' => [
        'title' => $contentHomeTitle,
        'description' => '콘텐츠 모듈 내부 sample view theme이 제공하는 메인 화면입니다.',
        'type' => 'website',
    ],
];
$contentHomeContents = isset($contentHomeContents) && is_array($contentHomeContents) ? $contentHomeContents : [];
$contentHomeGroups = isset($contentHomeGroups) && is_array($contentHomeGroups) ? $contentHomeGroups : [];
$contentHomeLayoutKey = isset($contentHomeLayoutKey) && is_string($contentHomeLayoutKey) ? $contentHomeLayoutKey : '';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.home',
    'layout_key' => $contentHomeLayoutKey,
]));
?>

<main class="example-content-theme example-content-theme-home" data-example-theme-view="content.home">
    <section class="example-content-hero" aria-labelledby="example_content_home_title">
        <p class="example-content-kicker">CONTENT MODULE VIEW THEME</p>
        <h1 id="example_content_home_title"><?php echo sr_e($contentHomeTitle); ?></h1>
        <p>이 화면은 <code>modules/content/views/home.php</code>가 아니라 <code>modules/content/theme/sample/home.php</code>에서 렌더링됩니다.</p>
    </section>

    <section class="example-content-layout-grid" aria-label="콘텐츠 탐색">
        <nav class="example-content-panel" aria-label="콘텐츠 그룹">
            <h2>Groups</h2>
            <?php if ($contentHomeGroups === []) { ?>
                <p>표시할 그룹이 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($contentHomeGroups as $contentHomeGroup) { ?>
                        <?php $contentHomeGroupKey = (string) ($contentHomeGroup['group_key'] ?? ''); ?>
                        <?php if (!sr_content_group_key_is_valid($contentHomeGroupKey)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url(sr_content_group_path($contentHomeGroupKey))); ?>">
                                <?php echo sr_e((string) ($contentHomeGroup['title'] ?? $contentHomeGroupKey)); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </nav>

        <section class="example-content-panel example-content-index" aria-label="최근 콘텐츠">
            <h2>Latest</h2>
            <?php if ($contentHomeContents === []) { ?>
                <p>공개된 콘텐츠가 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($contentHomeContents as $contentHomeItem) { ?>
                        <?php $contentHomeSlug = (string) ($contentHomeItem['slug'] ?? ''); ?>
                        <?php if (!sr_content_slug_is_valid($contentHomeSlug)) { ?>
                            <?php continue; ?>
                        <?php } ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url(sr_content_path($contentHomeSlug))); ?>">
                                <strong><?php echo sr_e((string) ($contentHomeItem['title'] ?? $contentHomeSlug)); ?></strong>
                                <?php if ((string) ($contentHomeItem['summary'] ?? '') !== '') { ?>
                                    <span><?php echo sr_e((string) $contentHomeItem['summary']); ?></span>
                                <?php } ?>
                            </a>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>
        </section>
    </section>
</main>

<?php sr_public_layout_end(); ?>
