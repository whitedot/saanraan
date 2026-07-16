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
$contentSettingsHelpOpenLabel = '도움말 보기';
$contentSettingsHelpButtonHtml = static function (string $label, string $modalId) use ($contentSettingsHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $contentSettingsHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$contentSettingsHelp = [
    'embed' => [
        'id' => 'content-settings-help-embed',
        'title' => '주소 자동 표시 도움말',
        'body' => '<p>임베드는 본문에 입력한 주소를 동영상 플레이어나 미리보기 카드처럼 바로 볼 수 있는 형태로 바꾸어 표시하는 기능입니다.</p>'
            . '<p>외부 서비스 자동 표시는 지원하는 YouTube, X, Instagram 주소에 적용합니다. 사이트 콘텐츠 자동 표시는 다른 모듈의 주소를 미리보기로 바꾸고, 이 콘텐츠의 주소도 다른 본문에서 미리보기로 사용할 수 있게 합니다.</p>'
            . '<p>설정을 꺼도 저장된 본문 주소는 삭제되지 않으며 공개 화면에서 자동 표시만 하지 않습니다.</p>',
    ],
    'auto_link' => [
        'id' => 'content-settings-help-auto-link',
        'title' => '일반 텍스트 URL 링크 도움말',
        'body' => '<p>일반 텍스트 입력 방식으로 저장한 본문에서 URL을 찾아 클릭할 수 있는 링크로 바꿉니다. 새 탭을 함께 켜면 이렇게 자동으로 만든 링크만 새 탭에서 엽니다.</p>'
            . '<p>Markdown이나 HTML 방식의 본문에 작성자가 직접 만든 링크에는 적용하지 않습니다. 설정을 바꿔도 저장된 본문 내용은 변경되지 않습니다.</p>',
    ],
    'appearance' => [
        'id' => 'content-settings-help-appearance',
        'title' => '공개 화면 스타일과 틀 도움말',
        'body' => '<p>화면 스타일은 콘텐츠 본문 안쪽의 글꼴, 색상, 버튼과 같은 보이는 모양을 정합니다.</p>'
            . '<p>화면 틀은 콘텐츠 화면 바깥쪽의 헤더, 푸터, 메뉴 배치처럼 전체 구조를 정합니다. 선택한 틀이 콘텐츠 화면을 지원해야 하며, 메뉴가 실제로 표시되는 위치는 화면 틀에 따라 달라집니다.</p>',
    ],
    'menus' => [
        'id' => 'content-settings-help-menus',
        'title' => '공개 화면 메뉴 도움말',
        'body' => '<p>주 메뉴는 화면 틀이 기본 메뉴 위치에 표시할 메뉴입니다. 추가 메뉴는 화면 틀이 여러 메뉴 위치를 지원할 때 함께 전달할 메뉴입니다.</p>'
            . '<p>추가 메뉴의 이름은 화면 틀이 각 메뉴 영역을 구분할 때 쓰는 값이며, 자동 식별값은 저장할 때 항목을 구분하는 값이라 직접 수정하지 않습니다. 실제 표시 위치와 지원 개수는 선택한 화면 틀에 따라 달라집니다.</p>',
    ],
    'reaction' => [
        'id' => 'content-settings-help-reaction',
        'title' => '기본 반응 구성 도움말',
        'body' => '<p>반응 구성은 좋아요 같은 반응 버튼의 종류와 표시 방식을 묶어 둔 설정이며, 리액션 모듈에서는 프리셋이라고 부릅니다.</p>'
            . '<p>콘텐츠나 댓글에서 별도 구성을 선택하지 않았을 때 이 값을 사용합니다. 개별 콘텐츠에서 반응 사용 안 함을 선택하면 이 기본값보다 우선합니다.</p>',
    ],
    'multi_asset' => [
        'id' => 'content-settings-help-multi-asset',
        'title' => '여러 포인트·금액 함께 결제 도움말',
        'body' => '<p>유료 열람이나 파일 다운로드 한 건을 결제할 때 포인트, 예치금, 적립금처럼 서로 다른 포인트·금액 항목을 함께 사용할 수 있게 합니다.</p>'
            . '<p>끄면 한 건의 결제에는 한 종류만 사용할 수 있습니다. 쿠폰으로 일부 할인한 뒤 남은 금액을 결제할 때도 같은 제한을 적용합니다.</p>',
    ],
    'submission' => [
        'id' => 'content-settings-help-submission',
        'title' => '회원 콘텐츠 제출 도움말',
        'body' => '<p>회원이 관리자 대신 콘텐츠 초안을 제출할 수 있게 하는 전체 사용 설정입니다.</p>'
            . '<p>이 설정만 켜서는 제출할 수 없습니다. 제출 대상 콘텐츠 그룹에서도 회원 제출을 허용해야 하며, 회원은 해당 그룹에서 요구하는 작성자 승인 또는 회원 그룹 조건을 만족해야 합니다.</p>',
    ],
    'review' => [
        'id' => 'content-settings-help-review',
        'title' => '제출 콘텐츠 기본 검수 도움말',
        'body' => '<p>회원이 제출한 콘텐츠를 바로 공개하지 않고 운영자 검수 대상으로 둘 기본값입니다.</p>'
            . '<p>콘텐츠 그룹에서 검수 방식을 따로 정하거나 작성자 권한에서 검수 필수·면제를 지정하면 해당 설정이 이 기본값보다 우선합니다.</p>',
    ],
    'reward' => [
        'id' => 'content-settings-help-reward',
        'title' => '제출 회원 보상 도움말',
        'body' => '<p>회원 제출본이 승인되어 콘텐츠로 공개될 때 제출 회원에게 아래에서 정한 보상을 한 번 지급합니다. 같은 제출본에는 중복 지급하지 않습니다.</p>'
            . '<p>보상 지급에 실패해도 콘텐츠 승인은 취소하지 않으며 실패 내용을 작성자 보상 로그에 남깁니다. 보상을 켜려면 지급할 포인트·금액 항목과 1 이상의 금액을 함께 설정해야 합니다.</p>',
    ],
];
$contentSiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$contentSiteMenuSelectOptions = static function (string $selectedMenuKey) use ($contentSiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach (sr_content_layout_menu_builtin_options() as $menuKey => $menuLabel) { ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e((string) $menuLabel); ?>
        </option>
    <?php } ?>
    <?php foreach ($contentSiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$contentLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : [];
$contentLayoutModuleReferences = [];
foreach ($contentLayoutOptions as $contentLayoutOption) {
    $providerModuleKey = is_array($contentLayoutOption) ? (string) ($contentLayoutOption['provider_module_key'] ?? '') : '';
    if ($providerModuleKey !== '' && $providerModuleKey !== 'content') {
        $contentLayoutModuleReferences[$providerModuleKey] = ['module_key' => $providerModuleKey];
    }
}
$contentSiteMenuModuleReferences = sr_module_enabled($pdo, 'site_menu')
    ? [['module_key' => 'site_menu']]
    : [];
$assetModuleOptions = isset($assetModuleOptions) && is_array($assetModuleOptions) ? $assetModuleOptions : [];
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
$contentReactionAvailable = isset($contentReactionAvailable)
    ? (bool) $contentReactionAvailable
    : (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php'));
$contentReactionInputAttributes = $contentReactionAvailable
    ? ''
    : ' disabled aria-describedby="content-settings-reaction-unavailable"';
$contentIdentityContentViewAvailable = isset($contentIdentityContentViewAvailable)
    ? (bool) $contentIdentityContentViewAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'content.view'));
$contentIdentityContentViewAdultAvailable = isset($contentIdentityContentViewAdultAvailable)
    ? (bool) $contentIdentityContentViewAdultAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'content.view.adult'));
$contentIdentityAuthorApplicationAvailable = isset($contentIdentityAuthorApplicationAvailable)
    ? (bool) $contentIdentityAuthorApplicationAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'content.author_application'));
$contentIdentityAuthorApplicationAdultAvailable = isset($contentIdentityAuthorApplicationAdultAvailable)
    ? (bool) $contentIdentityAuthorApplicationAdultAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'content.author_application.adult'));
$contentIdentityContentViewInputAttributes = $contentIdentityContentViewAvailable
    ? ''
    : ' disabled aria-describedby="content-settings-identity-unavailable"';
$contentIdentityContentViewAdultInputAttributes = $contentIdentityContentViewAdultAvailable
    ? ''
    : ' disabled aria-describedby="content-settings-identity-adult-unavailable"';
$contentIdentityAuthorApplicationInputAttributes = $contentIdentityAuthorApplicationAvailable
    ? ''
    : ' disabled aria-describedby="content-settings-author-identity-unavailable"';
$contentIdentityAuthorApplicationAdultInputAttributes = $contentIdentityAuthorApplicationAdultAvailable
    ? ''
    : ' disabled aria-describedby="content-settings-author-identity-adult-unavailable"';
$contentLayoutExtraMenuItems = function_exists('sr_content_layout_extra_menu_items_from_settings') ? sr_content_layout_extra_menu_items_from_settings($settings) : [];
if (is_array($adminFormDraft ?? null)) {
    $contentLayoutExtraMenuItems = sr_admin_form_draft_parallel_rows((array) $adminFormDraft['payload'], [
        'area_key' => 'layout_extra_menu_area_keys',
        'label' => 'layout_extra_menu_labels',
        'menu_key' => 'layout_extra_menu_keys',
    ]);
}
$contentLayoutExtraMenuRows = static function (array $menuItems, bool $template = false) use ($contentSiteMenuSelectOptions): void {
    foreach ($template ? [['area_key' => '', 'menu_key' => '']] : $menuItems as $menuItem) {
        $areaKey = is_array($menuItem) ? (string) ($menuItem['area_key'] ?? '') : '';
        $menuLabel = is_array($menuItem) ? (string) ($menuItem['label'] ?? '') : '';
        $selectedMenuKey = is_array($menuItem) ? (string) ($menuItem['menu_key'] ?? '') : (string) $menuItem;
        ?>
        <div class="admin-layout-menu-row"<?php echo $template ? ' hidden data-admin-layout-menu-template' : ''; ?> data-admin-layout-menu-row>
            <input type="text" name="layout_extra_menu_area_keys[]" value="<?php echo sr_e($areaKey); ?>" class="form-input admin-layout-menu-key-input" maxlength="60" pattern="(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="자동 식별값" aria-label="추가 메뉴 자동 식별값" readonly data-admin-layout-menu-key data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <input type="text" name="layout_extra_menu_labels[]" value="<?php echo sr_e($menuLabel); ?>" class="form-input" maxlength="80" placeholder="이름" aria-label="추가 메뉴 이름" data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <select name="layout_extra_menu_keys[]" class="form-select" data-admin-layout-menu-select data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
                <?php $contentSiteMenuSelectOptions((string) $selectedMenuKey); ?>
            </select>
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger admin-layout-menu-remove" data-admin-layout-menu-remove aria-label="추가 메뉴 제거" title="제거"><?php echo sr_material_icon_html('delete'); ?></button>
        </div>
        <?php
    }
};
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$contentSettingsSectionNavItems = [
    'content-settings-section-writing' => '작성 기본값',
    'content-settings-section-display' => '공개 화면',
    'content-settings-section-series' => '시리즈',
    'content-settings-section-reaction' => '리액션',
    'content-settings-section-access' => '이용/과금',
    'content-settings-section-submission' => '회원 제출',
    'content-comment-extra-fields-json-section' => '댓글 추가 입력',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="콘텐츠 설정 섹션">
    <?php $contentSettingsSectionNavIndex = 0; ?>
    <?php foreach ($contentSettingsSectionNavItems as $contentSettingsSectionId => $contentSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $contentSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $contentSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $contentSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $contentSettingsSectionLabel); ?>
        </a>
        <?php $contentSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form id="content-settings-form" method="post" action="<?php echo sr_e(sr_url('/admin/content/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <?php echo sr_admin_form_draft_status_html($adminFormDraft ?? null, 'content-settings-form'); ?>
    <section id="content-settings-section-writing" class="card" data-admin-section-anchor>
        <h2>작성 기본값</h2>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_editor">에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('content_admin_settings_editor', 'editor', $editorOptions, (string) ($settings['editor'] ?? 'textarea'), true); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_editor_toolbar_preset">툴바 구성 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="content_admin_settings_editor_toolbar_preset" name="editor_toolbar_preset" class="form-select" required>
                    <?php foreach ($toolbarPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['editor_toolbar_preset'] ?? 'content_basic') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">CKEditor를 사용할 때 콘텐츠 본문 입력 화면에 적용할 툴바입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_external_embed_enabled', '외부 서비스 자동 표시', $contentSettingsHelp['embed']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_external_embed_enabled', 'external_embed_enabled', '1', !empty($settings['external_embed_enabled']), '사용'); ?>
                <p class="form-help">본문에 단독으로 붙여 넣은 YouTube, X, Instagram 주소를 바로 볼 수 있게 표시합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_internal_embed_enabled', '사이트 콘텐츠 자동 표시', $contentSettingsHelp['embed']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_internal_embed_enabled', 'internal_embed_enabled', '1', !empty($settings['internal_embed_enabled']), '사용'); ?>
                <p class="form-help">사이트 안의 콘텐츠 주소를 본문에서 미리보기 형태로 표시합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">일반 텍스트 URL 링크</span>
            <div class="form-field">
                <div class="admin-content-url-link-switches">
                    <?php echo sr_admin_switch_html('content_admin_settings_plain_text_auto_link_urls', 'plain_text_auto_link_urls', '1', !empty($settings['plain_text_auto_link_urls']), '사용'); ?>
                    <?php echo sr_admin_switch_html('content_admin_settings_plain_text_auto_link_new_tab', 'plain_text_auto_link_new_tab', '1', !empty($settings['plain_text_auto_link_new_tab']), '새 탭'); ?>
                </div>
                <p class="form-help">일반 텍스트 본문의 URL을 링크로 바꾸고, 필요하면 새 탭에서 엽니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">비밀 댓글</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_secret_comments_enabled', 'secret_comments_enabled', '1', !empty($settings['secret_comments_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 새 댓글 작성/수정 요청의 비밀 댓글 값은 저장하지 않습니다.</p>
            </div>
        </div>
    </section>

    <section id="content-settings-section-display" class="card" data-admin-section-anchor>
        <h2>공개 화면 구성</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_theme_key', '기본 화면 스타일', $contentSettingsHelp['appearance']['id'], $contentSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="content_admin_settings_theme_key" name="theme_key" class="form-select" required>
                    <?php foreach ($publicThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">콘텐츠 본문 안쪽의 색상과 구성 요소 모양을 정합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_layout_key', '콘텐츠 화면 틀', $contentSettingsHelp['appearance']['id'], $contentSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="content_admin_settings_layout_key" name="layout_key" class="form-select" required>
                    <?php foreach ($contentLayoutOptions as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">콘텐츠 화면의 헤더, 푸터와 메뉴 배치를 정합니다.</p>
                <?php echo sr_admin_module_reference_list_html($pdo, $contentLayoutModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_layout_primary_menu_key', '주 메뉴', $contentSettingsHelp['menus']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="content_admin_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                    <?php $contentSiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                </select>
                <p class="form-help">콘텐츠 화면 틀의 기본 메뉴 위치에 표시할 메뉴를 정합니다.</p>
                <?php echo sr_admin_module_reference_list_html($pdo, $contentSiteMenuModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">추가 메뉴</span>
            <div class="form-field">
                <div class="admin-layout-menu-list" data-admin-layout-menu-list>
                    <div class="admin-layout-menu-header"<?php echo $contentLayoutExtraMenuItems === [] ? ' hidden' : ''; ?> aria-hidden="true" data-admin-layout-menu-header>
                        <span>자동 식별값</span>
                        <span>이름</span>
                        <span>메뉴</span>
                        <span>동작</span>
                    </div>
                    <?php $contentLayoutExtraMenuRows($contentLayoutExtraMenuItems); ?>
                    <?php $contentLayoutExtraMenuRows([], true); ?>
                    <div class="admin-layout-menu-actions" data-admin-layout-menu-actions>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-layout-menu-add><?php echo sr_material_icon_html('add'); ?> 추가 메뉴 추가</button>
                    </div>
                </div>
                <?php echo sr_admin_module_reference_list_html($pdo, $contentSiteMenuModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">사업자정보</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_business_info_visible', 'business_info_visible', '1', !empty($settings['business_info_visible']), '노출'); ?>
                <p class="form-help">사이트 설정에 저장된 사업자 정보 중 값이 있는 항목을 콘텐츠 공개 레이아웃 푸터에 표시합니다.</p>
            </div>
        </div>
    </section>

    <section id="content-settings-section-series" class="card" data-admin-section-anchor>
        <h2>시리즈</h2>
        <div class="form-row">
            <span class="form-label">시리즈 기능</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_series_enabled', 'series_enabled', '1', !empty($settings['series_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 콘텐츠 시리즈 생성, 연결, 관리와 공개 콘텐츠의 시리즈 내비게이션을 사용하지 않습니다.</p>
            </div>
        </div>
    </section>

    <section id="content-settings-section-reaction" class="card" data-admin-section-anchor>
        <h2>리액션</h2>
        <div class="form-row">
            <span class="form-label">리액션 사용 여부</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_reaction_enabled', 'reaction_enabled', '1', $contentReactionAvailable && !empty($settings['reaction_enabled']), '사용', '', $contentReactionInputAttributes); ?>
                <p class="form-help">꺼져 있으면 콘텐츠와 댓글의 리액션 위젯을 표시하지 않습니다.</p>
                <?php if (!$contentReactionAvailable) { ?>
                    <p id="content-settings-reaction-unavailable" class="form-help form-help-warning"><a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">리액션 모듈</a>을 설치하고 활성화하면 리액션 설정을 사용할 수 있습니다.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_reaction_preset_key', '콘텐츠 기본 반응 구성', $contentSettingsHelp['reaction']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="content_admin_settings_reaction_preset_key" name="reaction_preset_key" class="form-select"<?php echo $contentReactionInputAttributes; ?>>
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">개별 콘텐츠에서 따로 선택하지 않았을 때 사용할 반응 버튼 구성입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_reaction_comment_preset_key', '댓글 기본 반응 구성', $contentSettingsHelp['reaction']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="content_admin_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select"<?php echo $contentReactionInputAttributes; ?>>
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>

    <section id="content-settings-section-access" class="card" data-admin-section-anchor>
        <h2>이용/과금 기준</h2>
        <div class="form-row">
            <span class="form-label">콘텐츠 열람 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_content_view_required', 'identity_content_view_required', '1', $contentIdentityContentViewAvailable && !empty($settings['identity_content_view_required']), '사용', '', $contentIdentityContentViewInputAttributes); ?>
                <p class="form-help">사용하면 공개 콘텐츠를 보려는 회원에게 본인확인을 요구합니다.</p>
                <?php if (!$contentIdentityContentViewAvailable) { ?>
                    <p id="content-settings-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 목적에 맞는 제공자가 준비되지 않은 항목은 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">콘텐츠 열람 성인 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_content_view_adult_required', 'identity_content_view_adult_required', '1', $contentIdentityContentViewAdultAvailable && !empty($settings['identity_content_view_adult_required']), '사용', '', $contentIdentityContentViewAdultInputAttributes); ?>
                <?php if ($contentIdentityContentViewAdultAvailable) { ?>
                    <p class="form-help form-help-info">사용하면 성인 여부가 확인된 회원만 공개 콘텐츠를 볼 수 있습니다.</p>
                <?php } else { ?>
                    <p id="content-settings-identity-adult-unavailable" class="form-help form-help-warning">현재 저장할 수 없습니다. <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 생년월일 사용을 켜고 성인 열람 목적 제공자를 설정하세요.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_multi_asset_payment_enabled', '여러 포인트·금액 함께 결제', $contentSettingsHelp['multi_asset']['id'], $contentSettingsHelpOpenLabel, false, true); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_multi_asset_payment_enabled', 'multi_asset_payment_enabled', '1', !empty($settings['multi_asset_payment_enabled']), '허용'); ?>
                <p class="form-help">한 건의 유료 열람이나 다운로드에 여러 포인트·금액 항목을 함께 쓸 수 있게 합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_once_history_policy', '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpId, '설명 보기', true, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('content_admin_settings_once_history_policy', 'once_history_policy', sr_content_once_history_policy_values(), (string) ($settings['once_history_policy'] ?? 'all_access'), true); ?>
                <p class="form-help">과금 방식을 최초 1회로 운영할 때 예전에 이용한 회원을 다시 결제시킬지 정합니다. 기존 원장 거래와 쿠폰 사용 로그는 자동 환불하거나 추가 차감하지 않습니다.</p>
            </div>
        </div>
    </section>
    <section id="content-settings-section-submission" class="card" data-admin-section-anchor>
        <h2>회원 콘텐츠 제출</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_member_submission_enabled', '회원 제출 기능', $contentSettingsHelp['submission']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_enabled', 'member_submission_enabled', '1', !empty($settings['member_submission_enabled']), '사용'); ?>
                <p class="form-help">콘텐츠 그룹별 허용과 회원의 작성 자격을 함께 확인합니다.</p>
            </div>
        </div>
        <?php $memberSubmissionEnabled = !empty($settings['member_submission_enabled']); ?>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">작성자 신청 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_author_application_required', 'identity_author_application_required', '1', $contentIdentityAuthorApplicationAvailable && !empty($settings['identity_author_application_required']), '사용', '', $contentIdentityAuthorApplicationInputAttributes); ?>
                <p class="form-help">사용하면 콘텐츠 작성자 신청 전에 본인확인을 요구합니다.</p>
                <?php if (!$contentIdentityAuthorApplicationAvailable) { ?>
                    <p id="content-settings-author-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 작성자 신청 목적 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">작성자 신청 성인 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_author_application_adult_required', 'identity_author_application_adult_required', '1', $contentIdentityAuthorApplicationAdultAvailable && !empty($settings['identity_author_application_adult_required']), '사용', '', $contentIdentityAuthorApplicationAdultInputAttributes); ?>
                <?php if ($contentIdentityAuthorApplicationAdultAvailable) { ?>
                    <p class="form-help form-help-info">사용하면 성인 여부가 확인된 회원만 콘텐츠 작성자 신청을 할 수 있습니다.</p>
                <?php } else { ?>
                    <p id="content-settings-author-identity-adult-unavailable" class="form-help form-help-warning">현재 저장할 수 없습니다. <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 생년월일 사용을 켜고 작성자 신청 성인 목적 제공자를 설정하세요.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <?php echo sr_admin_form_label_help_html('content_admin_settings_member_submission_default_review_required', '제출 콘텐츠 기본 검수', $contentSettingsHelp['review']['id'], $contentSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_default_review_required', 'member_submission_default_review_required', '1', !empty($settings['member_submission_default_review_required']), '필수'); ?>
                <p class="form-help">별도 설정이 없는 회원 제출본을 운영자 검수 대상으로 둡니다.</p>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <?php echo sr_admin_form_label_help_html('content_admin_settings_member_submission_author_reward_enabled', '제출 회원 보상', $contentSettingsHelp['reward']['id'], $contentSettingsHelpOpenLabel, false, true); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_author_reward_enabled', 'member_submission_author_reward_enabled', '1', !empty($settings['member_submission_author_reward_enabled']), '지급'); ?>
                <p class="form-help">제출본이 승인되어 공개될 때 제출 회원에게 한 번 지급합니다.</p>
            </div>
        </div>
        <?php $authorRewardAssetSelected = (string) ($settings['member_submission_author_reward_asset_module'] ?? '') !== ''; ?>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <label class="form-label" for="content_admin_settings_member_submission_author_reward_asset_module">보상 설정</label>
            <div class="form-field">
                <select id="content_admin_settings_member_submission_author_reward_asset_module" name="member_submission_author_reward_asset_module" class="form-select">
                    <option value="">선택안함</option>
                    <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                        <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) ($settings['member_submission_author_reward_asset_module'] ?? '') === (string) $assetModule ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($assetOption['label'] ?? $assetModule)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled" data-admin-visible-when-select="#content_admin_settings_member_submission_author_reward_asset_module"<?php echo $memberSubmissionEnabled && $authorRewardAssetSelected ? '' : ' hidden'; ?>>
            <label class="form-label" for="content_admin_settings_member_submission_author_reward_amount">작성자 보상 금액 <span class="sr-required-label" data-admin-required-label-when-visible<?php echo $memberSubmissionEnabled && $authorRewardAssetSelected ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <?php echo sr_content_asset_single_amount_input_group_html('member_submission_author_reward_amount', (int) ($settings['member_submission_author_reward_amount'] ?? 0), $assetModuleOptions, (string) ($settings['member_submission_author_reward_asset_module'] ?? ''), '작성자 보상 금액', 'content_admin_settings_member_submission_author_reward_amount', false, 'member_submission_author_reward_asset_module', ' data-admin-required-when-visible data-admin-clear-when-hidden="1"' . ($memberSubmissionEnabled && $authorRewardAssetSelected ? ' required' : '')); ?>
            </div>
        </div>
    </section>
    <?php echo sr_admin_comment_extra_fields_editor_html(
        'content_comment_extra_fields_json',
        'comment_extra_fields_json',
        $settings['comment_extra_fields_json'] ?? '[]',
        '댓글 추가 입력 항목',
        '새 콘텐츠 등록 화면을 열 때 미리 채워지는 항목입니다. 기존 콘텐츠에는 반영되지 않으며, 등록 화면에서 수정한 최종 값이 해당 콘텐츠에 저장됩니다.'
    ); ?>
    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary admin-form-final-save">저장</button>
        <button type="submit" name="admin_form_action" value="save_draft" class="btn btn-solid-light admin-form-draft-save" formnovalidate>임시저장</button>
        <?php if (is_array($adminFormDraft ?? null)) { ?>
            <button type="submit" name="admin_form_action" value="discard_draft" class="btn btn-outline-danger admin-form-draft-delete" formnovalidate>임시저장 삭제</button>
        <?php } ?>
    </div>
</form>
<?php echo sr_admin_form_draft_restore_script($adminFormDraft ?? null, 'content-settings-form'); ?>

<?php echo sr_admin_help_modal_html($contentOnceHistoryPolicyHelpId, '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpBody); ?>
<?php foreach ($contentSettingsHelp as $contentSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $contentSettingsHelpModal['id'], (string) $contentSettingsHelpModal['title'], (string) $contentSettingsHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
