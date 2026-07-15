<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';

$surveySiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$surveySiteMenuSelectOptions = static function (string $selectedMenuKey) use ($surveySiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach ($surveySiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$surveyLayoutExtraMenuItems = function_exists('sr_survey_layout_extra_menu_items_from_settings') ? sr_survey_layout_extra_menu_items_from_settings($settings) : [];
$surveyIdentityViewAvailable = isset($surveyIdentityViewAvailable)
    ? (bool) $surveyIdentityViewAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'survey.view'));
$surveyIdentityViewAdultAvailable = isset($surveyIdentityViewAdultAvailable)
    ? (bool) $surveyIdentityViewAdultAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'survey.view.adult'));
$surveyIdentityUnavailable = !$surveyIdentityViewAvailable;
$surveyIdentityViewInputAttributes = $surveyIdentityViewAvailable
    ? ''
    : ' disabled aria-describedby="survey-settings-identity-unavailable"';
$surveyIdentityViewAdultInputAttributes = $surveyIdentityViewAdultAvailable
    ? ''
    : ' disabled aria-describedby="survey-settings-identity-adult-unavailable"';
$surveyReactionAvailable = isset($surveyReactionAvailable)
    ? (bool) $surveyReactionAvailable
    : (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php'));
$surveyReactionInputAttributes = $surveyReactionAvailable
    ? ''
    : ' disabled aria-describedby="survey-settings-reaction-unavailable"';
$surveyLayoutExtraMenuRows = static function (array $menuItems, bool $template = false) use ($surveySiteMenuSelectOptions): void {
    foreach ($template ? [['area_key' => '', 'label' => '', 'menu_key' => '']] : $menuItems as $menuItem) {
        $areaKey = is_array($menuItem) ? (string) ($menuItem['area_key'] ?? '') : '';
        $menuLabel = is_array($menuItem) ? (string) ($menuItem['label'] ?? '') : '';
        $selectedMenuKey = is_array($menuItem) ? (string) ($menuItem['menu_key'] ?? '') : (string) $menuItem;
        ?>
        <div class="admin-layout-menu-row"<?php echo $template ? ' hidden data-admin-layout-menu-template' : ''; ?> data-admin-layout-menu-row>
            <input type="text" name="layout_extra_menu_area_keys[]" value="<?php echo sr_e($areaKey); ?>" class="form-input admin-layout-menu-key-input" maxlength="60" pattern="(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="자동 key" aria-label="추가 메뉴 자동 key" readonly data-admin-layout-menu-key data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <input type="text" name="layout_extra_menu_labels[]" value="<?php echo sr_e($menuLabel); ?>" class="form-input" maxlength="80" placeholder="이름" aria-label="추가 메뉴 이름" data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <select name="layout_extra_menu_keys[]" class="form-select" data-admin-layout-menu-select data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
                <?php $surveySiteMenuSelectOptions((string) $selectedMenuKey); ?>
            </select>
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger admin-layout-menu-remove" data-admin-layout-menu-remove aria-label="추가 메뉴 제거" title="제거"><?php echo sr_material_icon_html('delete'); ?></button>
        </div>
        <?php
    }
};
$surveySettingsHelpOpenLabel = '설명 보기';
$surveySettingsHelpBodyHtml = static function (array $items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<p>' . sr_e((string) $item) . '</p>';
    }
    return $html;
};
$surveySettingsHelp = [
    'layout_key' => [
        'id' => 'survey-settings-help-layout-key',
        'title' => '설문 공개 레이아웃',
        'body_html' => $surveySettingsHelpBodyHtml([
            '선택한 테마 아래에서 설문 목록, 응답, 완료 화면을 감싸는 공개 화면 틀입니다.',
            '공통 레이아웃과 설문 공개 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.',
        ]),
    ],
    'theme_key' => [
        'id' => 'survey-settings-help-theme-key',
        'title' => '설문 공개 테마',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 공개 테마는 설문 공개 화면의 본문 구조, 색, 표면, 테두리, 상호작용 상태를 적용하는 설정입니다.',
            '테마 변경은 설문 데이터나 응답 기록을 바꾸지 않고 선택한 theme 디렉터리의 공개 view와 asset을 공개 출력에 적용합니다.',
        ]),
    ],
    'default_status' => [
        'id' => 'survey-settings-help-default-status',
        'title' => '기본 상태',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문을 만들 때 먼저 선택되어 있을 상태입니다.',
            '바로 공개하지 않으려면 초안으로 두고, 문항과 QA 점검을 마친 뒤 수정 화면에서 공개로 바꿉니다.',
        ]),
    ],
    'default_response_limit_policy' => [
        'id' => 'survey-settings-help-default-response-limit-policy',
        'title' => '기본 응답 제한',
        'body_html' => $surveySettingsHelpBodyHtml([
            '회원 또는 익명 응답자가 같은 설문에 다시 응답할 수 있는 기본 기준입니다.',
            '기간당 1회를 선택하면 제한 기간을 초 단위로 함께 입력해야 합니다.',
        ]),
    ],
    'default_response_limit_period_seconds' => [
        'id' => 'survey-settings-help-default-response-limit-period',
        'title' => '기본 제한 기간',
        'body_html' => $surveySettingsHelpBodyHtml([
            '기본 응답 제한이 기간당 1회일 때만 사용하는 초 단위 값입니다.',
            '예를 들어 하루에 한 번만 응답하게 하려면 86400을 입력합니다.',
        ]),
    ],
    'public_list_limit' => [
        'id' => 'survey-settings-help-public-list-limit',
        'title' => '공개 목록 페이지당 표시 수',
        'body_html' => $surveySettingsHelpBodyHtml([
            '공개 설문 목록의 한 페이지에 표시할 설문 수입니다. 다음 페이지에서 나머지 설문을 계속 탐색할 수 있습니다.',
            '관리자 목록과 응답 목록의 페이지 크기는 관리자 공통 페이징 설정을 계속 사용합니다.',
        ]),
    ],
    'skin_key' => [
        'id' => 'survey-settings-help-skin-key',
        'title' => '설문 스킨',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 공개 목록, 상세/응답, 완료 화면의 본문 출력 방식입니다.',
            '허용된 스킨 Key만 저장하고, 누락된 화면 파일은 기본 스킨으로 대체합니다.',
        ]),
    ],
    'default_login_required' => [
        'id' => 'survey-settings-help-default-login-required',
        'title' => '로그인 필요',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문 생성 시 로그인 필요를 기본으로 켤지 정합니다.',
            '보상 설문이나 회원 그룹 제한 설문은 저장 시점에 로그인 필요 상태가 강제됩니다.',
        ]),
    ],
    'default_consent_required' => [
        'id' => 'survey-settings-help-default-consent-required',
        'title' => '참여 동의 필요',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문 생성 시 참여 동의 체크를 기본으로 켤지 정합니다.',
            '동의가 필요한 설문은 저장할 때 동의 문구도 함께 입력해야 합니다.',
        ]),
    ],
    'reaction_preset_key' => [
        'id' => 'survey-settings-help-reaction-preset',
        'title' => '설문 리액션 프리셋',
        'body_html' => $surveySettingsHelpBodyHtml([
            '개별 설문에서 프리셋을 비워둘 때 사용하는 기본 리액션 세트입니다.',
        ]) . '<p><a href="' . sr_e(sr_url('/admin/reactions/presets')) . '" target="_blank" rel="noopener noreferrer">리액션 프리셋 관리</a>에서 운영자가 세트를 추가하거나 수정할 수 있습니다.</p>',
    ],
    'reaction_comment_preset_key' => [
        'id' => 'survey-settings-help-reaction-comment-preset',
        'title' => '댓글 리액션 프리셋',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 댓글에 적용할 기본 리액션 세트입니다.',
            '개별 설문에서 댓글 프리셋을 지정하면 이 값보다 우선합니다.',
        ]),
    ],
];
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">공개 화면 구성과 새 설문 기본값</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_theme_key', '설문 공개 테마', $surveySettingsHelp['theme_key']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="survey_settings_theme_key" name="theme_key" class="form-select" required>
                        <?php foreach ($publicThemeOptions as $themeKey => $themeOption) { ?>
                            <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">설문 공개 화면의 본문 구조, 색, 표면, 상호작용 상태에 적용할 시각 테마입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_layout_key', '설문 공개 레이아웃', $surveySettingsHelp['layout_key']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="survey_settings_layout_key" name="layout_key" class="form-select" required>
                        <?php foreach ($surveyLayoutOptions as $layoutKey => $layoutOption) { ?>
                            <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">선택한 테마 아래에서 설문 화면을 감싸는 공개 화면 틀입니다. 공통 레이아웃과 필요한 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_skin_key', '설문 스킨', $surveySettingsHelp['skin_key']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="survey_settings_skin_key" name="skin_key" class="form-select" required>
                        <?php foreach (sr_survey_skin_options() as $skinKey => $skinLabel) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($settings['skin_key'] ?? 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $skinLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">공개 레이아웃 안쪽의 설문 본문 출력 스킨입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="survey_settings_layout_primary_menu_key">주 메뉴</label>
                <div class="form-field">
                    <select id="survey_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                        <?php $surveySiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">추가 메뉴</span>
                <div class="form-field">
                    <div class="admin-layout-menu-list" data-admin-layout-menu-list>
                        <div class="admin-layout-menu-header"<?php echo $surveyLayoutExtraMenuItems === [] ? ' hidden' : ''; ?> aria-hidden="true" data-admin-layout-menu-header>
                            <span>Key</span>
                            <span>이름</span>
                            <span>메뉴</span>
                            <span>동작</span>
                        </div>
                        <?php $surveyLayoutExtraMenuRows($surveyLayoutExtraMenuItems); ?>
                        <?php $surveyLayoutExtraMenuRows([], true); ?>
                        <div class="admin-layout-menu-actions" data-admin-layout-menu-actions>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-layout-menu-add><?php echo sr_material_icon_html('add'); ?> 추가 메뉴 추가</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">사업자정보</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_business_info_visible', 'business_info_visible', '1', !empty($settings['business_info_visible']), '노출'); ?>
                    <p class="form-help">사이트 설정에 저장된 사업자 정보 중 값이 있는 항목을 설문 공개 레이아웃 푸터에 표시합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_default_status', '기본 상태', $surveySettingsHelp['default_status']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $surveyStatusToggleOptions = [];
                    foreach (sr_survey_statuses() as $status) {
                        $surveyStatusToggleOptions[$status] = sr_survey_status_label($status);
                    }
                    echo sr_admin_radio_toggle_group_html('survey_settings_default_status', 'default_status', $surveyStatusToggleOptions, (string) ($settings['default_status'] ?? 'draft'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_response_limit_policy', '기본 응답 제한', $surveySettingsHelp['default_response_limit_policy']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $surveyLimitPolicyToggleOptions = [];
                    foreach (sr_survey_response_limit_policies() as $policy) {
                        $surveyLimitPolicyToggleOptions[$policy] = sr_survey_response_limit_policy_label($policy);
                    }
                    echo sr_admin_radio_toggle_group_html('survey_settings_response_limit_policy', 'default_response_limit_policy', $surveyLimitPolicyToggleOptions, (string) ($settings['default_response_limit_policy'] ?? 'per_survey_once'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_response_limit_period', '기본 제한 기간', $surveySettingsHelp['default_response_limit_period_seconds']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <input id="survey_settings_response_limit_period" type="number" name="default_response_limit_period_seconds" value="<?php echo sr_e((string) (int) ($settings['default_response_limit_period_seconds'] ?? 0)); ?>" class="form-input" min="0">
                    <p class="form-help">기간당 1회 제한일 때 초 단위로 입력합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_public_list_limit', '공개 목록 페이지당 표시 수', $surveySettingsHelp['public_list_limit']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="survey_settings_public_list_limit" type="number" name="public_list_limit" value="<?php echo sr_e((string) (int) ($settings['public_list_limit'] ?? 50)); ?>" class="form-input" min="1" max="100" required>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">내부 모듈 간 임베드</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_internal_embed_enabled', 'internal_embed_enabled', '1', !empty($settings['internal_embed_enabled']), '사용'); ?>
                    <p class="form-help">꺼져 있으면 콘텐츠나 커뮤니티 본문에 붙여 넣은 설문 URL을 자동 표시하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">설문 참여 본인확인</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_identity_view_required', 'identity_view_required', '1', $surveyIdentityViewAvailable && !empty($settings['identity_view_required']), '사용', '', $surveyIdentityViewInputAttributes); ?>
                    <p class="form-help">사용하면 설문 상세와 응답 전에 본인확인을 요구합니다.</p>
                    <?php if ($surveyIdentityUnavailable) { ?>
                        <p id="survey-settings-identity-unavailable" class="form-help form-help-warning">
                            <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 목적에 맞는 제공자가 준비되지 않은 항목은 사용할 수 없습니다.
                        </p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">설문 참여 성인 본인확인</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_identity_view_adult_required', 'identity_view_adult_required', '1', $surveyIdentityViewAdultAvailable && !empty($settings['identity_view_adult_required']), '사용', '', $surveyIdentityViewAdultInputAttributes); ?>
                    <?php if ($surveyIdentityViewAdultAvailable) { ?>
                        <p class="form-help form-help-info">사용하면 성인 여부가 확인된 회원만 설문에 접근할 수 있습니다.</p>
                    <?php } else { ?>
                        <p id="survey-settings-identity-adult-unavailable" class="form-help form-help-warning">현재 저장할 수 없습니다. <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 생년월일 사용을 켜고 설문 성인 참여 목적 제공자를 설정하세요.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_login_required', '로그인 필요', $surveySettingsHelp['default_login_required']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_login_required', 'default_login_required', '1', (int) ($settings['default_login_required'] ?? 1) === 1, '적용'); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_consent_required', '참여 동의 필요', $surveySettingsHelp['default_consent_required']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_consent_required', 'default_consent_required', '1', (int) ($settings['default_consent_required'] ?? 0) === 1, '적용'); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_reaction_preset_key', '설문 리액션 프리셋', $surveySettingsHelp['reaction_preset_key']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="survey_settings_reaction_preset_key" name="reaction_preset_key" class="form-select"<?php echo $surveyReactionInputAttributes; ?>>
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">개별 설문에서 값을 비워두면 이 값을 사용합니다.</p>
                    <?php if (!$surveyReactionAvailable) { ?>
                        <p id="survey-settings-reaction-unavailable" class="form-help form-help-warning"><a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">리액션 모듈</a>을 설치하고 활성화하면 리액션 기본값을 사용할 수 있습니다.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_reaction_comment_preset_key', '댓글 리액션 프리셋', $surveySettingsHelp['reaction_comment_preset_key']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="survey_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select"<?php echo $surveyReactionInputAttributes; ?>>
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">설문 댓글 리액션에 적용할 기본 프리셋입니다.</p>
                </div>
            </div>
        </div>
    </section>

    <?php echo sr_admin_comment_extra_fields_editor_html(
        'survey_comment_extra_fields_json',
        'comment_extra_fields_json',
        $settings['comment_extra_fields_json'] ?? '[]',
        '댓글 추가 입력 항목',
        '새 설문 등록 화면을 열 때 미리 채워지는 항목입니다. 기존 설문에는 반영되지 않으며, 등록 화면에서 수정한 최종 값이 해당 설문에 저장됩니다.'
    ); ?>
    <div class="form-sticky-actions form-actions form-actions-split">
        <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/surveys')); ?>">설문 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($surveySettingsHelp as $helpModal): ?>
    <?php echo sr_admin_help_modal_html((string) $helpModal['id'], (string) $helpModal['title'], (string) $helpModal['body_html']); ?>
<?php endforeach; ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
