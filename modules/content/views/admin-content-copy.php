<?php

$adminPageTitle = '콘텐츠 복사';
$adminPageSubtitle = '원본 콘텐츠의 운영 설정과 링크 참조를 복사합니다.';
$adminContainerClass = 'admin-content-form admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts('', $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/content/copy')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e('복사 정보'); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="content_id" value="<?php echo sr_e((string) (int) $sourceContent['id']); ?>">
        <dl class="admin-meta-list">
            <dt><?php echo sr_e('원본 제목'); ?></dt>
            <dd><?php echo sr_e((string) $sourceContent['title']); ?></dd>
            <dt><?php echo sr_e('원본 slug'); ?></dt>
            <dd><code><?php echo sr_e((string) $sourceContent['slug']); ?></code></dd>
            <dt><?php echo sr_e('복사 상태'); ?></dt>
            <dd><?php echo sr_e('복사본은 초안으로 저장되며 발행 시각과 시리즈 연결은 복사하지 않습니다.'); ?></dd>
        </dl>
        <div class="admin-form-row">
            <label class="form-label" for="content_copy_title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="admin-form-field">
                <input id="content_copy_title" type="text" name="title" value="<?php echo sr_e((string) $values['title']); ?>" class="form-input form-control-full" maxlength="160" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_copy_slug"><?php echo sr_e('Slug'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="admin-form-field">
                <input id="content_copy_slug" type="text" name="slug" value="<?php echo sr_e((string) $values['slug']); ?>" class="form-input form-control-full" maxlength="120" pattern="[a-z0-9][a-z0-9\-]{1,118}[a-z0-9]" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-slug-input>
                <p class="admin-form-help"><?php echo sr_e('본문, 요약, 유료 열람/완료 버튼 설정, 배너/팝업 연결, SEO 입력값, 링크 참조를 복사합니다. 댓글, 열람/차감 로그, 리비전, 시리즈 연결은 복사하지 않습니다.'); ?></p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('복사본 만들기'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
