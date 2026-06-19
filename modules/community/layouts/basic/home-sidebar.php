<aside class="community-home-sidebar" aria-label="커뮤니티 보조 메뉴" data-community-home-accordion>
    <?php if (trim((string) ($homeSidebarMenuHtml ?? '')) !== '') { ?>
        <?php echo $homeSidebarMenuHtml; ?>
    <?php } else { ?>
        <p>보조 메뉴가 없습니다.</p>
    <?php } ?>
    <div class="community-home-secondary-banner-slot">
        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.home',
            'slot_key' => 'after_secondary_navigation',
        ]); ?>
    </div>
</aside>
