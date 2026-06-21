<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = $pdo instanceof PDO ? $pdo : null;
$homeSiteName = sr_site_display_name($homeSite, $homePdo);
$homeModuleLinks = [];
$homeModuleLinkCandidates = [
    [
        'module_key' => 'content',
        'label' => '콘텐츠',
        'path' => '/content',
    ],
    [
        'module_key' => 'community',
        'label' => '커뮤니티',
        'path' => '/community',
    ],
    [
        'module_key' => 'quiz',
        'label' => '퀴즈·테스트',
        'path' => '/quiz',
    ],
    [
        'module_key' => 'survey',
        'label' => '설문·여론조사',
        'path' => '/survey',
    ],
];
if ($homePdo instanceof PDO) {
    foreach ($homeModuleLinkCandidates as $homeModuleLinkCandidate) {
        $homeModuleKey = (string) ($homeModuleLinkCandidate['module_key'] ?? '');
        $homeModulePath = (string) ($homeModuleLinkCandidate['path'] ?? '');
        if (
            $homeModuleKey !== ''
            && $homeModulePath !== ''
            && sr_module_enabled($homePdo, $homeModuleKey)
            && sr_site_home_path_is_available($homePdo, $homeModulePath)
        ) {
            $homeModuleLinks[] = $homeModuleLinkCandidate;
        }
    }
}
$seo = [
    'title' => $homeSiteName,
    'canonical' => sr_canonical_url($homeSite, '/'),
];

sr_public_layout_begin($homePdo, $homeSite, $seo, [
    'body_class' => 'public-layout-home sr-site-home',
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/module.css',
        '/modules/banner/assets/module.css',
    ],
]);
?>
    <main class="public-ui-scope public-home">
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>
        <?php } ?>
        <section class="public-home-hero">
            <?php if ($homeModuleLinks !== []) { ?>
                <nav class="public-home-module-links" aria-label="<?php echo sr_e('초기 설치 모듈'); ?>">
                    <?php foreach ($homeModuleLinks as $homeModuleLink) { ?>
                        <a class="public-home-module-card" href="<?php echo sr_e(sr_url((string) $homeModuleLink['path'])); ?>">
                            <span class="public-home-module-card-title"><?php echo sr_e((string) $homeModuleLink['label']); ?></span>
                        </a>
                    <?php } ?>
                </nav>
            <?php } ?>
        </section>
        <?php if ($homePdo instanceof PDO) { ?>
            <?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
