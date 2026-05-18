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
    <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
        <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($page['popup_layer_id'] ?? 0)); ?>
    <?php } ?>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'page',
        'point_key' => 'page.view',
        'slot_key' => 'before_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_before_content_id'] ?? 0)); ?>
    <?php } ?>

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

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'page',
        'point_key' => 'page.view',
        'slot_key' => 'after_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_after_content_id'] ?? 0)); ?>
    <?php } ?>
</main>
<?php sr_public_layout_end(); ?>
