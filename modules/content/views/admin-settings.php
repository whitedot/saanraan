<?php

$adminPageTitle = '콘텐츠 환경설정';
$contentOnceHistoryPolicyHelpId = 'content_settings_help_once_history_policy';
$contentOnceHistoryPolicyHelpBody = '<p>유료 열람이나 다운로드를 최초 1회 결제로 운영할 때, 예전에 이용한 회원을 다시 결제시킬지 정합니다.</p>'
    . '<ul>'
    . '<li><strong>결제/쿠폰 이력</strong>: 포인트, 예치금, 적립금 결제나 쿠폰 이용 이력이 있으면 다시 결제하지 않습니다.</li>'
    . '<li><strong>결제 이력만</strong>: 포인트, 예치금, 적립금으로 결제한 이력만 인정하고 쿠폰 이용자는 다시 결제합니다.</li>'
    . '<li><strong>현재 결제수단 이력만</strong>: 지금 선택한 결제수단으로 최초 1회 결제한 이력만 인정합니다. 예를 들어 지금 포인트만 받으면 예전에 포인트로 결제한 회원만 다시 결제하지 않습니다.</li>'
    . '</ul>'
    . '<p>이 설정은 앞으로의 재결제 여부만 바꾸며, 기존 원장 거래와 쿠폰 사용 로그를 환불하거나 추가 차감하지 않습니다.</p>';
$contentSiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$contentSiteMenuSelectOptions = static function (string $selectedMenuKey) use ($contentSiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach ($contentSiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$contentLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/content/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <h2>작성 기본값</h2>
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_editor">에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="content_admin_settings_editor" name="editor" class="form-select" required>
                    <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                        <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo (string) ($settings['editor'] ?? 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $editorLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>공개 화면 구성</h2>
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_layout_key">기본 콘텐츠 레이아웃 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="content_admin_settings_layout_key" name="layout_key" class="form-select" required>
                    <?php foreach ($contentLayoutOptions as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">새 콘텐츠와 새 콘텐츠 그룹을 만들 때 먼저 채울 공개 레이아웃입니다. 기존 콘텐츠와 그룹은 자동 변경되지 않습니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_layout_primary_menu_key">주 메뉴 슬롯</label>
            <div class="admin-form-field">
                <select id="content_admin_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                    <?php $contentSiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                </select>
                <p class="admin-form-help">선택한 공개 레이아웃이 주 메뉴 슬롯을 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다. 사용 안 함이면 공개 가능한 콘텐츠 그룹이 주 메뉴 후보로 표시됩니다.</p>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>이용/과금 기준</h2>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_once_history_policy', '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpId, '설명 보기', true); ?>
            <div class="admin-form-field">
                <select id="content_admin_settings_once_history_policy" name="once_history_policy" class="form-select" required>
                    <?php foreach (sr_content_once_history_policy_values() as $policyKey => $policyLabel) { ?>
                        <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($settings['once_history_policy'] ?? 'all_access') === (string) $policyKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $policyLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">과금 방식을 최초 1회로 운영할 때 예전에 이용한 회원을 다시 결제시킬지 정합니다. 기존 원장 거래와 쿠폰 사용 로그는 자동 환불하거나 추가 차감하지 않습니다.</p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php echo sr_admin_help_modal_html($contentOnceHistoryPolicyHelpId, '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpBody); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
