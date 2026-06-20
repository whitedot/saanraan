<?php

$adminPageTitle = sr_t('admin::ui.settings.4738c9b6');
$siteSettingsHelpOpenLabel = sr_t('admin::settings.help.open');
$siteSettingsHelpBodyHtml = static function (array $translationKeys): string {
    $html = '';
    foreach ($translationKeys as $translationKey) {
        $html .= '<p>' . sr_e(sr_t('admin::' . $translationKey)) . '</p>';
    }

    return $html;
};
$siteSettingsHelp = [
    'base_url' => [
        'id' => 'admin-settings-base-url-help-modal',
        'title' => sr_t('admin::settings.help.base_url.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.base_url.body.1',
            'settings.help.base_url.body.2',
        ]),
    ],
    'timezone' => [
        'id' => 'admin-settings-timezone-help-modal',
        'title' => sr_t('admin::settings.help.timezone.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.timezone.body.1',
            'settings.help.timezone.body.2',
        ]),
    ],
    'default_locale' => [
        'id' => 'admin-settings-default-locale-help-modal',
        'title' => sr_t('admin::settings.help.default_locale.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.default_locale.body.1',
            'settings.help.default_locale.body.2',
        ]),
    ],
    'supported_locales' => [
        'id' => 'admin-settings-supported-locales-help-modal',
        'title' => sr_t('admin::settings.help.supported_locales.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.supported_locales.body.1',
            'settings.help.supported_locales.body.2',
        ]),
    ],
    'status' => [
        'id' => 'admin-settings-status-help-modal',
        'title' => sr_t('admin::settings.help.status.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.status.body.1',
            'settings.help.status.body.2',
            'settings.help.status.body.3',
            'settings.help.status.body.4',
        ]),
    ],
    'public_layout' => [
        'id' => 'admin-settings-public-layout-help-modal',
        'title' => sr_t('admin::settings.help.public_layout.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.public_layout.body.1',
            'settings.help.public_layout.body.2',
        ]),
    ],
    'home_path' => [
        'id' => 'admin-settings-home-path-help-modal',
        'title' => sr_t('admin::settings.help.home_path.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.home_path.body.1',
            'settings.help.home_path.body.2',
        ]),
    ],
    'member_only' => [
        'id' => 'admin-settings-member-only-help-modal',
        'title' => '회원 전용 모드',
        'body_html' => '<p>' . sr_e('켜면 비로그인 방문자가 공개 사이트 화면에 접근할 때 로그인 화면으로 이동합니다.') . '</p>'
            . '<p>' . sr_e('로그인 후에는 원래 요청한 내부 화면으로 돌아갑니다. 로그인, 가입, 비밀번호 재설정 같은 인증 화면은 계속 접근할 수 있습니다.') . '</p>',
    ],
    'business_info' => [
        'id' => 'admin-settings-business-info-help-modal',
        'title' => '사업자 정보',
        'body_html' => '<p>' . sr_e('상호, 대표자명, 사업자등록번호처럼 운영자가 공개 표시나 정책 문서에서 재사용할 수 있는 사업자 정보를 저장합니다.') . '</p>'
            . '<p>' . sr_e('통신판매신고업번호처럼 서비스에 필요한 항목이 더 있으면 항목 추가 버튼으로 직접 이름과 값을 입력하세요.') . '</p>',
    ],
    'admin_color_scheme' => [
        'id' => 'admin-settings-admin-color-scheme-help-modal',
        'title' => sr_t('admin::settings.help.ui_color_scheme.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.ui_color_scheme.body.1',
            'settings.help.ui_color_scheme.body.2',
        ]),
    ],
    'admin_skin' => [
        'id' => 'admin-settings-admin-skin-help-modal',
        'title' => sr_t('admin::settings.help.admin_skin.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.admin_skin.body.1',
            'settings.help.admin_skin.body.2',
        ]),
    ],
    'list_pagination_per_page' => [
        'id' => 'admin-settings-list-pagination-per-page-help-modal',
        'title' => sr_t('admin::settings.help.list_pagination_per_page.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.list_pagination_per_page.body.1',
            'settings.help.list_pagination_per_page.body.2',
        ]),
    ],
];
$businessInfoRows = sr_admin_business_info_form_rows($values);
$adminIconOverrideCount = 0;
$adminIconRows = [];
foreach ($adminIconDefaults as $adminIconSymbolName => $_adminIconDefaultMaterialName) {
    if (is_array($adminIconOverrides[$adminIconSymbolName] ?? null)) {
        $adminIconOverrideCount++;
    }
    $adminIconRows[(string) $adminIconSymbolName] = [
        'key' => (string) $adminIconSymbolName,
        'default_material_name' => (string) $_adminIconDefaultMaterialName,
        'is_default' => true,
    ];
}
foreach ($adminIconOverrides as $adminIconSymbolName => $adminIconCustom) {
    $adminIconSymbolName = (string) $adminIconSymbolName;
    if (isset($adminIconDefaults[$adminIconSymbolName]) || !sr_admin_custom_icon_key_is_valid($adminIconSymbolName)) {
        continue;
    }
    $adminIconOverrideCount++;
    if (is_array($adminIconCustom) && (string) ($adminIconCustom['type'] ?? '') === 'material') {
        $adminIconDefaultMaterialName = sr_material_icon_name((string) ($adminIconCustom['material_name'] ?? $adminIconSymbolName));
    } else {
        $adminIconDefaultMaterialName = sr_material_icon_name($adminIconSymbolName);
    }
    $adminIconRows[$adminIconSymbolName] = [
        'key' => $adminIconSymbolName,
        'default_material_name' => $adminIconDefaultMaterialName,
        'is_default' => false,
    ];
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (!$currentHomepageAvailable) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon">!</span>
        <div class="admin-notice-copy">
            <strong><?php echo sr_e(sr_t('admin::ui.save.active.794b5bfc')); ?></strong>
            <p><?php echo sr_e(sr_t('admin::ui.page.active.select.save.8a7dbcc6')); ?></p>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme" enctype="multipart/form-data">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <section class="card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.f6fc85bc')); ?></h2>
        <div class="form-row">
            <label class="form-label" for="admin_settings_name"><?php echo sr_e(sr_t('admin::ui.name.51f4c6af')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="form-field">
                <?php
                $siteNameReferenceModalId = 'site-name-reference-modal';
                $siteNameReferenceResult = isset($siteNameReadReferences) && is_array($siteNameReadReferences) ? $siteNameReadReferences : ['rows' => [], 'errors' => []];
                ?>
                <div class="admin-site-name-reference-line">
                    <input id="admin_settings_name" type="text" name="name" value="<?php echo sr_e($values['name']); ?>" class="form-input" maxlength="120" required>
                    <div class="admin-site-name-reference-action">
                        <?php echo sr_admin_read_reference_button_html($siteNameReferenceModalId, $siteNameReferenceResult); ?>
                    </div>
                </div>
                <p class="form-help"><?php echo sr_e('사이트명을 읽는 모듈 참조를 확인한 뒤 변경하세요.'); ?></p>
            </div>
        </div>
        <?php echo sr_admin_read_reference_modal_html($siteNameReferenceModalId, '사이트명 참조 현황', $siteNameReferenceResult); ?>
        <div class="form-row">
            <span class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('admin::ui.url.09f44187') . ' ' . $siteSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($siteSettingsHelp['base_url']['id']); ?>" data-overlay="#<?php echo sr_e($siteSettingsHelp['base_url']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <span><?php echo sr_e(sr_t('admin::ui.url.09f44187')); ?></span>
            </span>
            <div class="form-field">
                <?php if ($values['base_url'] !== '') { ?>
                    <code><?php echo sr_e($values['base_url']); ?></code>
                <?php } else { ?>
                    <span><?php echo sr_e(sr_t('admin::ui.settings.9182f8fe')); ?></span>
                <?php } ?>
                <p class="form-help"><?php echo sr_e(sr_t('admin::ui.search.active.admin.settings.7aedc357')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_timezone', sr_t('admin::ui.text.26e997a5'), $siteSettingsHelp['timezone']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="admin_settings_timezone" name="timezone" class="form-select" required>
                    <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                        <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($timezoneOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_default_locale', sr_t('admin::ui.locale.c7cd39b4'), $siteSettingsHelp['default_locale']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="admin_settings_default_locale" name="default_locale" class="form-select" required data-admin-default-locale-select>
                    <?php foreach ($localeOptions as $localeOption) { ?>
                        <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($localeOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_supported_locales', sr_t('admin::ui.locale.list.51d8e798'), $siteSettingsHelp['supported_locales']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php $selectedSupportedLocales = sr_supported_locales($values); ?>
                <?php $selectedSupportedLocaleMap = array_fill_keys($selectedSupportedLocales, true); ?>
                <div data-admin-required-checkbox-group data-admin-required-checkbox-message="지원 locale 목록은 최소 한 개 이상 선택하세요." data-admin-supported-locales>
                    <div id="admin_settings_supported_locales" class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                        <?php if ($localeOptions === []) { ?>
                            <span class="form-help"><?php echo sr_e(sr_t('admin::ui.locale.9d745a6e')); ?></span>
                        <?php } ?>
                        <?php foreach ($localeOptions as $localeIndex => $localeOption) { ?>
                            <?php
                            $localeOption = (string) $localeOption;
                            $localeInputId = 'admin_settings_supported_locales_' . (string) $localeIndex;
                            $localeIsDefault = $localeOption === (string) $values['default_locale'];
                            ?>
                            <span class="filtering-toggle-item">
                                <input id="<?php echo sr_e($localeInputId); ?>" type="checkbox" name="supported_locales[]" value="<?php echo sr_e($localeOption); ?>" class="form-choice-toggle-input sr-only"<?php echo isset($selectedSupportedLocaleMap[$localeOption]) ? ' checked' : ''; ?><?php echo $localeIsDefault ? ' disabled' : ''; ?> data-admin-supported-locale-option>
                                <label for="<?php echo sr_e($localeInputId); ?>" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html($localeOption); ?></label>
                            </span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">기본 통화</span>
            <div class="form-field">
                <code><?php echo sr_e((string) ($values['default_currency'] ?? 'KRW')); ?></code>
                <p class="form-help">설치 시 선택한 값이며 일반 설정 저장으로 변경되지 않습니다. 신규 가격/정책 row의 기본값일 뿐 기존 가격, 로그, 구매력 snapshot은 변환하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_status', sr_t('admin::ui.status.e4163930'), $siteSettingsHelp['status']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('admin_settings_status', 'status', ['active' => sr_t('admin::ui.text.0928a1b8'), 'maintenance' => sr_t('admin::ui.text.4fd02e48')], (string) $values['status'], true); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_member_only_enabled', '회원 전용 모드', $siteSettingsHelp['member_only']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('admin_settings_member_only_enabled', 'member_only_enabled', ['0' => '끄기', '1' => '켜기'], (string) ($values['member_only_enabled'] ?? '0'), true); ?>
                <p class="form-help">비로그인 방문자는 로그인 화면으로 이동하며, 로그인 후 원래 경로로 돌아갑니다.</p>
            </div>
        </div>
    </section>
    <section class="card">
        <h2>사업자 정보</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_business_info_label_0', '입력 항목', $siteSettingsHelp['business_info']['id'], $siteSettingsHelpOpenLabel, false); ?>
            <div class="form-field">
                <div class="admin-business-info-list" data-admin-business-info-list>
                    <?php foreach ($businessInfoRows as $businessInfoIndex => $businessInfoRow) { ?>
                        <?php
                        $businessInfoKey = (string) ($businessInfoRow['key'] ?? '');
                        $businessInfoLabel = (string) ($businessInfoRow['label'] ?? '');
                        $businessInfoValue = (string) ($businessInfoRow['value'] ?? '');
                        $businessInfoIsDefault = !empty($businessInfoRow['is_default']);
                        $businessInfoLabelId = 'admin_settings_business_info_label_' . (string) $businessInfoIndex;
                        $businessInfoValueId = 'admin_settings_business_info_value_' . (string) $businessInfoIndex;
                        ?>
                        <div class="admin-business-info-row" data-admin-business-info-row>
                            <input type="hidden" name="business_info_key[]" value="<?php echo sr_e($businessInfoKey); ?>">
                            <label class="sr-only" for="<?php echo sr_e($businessInfoLabelId); ?>">사업자 정보 항목명</label>
                            <?php if ($businessInfoIsDefault) { ?>
                                <input id="<?php echo sr_e($businessInfoLabelId); ?>" type="text" name="business_info_label[]" value="<?php echo sr_e($businessInfoLabel); ?>" class="form-input form-input-sm" readonly aria-readonly="true">
                            <?php } else { ?>
                                <input id="<?php echo sr_e($businessInfoLabelId); ?>" type="text" name="business_info_label[]" value="<?php echo sr_e($businessInfoLabel); ?>" class="form-input form-input-sm" maxlength="80" placeholder="항목명">
                            <?php } ?>
                            <label class="sr-only" for="<?php echo sr_e($businessInfoValueId); ?>"><?php echo sr_e($businessInfoLabel !== '' ? $businessInfoLabel : '사업자 정보 값'); ?></label>
                            <input id="<?php echo sr_e($businessInfoValueId); ?>" type="text" name="business_info_value[]" value="<?php echo sr_e($businessInfoValue); ?>" class="form-input form-input-sm" maxlength="255" placeholder="값">
                            <?php if ($businessInfoIsDefault) { ?>
                                <span class="admin-business-info-fixed">기본</span>
                            <?php } else { ?>
                                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-business-info-remove" aria-label="사업자 정보 항목 제거" title="제거" data-admin-business-info-remove>
                                    <?php echo sr_material_icon_html('delete'); ?>
                                </button>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="admin-business-info-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-business-info-add><?php echo sr_material_icon_html('add'); ?>항목 추가</button>
                </div>
                <p class="form-help">빈 값은 저장되지만 표시 여부는 각 공개 화면이나 정책 문서 템플릿에서 결정합니다. 필요 없는 추가 항목은 제거할 수 있습니다.</p>
            </div>
        </div>
    </section>
    <section class="card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.b5361f64')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_public_layout_key', sr_t('admin::ui.text.974e65f4'), $siteSettingsHelp['public_layout']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="admin_settings_public_layout_key" name="public_layout_key" class="form-select">
                                    <?php foreach ($publicLayoutOptions as $layoutKey => $layoutOption) { ?>
                                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_home_path', sr_t('admin::ui.text.214b5fb8'), $siteSettingsHelp['home_path']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="admin_settings_home_path" name="home_path" class="form-select">
                    <?php foreach ($homepageCandidates as $candidate) { ?>
                        <?php $candidatePath = (string) ($candidate['path'] ?? ''); ?>
                        <?php $candidateSelected = (string) ($values['home_path'] ?? '/') === $candidatePath; ?>
                        <option value="<?php echo sr_e($candidatePath); ?>"<?php echo $candidateSelected ? ' selected' : ''; ?><?php echo empty($candidate['available']) && !$candidateSelected ? ' disabled' : ''; ?>>
                            <?php echo sr_e((string) ($candidate['label'] ?? $candidatePath)); ?>
                            <?php echo $candidatePath !== '/' ? ' - ' . sr_e($candidatePath) : ''; ?>
                            <?php echo empty($candidate['available']) ? sr_e(sr_t('admin::ui.active.6e2fcb45')) : ''; ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e(sr_t('admin::ui.page.page.community.status.active.ee1178b4')); ?> 회원 전용 모드가 켜져 있으면 비로그인 방문자에게 초기화면 대신 로그인 화면이 먼저 표시됩니다.</p>
            </div>
        </div>
    </section>
    <section class="card">
        <h2><?php echo sr_e(sr_t('admin::settings.section.admin_screen')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_admin_color_scheme', sr_t('admin::ui.ui.cf6c41c6'), $siteSettingsHelp['admin_color_scheme']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('admin_settings_admin_color_scheme', 'admin_color_scheme', sr_color_scheme_options(), (string) $values['admin_color_scheme'], true, ' data-admin-color-scheme-select'); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_admin_skin_key', sr_t('admin::ui.admin.1465c5b7'), $siteSettingsHelp['admin_skin']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="admin_settings_admin_skin_key" name="admin_skin_key" class="form-select">
                    <?php foreach ($adminSkinOptions as $skinKey => $skinOption) { ?>
                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $adminSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_list_pagination_per_page', '페이징 기본수', $siteSettingsHelp['list_pagination_per_page']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_settings_list_pagination_per_page" type="number" name="list_pagination_per_page" value="<?php echo sr_e((string) $values['list_pagination_per_page']); ?>" class="form-input" min="10" max="500" required>
                    <span class="input-group-text">개</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.admin.menu.icon.8b29d6ef')); ?></span>
            <div class="form-field">
                <div class="admin-icon-settings-summary">
                    <span>Google Material Symbols</span>
                    <small><?php echo sr_e(number_format($adminIconOverrideCount)); ?>개 커스텀</small>
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-icon-key-settings-modal" data-overlay="#admin-icon-key-settings-modal">설정</button>
                </div>
                <p class="form-help">공용 아이콘 키를 Material 이름이나 업로드 이미지로 바꿀 수 있습니다.</p>
            </div>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-primary">
        <a href="<?php echo sr_e(sr_url('/')); ?>" class="btn btn-solid-light" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('admin::ui.text.b47e1675')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php if (!empty($currencyChangeCanChange)) { ?>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">위험! 기본 통화 변경</h2>
        <div class="card-actions">
            <?php if (!empty($currencyChangeCanSubmit)) { ?>
                <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-currency-change-modal" data-overlay="#admin-currency-change-modal"><?php echo sr_material_icon_html('currency_exchange'); ?>변경</button>
            <?php } else { ?>
                <span class="admin-status is-warning">변경 후보 없음</span>
            <?php } ?>
        </div>
    </div>
    <div class="admin-list-summary-row">
        <p class="admin-list-summary">현재 기본 통화는 <code><?php echo sr_e((string) $currencyChangeCurrentCurrency); ?></code>입니다. 기본 통화는 신규 가격/정책 row와 통화가 빠진 자산 구매력 계약의 fallback 기준입니다. 기존 가격, 거래 로그, 구매력 snapshot은 변환하지 않습니다.</p>
    </div>
    <?php if (!empty($currencyChangeImpactSummary['totals_by_currency'])) { ?>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>대상</th>
                        <th>컬럼</th>
                        <th>통화별 row</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($currencyChangeImpactSummary['rows'] ?? []) as $currencyImpactRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($currencyImpactRow['label'] ?? '')); ?></td>
                            <td><code><?php echo sr_e((string) ($currencyImpactRow['column'] ?? '')); ?></code></td>
                            <td>
                                <?php
                                $currencyDistributionParts = [];
                                foreach ((array) ($currencyImpactRow['distribution'] ?? []) as $currencyCode => $rowCount) {
                                    $currencyDistributionParts[] = (string) $currencyCode . ' ' . number_format((int) $rowCount);
                                }
                                ?>
                                <?php echo sr_e(implode(', ', $currencyDistributionParts)); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="admin-list-summary-row">
            <p class="admin-list-summary">현재 확인 가능한 가격/정산 통화 row가 없습니다. 그래도 이후 새 정책 기본값과 자산 구매력 fallback은 변경된 통화를 사용합니다.</p>
        </div>
    <?php } ?>
    <?php if (!empty($currencyChangeImpactSummary['asset_purchase_power'])) { ?>
        <div class="table-wrapper">
            <table class="table table-list table-sm">
                <thead>
                    <tr>
                        <th>자산</th>
                        <th>구매력</th>
                        <th>정산 통화</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) ($currencyChangeImpactSummary['asset_purchase_power'] ?? []) as $assetPowerRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($assetPowerRow['label'] ?? $assetPowerRow['module_key'] ?? '')); ?></td>
                            <td><?php echo sr_e(number_format((int) ($assetPowerRow['asset_units'] ?? 1)) . ' 단위 = ' . number_format((int) ($assetPowerRow['settlement_units'] ?? 1))); ?></td>
                            <td><code><?php echo sr_e((string) ($assetPowerRow['settlement_currency'] ?? '')); ?></code></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php if (!empty($currencyChangeCanSubmit)) { ?>
<div id="admin-currency-change-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-currency-change-modal-title" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="modal-content ui-form-theme" autocomplete="off" data-admin-currency-change-form data-current-currency="<?php echo sr_e((string) $currencyChangeCurrentCurrency); ?>">
            <div class="modal-header">
                <h3 id="admin-currency-change-modal-title" class="modal-title">위험! 기본 통화 변경</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-currency-change-modal">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="currency_change">
                <div class="form-row">
                    <label class="form-label" for="admin_settings_new_default_currency">새 기본 통화 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <select id="admin_settings_new_default_currency" name="new_default_currency" class="form-select" required data-admin-currency-change-target data-overlay-focus>
                            <?php foreach ($currencyChangeCurrencyOptions as $currencyCode) { ?>
                                <option value="<?php echo sr_e((string) $currencyCode); ?>"><?php echo sr_e((string) $currencyCode); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">현재 기본 통화는 <code><?php echo sr_e((string) $currencyChangeCurrentCurrency); ?></code>입니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_reason">변경 사유 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <textarea id="admin_settings_currency_change_reason" name="currency_change_reason" class="form-textarea" rows="3" maxlength="1000" required></textarea>
                        <p class="form-help">설치 초기에 잘못 선택한 통화를 정정하는 경우처럼 운영 맥락을 남깁니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_confirmation">확인 문구 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="admin_settings_currency_change_confirmation" type="text" name="currency_change_confirmation" class="form-input" maxlength="120" required autocomplete="one-time-code" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-admin-currency-change-confirmation>
                        <p class="form-help">아래 문구를 그대로 입력하세요: <code data-admin-currency-change-phrase><?php echo sr_e((string) $currencyChangeConfirmationPhrase); ?></code></p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_settings_currency_change_password">현재 비밀번호 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="admin_settings_currency_change_password" type="password" name="currency_change_password" class="form-input" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" required>
                        <p class="form-help">변경 직전에 서버가 통화 값, 확인 문구, 사유, 비밀번호를 다시 검증하고 감사 로그를 남깁니다.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-currency-change-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-outline-danger modal-action"><?php echo sr_material_icon_html('currency_exchange'); ?>기본 통화 변경</button>
            </div>
        </form>
    </div>
</div>
<?php } ?>
<?php } ?>

<div id="admin-icon-key-settings-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-icon-key-settings-modal-title" aria-hidden="true" inert data-overlay-stack="true">
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="modal-content ui-form-theme" enctype="multipart/form-data">
            <div class="modal-header">
                <h3 id="admin-icon-key-settings-modal-title" class="modal-title">공용 아이콘 설정</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-icon-key-settings-modal">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body admin-icon-key-modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="icon_settings">
                <div class="admin-icon-key-list" data-admin-icon-key-list>
                    <?php foreach ($adminIconRows as $adminIconRow) { ?>
                        <?php
                        $iconSymbolName = (string) ($adminIconRow['key'] ?? '');
                        $iconDefaultMaterialName = (string) ($adminIconRow['default_material_name'] ?? $iconSymbolName);
                        $iconIsDefault = !empty($adminIconRow['is_default']);
                        $iconCustom = is_array($adminIconOverrides[$iconSymbolName] ?? null) ? $adminIconOverrides[$iconSymbolName] : [];
                        $iconType = (string) ($iconCustom['type'] ?? 'material');
                        if (!in_array($iconType, ['material', 'image'], true)) {
                            $iconType = 'material';
                        }
                        $iconMaterialName = $iconType === 'material'
                            ? sr_material_icon_name((string) ($iconCustom['material_name'] ?? $iconDefaultMaterialName))
                            : sr_material_icon_name((string) $iconDefaultMaterialName);
                        $iconStorageReference = $iconType === 'image' ? (string) ($iconCustom['storage_reference'] ?? '') : '';
                        $iconHasImage = $iconStorageReference !== '' && sr_admin_icon_image_storage_reference($iconStorageReference) !== null;
                        $iconInputIdSuffix = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $iconSymbolName);
                        $iconTypeInputId = 'admin_icon_key_type_' . $iconInputIdSuffix;
                        $iconMaterialInputId = 'admin_icon_key_material_' . $iconInputIdSuffix;
                        $iconImageInputId = 'admin_settings_icon_image_' . $iconInputIdSuffix;
                        ?>
                        <div class="admin-icon-key-item" data-admin-icon-key-item data-admin-icon-key-default="<?php echo sr_e((string) $iconDefaultMaterialName); ?>">
                            <?php if ($iconIsDefault) { ?>
                                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-icon-key-reset" aria-label="<?php echo sr_e((string) $iconSymbolName); ?> 초기화" title="초기화" data-admin-icon-key-reset>
                                    <?php echo sr_material_icon_html('restart_alt'); ?>
                                </button>
                            <?php } else { ?>
                                <label class="btn btn-icon-xs btn-ghost-default admin-icon-key-delete" aria-label="<?php echo sr_e((string) $iconSymbolName); ?> 제거" title="제거">
                                    <input type="checkbox" name="icon_key_delete[<?php echo sr_e((string) $iconSymbolName); ?>]" value="1" data-admin-icon-key-delete>
                                    <?php echo sr_material_icon_html('delete'); ?>
                                </label>
                            <?php } ?>
                            <span class="admin-icon-key-name"><?php echo sr_e((string) $iconSymbolName); ?></span>
                            <span class="admin-icon-key-preview" data-admin-icon-key-preview>
                                <?php if ($iconHasImage) { ?>
                                    <img src="<?php echo sr_e(sr_url('/admin/icon-image?file=' . rawurlencode($iconStorageReference))); ?>" alt="">
                                <?php } else { ?>
                                    <?php echo sr_material_icon_html($iconMaterialName); ?>
                                <?php } ?>
                            </span>
                            <label class="sr-only" for="<?php echo sr_e($iconTypeInputId); ?>">표시 방식</label>
                            <select id="<?php echo sr_e($iconTypeInputId); ?>" name="icon_key_type[<?php echo sr_e((string) $iconSymbolName); ?>]" class="form-select form-select-sm" data-admin-icon-key-type>
                                <option value="material"<?php echo $iconType === 'material' ? ' selected' : ''; ?>>Material</option>
                                <option value="image"<?php echo $iconType === 'image' ? ' selected' : ''; ?>>이미지</option>
                            </select>
                            <label class="sr-only" for="<?php echo sr_e($iconMaterialInputId); ?>">Material 이름</label>
                            <input id="<?php echo sr_e($iconMaterialInputId); ?>" type="text" name="icon_key_material_name[<?php echo sr_e((string) $iconSymbolName); ?>]" value="<?php echo sr_e($iconMaterialName); ?>" class="form-input form-input-sm" maxlength="80" pattern="[a-z0-9_]+" data-admin-key-input data-admin-icon-key-material>
                            <label class="sr-only" for="<?php echo sr_e($iconImageInputId); ?>">이미지 파일</label>
                            <input id="<?php echo sr_e($iconImageInputId); ?>" type="file" name="icon_key_image_<?php echo sr_e((string) $iconSymbolName); ?>" accept="image/jpeg,image/png,image/gif,image/webp" class="form-input form-input-sm admin-icon-key-file" data-admin-icon-key-file>
                            <span class="admin-icon-key-file-name" data-admin-icon-key-file-name></span>
                            <?php if ($iconHasImage) { ?>
                                <?php echo sr_admin_checkbox_toggle_html('admin_icon_key_remove_image_' . $iconInputIdSuffix, 'icon_key_remove_image[' . (string) $iconSymbolName . ']', '1', false, '제거', ' data-admin-icon-key-remove'); ?>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="admin-icon-key-add">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-icon-key-add><?php echo sr_material_icon_html('add'); ?>키 추가</button>
                </div>
                <p class="form-help">Material 이름은 Google Material Symbols의 ligature 이름입니다. 이미지 업로드는 JPG, PNG, GIF, WebP / 최대 <?php echo sr_e(sr_admin_icon_format_bytes(sr_admin_icon_upload_max_bytes())); ?> / 512px 이하만 허용합니다.</p>
            </div>
            <div class="modal-footer-note">
                <p class="form-help">이 모달의 저장 버튼은 공용 아이콘 설정만 바로 저장합니다. 위 사이트 설정 form에 작성 중인 값은 함께 저장되지 않습니다.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-icon-key-settings-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.save.5fb92622')); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    function updateRequiredCheckboxGroup(root) {
        var checkboxes = Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]'));
        if (checkboxes.length === 0) {
            return;
        }

        var checked = checkboxes.some(function (checkbox) {
            return checkbox.checked;
        });
        var message = root.getAttribute('data-admin-required-checkbox-message') || '최소 한 개 이상 선택하세요.';
        checkboxes[0].setCustomValidity(checked ? '' : message);
    }

    document.querySelectorAll('[data-admin-required-checkbox-group]').forEach(function (root) {
        updateRequiredCheckboxGroup(root);
        root.addEventListener('change', function () {
            updateRequiredCheckboxGroup(root);
        });
    });

    var defaultLocaleSelect = document.querySelector('[data-admin-default-locale-select]');
    var supportedLocalesRoot = document.querySelector('[data-admin-supported-locales]');

    function syncDefaultSupportedLocale() {
        if (!defaultLocaleSelect || !supportedLocalesRoot) {
            return;
        }

        var defaultLocale = defaultLocaleSelect.value;
        supportedLocalesRoot.querySelectorAll('[data-admin-supported-locale-option]').forEach(function (checkbox) {
            var isDefault = checkbox.value === defaultLocale;
            checkbox.disabled = isDefault;
            if (isDefault) {
                checkbox.checked = true;
            }
        });
        updateRequiredCheckboxGroup(supportedLocalesRoot);
    }

    if (defaultLocaleSelect && supportedLocalesRoot) {
        syncDefaultSupportedLocale();
        defaultLocaleSelect.addEventListener('change', syncDefaultSupportedLocale);
    }

    var currencyChangeForm = document.querySelector('[data-admin-currency-change-form]');
    if (currencyChangeForm) {
        var currencyChangeTarget = currencyChangeForm.querySelector('[data-admin-currency-change-target]');
        var currencyChangePhrase = currencyChangeForm.querySelector('[data-admin-currency-change-phrase]');
        var currencyChangeCurrent = currencyChangeForm.getAttribute('data-current-currency') || '';

        function syncCurrencyChangePhrase() {
            if (!currencyChangeTarget || !currencyChangePhrase) {
                return;
            }
            currencyChangePhrase.textContent = currencyChangeCurrent + '에서 ' + currencyChangeTarget.value + '로 변경';
        }

        syncCurrencyChangePhrase();
        if (currencyChangeTarget) {
            currencyChangeTarget.addEventListener('change', syncCurrencyChangePhrase);
        }
    }

    function bindAdminIconKeyFile(input) {
        var item = input.closest('[data-admin-icon-key-item]');
        var fileName = item ? item.querySelector('[data-admin-icon-key-file-name]') : null;
        var preview = item ? item.querySelector('[data-admin-icon-key-preview]') : null;
        var defaultFileName = fileName ? fileName.textContent : '';

        if (preview) {
            preview.dataset.defaultHtml = preview.innerHTML;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files.length > 0 ? input.files[0] : null;
            var typeSelect = item ? item.querySelector('[data-admin-icon-key-type]') : null;

            if (preview && preview.dataset.previewUrl) {
                URL.revokeObjectURL(preview.dataset.previewUrl);
                delete preview.dataset.previewUrl;
            }

            if (fileName) {
                fileName.textContent = file ? file.name : defaultFileName;
            }

            if (!file) {
                if (preview) {
                    preview.innerHTML = preview.dataset.defaultHtml || '';
                }
                return;
            }

            if (typeSelect) {
                typeSelect.value = 'image';
            }

            if (!preview || !file.type || file.type.indexOf('image/') !== 0) {
                return;
            }

            var image = document.createElement('img');
            var previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
            image.alt = '';
            preview.dataset.previewUrl = previewUrl;
            preview.replaceChildren(image);
        });
    }

    document.querySelectorAll('[data-admin-icon-key-file]').forEach(bindAdminIconKeyFile);

    document.querySelectorAll('[data-admin-icon-key-remove]').forEach(function (input) {
        input.addEventListener('change', function () {
            var item = input.closest('[data-admin-icon-key-item]');
            if (!item || !input.checked) {
                return;
            }

            var fileInput = item.querySelector('[data-admin-icon-key-file]');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                return;
            }

            var defaultMaterialName = item.getAttribute('data-admin-icon-key-default') || 'folder';
            var typeSelect = item.querySelector('[data-admin-icon-key-type]');
            var materialInput = item.querySelector('[data-admin-icon-key-material]');
            var preview = item.querySelector('[data-admin-icon-key-preview]');

            if (typeSelect) {
                typeSelect.value = 'material';
            }

            if (materialInput && materialInput.value.trim() === '') {
                materialInput.value = defaultMaterialName;
            }

            if (preview) {
                if (preview.dataset.previewUrl) {
                    URL.revokeObjectURL(preview.dataset.previewUrl);
                    delete preview.dataset.previewUrl;
                }
                var icon = document.createElement('span');
                icon.className = 'sr-icon material-symbols-outlined';
                icon.setAttribute('aria-hidden', 'true');
                icon.setAttribute('data-sr-material-icon', '1');
                icon.textContent = materialInput ? materialInput.value : defaultMaterialName;
                preview.replaceChildren(icon);
            }
        });
    });

    document.querySelectorAll('[data-admin-icon-key-delete]').forEach(function (input) {
        input.addEventListener('change', function () {
            var item = input.closest('[data-admin-icon-key-item]');
            if (item) {
                item.classList.toggle('is-deleted', input.checked);
            }
        });
    });

    document.querySelectorAll('[data-admin-icon-key-reset]').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('[data-admin-icon-key-item]');
            if (!item) {
                return;
            }

            var defaultMaterialName = item.getAttribute('data-admin-icon-key-default') || 'folder';
            var typeSelect = item.querySelector('[data-admin-icon-key-type]');
            var materialInput = item.querySelector('[data-admin-icon-key-material]');
            var fileInput = item.querySelector('[data-admin-icon-key-file]');
            var fileName = item.querySelector('[data-admin-icon-key-file-name]');
            var removeInput = item.querySelector('[data-admin-icon-key-remove]');
            var preview = item.querySelector('[data-admin-icon-key-preview]');

            if (typeSelect) {
                typeSelect.value = 'material';
            }

            if (materialInput) {
                materialInput.value = defaultMaterialName;
            }

            if (fileInput) {
                fileInput.value = '';
            }

            if (fileName) {
                fileName.textContent = '';
            }

            if (removeInput) {
                removeInput.checked = true;
            }

            if (preview) {
                if (preview.dataset.previewUrl) {
                    URL.revokeObjectURL(preview.dataset.previewUrl);
                    delete preview.dataset.previewUrl;
                }
                preview.innerHTML = '<span class="sr-icon material-symbols-outlined" aria-hidden="true" data-sr-material-icon="1">' + defaultMaterialName + '</span>';
            }
        });
    });

    var iconKeyAddButton = document.querySelector('[data-admin-icon-key-add]');
    var iconKeyList = document.querySelector('[data-admin-icon-key-list]');
    var iconKeyAddIndex = 0;
    if (iconKeyAddButton && iconKeyList) {
        iconKeyAddButton.addEventListener('click', function () {
            var row = document.createElement('div');
            var index = iconKeyAddIndex++;
            row.className = 'admin-icon-key-item admin-icon-key-item-custom';
            row.setAttribute('data-admin-icon-key-item', '');
            row.setAttribute('data-admin-icon-key-default', 'help');
            row.innerHTML = ''
                + '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-icon-key-delete" aria-label="제거" title="제거" data-admin-icon-key-remove-row><?php echo str_replace(["\n", "'"], ['', "\\'"], sr_material_icon_html('delete')); ?></button>'
                + '<input type="text" name="custom_icon_key[]" class="form-input form-input-sm admin-icon-key-name-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="아이콘 관리용 키" data-admin-key-input>'
                + '<span class="admin-icon-key-preview" data-admin-icon-key-preview><?php echo str_replace(["\n", "'"], ['', "\\'"], sr_material_icon_html('help')); ?></span>'
                + '<label class="sr-only" for="custom_icon_key_type_' + index + '">표시 방식</label>'
                + '<select id="custom_icon_key_type_' + index + '" name="custom_icon_key_type[]" class="form-select form-select-sm" data-admin-icon-key-type><option value="material">Material</option><option value="image">이미지</option></select>'
                + '<label class="sr-only" for="custom_icon_key_material_' + index + '">Material 이름</label>'
                + '<input id="custom_icon_key_material_' + index + '" type="text" name="custom_icon_key_material_name[]" value="help" class="form-input form-input-sm" maxlength="80" pattern="[a-z0-9_]+" data-admin-key-input data-admin-icon-key-material>'
                + '<label class="sr-only" for="custom_icon_key_image_' + index + '">이미지 파일</label>'
                + '<input id="custom_icon_key_image_' + index + '" type="file" name="custom_icon_key_image[]" accept="image/jpeg,image/png,image/gif,image/webp" class="form-input form-input-sm admin-icon-key-file" data-admin-icon-key-file>'
                + '<span class="admin-icon-key-file-name" data-admin-icon-key-file-name></span>'
                + '<span></span>';
            iconKeyList.appendChild(row);
            bindAdminIconKeyFile(row.querySelector('[data-admin-icon-key-file]'));
            var removeButton = row.querySelector('[data-admin-icon-key-remove-row]');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                });
            }
        });
    }

    document.querySelectorAll('[data-admin-business-info-remove]').forEach(function (button) {
        button.addEventListener('click', function () {
            var row = button.closest('[data-admin-business-info-row]');
            if (row) {
                row.remove();
            }
        });
    });

    var businessInfoAddButton = document.querySelector('[data-admin-business-info-add]');
    var businessInfoList = document.querySelector('[data-admin-business-info-list]');
    var businessInfoAddIndex = 0;
    if (businessInfoAddButton && businessInfoList) {
        businessInfoAddButton.addEventListener('click', function () {
            var index = businessInfoAddIndex++;
            var row = document.createElement('div');
            row.className = 'admin-business-info-row';
            row.setAttribute('data-admin-business-info-row', '');
            row.innerHTML = ''
                + '<input type="hidden" name="business_info_key[]" value="">'
                + '<label class="sr-only" for="admin_settings_business_info_custom_label_' + index + '">사업자 정보 항목명</label>'
                + '<input id="admin_settings_business_info_custom_label_' + index + '" type="text" name="business_info_label[]" class="form-input form-input-sm" maxlength="80" placeholder="항목명">'
                + '<label class="sr-only" for="admin_settings_business_info_custom_value_' + index + '">사업자 정보 값</label>'
                + '<input id="admin_settings_business_info_custom_value_' + index + '" type="text" name="business_info_value[]" class="form-input form-input-sm" maxlength="255" placeholder="값">'
                + '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-business-info-remove" aria-label="사업자 정보 항목 제거" title="제거" data-admin-business-info-remove><?php echo str_replace(["\n", "'"], ['', "\\'"], sr_material_icon_html('delete')); ?></button>';
            businessInfoList.appendChild(row);
            var labelInput = row.querySelector('input[name="business_info_label[]"]');
            var removeButton = row.querySelector('[data-admin-business-info-remove]');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                });
            }
            if (labelInput) {
                labelInput.focus();
            }
        });
    }
}());
</script>

<?php foreach ($siteSettingsHelp as $siteSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html($siteSettingsHelpModal['id'], $siteSettingsHelpModal['title'], $siteSettingsHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
