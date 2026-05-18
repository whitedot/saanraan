<?php

$seoTitle = (string) ($page['seo_title'] ?: $page['title']);
$seoDescription = (string) ($page['seo_description'] ?: $page['summary']);
$seo = [
    'title' => $seoTitle,
    'description' => $seoDescription,
    'canonical' => sr_page_path((string) $page['slug']),
    'og' => [
        'title' => $seoTitle,
        'description' => $seoDescription,
        'type' => 'article',
    ],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
<main class="page-public page-public-basic">
    <article class="page-content">
        <header class="page-header">
            <h1><?php echo sr_e((string) $page['title']); ?></h1>
            <?php if ((string) $page['summary'] !== '') { ?>
                <p><?php echo sr_e((string) $page['summary']); ?></p>
            <?php } ?>
        </header>
        <div class="page-body">
            <?php echo nl2br(sr_e((string) $page['body_text'])); ?>
        </div>
    </article>
</main>
<?php sr_public_layout_end(); ?>
