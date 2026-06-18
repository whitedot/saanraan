<?php

$pageTitle = sr_t('member::ui.email.ac61c51c');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => sr_member_skin_stylesheets($memberSkinKey),
]);
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">
                <p class="member-skin-basic-muted type-small"><?php echo sr_e(sr_t('member::ui.email.87682e59')); ?></p>
                <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('member::ui.text.13b28045')); ?></a></p>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
