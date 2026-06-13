<?php

$pageTitle = sr_t('privacy::ui.privacy.guidance.title');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <p><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.1')); ?></p>
        <p><?php echo sr_e(sr_t('privacy::ui.privacy.guidance.body.2')); ?></p>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('privacy::ui.text.13b28045')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
