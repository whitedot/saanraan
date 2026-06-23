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
        <h2><?php echo sr_e('URL 임베딩'); ?></h2>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('기능 사용'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_url_embed_enabled', 'url_embed_enabled', '1', !empty($settings['url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('꺼져 있으면 본문 URL을 임베드 후보로 해석하지 않고 원래 링크나 텍스트를 그대로 출력합니다.'); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('내부 URL'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_internal_url_embed_enabled', 'internal_url_embed_enabled', '1', !empty($settings['internal_url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('콘텐츠, 커뮤니티, 퀴즈, 설문처럼 이 사이트 안의 공개 URL을 대상 모듈 renderer로 표시합니다.'); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('외부 URL'); ?></span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('embed_manager_external_url_embed_enabled', 'external_url_embed_enabled', '1', !empty($settings['external_url_embed_enabled']), '사용'); ?>
                <p class="form-help"><?php echo sr_e('외부 provider 계약과 개인정보/CSP 기준이 준비된 경우에만 켭니다. 기본 제공 범위에는 외부 provider가 없습니다.'); ?></p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2><?php echo sr_e('임베딩 범위'); ?></h2>
        <div class="form-row">
            <label class="form-label" for="embed_manager_embed_scope"><?php echo sr_e('후보 기준'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('embed_manager_embed_scope', 'embed_scope', [
                    'standalone_url_only' => '단독 URL만',
                    'all_supported_links' => '지원 링크 전체',
                ], (string) ($settings['embed_scope'] ?? 'standalone_url_only'), true); ?>
                <p class="form-help"><?php echo sr_e('단독 URL만은 한 줄 또는 한 블록이 URL 하나로 구성된 경우에만 임베드합니다. 지원 링크 전체는 라벨이 URL과 같은 링크도 임베드 후보로 봅니다. 의미 있는 라벨 링크는 그대로 둡니다.'); ?></p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
