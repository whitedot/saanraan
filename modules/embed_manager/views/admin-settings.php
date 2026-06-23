<?php

$adminPageTitle = '환경설정';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-embed-manager-settings admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/embed-manager/settings');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/embed-manager/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2><?php echo sr_e('본문 주소 자동 표시'); ?></h2>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('기능 사용'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_url_embed_enabled', 'url_embed_enabled', '1', !empty($settings['url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('켜면 본문 안의 주소 중 표시 가능한 대상을 찾아 제목, 요약, 이미지 미리보기로 바꿉니다. 꺼져 있으면 원래 링크나 텍스트를 그대로 출력합니다.'); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('이 사이트 주소'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_internal_url_embed_enabled', 'internal_url_embed_enabled', '1', !empty($settings['internal_url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('현재 활성 모듈이 제공하는 URL 해석 계약을 읽어 이 사이트 안의 공개 주소를 미리보기로 표시합니다. 브라우저 주소창에서 복사한 절대 주소도 같은 사이트 주소면 내부 대상으로 처리합니다.'); ?></p>
                <?php if (($urlContractTargetLabels ?? []) !== []) { ?>
                    <p class="form-help"><?php echo sr_e('현재 해석 가능 대상: ' . implode(', ', (array) $urlContractTargetLabels)); ?></p>
                <?php } else { ?>
                    <p class="form-help"><?php echo sr_e('현재 활성화된 주소 해석 계약이 없습니다.'); ?></p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('다른 사이트 주소'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_external_url_embed_enabled', 'external_url_embed_enabled', '1', !empty($settings['external_url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('YouTube 같은 다른 사이트 주소를 별도 제공 모듈이 해석할 수 있을 때만 사용합니다. 현재 기본 번들은 다른 사이트 임베드를 제공하지 않습니다.'); ?></p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2><?php echo sr_e('자동 표시 범위'); ?></h2>
        <div class="form-row">
            <label class="form-label" for="embed_manager_embed_scope"><?php echo sr_e('바꿀 링크'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('embed_manager_embed_scope', 'embed_scope', [
                    'standalone_url_only' => '주소만 있는 줄',
                    'all_supported_links' => '주소와 같은 링크 글자까지',
                ], (string) ($settings['embed_scope'] ?? 'standalone_url_only'), true); ?>
                <p class="form-help"><?php echo sr_e('주소만 있는 줄은 본문 한 줄이나 한 블록이 https://example.com/post/1 같은 주소 하나로만 되어 있을 때 바꿉니다. 주소와 같은 링크 글자까지는 <a href>의 링크 글자가 실제 주소와 같을 때도 바꿉니다. 제목처럼 직접 쓴 링크 글자는 그대로 둡니다.'); ?></p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
