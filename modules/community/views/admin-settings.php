<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? sr_t('community::ui.community.c1f4d427') : sr_t('community::ui.community.settings.af4e5ebd');
$communityPostBodyLengthMax = sr_community_post_body_setting_max_length();
$communitySiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$communitySiteMenuSelectOptions = static function (string $selectedMenuKey) use ($communitySiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach (sr_community_layout_menu_builtin_options() as $menuKey => $menuLabel) { ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e((string) $menuLabel); ?>
        </option>
    <?php } ?>
    <?php foreach ($communitySiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$communityLayoutMenuFields = [
    'layout_primary_menu_key' => [
        'label' => '주 메뉴 슬롯',
        'help' => '선택한 공개 레이아웃이 주 메뉴 슬롯을 출력할 때 사용할 메뉴입니다. 게시판 그룹을 선택하면 접근 가능한 게시판 그룹을 표시합니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다. 사용 안 함이면 접근 가능한 게시판 그룹이 주 메뉴 후보로 표시됩니다.',
        'default' => 'header',
    ],
];
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_community_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? sr_t('community::ui.text.706623d8') . implode(', ', $assetDeductionPriorityLabels)
    : sr_t('community::ui.text.3e195cdd');
$communityAssetAuditUrl = sr_admin_asset_settings_audit_url('community.settings.asset_settings.updated', 'module', 'community');
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
$privacyConsentDocumentOptions = isset($privacyConsentDocumentOptions) && is_array($privacyConsentDocumentOptions) ? $privacyConsentDocumentOptions : [];
$privacyConsentDocumentSelectOptionsHtml = static function (string $selectedDocumentKey) use ($privacyConsentDocumentOptions): string {
    $html = '<option value="">' . sr_e('선택 안 함') . '</option>';
    foreach ($privacyConsentDocumentOptions as $privacyConsentDocumentKey => $privacyConsentDocumentOption) {
        $privacyConsentDocumentTitle = is_array($privacyConsentDocumentOption)
            ? (string) ($privacyConsentDocumentOption['title'] ?? $privacyConsentDocumentKey)
            : (string) $privacyConsentDocumentOption;
        $html .= '<option value="' . sr_e((string) $privacyConsentDocumentKey) . '"' . ($selectedDocumentKey === (string) $privacyConsentDocumentKey ? ' selected' : '') . '>'
            . sr_e($privacyConsentDocumentTitle)
            . '</option>';
    }

    return $html;
};
$messageWritePolicyLabels = [
    'member' => sr_t('community::ui.message_policy.member'),
    'group' => sr_t('community::ui.message_policy.group'),
];
$thumbnailCriterionValue = sr_community_thumbnail_criterion((string) ($settings['thumbnail_criterion'] ?? 'width'));
$canViewCommunityThumbnailFileCache = !empty($canViewCommunityThumbnailFileCache);
$canViewCommunityEmbedManager = !empty($canViewCommunityEmbedManager);
$levelScoreHelpModalId = 'community-level-score-help-modal';
$levelScoreHelpBodyHtml = '<p>' . sr_e(sr_t('community::ui.level_score_help_global_default')) . '</p>'
    . '<p>' . sr_e(sr_t('community::ui.level_score_help_formula')) . '</p>'
    . '<ul>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_priority')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_group_initial')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_board_initial')) . '</li>'
    . '</ul>';
$communitySettingsHelpOpenLabel = sr_t('community::help.open');
$communitySettingsHelpButtonHtml = static function (string $label, string $modalId) use ($communitySettingsHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $communitySettingsHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$communitySettingsHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$communitySettingsHelp = [
    'level_feature' => [
        'id' => 'community_settings_help_level_feature',
        'title' => sr_t('community::help.level_feature.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_feature.body.1', 'community::help.level_feature.body.2']),
    ],
    'level_auto_recalculate' => [
        'id' => 'community_settings_help_level_auto_recalculate',
        'title' => sr_t('community::help.level_auto_recalculate.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_auto_recalculate.body.1', 'community::help.level_auto_recalculate.body.2']),
    ],
    'message_policy' => [
        'id' => 'community_settings_help_message_policy',
        'title' => sr_t('community::help.message_policy.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_policy.body.1', 'community::help.message_policy.body.2', 'community::help.message_policy.body.3', 'community::help.message_policy.body.4']),
    ],
    'message_group' => [
        'id' => 'community_settings_help_message_group',
        'title' => sr_t('community::help.message_group.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_group.body.1', 'community::help.message_group.body.2']),
    ],
    'message_min_level' => [
        'id' => 'community_settings_help_message_min_level',
        'title' => sr_t('community::help.message_min_level.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_min_level.body.1', 'community::help.message_min_level.body.2']),
    ],
    'asset_settings' => [
        'id' => 'community_settings_help_asset_settings',
        'title' => sr_t('community::help.asset_settings.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.asset_settings.body.1', 'community::help.asset_settings.body.2', 'community::help.asset_settings.body.3']),
    ],
    'once_history_policy' => [
        'id' => 'community_settings_help_once_history_policy',
        'title' => sr_t('community::ui.once_history_policy.label'),
        'body' => '<p>' . sr_e('유료 열람이나 첨부 다운로드를 최초 1회 결제로 운영할 때, 예전에 이용한 회원을 다시 결제시킬지 정합니다.') . '</p>'
            . '<ul>'
            . '<li><strong>' . sr_e('결제/쿠폰 이력') . '</strong>: ' . sr_e('포인트, 예치금, 적립금 결제나 쿠폰 이용 이력이 있으면 다시 결제하지 않습니다.') . '</li>'
            . '<li><strong>' . sr_e('결제 이력만') . '</strong>: ' . sr_e('포인트, 예치금, 적립금으로 결제한 이력만 인정하고 쿠폰 이용자는 다시 결제합니다.') . '</li>'
            . '<li><strong>' . sr_e('현재 결제수단 이력만') . '</strong>: ' . sr_e('지금 선택한 결제수단으로 최초 1회 결제한 이력만 인정합니다. 예를 들어 지금 포인트만 받으면 예전에 포인트로 결제한 회원만 다시 결제하지 않습니다.') . '</li>'
            . '</ul>'
            . '<p>' . sr_e('이 설정은 앞으로의 재결제 여부만 바꾸며, 기존 원장 거래와 쿠폰 사용 로그를 환불하거나 추가 차감하지 않습니다.') . '</p>',
    ],
    'layout' => [
        'id' => 'community_settings_help_layout',
        'title' => sr_t('community::help.layout.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.layout.body.1', 'community::help.layout.body.2']),
    ],
    'theme' => [
        'id' => 'community_settings_help_theme',
        'title' => sr_t('community::help.theme.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.theme.body.1', 'community::help.theme.body.2']),
    ],
    'level_min_score' => [
        'id' => 'community_settings_help_level_min_score',
        'title' => sr_t('community::help.level_min_score.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_min_score.body.1', 'community::help.level_min_score.body.2']),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communitySettingsPage === 'settings') { ?>
<?php
$communitySettingsSectionNavItems = [
    'community-settings-section-level' => '레벨 기본값',
    'community-settings-section-message' => '쪽지 정책',
    'community-settings-section-privacy-consent' => '개인정보 동의',
    'community-settings-section-assets' => '자산/과금',
    'community-settings-section-series' => '시리즈',
    'community-settings-section-reaction' => '리액션',
    'community-settings-section-thumbnail' => '썸네일',
    'community-settings-section-display' => '공개 화면',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="커뮤니티 설정 섹션">
    <?php $communitySettingsSectionNavIndex = 0; ?>
    <?php foreach ($communitySettingsSectionNavItems as $communitySettingsSectionId => $communitySettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $communitySettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $communitySettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $communitySettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $communitySettingsSectionLabel); ?>
        </a>
        <?php $communitySettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="admin-form ui-form-theme" data-community-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">
    <input type="hidden" name="level_max_change_confirmed" value="0" data-community-settings-level-max-confirmed>
    <input type="hidden" name="level_max_change_confirm_text" value="" data-community-settings-level-max-confirm-text>

    <section id="community-settings-section-level" class="card" data-admin-section-anchor>
        <h2>레벨 기본값</h2>
        <div class="form-grid">
            <div class="form-row">
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.7d97b5a5'), $communitySettingsHelp['level_feature']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></span></div>
                <div class="form-field">
                    <label class="form-check form-label" for="modules_community_admin_settings_level_enabled">
                        <input id="modules_community_admin_settings_level_enabled" type="checkbox" name="level_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?> data-community-level-enabled>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_display_name">레벨 표시명 <span class="sr-required-label" data-community-level-required-label<?php echo !empty($settings['level_enabled']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_display_name" type="text" name="level_display_name" maxlength="40" value="<?php echo sr_e((string) ($settings['level_display_name'] ?? '레벨')); ?>"<?php echo !empty($settings['level_enabled']) ? ' required' : ''; ?> class="form-input" data-community-level-required-field>
                    <p class="form-help">회원 화면과 관리자 선택지에서 레벨을 부를 이름입니다.</p>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_short_label">레벨 약칭</label>
                <div class="form-field">
                    <input id="community_admin_settings_level_short_label" type="text" name="level_short_label" maxlength="20" value="<?php echo sr_e((string) ($settings['level_short_label'] ?? 'Lv.')); ?>" class="form-input">
                    <p class="form-help">아바타 아래나 회원 드롭다운처럼 좁은 곳에서 쓰는 짧은 이름입니다. 비워 두면 표시명을 사용합니다.</p>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.f9447e05'), $communitySettingsHelp['level_auto_recalculate']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.f9447e05')); ?></span></div>
                <div class="form-field">
                    <label class="form-check form-label" for="modules_community_admin_settings_level_auto_recalculate">
                        <input id="modules_community_admin_settings_level_auto_recalculate" type="checkbox" name="level_auto_recalculate" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?> data-community-level-auto-toggle>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_max_value"><?php echo sr_e(sr_t('community::ui.level_max_value')); ?> <span class="sr-required-label" data-community-level-required-label<?php echo !empty($settings['level_enabled']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_max_value" type="number" name="level_max_value" min="1" max="100" value="<?php echo sr_e((string) $settings['level_max_value']); ?>"<?php echo !empty($settings['level_enabled']) ? ' required' : ''; ?> class="form-input" data-community-settings-level-max-value data-community-settings-level-max-initial="<?php echo sr_e((string) $settings['level_max_value']); ?>" data-community-level-required-field>
                    <p class="form-help"><?php echo sr_e(sr_t('community::ui.level_max_value_help')); ?></p>
                </div>
            </div>
            <div class="form-row"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?> data-community-level-auto-field>
                <label class="form-label" for="community_admin_settings_level_post_score"><?php echo sr_e(sr_t('community::ui.text.99092cba')); ?> <span class="sr-required-label" data-community-level-auto-required-label<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? ' required' : ''; ?> class="form-input" data-community-level-auto-required-field>
                </div>
            </div>
            <div class="form-row"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?> data-community-level-auto-field>
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.96af1f5c'), $levelScoreHelpModalId); ?><span><?php echo sr_e(sr_t('community::ui.text.96af1f5c')); ?> <span class="sr-required-label" data-community-level-auto-required-label<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?>>(필수)</span></span></div>
                <div class="form-field">
                    <input id="community_admin_settings_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? ' required' : ''; ?> class="form-input" data-community-level-auto-required-field>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-message" class="card" data-admin-section-anchor>
        <h2>쪽지 정책</h2>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_message_enabled">쪽지 사용</label>
            <div class="form-field">
                <label class="form-check form-label" for="community_admin_settings_message_enabled">
                    <input id="community_admin_settings_message_enabled" type="checkbox" name="message_enabled" value="1" class="form-switch form-switch-light"<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? ' checked' : ''; ?> data-community-message-enabled>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
                <p class="form-help">끄면 회원이 쪽지함을 열거나 새 쪽지를 보낼 수 없습니다. 기존 쪽지 내역은 데이터로 유지됩니다.</p>
            </div>
        </div>
        <div class="form-row"<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? '' : ' hidden'; ?> data-community-message-dependent-field>
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('community::ui.text.31edcf4a') . ' ' . $communitySettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communitySettingsHelp['message_policy']['id']); ?>" data-overlay="#<?php echo sr_e($communitySettingsHelp['message_policy']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="community_admin_settings_message_write_policy_member"><?php echo sr_e(sr_t('community::ui.text.31edcf4a')); ?> <span class="sr-required-label" data-community-message-required-label<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? '' : ' hidden'; ?>>(필수)</span></label>
            </div>
            <div class="form-field">
                <?php
                $messagePolicyToggleOptions = [];
                foreach (['member', 'group'] as $policy) {
                    $messagePolicyToggleOptions[$policy] = (string) ($messageWritePolicyLabels[$policy] ?? sr_admin_code_label($policy, 'policy'));
                }
                $messagePolicyValue = (string) ($settings['message_write_policy'] ?? 'member');
                if (!isset($messagePolicyToggleOptions[$messagePolicyValue])) {
                    $messagePolicyValue = 'member';
                }
                echo sr_admin_radio_toggle_group_html('community_admin_settings_message_write_policy', 'message_write_policy', $messagePolicyToggleOptions, $messagePolicyValue, false, ' data-community-message-policy data-community-message-required-field');
                ?>
                <p class="form-help"><?php echo sr_e(sr_t('community::ui.message_policy.help')); ?></p>
            </div>
        </div>
        <div class="form-row"<?php echo (string) ($settings['message_write_policy'] ?? 'member') === 'group' ? '' : ' hidden'; ?> data-community-message-dependent-field data-community-message-group-field>
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('community::ui.member.69b1363d') . ' ' . $communitySettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communitySettingsHelp['message_group']['id']); ?>" data-overlay="#<?php echo sr_e($communitySettingsHelp['message_group']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="community_admin_settings_message_write_group_keys"><?php echo sr_e(sr_t('community::ui.member.69b1363d')); ?> <span class="sr-required-label" data-community-message-group-required-label<?php echo (string) ($settings['message_write_policy'] ?? 'member') === 'group' ? '' : ' hidden'; ?>>(필수)</span></label>
            </div>
            <div class="form-field" data-community-message-group-controls>
                <?php echo sr_admin_member_group_key_badge_select_html('community_admin_settings_message_write_group_keys', 'message_write_group_keys', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : [], $enabledMemberGroups); ?>
            </div>
        </div>
        <div class="form-row"<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? '' : ' hidden'; ?> data-community-message-dependent-field>
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('community::ui.text.c96c86df') . ' ' . $communitySettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communitySettingsHelp['message_min_level']['id']); ?>" data-overlay="#<?php echo sr_e($communitySettingsHelp['message_min_level']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="community_admin_settings_message_write_min_level"><?php echo sr_e(sr_t('community::ui.text.c96c86df')); ?> <span class="sr-required-label" data-community-message-required-label<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? '' : ' hidden'; ?>>(필수)</span></label>
            </div>
            <div class="form-field">
                <select id="community_admin_settings_message_write_min_level" name="message_write_min_level" class="form-select"<?php echo (string) ($settings['message_write_policy'] ?? 'member') !== 'disabled' ? ' required' : ''; ?> data-community-message-required-field>
                                    <?php for ($levelValue = 0; $levelValue <= sr_community_max_level_value($settings); $levelValue++) { ?>
                                        <option value="<?php echo sr_e((string) $levelValue); ?>"<?php echo (int) $settings['message_write_min_level'] === $levelValue ? ' selected' : ''; ?>>
                                            <?php echo sr_e(sr_community_level_label_for_value($pdo, $levelValue, $settings)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>

    <section id="community-settings-section-privacy-consent" class="card" data-admin-section-anchor>
        <h2>개인정보 수집 및 이용동의 기본값</h2>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_privacy_consent_enabled">동의 사용</label>
                <div class="form-field">
                    <label class="form-check form-label" for="community_admin_settings_privacy_consent_enabled">
                        <input id="community_admin_settings_privacy_consent_enabled" type="checkbox" name="privacy_consent_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['privacy_consent_enabled']) ? ' checked' : ''; ?> data-community-privacy-consent-enabled>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                    <p class="form-help">게시판 개별 설정에서 다른 값으로 재정의할 수 있습니다.</p>
                </div>
            </div>
            <div class="form-row" data-admin-required-selection-mode="any">
                <span class="form-label">동의 적용 대상 <span class="sr-required-label" data-community-privacy-consent-required<?php echo !empty($settings['privacy_consent_enabled']) ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                <div class="form-field" data-community-privacy-consent-controls>
                    <div class="community-privacy-consent-document-list">
                        <?php foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) { ?>
                            <?php $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey); ?>
                            <label class="community-privacy-consent-document-row" for="<?php echo sr_e('community_admin_settings_' . $privacyConsentDocumentSettingKey); ?>">
                                <span><?php echo sr_e(sr_community_privacy_consent_admin_label($privacyConsentTargetKey)); ?></span>
                                <select id="<?php echo sr_e('community_admin_settings_' . $privacyConsentDocumentSettingKey); ?>" name="<?php echo sr_e($privacyConsentDocumentSettingKey); ?>" class="form-select" data-community-privacy-consent-document="<?php echo sr_e($privacyConsentTargetKey); ?>">
                                    <?php echo $privacyConsentDocumentSelectOptionsHtml(sr_community_privacy_consent_admin_document_key_from_settings($settings, $privacyConsentTargetKey)); ?>
                                </select>
                            </label>
                        <?php } ?>
                    </div>
                    <p class="form-help">동의 사용 시 3가지 중 하나 이상 정책 문서를 선택해야 하며, 선택 안 함인 대상에는 동의를 적용하지 않습니다.</p>
                    <input type="hidden" name="privacy_consent_title" value="">
                    <input type="hidden" name="privacy_consent_version" value="">
                    <input type="hidden" name="privacy_consent_body" value="">
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-assets" class="card" data-admin-section-anchor>
        <h2>
            <span>자산/과금</span>
            <span class="form-actions">
                <a href="<?php echo sr_e($communityAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 설정 변경 이력'); ?></a>
            </span>
        </h2>
        <div class="form-grid">
            <?php foreach ([
                'post_reward' => sr_t('community::ui.text.a3cc976c'),
                'comment_reward' => sr_t('community::ui.text.bb39df0e'),
                'write_charge' => sr_t('community::ui.text.ce1392a2'),
                'comment_charge' => sr_t('community::ui.text.629c5136'),
                'message_charge' => sr_t('community::asset_setting.message_charge'),
                'paid_read' => sr_t('community::ui.text.c9b3e6f0'),
                'paid_attachment_download' => sr_t('community::ui.text.5b864b9e'),
            ] as $assetPrefix => $assetLabel) { ?>
                <?php $assetEnabledId = 'modules_community_admin_settings_' . (string) $assetPrefix . '_enabled'; ?>
                <?php $assetSourceId = 'community_admin_settings_' . (string) $assetPrefix . '_asset_source'; ?>
                <?php $isRewardAsset = in_array((string) $assetPrefix, ['post_reward', 'comment_reward'], true); ?>
                <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($settings[$assetPrefix . '_asset_module'] ?? '', true); ?>
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml($assetLabel, $communitySettingsHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?> 사용</span></div>
                    <div class="form-field">
                        <div class="admin-asset-setting-line">
                            <div class="admin-asset-setting-control">
                                <div class="admin-asset-setting-primary">
                                    <label class="form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                        <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings[$assetPrefix . '_enabled']) ? ' checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html('사용'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($isRewardAsset) { ?>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e($assetLabel . ' 회수'); ?></span>
                        <div class="form-field">
                            <label class="form-check form-label" for="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>">
                                <input id="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_reversal_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings[$assetPrefix . '_reversal_enabled']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('회수'); ?>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($assetPrefix === 'paid_read') { ?>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_read_charge_policy"><?php echo sr_e(sr_t('community::ui.text.05ead7ab')); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_paid_read_charge_policy', 'paid_read_charge_policy', ['once' => sr_t('community::ui.text.6eb4fe4e'), 'every_view' => sr_t('community::ui.text.53e8d077')], (string) ($settings['paid_read_charge_policy'] ?? 'once')); ?>
                        </div>
                    </div>
                <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_attachment_download_charge_policy"><?php echo sr_e(sr_t('community::ui.text.978f8b2e')); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_paid_attachment_download_charge_policy', 'paid_attachment_download_charge_policy', ['once' => sr_t('community::ui.text.6eb4fe4e'), 'every_download' => sr_t('community::ui.text.e9d14df2')], (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once')); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <span class="form-label">게시자 리워드</span>
                        <div class="form-field">
                            <label class="form-check form-label" for="modules_community_admin_settings_paid_attachment_download_publisher_reward_enabled">
                                <input id="modules_community_admin_settings_paid_attachment_download_publisher_reward_enabled" type="checkbox" name="paid_attachment_download_publisher_reward_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['paid_attachment_download_publisher_reward_enabled']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('지급'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_attachment_download_publisher_reward_rate">게시자 리워드 지급률</label>
                        <div class="form-field">
                            <div class="input-group admin-asset-single-amount-group admin-community-publisher-reward-rate-group">
                                <input id="modules_community_admin_settings_paid_attachment_download_publisher_reward_rate" type="number" min="0" max="100" name="paid_attachment_download_publisher_reward_rate" value="<?php echo sr_e((string) (int) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0)); ?>" class="form-input admin-community-publisher-reward-rate-input">
                                <span class="input-group-text">%</span>
                            </div>
                            <p class="form-help">실제 차감된 자산과 같은 자산으로 게시글 작성자에게 지급합니다. 본인 다운로드, 무료 통과, 이미 차감된 once 다운로드는 지급하지 않습니다.</p>
                        </div>
                    </div>
                <?php } ?>
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e($assetLabel . ' 자산 설정'); ?></span>
                    <div class="form-field">
                        <?php if ($isRewardAsset) { ?>
                            <div class="admin-asset-setting-target admin-asset-single-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                <select id="<?php echo sr_e($assetSourceId); ?>" name="<?php echo sr_e((string) $assetPrefix); ?>_asset_module" class="form-select" data-admin-asset-unit-select>
                                    <option value=""><?php echo sr_e($assetModuleOptions === [] ? sr_t('community::ui.text.3e195cdd') : sr_t('community::ui.text.asset_none')); ?></option>
                                    <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                        <option value="<?php echo sr_e((string) $assetModule); ?>" data-admin-asset-unit="<?php echo sr_e((string) ($assetOption['unit_label'] ?? '')); ?>"<?php echo (string) ($settings[$assetPrefix . '_asset_module'] ?? '') === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                    <?php } ?>
                                </select>
                                <?php echo sr_community_asset_single_amount_input_group_html((string) $assetPrefix . '_amount', (int) ($settings[$assetPrefix . '_amount'] ?? 0), $assetModuleOptions, (string) ($settings[$assetPrefix . '_asset_module'] ?? ''), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel])); ?>
                            </div>
                        <?php } else { ?>
                            <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                <?php echo sr_community_asset_grouped_amount_inputs_html($assetSourceId, (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $settings[$assetPrefix . '_amounts_json'] ?? '', (int) ($settings[$assetPrefix . '_amount'] ?? 0), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                            </div>
                            <input type="hidden" name="<?php echo sr_e((string) $assetPrefix); ?>_amount" value="<?php echo sr_e((string) ($settings[$assetPrefix . '_amount'] ?? 0)); ?>">
                            <p class="form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e('community_settings_' . (string) $assetPrefix . '_policy_set_ids'); ?>"><?php echo sr_e('회원 그룹별 적용'); ?></label>
                    <div class="form-field admin-policy-set-field">
                        <?php echo sr_community_asset_policy_set_checkboxes_html('community_settings_' . (string) $assetPrefix . '_policy_set_ids', (string) $assetPrefix . '_policy_set_ids', $assetPolicySets ?? [], sr_community_asset_policy_set_ids_with_legacy($settings[$assetPrefix . '_group_policies_json'] ?? '', (int) ($settings[$assetPrefix . '_policy_set_id'] ?? 0)), $isRewardAsset ? 'grant' : 'use', '#' . $assetSourceId, $pdo); ?>
                        <p class="form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹, 레벨, 대상 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 커뮤니티 회원 그룹별 설정 화면에서 관리합니다.</p>
                    </div>
                </div>
            <?php } ?>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('modules_community_admin_settings_once_history_policy', sr_t('community::ui.once_history_policy.label'), $communitySettingsHelp['once_history_policy']['id'], $communitySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_once_history_policy', 'once_history_policy', sr_community_once_history_policy_values(), (string) ($settings['once_history_policy'] ?? 'all_access'), true); ?>
                    <p class="form-help"><?php echo sr_e(sr_t('community::ui.once_history_policy.help')); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-series" class="card" data-admin-section-anchor>
        <h2>시리즈</h2>
        <div class="form-row">
            <span class="form-label">시리즈 기능</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_series_enabled', 'series_enabled', '1', !empty($settings['series_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 시리즈 생성, 연결, 관리, 스크랩, 공개 내비게이션과 커뮤니티 메인 시리즈 섹션을 사용하지 않습니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-reaction" class="card" data-admin-section-anchor>
        <h2>리액션</h2>
        <div class="form-row">
            <span class="form-label">리액션 사용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_reaction_enabled', 'reaction_enabled', '1', !empty($settings['reaction_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 커뮤니티 게시글과 댓글의 리액션 위젯을 표시하지 않고, 게시판 목록에도 반응 수를 표시하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_reaction_post_preset_key">게시글 리액션 프리셋</label>
            <div class="form-field">
                <select id="community_admin_settings_reaction_post_preset_key" name="reaction_post_preset_key" class="form-select">
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_post_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_reaction_comment_preset_key">댓글 리액션 프리셋</label>
            <div class="form-field">
                <select id="community_admin_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">게시판 그룹과 게시판에서 따로 선택하지 않은 대상에 적용합니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-thumbnail" class="card" data-admin-section-anchor>
        <h2>썸네일</h2>
        <div class="form-row">
            <span class="form-label">썸네일 생성</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_thumbnail_enabled', 'thumbnail_enabled', '1', !empty($settings['thumbnail_enabled']), '사용'); ?>
                <p class="form-help">게시글 목록 이미지는 공개 첨부 이미지가 있으면 항상 캐시 썸네일을 우선 사용합니다. 이 설정은 읽기 화면의 첨부 이미지 미리보기에 적용되며, 게시판 개별 설정에서 재정의할 수 있습니다.</p>
                <?php if ($canViewCommunityThumbnailFileCache) { ?>
                    <p class="form-help">생성된 공개 썸네일 파일은 <a href="<?php echo sr_e(sr_url('/admin/storage-cache?module_key=community')); ?>">썸네일 파일 캐시</a>에서 확인하고 정리할 수 있습니다. 정리해도 원본 파일과 게시글은 삭제되지 않습니다.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">생성 기준 <span class="sr-required-label">(필수)</span></span>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('community_admin_settings_thumbnail_criterion', 'thumbnail_criterion', ['width' => '너비 기준', 'bytes' => '용량 기준'], $thumbnailCriterionValue, true, ' data-community-thumbnail-criterion'); ?>
                <p class="form-help">선택한 기준 하나만 읽기 화면의 첨부 이미지 미리보기에 적용합니다. 목록 표시 크기는 목록 화면과 스킨 CSS가 결정합니다.</p>
            </div>
        </div>
        <div class="form-row" data-community-thumbnail-rule="width"<?php echo $thumbnailCriterionValue === 'width' ? '' : ' hidden'; ?>>
            <label class="form-label" for="community_admin_settings_thumbnail_min_width">생성 기준 너비 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="community_admin_settings_thumbnail_min_width" type="number" name="thumbnail_min_width" min="1" max="4000" value="<?php echo sr_e((string) ($settings['thumbnail_min_width'] ?? 320)); ?>"<?php echo $thumbnailCriterionValue === 'width' ? ' required' : ''; ?> data-admin-required-when-visible class="form-input">
                    <span class="input-group-text">px</span>
                </div>
                <p class="form-help">너비 기준을 선택했을 때 읽기 화면 첨부 이미지의 원본 너비가 이 값보다 작으면 캐시 썸네일을 만들지 않습니다.</p>
            </div>
        </div>
        <div class="form-row" data-community-thumbnail-rule="bytes"<?php echo $thumbnailCriterionValue === 'bytes' ? '' : ' hidden'; ?>>
            <label class="form-label" for="community_admin_settings_thumbnail_min_bytes">생성 기준 용량 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="admin-community-thumbnail-bytes-line">
                    <div class="input-group admin-input-unit admin-community-thumbnail-bytes-group">
                        <input id="community_admin_settings_thumbnail_min_bytes" type="number" name="thumbnail_min_bytes" min="0" max="20971520" value="<?php echo sr_e((string) ($settings['thumbnail_min_bytes'] ?? 102400)); ?>"<?php echo $thumbnailCriterionValue === 'bytes' ? ' required' : ''; ?> data-admin-required-when-visible data-community-thumbnail-bytes-input class="form-input">
                        <span class="input-group-text">bytes</span>
                    </div>
                    <span class="form-help admin-community-thumbnail-bytes-label" data-community-thumbnail-bytes-label></span>
                </div>
                <p class="form-help">용량 기준을 선택했을 때 읽기 화면 첨부 이미지의 원본 파일 크기가 이 값보다 작으면 캐시 썸네일을 만들지 않습니다. 0이면 모든 용량에서 생성합니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-display" class="card" data-admin-section-anchor>
        <h2>공개 화면 구성</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_theme_key', '커뮤니티 공개 테마', $communitySettingsHelp['theme']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="community_admin_settings_theme_key" name="theme_key" class="form-select" required>
                    <?php foreach ($communityThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">커뮤니티 공개 화면의 본문 구조, 색, 표면, 상호작용 상태에 적용할 시각 테마입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_layout_key', sr_t('community::ui.community.8f453af4'), $communitySettingsHelp['layout']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="community_admin_settings_layout_key" name="layout_key" class="form-select">
                    <?php foreach ($communityLayoutOptions as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) $settings['layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">선택한 테마 아래에서 커뮤니티 화면을 감싸는 공개 화면 틀입니다. 공통 레이아웃과 필요한 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.</p>
            </div>
        </div>
        <?php foreach ($communityLayoutMenuFields as $communityLayoutMenuSettingKey => $communityLayoutMenuField) { ?>
            <?php $communityLayoutMenuInputId = 'community_admin_settings_' . $communityLayoutMenuSettingKey; ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e($communityLayoutMenuInputId); ?>"><?php echo sr_e((string) $communityLayoutMenuField['label']); ?></label>
                <div class="form-field">
                    <select id="<?php echo sr_e($communityLayoutMenuInputId); ?>" name="<?php echo sr_e((string) $communityLayoutMenuSettingKey); ?>" class="form-select">
                        <?php $communitySiteMenuSelectOptions((string) ($settings[$communityLayoutMenuSettingKey] ?? $communityLayoutMenuField['default'])); ?>
                    </select>
                    <p class="form-help"><?php echo sr_e((string) $communityLayoutMenuField['help']); ?></p>
                </div>
            </div>
        <?php } ?>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_post_editor">게시글 에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('community_admin_settings_post_editor', 'post_editor', $editorOptions, (string) ($settings['post_editor'] ?? 'textarea'), true); ?>
                <p class="form-help">새 게시판을 만들 때 참고할 전역 기본값입니다. 기존 게시판 값은 자동 변경되지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_post_toolbar_preset">툴바 구성 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="community_admin_settings_post_toolbar_preset" name="post_toolbar_preset" class="form-select" required>
                    <?php foreach ($toolbarPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['post_toolbar_preset'] ?? 'community_post_basic') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">CKEditor를 사용할 때 커뮤니티 게시글 작성/수정 화면에 적용할 툴바입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_post_body_min_length">게시글 본문 최소 길이 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="community_admin_settings_post_body_min_length" type="number" name="post_body_min_length" min="0" max="<?php echo sr_e((string) $communityPostBodyLengthMax); ?>" value="<?php echo sr_e((string) ($settings['post_body_min_length'] ?? 0)); ?>" required class="form-input">
                <p class="form-help">새 게시판을 만들 때 참고할 전역 기본값입니다. 0이면 최소 길이를 검사하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_post_body_max_length">게시글 본문 최대 길이 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="community_admin_settings_post_body_max_length" type="number" name="post_body_max_length" min="0" max="<?php echo sr_e((string) $communityPostBodyLengthMax); ?>" value="<?php echo sr_e((string) ($settings['post_body_max_length'] ?? 0)); ?>" required class="form-input">
                <p class="form-help">새 게시판을 만들 때 참고할 전역 기본값입니다. 0이면 최대 길이를 검사하지 않습니다. 저장 가능한 실제 크기는 DB와 서버 요청 크기 설정의 영향을 받습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">임베드 사용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_embed_enabled', 'embed_enabled', '1', !empty($settings['embed_enabled']), '사용'); ?>
                <p class="form-help">켜져 있으면 본문에 단독으로 붙여 넣은 YouTube, X, Instagram, 내부 콘텐츠 URL을 공개 화면에서 자동 해석해 표시합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">본문 URL 자동 링크</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_plain_text_auto_link_urls', 'plain_text_auto_link_urls', '1', !empty($settings['plain_text_auto_link_urls']), '사용'); ?>
                <p class="form-help">textarea로 저장된 plain text 게시글에만 적용합니다. HTML 게시글은 저장된 링크와 정화 정책을 그대로 사용합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">비밀글</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_secret_posts_enabled', 'secret_posts_enabled', '1', !empty($settings['secret_posts_enabled']), '사용'); ?>
                <p class="form-help">게시판 설정에서 별도로 재정의할 수 있습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">비밀 댓글</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_secret_comments_enabled', 'secret_comments_enabled', '1', !empty($settings['secret_comments_enabled']), '사용'); ?>
                <p class="form-help">게시판 설정에서 별도로 재정의할 수 있습니다.</p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.settings.save.59aa86cd')); ?></button>
    </div>
</form>

<button type="button" class="btn btn-solid-light" hidden data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-open><?php echo sr_e(sr_t('community::ui.level_max_value')); ?></button>
<div id="community-settings-level-max-confirm-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-settings-level-max-confirm-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog-center">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="community-settings-level-max-confirm-modal-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.level_max_value')); ?></h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div data-community-settings-level-max-step="notice">
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_notice_1')); ?></p>
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_notice_2')); ?></p>
                </div>
                <div data-community-settings-level-max-step="text" hidden>
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_text_help', ['text' => sr_t('community::ui.level_max_change_confirmation_text')])); ?></p>
                    <label class="sr-only" for="community-settings-level-max-confirm-input"><?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_label')); ?></label>
                    <input id="community-settings-level-max-confirm-input" type="text" class="form-input" autocomplete="off" placeholder="<?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_text')); ?>" aria-describedby="community-settings-level-max-confirm-error" data-overlay-focus data-community-settings-level-max-confirm-input>
                    <p id="community-settings-level-max-confirm-error" class="community-confirm-validation-message type-caption" data-community-settings-level-max-confirm-error hidden><?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_error')); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-cancel><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-settings-level-max-next><?php echo sr_e(sr_t('community::ui.level_max_change_modal_next')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-settings-level-max-submit hidden><?php echo sr_e(sr_t('community::ui.level_max_change_modal_execute')); ?></button>
            </div>
        </div>
    </div>
</div>

<?php echo sr_admin_help_modal_html($levelScoreHelpModalId, sr_t('community::ui.level_score_help_title'), $levelScoreHelpBodyHtml); ?>
<?php foreach ($communitySettingsHelp as $communitySettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $communitySettingsHelpModal['id'], (string) $communitySettingsHelpModal['title'], (string) $communitySettingsHelpModal['body']); ?>
<?php } ?>
<script>
(function () {
    var enabled = document.querySelector('[data-community-privacy-consent-enabled]');
    var controls = document.querySelector('[data-community-privacy-consent-controls]');
    if (!enabled || !controls) {
        return;
    }

    function syncPrivacyConsentControls() {
        var requiredLabel = controls.parentNode ? controls.parentNode.querySelector('[data-community-privacy-consent-required]') : null;
        if (requiredLabel) {
            requiredLabel.hidden = !enabled.checked;
        }
        Array.prototype.slice.call(controls.querySelectorAll('[data-community-privacy-consent-document]')).forEach(function (select) {
            select.disabled = !enabled.checked;
            select.required = false;
        });
    }

    enabled.addEventListener('change', syncPrivacyConsentControls);
    syncPrivacyConsentControls();
})();

(function () {
    var levelEnabled = document.querySelector('[data-community-level-enabled]');
    var toggle = document.querySelector('[data-community-level-auto-toggle]');
    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-field]'));
    var dependentFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-dependent-field]'));
    var requiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-required-field]'));
    var requiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-required-label]'));
    var autoRequiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-required-field]'));
    var autoRequiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-required-label]'));
    if (!levelEnabled || !toggle || fields.length < 1) {
        return;
    }

    function syncAutoFields() {
        var enabled = levelEnabled.checked;
        var autoEnabled = enabled && toggle.checked;
        dependentFields.forEach(function (field) {
            Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function (control) {
                if (control === levelEnabled) {
                    return;
                }
                control.disabled = !enabled;
            });
        });
        requiredFields.forEach(function (control) {
            control.required = enabled;
        });
        requiredLabels.forEach(function (label) {
            label.hidden = !enabled;
        });
        fields.forEach(function (field) {
            field.hidden = !autoEnabled;
        });
        autoRequiredFields.forEach(function (control) {
            control.disabled = !autoEnabled;
            control.required = autoEnabled;
        });
        autoRequiredLabels.forEach(function (label) {
            label.hidden = !autoEnabled;
        });
    }

    levelEnabled.addEventListener('change', syncAutoFields);
    toggle.addEventListener('change', syncAutoFields);
    syncAutoFields();
})();

(function () {
    var enabled = document.querySelector('[data-community-message-enabled]');
    var dependentFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-message-dependent-field]'));
    var policyInputs = Array.prototype.slice.call(document.querySelectorAll('input[name="message_write_policy"]'));
    var requiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-message-required-field]'));
    var requiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-message-required-label]'));
    var groupField = document.querySelector('[data-community-message-group-field]');
    var groupControls = document.querySelector('[data-community-message-group-controls]');
    var groupRequiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-message-group-required-label]'));
    if (!enabled || dependentFields.length < 1) {
        return;
    }

    function selectedPolicy() {
        var checked = policyInputs.find(function (input) {
            return input.checked;
        });
        return checked ? checked.value : 'member';
    }

    function setControlsDisabled(container, disabled) {
        Array.prototype.slice.call(container.querySelectorAll('input, select, textarea, button')).forEach(function (control) {
            if (control === enabled) {
                return;
            }
            control.disabled = disabled;
        });
    }

    function syncMessageFields() {
        var messageEnabled = enabled.checked;
        var groupRequired = messageEnabled && selectedPolicy() === 'group';
        dependentFields.forEach(function (field) {
            field.hidden = !messageEnabled;
            setControlsDisabled(field, !messageEnabled);
        });
        requiredFields.forEach(function (control) {
            control.required = messageEnabled;
        });
        requiredLabels.forEach(function (label) {
            label.hidden = !messageEnabled;
        });
        if (groupField) {
            groupField.hidden = !groupRequired;
        }
        if (groupControls) {
            setControlsDisabled(groupControls, !groupRequired);
        }
        groupRequiredLabels.forEach(function (label) {
            label.hidden = !groupRequired;
        });
    }

    enabled.addEventListener('change', syncMessageFields);
    policyInputs.forEach(function (input) {
        input.addEventListener('change', syncMessageFields);
    });
    syncMessageFields();
})();

(function () {
    var form = document.querySelector('[data-community-settings-form]');
    var maxInput = document.querySelector('[data-community-settings-level-max-value]');
    var openButton = document.querySelector('[data-community-settings-level-max-open]');
    var confirmedInput = document.querySelector('[data-community-settings-level-max-confirmed]');
    var confirmTextHidden = document.querySelector('[data-community-settings-level-max-confirm-text]');
    var confirmTextInput = document.querySelector('[data-community-settings-level-max-confirm-input]');
    var confirmError = document.querySelector('[data-community-settings-level-max-confirm-error]');
    var stepNotice = document.querySelector('[data-community-settings-level-max-step="notice"]');
    var stepText = document.querySelector('[data-community-settings-level-max-step="text"]');
    var nextButton = document.querySelector('[data-community-settings-level-max-next]');
    var submitButton = document.querySelector('[data-community-settings-level-max-submit]');
    var closeButton = document.querySelector('[data-community-settings-level-max-close]');
    var cancelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-settings-level-max-cancel]'));
    var requiredConfirmationText = <?php echo sr_js_json_encode(sr_t('community::ui.level_max_change_confirmation_text')); ?>;

    if (!form || !maxInput || !openButton || !confirmedInput || !confirmTextHidden) {
        return;
    }

    function maxChanged() {
        return String(maxInput.value).trim() !== String(maxInput.getAttribute('data-community-settings-level-max-initial') || '').trim();
    }

    function resetModal() {
        if (stepNotice) {
            stepNotice.hidden = false;
        }
        if (stepText) {
            stepText.hidden = true;
        }
        if (nextButton) {
            nextButton.hidden = false;
        }
        if (submitButton) {
            submitButton.hidden = true;
        }
        if (confirmTextInput) {
            confirmTextInput.value = '';
        }
        setConfirmInvalid(false);
    }

    function setConfirmInvalid(isInvalid) {
        if (confirmTextInput) {
            confirmTextInput.classList.toggle('form-input-invalid', isInvalid);
            if (isInvalid) {
                confirmTextInput.setAttribute('aria-invalid', 'true');
            } else {
                confirmTextInput.removeAttribute('aria-invalid');
            }
        }
        if (confirmError) {
            confirmError.hidden = !isInvalid;
        }
    }

    cancelButtons.forEach(function (cancelButton) {
        cancelButton.addEventListener('click', resetModal);
    });

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (stepNotice) {
                stepNotice.hidden = true;
            }
            if (stepText) {
                stepText.hidden = false;
            }
            nextButton.hidden = true;
            if (submitButton) {
                submitButton.hidden = false;
            }
            if (confirmTextInput) {
                confirmTextInput.focus();
            }
        });
    }

    if (submitButton) {
        submitButton.addEventListener('click', function () {
            var value = confirmTextInput ? confirmTextInput.value.trim() : '';
            if (value !== requiredConfirmationText) {
                setConfirmInvalid(true);
                if (confirmTextInput) {
                    confirmTextInput.focus();
                }
                return;
            }

            setConfirmInvalid(false);
            confirmedInput.value = '1';
            confirmTextHidden.value = value;
            if (closeButton) {
                closeButton.click();
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (!maxChanged()) {
            confirmedInput.value = '0';
            confirmTextHidden.value = '';
            return;
        }

        if (confirmedInput.value === '1' && confirmTextHidden.value === requiredConfirmationText) {
            return;
        }

        event.preventDefault();
        resetModal();
        openButton.click();
    });

    if (confirmTextInput) {
        confirmTextInput.addEventListener('input', function () {
            setConfirmInvalid(false);
        });
    }
})();
</script>

<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<?php $communityLevelEnabled = !empty($settings['level_enabled']); ?>
<?php $communityLevelRecalculateModalId = 'community-level-recalculate-confirm-modal'; ?>
<?php $communityLevelRecalculateTargetCount = sr_community_recalculate_target_account_count($pdo); ?>
<?php $communityLevelRecalculateLoad = sr_admin_high_load_assessment([
    'target_records' => $communityLevelRecalculateTargetCount,
    'table_count' => 4,
    'batch_available' => true,
]); ?>
<?php if (!$communityLevelEnabled) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon" aria-hidden="true">i</span>
        <div class="admin-notice-copy">
            <strong><?php echo sr_e(sr_t('community::ui.level_disabled_notice_title')); ?></strong>
            <p><?php echo sr_e(sr_t('community::ui.level_disabled_notice_body')); ?></p>
            <p><a href="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.level_disabled_notice_link')); ?></a></p>
        </div>
    </div>
<?php } ?>
<form id="community-level-definitions-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_level_definitions">
    <input type="hidden" name="level_max_value" value="<?php echo sr_e((string) $settings['level_max_value']); ?>">
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.text.b2845de5')); ?></h2>
        </div>
        <p class="form-help">
            <?php echo sr_e(sr_t('community::ui.level_definitions_help_score')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_definitions_help_formula')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_recalculate_notice_change')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_recalculate_notice_load')); ?>
        </p>
        <p class="form-help">레벨 설정 저장은 최소 점수만 저장합니다. 회원 레벨 재계산은 저장과 별도로 실행되며, 작성 중인 최소 점수 입력값을 함께 저장하지 않습니다.</p>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?></th>
                        <th><span class="form-check form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.2ba8a858'), $communitySettingsHelp['level_min_score']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.2ba8a858')); ?></span></span></th>
                        <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($levels === []) { ?>
                        <tr><td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.b4915f04')); ?></td></tr>
                    <?php } else { ?>
                        <?php foreach ($levels as $level) { ?>
                            <tr>
                                <td><?php echo sr_e(sr_community_level_label((int) $level['level_value'], $settings)); ?></td>
                                <td><?php echo sr_e((string) $level['title']); ?></td>
                                <td>
                                    <input
                                        type="number"
                                        name="level_min_score[<?php echo sr_e((string) $level['id']); ?>]"
                                        class="form-input"
                                        min="0"
                                        max="1000000000"
                                        value="<?php echo sr_e((string) $level['min_score']); ?>"
                                    >
                                </td>
                                <td><?php echo sr_e(sr_admin_code_label((string) $level['status'], 'content_status')); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="button" class="btn btn-solid-light"<?php echo $communityLevelEnabled ? '' : ' disabled'; ?> aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-open><?php echo sr_e(sr_t('community::ui.member.9fba6ddf')); ?></button>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.save.bca4cb2b')); ?></button>
    </div>
</form>

<form
    id="community-level-recalculate-form"
    method="post"
    action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>"
    data-community-level-recalculate-form
    data-recalculate-url="<?php echo sr_e(sr_url('/admin/community/levels/recalculate')); ?>"
    data-batch-size="50"
>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="recalculate_levels">
    <input type="hidden" name="recalculate_confirmed" value="0" data-community-level-recalculate-confirmed>
    <input type="hidden" name="recalculate_confirm_text" value="" data-community-level-recalculate-confirm-text>
</form>
<div class="community-level-recalculate-progress" data-community-level-recalculate-progress hidden>
    <p class="type-small" data-community-level-recalculate-status role="status" aria-live="polite"><?php echo sr_e(sr_t('community::ui.level_recalculate_ready')); ?></p>
    <progress value="0" max="100" data-community-level-recalculate-meter></progress>
</div>
<div id="<?php echo sr_e($communityLevelRecalculateModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityLevelRecalculateModalId); ?>-label" aria-hidden="true" inert>
    <div class="modal-dialog-center">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($communityLevelRecalculateModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_title')); ?></h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div data-community-level-recalculate-step="notice">
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_notice_1')); ?></p>
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_notice_2')); ?></p>
                    <dl class="admin-meta-list">
                        <dt><?php echo sr_e('부하 등급'); ?></dt>
                        <dd><?php echo sr_e((string) $communityLevelRecalculateLoad['label']); ?></dd>
                        <dt><?php echo sr_e('처리 대상'); ?></dt>
                        <dd><?php echo sr_e('활성/대기 회원 ' . number_format($communityLevelRecalculateTargetCount) . '명'); ?></dd>
                        <dt><?php echo sr_e('중단/실패 시 상태'); ?></dt>
                        <dd><?php echo sr_e((string) $communityLevelRecalculateLoad['failure_state']); ?></dd>
                        <dt><?php echo sr_e('권장 실행 시점'); ?></dt>
                        <dd><?php echo sr_e((string) $communityLevelRecalculateLoad['recommended_time']); ?></dd>
                        <dt><?php echo sr_e('기록 위치'); ?></dt>
                        <dd><?php echo sr_e('완료 결과는 감사 로그 community.levels.recalculated metadata에 대상 수와 배치 정보를 남깁니다.'); ?></dd>
                    </dl>
                </div>
                <div data-community-level-recalculate-step="text" hidden>
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_text_help', ['text' => sr_t('community::ui.level_recalculate_confirmation_text')])); ?></p>
                    <label class="sr-only" for="community-level-recalculate-confirm-input"><?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_label')); ?></label>
                    <input id="community-level-recalculate-confirm-input" type="text" class="form-input" autocomplete="off" placeholder="<?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_text')); ?>" aria-describedby="community-level-recalculate-confirm-error" data-overlay-focus data-community-level-recalculate-confirm-input>
                    <p id="community-level-recalculate-confirm-error" class="community-confirm-validation-message type-caption" data-community-level-recalculate-confirm-error hidden><?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_error')); ?></p>
                </div>
            </div>
            <div class="modal-footer-note">
                <p class="form-help">재계산 실행은 현재 저장된 레벨 기준으로 회원 레벨을 다시 계산합니다. 레벨 설정 form의 작성 중인 최소 점수 입력값은 함께 저장되지 않습니다.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-cancel><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-level-recalculate-next><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_next')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-level-recalculate-confirm-submit hidden><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_execute')); ?></button>
            </div>
        </div>
    </div>
</div>
<?php echo sr_admin_help_modal_html((string) $communitySettingsHelp['level_min_score']['id'], (string) $communitySettingsHelp['level_min_score']['title'], (string) $communitySettingsHelp['level_min_score']['body']); ?>
<script>
(function () {
    var form = document.querySelector('[data-community-level-recalculate-form]');
    if (!form || !window.fetch || !window.FormData) {
        return;
    }

    var openButton = document.querySelector('[data-community-level-recalculate-open]');
    var progress = document.querySelector('[data-community-level-recalculate-progress]');
    var status = document.querySelector('[data-community-level-recalculate-status]');
    var meter = document.querySelector('[data-community-level-recalculate-meter]');
    var confirmedInput = document.querySelector('[data-community-level-recalculate-confirmed]');
    var confirmTextHidden = document.querySelector('[data-community-level-recalculate-confirm-text]');
    var confirmTextInput = document.querySelector('[data-community-level-recalculate-confirm-input]');
    var confirmError = document.querySelector('[data-community-level-recalculate-confirm-error]');
    var stepNotice = document.querySelector('[data-community-level-recalculate-step="notice"]');
    var stepText = document.querySelector('[data-community-level-recalculate-step="text"]');
    var nextButton = document.querySelector('[data-community-level-recalculate-next]');
    var confirmSubmitButton = document.querySelector('[data-community-level-recalculate-confirm-submit]');
    var modalCloseButton = document.querySelector('[data-community-level-recalculate-close]');
    var cancelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-recalculate-cancel]'));
    var requiredConfirmationText = <?php echo sr_js_json_encode(sr_t('community::ui.level_recalculate_confirmation_text')); ?>;
    var labels = <?php echo sr_js_json_encode([
        'start' => sr_t('community::ui.level_recalculate_start'),
        'running' => sr_t('community::ui.level_recalculate_running'),
        'running_unknown' => sr_t('community::ui.level_recalculate_running_unknown'),
        'error' => sr_t('community::ui.level_recalculate_error'),
    ]); ?>;

    function text(template, values) {
        return Object.keys(values).reduce(function (message, key) {
            return message.replace('{' + key + '}', String(values[key]));
        }, template);
    }

    function resetModal(clearConfirmation) {
        if (clearConfirmation === void 0) {
            clearConfirmation = true;
        }
        if (stepNotice) {
            stepNotice.hidden = false;
        }
        if (stepText) {
            stepText.hidden = true;
        }
        if (nextButton) {
            nextButton.hidden = false;
        }
        if (confirmSubmitButton) {
            confirmSubmitButton.hidden = true;
        }
        if (confirmTextInput) {
            confirmTextInput.value = '';
        }
        if (clearConfirmation && confirmTextHidden) {
            confirmTextHidden.value = '';
        }
        if (clearConfirmation && confirmedInput) {
            confirmedInput.value = '0';
        }
        setConfirmInvalid(false);
    }

    function setConfirmInvalid(isInvalid) {
        if (confirmTextInput) {
            confirmTextInput.classList.toggle('form-input-invalid', isInvalid);
            if (isInvalid) {
                confirmTextInput.setAttribute('aria-invalid', 'true');
            } else {
                confirmTextInput.removeAttribute('aria-invalid');
            }
        }
        if (confirmError) {
            confirmError.hidden = !isInvalid;
        }
    }

    function updateStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function updateProgress(processed, total, done) {
        if (!meter) {
            return;
        }

        meter.value = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : (done ? 100 : 0);
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (stepNotice) {
                stepNotice.hidden = true;
            }
            if (stepText) {
                stepText.hidden = false;
            }
            nextButton.hidden = true;
            if (confirmSubmitButton) {
                confirmSubmitButton.hidden = false;
            }
            if (confirmTextInput) {
                confirmTextInput.focus();
            }
        });
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            resetModal();
        });
    }

    cancelButtons.forEach(function (cancelButton) {
        cancelButton.addEventListener('click', resetModal);
    });

    if (confirmSubmitButton) {
        confirmSubmitButton.addEventListener('click', function (event) {
            var value = confirmTextInput ? confirmTextInput.value.trim() : '';
            if (value !== requiredConfirmationText) {
                event.preventDefault();
                event.stopPropagation();
                setConfirmInvalid(true);
                if (confirmTextInput) {
                    confirmTextInput.focus();
                }
                return;
            }

            setConfirmInvalid(false);
            if (confirmTextHidden) {
                confirmTextHidden.value = value;
            }
            if (confirmedInput) {
                confirmedInput.value = '1';
            }
            if (modalCloseButton) {
                modalCloseButton.click();
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (openButton && openButton.disabled) {
            event.preventDefault();
            return;
        }

        event.preventDefault();

        var url = form.getAttribute('data-recalculate-url') || '';
        var batchSize = parseInt(form.getAttribute('data-batch-size') || '50', 10);
        if (!url || !Number.isFinite(batchSize) || batchSize < 1) {
            return;
        }

        if (!confirmedInput || confirmedInput.value !== '1' || !confirmTextHidden || confirmTextHidden.value !== requiredConfirmationText) {
            return;
        }

        var cursor = 0;
        var processed = 0;
        var total = 0;
        var jobId = 0;
        var lockToken = '';

        if (openButton) {
            openButton.disabled = true;
        }
        if (progress) {
            progress.hidden = false;
        }
        resetModal(false);
        updateProgress(0, 0, false);
        updateStatus(labels.start);

        function runBatch() {
            var body = new FormData(form);
            body.set('cursor', String(cursor));
            body.set('batch_size', String(batchSize));
            body.set('processed_total', String(processed));
            body.set('job_id', String(jobId));
            body.set('lock_token', lockToken);

            return window.fetch(url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        throw new Error(payload && payload.message ? payload.message : labels.error);
                    }

                    return payload;
                });
            }).then(function (payload) {
                processed = Number(payload.processed_total || processed);
                total = Number(payload.total || total);
                cursor = Number(payload.next_cursor || cursor);
                jobId = Number(payload.job_id || jobId);
                lockToken = String(payload.lock_token || lockToken);
                updateProgress(processed, total, !!payload.done);

                if (payload.done) {
                    updateStatus(payload.message || text(labels.running, {
                        processed: processed,
                        total: total,
                    }));
                    if (openButton) {
                        openButton.disabled = false;
                    }
                    resetModal();
                    return;
                }

                updateStatus(total > 0 ? text(labels.running, {
                    processed: processed,
                    total: total,
                }) : text(labels.running_unknown, {
                    processed: processed,
                }));
                return runBatch();
            }).catch(function (error) {
                updateStatus(error && error.message ? error.message : labels.error);
                if (openButton) {
                    openButton.disabled = false;
                }
                resetModal();
            });
        }

        runBatch();
    });

    if (confirmTextInput) {
        confirmTextInput.addEventListener('input', function () {
            setConfirmInvalid(false);
        });
    }
})();
</script>
<?php } ?>

<script>
(function () {
    function formatThumbnailBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes < 0) {
            bytes = 0;
        }
        if (bytes === 0) {
            return '0 bytes';
        }
        if (bytes < 1024) {
            return bytes.toLocaleString('ko-KR', {
                maximumFractionDigits: 0
            }) + ' bytes';
        }
        if (bytes < 1048576) {
            var kb = bytes / 1024;
            return kb.toLocaleString('ko-KR', {
                minimumFractionDigits: kb >= 10 ? 0 : 1,
                maximumFractionDigits: kb >= 10 ? 1 : 1
            }) + ' KB';
        }
        var mb = bytes / 1048576;
        return mb.toLocaleString('ko-KR', {
            minimumFractionDigits: mb >= 10 ? 0 : 2,
            maximumFractionDigits: mb >= 10 ? 1 : 2
        }) + ' MB';
    }

    function syncCommunityThumbnailBytesLabel(root) {
        Array.prototype.slice.call((root || document).querySelectorAll('[data-community-thumbnail-bytes-input]')).forEach(function (input) {
            var field = input.closest ? input.closest('.form-field') : null;
            var label = field ? field.querySelector('[data-community-thumbnail-bytes-label]') : null;
            if (label) {
                label.textContent = formatThumbnailBytes(input.value);
            }
        });
    }

    function syncCommunityThumbnailCriterion(form) {
        var root = form || document;
        var checked = root.querySelector('input[name="thumbnail_criterion"]:checked');
        var selected = checked ? checked.value : 'width';
        Array.prototype.slice.call(root.querySelectorAll('[data-community-thumbnail-rule]')).forEach(function (row) {
            var visible = row.getAttribute('data-community-thumbnail-rule') === selected;
            row.hidden = !visible;
            Array.prototype.slice.call(row.querySelectorAll('[data-admin-required-when-visible]')).forEach(function (input) {
                input.required = visible;
                if (!visible && typeof input.setCustomValidity === 'function') {
                    input.setCustomValidity('');
                }
            });
        });
        syncCommunityThumbnailBytesLabel(root);
    }

    document.addEventListener('change', function (event) {
        if (!event.target || event.target.name !== 'thumbnail_criterion') {
            return;
        }
        syncCommunityThumbnailCriterion(event.target.closest('form'));
    });
    document.addEventListener('input', function (event) {
        if (!event.target || !event.target.matches('[data-community-thumbnail-bytes-input]')) {
            return;
        }
        syncCommunityThumbnailBytesLabel(event.target.closest('form'));
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            syncCommunityThumbnailCriterion(document);
        });
    } else {
        syncCommunityThumbnailCriterion(document);
    }
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
