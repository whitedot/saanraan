<?php

$adminPageTitle = '환경설정';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-embed-manager-settings admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/embed-manager/settings');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/embed-manager/settings')); ?>" class="card admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e('URL 임베딩 설정'); ?></h2>
    </div>
    <div class="form-row">
        <label class="form-label" for="embed_manager_url_embed_enabled"><?php echo sr_e('URL 임베딩'); ?></label>
        <input id="embed_manager_url_embed_enabled" type="checkbox" name="url_embed_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['url_embed_enabled']) ? ' checked' : ''; ?>>
        <p class="form-help"><?php echo sr_e('꺼져 있으면 URL resolver와 renderer를 호출하지 않고 원래 링크를 출력합니다.'); ?></p>
    </div>
    <div class="form-row">
        <label class="form-label" for="embed_manager_internal_url_embed_enabled"><?php echo sr_e('내부 URL 임베딩'); ?></label>
        <input id="embed_manager_internal_url_embed_enabled" type="checkbox" name="internal_url_embed_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['internal_url_embed_enabled']) ? ' checked' : ''; ?>>
    </div>
    <div class="form-row">
        <label class="form-label" for="embed_manager_external_url_embed_enabled"><?php echo sr_e('외부 URL 임베딩'); ?></label>
        <input id="embed_manager_external_url_embed_enabled" type="checkbox" name="external_url_embed_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['external_url_embed_enabled']) ? ' checked' : ''; ?>>
        <p class="form-help"><?php echo sr_e('외부 provider 계약과 개인정보/CSP 기준이 준비된 경우에만 켭니다.'); ?></p>
    </div>
    <div class="form-row">
        <label class="form-label" for="embed_manager_embed_scope"><?php echo sr_e('임베딩 범위'); ?></label>
        <select id="embed_manager_embed_scope" name="embed_scope" class="form-select">
            <option value="standalone_url_only"<?php echo (string) ($settings['embed_scope'] ?? '') === 'standalone_url_only' ? ' selected' : ''; ?>><?php echo sr_e('단독 URL만'); ?></option>
            <option value="all_supported_links"<?php echo (string) ($settings['embed_scope'] ?? '') === 'all_supported_links' ? ' selected' : ''; ?>><?php echo sr_e('지원 링크 전체'); ?></option>
        </select>
        <p class="form-help"><?php echo sr_e('단독 URL만은 한 줄 또는 한 블록을 URL만으로 구성한 경우에 임베드합니다. 지원 링크 전체는 라벨이 URL인 링크도 임베드 후보로 봅니다. 의미 있는 라벨 링크는 보존합니다.'); ?></p>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
