<?php

$seo = [
    'title' => (string) ($pageGroup['title'] ?? '페이지 그룹'),
    'description' => (string) ($pageGroup['description'] ?? ''),
    'canonical' => sr_page_group_path((string) ($pageGroup['group_key'] ?? '')),
    'og' => [
        'title' => (string) ($pageGroup['title'] ?? '페이지 그룹'),
        'description' => (string) ($pageGroup['description'] ?? ''),
        'type' => 'website',
    ],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>

<main class="page-group">
    <section class="page-group-header">
        <p class="page-group-kicker">페이지 그룹</p>
        <h1><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></h1>
        <?php if ((string) ($pageGroup['description'] ?? '') !== '') { ?>
            <p><?php echo nl2br(sr_e((string) $pageGroup['description'])); ?></p>
        <?php } ?>
    </section>

    <section class="page-group-list" aria-label="페이지 목록">
        <?php if (($groupPages ?? []) === []) { ?>
            <p class="page-group-empty">공개된 페이지가 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($groupPages as $groupPage) { ?>
                <?php $groupPageSlug = (string) ($groupPage['slug'] ?? ''); ?>
                <?php if (!sr_page_slug_is_valid($groupPageSlug)) { ?>
                    <?php continue; ?>
                <?php } ?>
                <article class="page-group-item">
                    <h2><a href="<?php echo sr_e(sr_url(sr_page_path($groupPageSlug))); ?>"><?php echo sr_e((string) ($groupPage['title'] ?? $groupPageSlug)); ?></a></h2>
                    <?php if ((string) ($groupPage['summary'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $groupPage['summary']); ?></p>
                    <?php } ?>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
</main>
<?php sr_public_layout_end(); ?>
