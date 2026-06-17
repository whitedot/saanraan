<?php

$homeSite = is_array($site ?? null) ? $site : [];
$homePdo = $pdo instanceof PDO ? $pdo : null;
$homeSiteName = sr_site_display_name($homeSite, $homePdo);
if (!function_exists('sr_admin_icon_render_icon') && is_file(SR_ROOT . '/modules/admin/helpers.php')) {
    require_once SR_ROOT . '/modules/admin/helpers.php';
}
$homeModuleIconHtml = static function (?PDO $pdo, string $moduleKey): string {
    $metadata = sr_module_metadata($moduleKey);
    $admin = isset($metadata['admin']) && is_array($metadata['admin']) ? $metadata['admin'] : [];
    $icon = $admin['icon'] ?? null;
    $symbolName = 'apps';

    if (is_string($icon)) {
        $symbolName = trim($icon);
    } elseif (is_array($icon)) {
        if ((string) ($icon['type'] ?? 'symbol') === 'asset' && function_exists('sr_admin_module_menu_asset_icon')) {
            $assetIcon = sr_admin_module_menu_asset_icon($moduleKey, $icon);
            $url = trim((string) ($assetIcon['url'] ?? ''));
            if ($url !== '') {
                return '<img class="public-home-module-card-icon-image" src="' . sr_e($url) . '" alt="">';
            }
        }

        $symbolName = trim((string) ($icon['name'] ?? $icon['symbol'] ?? $symbolName));
    }

    if ($symbolName === '') {
        $symbolName = 'apps';
    }

    if ($pdo instanceof PDO && function_exists('sr_admin_icon_render_icon')) {
        $renderIcon = sr_admin_icon_render_icon($pdo, $symbolName);
        if ((string) ($renderIcon['type'] ?? '') === 'asset') {
            $url = trim((string) ($renderIcon['url'] ?? ''));
            if ($url !== '') {
                return '<img class="public-home-module-card-icon-image" src="' . sr_e($url) . '" alt="">';
            }
        }

        return sr_material_icon_html((string) ($renderIcon['name'] ?? 'apps'));
    }

    return sr_icon($symbolName);
};
$homeModuleLinks = [];
$homeModuleLinkCandidates = [
    [
        'module_key' => 'content',
        'label' => '콘텐츠',
        'path' => '/content',
        'description' => '공개된 글과 자료를 한곳에서 확인합니다.',
    ],
    [
        'module_key' => 'community',
        'label' => '커뮤니티',
        'path' => '/community',
        'description' => '게시판과 모임 글을 둘러보고 참여합니다.',
    ],
    [
        'module_key' => 'quiz',
        'label' => '퀴즈',
        'path' => '/quiz',
        'description' => '공개된 퀴즈를 풀고 결과를 확인합니다.',
    ],
    [
        'module_key' => 'survey',
        'label' => '설문',
        'path' => '/survey',
        'description' => '진행 중인 설문에 참여하고 응답을 제출합니다.',
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
    'body_class' => 'public-layout-home',
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/theme.css',
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
                            <span class="public-home-module-card-icon" aria-hidden="true"><?php echo $homeModuleIconHtml($homePdo, (string) $homeModuleLink['module_key']); ?></span>
                            <span class="public-home-module-card-title"><?php echo sr_e((string) $homeModuleLink['label']); ?></span>
                            <span class="public-home-module-card-description"><?php echo sr_e((string) ($homeModuleLink['description'] ?? '')); ?></span>
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
