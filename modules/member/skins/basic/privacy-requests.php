<?php

$privacyAccountRequestsView = SR_ROOT . '/modules/privacy/views/account-privacy-requests.php';
if (
    isset($pdo)
    && $pdo instanceof PDO
    && function_exists('sr_module_enabled')
    && sr_module_enabled($pdo, 'privacy')
    && is_file($privacyAccountRequestsView)
) {
    include $privacyAccountRequestsView;
    return;
}

$pageTitle = '개인정보 안내';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">
                <p class="member-skin-basic-muted type-small">개인정보 요청 화면은 개인정보 모듈이 활성화된 경우 사용할 수 있습니다.</p>
                <div class="member-skin-basic-actions">
                    <a class="btn btn-outline-default btn-block" href="<?php echo sr_e(sr_url('/mypage/privacy')); ?>">개인정보 화면으로 이동</a>
                </div>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
