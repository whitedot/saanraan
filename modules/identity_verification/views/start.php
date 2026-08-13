<?php

$pageTitle = '본인확인 시작';
sr_public_layout_begin($pdo ?? null, $site ?? null, [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
]);
?>
    <main class="ui-page identity-verification-start-page">
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body ui-card-body-stack">
                <p><?php echo sr_e('외부 본인확인 제공자로 이동합니다. 계속하려면 아래 버튼을 눌러 주세요.'); ?></p>
                <form method="post" action="<?php echo sr_e(sr_url('/identity/verify/start')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="purpose" value="<?php echo sr_e($purpose); ?>">
                    <input type="hidden" name="return_url" value="<?php echo sr_e($returnUrl); ?>">
                    <?php if ($requestedProviderKey !== '') { ?>
                        <input type="hidden" name="provider_key" value="<?php echo sr_e($requestedProviderKey); ?>">
                    <?php } ?>
                    <?php if ($popupMode) { ?>
                        <input type="hidden" name="popup" value="1">
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary btn-block"><?php echo sr_e('본인확인 계속'); ?></button>
                </form>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
