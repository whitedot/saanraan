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
$pageLayoutKey = sr_public_layout_normalize_key((string) ($page['layout_key'] ?? ''));
if ($pageLayoutKey === '' || !isset(sr_public_layout_options($pdo ?? null)[$pageLayoutKey])) {
    $pageLayoutKey = sr_public_layout_key($site ?? null, $pdo ?? null);
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'layout_key' => $pageLayoutKey,
]);
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
        <?php if (empty($pageAccess['allowed'])) { ?>
            <div class="page-body">
                <p><?php echo sr_e((string) ($pageAccess['message'] ?? '페이지를 열람할 수 없습니다.')); ?></p>
            </div>
        <?php } else { ?>
            <?php if ((string) ($pageActionNotice ?? '') !== '') { ?>
                <p class="page-access-notice"><?php echo sr_e((string) $pageActionNotice); ?></p>
            <?php } ?>
            <?php if (is_array($pageActionErrors ?? null)) { ?>
                <?php foreach ($pageActionErrors as $pageActionError) { ?>
                    <p class="page-access-notice"><?php echo sr_e((string) $pageActionError); ?></p>
                <?php } ?>
            <?php } ?>
            <?php if (!empty($pageAccess['charged'])) { ?>
                <p class="page-access-notice">
                    <?php echo sr_e((string) ($pageAccess['asset_label'] ?? '회원 자산')); ?>
                    <?php echo sr_e(number_format((int) ($pageAccess['amount'] ?? 0))); ?> 차감 후 열람했습니다.
                </p>
            <?php } ?>
            <div class="page-body">
                <?php echo nl2br(sr_e((string) $page['body_text'])); ?>
            </div>
            <?php if (is_array($pageFiles ?? null) && $pageFiles !== []) { ?>
                <section class="page-downloads">
                    <h2>다운로드</h2>
                    <ul>
                        <?php foreach ($pageFiles as $pageFile) { ?>
                            <li>
                                <a href="<?php echo sr_e(sr_url('/pages/download?id=' . rawurlencode((string) $pageFile['id']))); ?>">
                                    <?php echo sr_e((string) $pageFile['title']); ?>
                                </a>
                                <small>
                                    <?php echo sr_e((string) $pageFile['original_name']); ?>
                                    · <?php echo sr_e(sr_page_format_bytes((int) $pageFile['size_bytes'])); ?>
                                    <?php if ((int) ($pageFile['asset_download_enabled'] ?? 0) === 1) { ?>
                                        · <?php echo sr_e(sr_page_asset_module_label((string) ($pageFile['asset_module'] ?? ''))); ?>
                                        <?php echo sr_e(number_format((int) ($pageFile['asset_download_amount'] ?? 0))); ?>
                                    <?php } ?>
                                </small>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
            <?php if (sr_page_asset_action_required($page)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/pages/action')); ?>" class="page-action-form">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="page_id" value="<?php echo sr_e((string) $page['id']); ?>">
                    <button type="submit" class="btn btn-solid-primary">
                        <?php echo sr_e((string) ($page['asset_action_label'] ?? '완료')); ?>
                    </button>
                </form>
            <?php } ?>
        <?php } ?>
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
