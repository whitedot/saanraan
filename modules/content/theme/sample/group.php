<?php

$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
$pageGroup = isset($pageGroup) && is_array($pageGroup) ? $pageGroup : [];
$groupContents = isset($groupContents) && is_array($groupContents) ? $groupContents : [];
$pageGroupLayoutKey = isset($pageGroupLayoutKey) && is_string($pageGroupLayoutKey) ? $pageGroupLayoutKey : '';
$groupTitle = (string) ($pageGroup['title'] ?? '콘텐츠 그룹');
$groupDescription = (string) ($pageGroup['description'] ?? '');
$seo = [
    'title' => $groupTitle,
    'description' => $groupDescription,
    'canonical' => sr_content_group_path((string) ($pageGroup['group_key'] ?? '')),
    'og' => [
        'title' => $groupTitle,
        'description' => $groupDescription,
        'type' => 'website',
    ],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.group',
    'layout_key' => $pageGroupLayoutKey,
]));
?>

<main class="example-content-theme example-content-theme-group" data-example-theme-view="content.group">
    <header class="example-content-hero">
        <p class="example-content-kicker">GROUP VIEW FROM THEME</p>
        <h1><?php echo sr_e($groupTitle); ?></h1>
        <?php if ($groupDescription !== '') { ?>
            <p><?php echo nl2br(sr_e($groupDescription)); ?></p>
        <?php } ?>
    </header>

    <section class="example-content-timeline" aria-label="그룹 콘텐츠">
        <?php if ($groupContents === []) { ?>
            <p class="example-content-panel">이 그룹에 공개된 콘텐츠가 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($groupContents as $groupContent) { ?>
                <?php $groupContentSlug = (string) ($groupContent['slug'] ?? ''); ?>
                <?php if (!sr_content_slug_is_valid($groupContentSlug)) { ?>
                    <?php continue; ?>
                <?php } ?>
                <article class="example-content-timeline-item">
                    <span class="example-content-timeline-marker" aria-hidden="true"></span>
                    <div>
                        <p class="example-content-kicker">
                            <?php echo sr_e((string) ($groupContent['published_at'] ?? '') !== '' ? sr_relative_time_label((string) $groupContent['published_at']) : '콘텐츠'); ?>
                        </p>
                        <h2>
                            <a href="<?php echo sr_e(sr_url(sr_content_path($groupContentSlug))); ?>">
                                <?php echo sr_e((string) ($groupContent['title'] ?? $groupContentSlug)); ?>
                            </a>
                        </h2>
                        <?php if ((string) ($groupContent['summary'] ?? '') !== '') { ?>
                            <p><?php echo sr_e((string) $groupContent['summary']); ?></p>
                        <?php } ?>
                    </div>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
</main>

<?php sr_public_layout_end(); ?>
