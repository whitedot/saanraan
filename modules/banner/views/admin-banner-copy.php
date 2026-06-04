<?php

$adminPageTitle = '배너 복사';
$adminPageSubtitle = '배너 내용과 노출 대상을 새 draft 배너로 복사합니다.';
$adminContainerClass = 'admin-banner-form admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts('', $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/banners/copy')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e('복사 정보'); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) (int) $sourceBanner['id']); ?>">
        <dl class="admin-meta-list">
            <dt><?php echo sr_e('원본 제목'); ?></dt>
            <dd><?php echo sr_e((string) $sourceBanner['title']); ?></dd>
            <dt><?php echo sr_e('복사 상태'); ?></dt>
            <dd><?php echo sr_e('복사본은 draft로 저장되며 클릭 수와 클릭 로그는 복사하지 않습니다.'); ?></dd>
        </dl>
        <div class="admin-form-row">
            <label class="form-label" for="banner_copy_title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="admin-form-field">
                <input id="banner_copy_title" type="text" name="title" value="<?php echo sr_e((string) $values['title']); ?>" class="form-input form-control-full" maxlength="160" required>
                <p class="admin-form-help"><?php echo sr_e('본문, 링크, 이미지 URL, 스킨, 기간, 정렬 순서와 노출 대상을 복사합니다.'); ?></p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('복사본 만들기'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
