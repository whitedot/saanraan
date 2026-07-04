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
$assetModuleOptions = isset($assetModuleOptions) && is_array($assetModuleOptions) ? $assetModuleOptions : [];
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
$contentLayoutExtraMenuItems = function_exists('sr_content_layout_extra_menu_items_from_settings') ? sr_content_layout_extra_menu_items_from_settings($settings) : [];
$contentLayoutExtraMenuRows = static function (array $menuItems, bool $template = false) use ($contentSiteMenuSelectOptions): void {
    foreach ($template ? [['area_key' => '', 'menu_key' => '']] : $menuItems as $menuItem) {
        $areaKey = is_array($menuItem) ? (string) ($menuItem['area_key'] ?? '') : '';
        $menuLabel = is_array($menuItem) ? (string) ($menuItem['label'] ?? '') : '';
        $selectedMenuKey = is_array($menuItem) ? (string) ($menuItem['menu_key'] ?? '') : (string) $menuItem;
        ?>
        <div class="admin-layout-menu-row"<?php echo $template ? ' hidden data-admin-layout-menu-template' : ''; ?> data-admin-layout-menu-row>
            <input type="text" name="layout_extra_menu_area_keys[]" value="<?php echo sr_e($areaKey); ?>" class="form-input admin-layout-menu-key-input" maxlength="60" pattern="(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="자동 key" aria-label="추가 메뉴 자동 key" readonly data-admin-layout-menu-key data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
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

<form method="post" action="<?php echo sr_e(sr_url('/admin/content/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="card">
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
            <span class="form-label">임베드 사용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_embed_enabled', 'embed_enabled', '1', !empty($settings['embed_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 콘텐츠 본문 안의 주소 임베드를 표시하지 않고, 다른 본문에 붙여 넣은 콘텐츠 URL도 자동 표시하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">본문 URL 자동 링크</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_plain_text_auto_link_urls', 'plain_text_auto_link_urls', '1', !empty($settings['plain_text_auto_link_urls']), '사용'); ?>
                <p class="form-help">textarea로 저장된 plain text 본문에만 적용합니다. HTML 본문은 저장된 링크와 정화 정책을 그대로 사용합니다.</p>
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

    <section class="card">
        <h2>공개 화면 구성</h2>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_theme_key">기본 콘텐츠 테마 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="content_admin_settings_theme_key" name="theme_key" class="form-select" required>
                    <?php foreach ($publicThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">콘텐츠 공개 화면의 본문 구조, 색, 표면, 상호작용 상태에 적용할 시각 테마입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_layout_key">콘텐츠 공개 레이아웃 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="content_admin_settings_layout_key" name="layout_key" class="form-select" required>
                    <?php foreach ($contentLayoutOptions as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">선택한 테마 아래에서 콘텐츠 화면을 감싸는 공개 화면 틀입니다. 공통 레이아웃과 필요한 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_layout_primary_menu_key">주 메뉴</label>
            <div class="form-field">
                <select id="content_admin_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                    <?php $contentSiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">추가 메뉴</span>
            <div class="form-field">
                <div class="admin-layout-menu-list" data-admin-layout-menu-list>
                    <div class="admin-layout-menu-header"<?php echo $contentLayoutExtraMenuItems === [] ? ' hidden' : ''; ?> aria-hidden="true" data-admin-layout-menu-header>
                        <span>Key</span>
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
            </div>
        </div>
    </section>

    <section class="card">
        <h2>시리즈</h2>
        <div class="form-row">
            <span class="form-label">시리즈 기능</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_series_enabled', 'series_enabled', '1', !empty($settings['series_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 콘텐츠 시리즈 생성, 연결, 관리와 공개 콘텐츠의 시리즈 내비게이션을 사용하지 않습니다.</p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>리액션</h2>
        <div class="form-row">
            <span class="form-label">리액션 사용 여부</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_reaction_enabled', 'reaction_enabled', '1', !empty($settings['reaction_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 콘텐츠와 댓글의 리액션 위젯을 표시하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_reaction_preset_key">콘텐츠 리액션 프리셋</label>
            <div class="form-field">
                <select id="content_admin_settings_reaction_preset_key" name="reaction_preset_key" class="form-select">
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">개별 콘텐츠에서 따로 선택하지 않았을 때 사용할 기본 프리셋입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="content_admin_settings_reaction_comment_preset_key">댓글 리액션 프리셋</label>
            <div class="form-field">
                <select id="content_admin_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>이용/과금 기준</h2>
        <div class="form-row">
            <span class="form-label">콘텐츠 열람 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_content_view_required', 'identity_content_view_required', '1', !empty($settings['identity_content_view_required']), '사용'); ?>
                <p class="form-help">사용하면 공개 콘텐츠를 보려는 회원에게 본인확인을 요구합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">콘텐츠 열람 성인 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_content_view_adult_required', 'identity_content_view_adult_required', '1', !empty($settings['identity_content_view_adult_required']), '사용'); ?>
                <p class="form-help">사용하면 성인 여부가 확인된 본인확인 결과가 있어야 공개 콘텐츠를 볼 수 있습니다. 본인확인 환경설정의 생년월일 사용이 켜져 있어야 저장할 수 있습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">복합 자산 결제</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_multi_asset_payment_enabled', 'multi_asset_payment_enabled', '1', !empty($settings['multi_asset_payment_enabled']), '허용'); ?>
                <p class="form-help">끄면 유료 열람과 파일 다운로드 결제는 포인트/금액 항목을 하나만 사용할 수 있습니다. 쿠폰 일부 할인 후 남은 금액도 같은 기준으로 처리합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('content_admin_settings_once_history_policy', '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpId, '설명 보기', true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('content_admin_settings_once_history_policy', 'once_history_policy', sr_content_once_history_policy_values(), (string) ($settings['once_history_policy'] ?? 'all_access'), true); ?>
                <p class="form-help">과금 방식을 최초 1회로 운영할 때 예전에 이용한 회원을 다시 결제시킬지 정합니다. 기존 원장 거래와 쿠폰 사용 로그는 자동 환불하거나 추가 차감하지 않습니다.</p>
            </div>
        </div>
    </section>
    <section class="card">
        <h2>회원 콘텐츠 제출</h2>
        <div class="form-row">
            <span class="form-label">회원 제출 기능</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_enabled', 'member_submission_enabled', '1', !empty($settings['member_submission_enabled']), '사용'); ?>
                <p class="form-help">콘텐츠 그룹별 제출 허용과 작성자 승인/회원 그룹 조건을 함께 만족해야 합니다.</p>
            </div>
        </div>
        <?php $memberSubmissionEnabled = !empty($settings['member_submission_enabled']); ?>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">작성자 신청 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_author_application_required', 'identity_author_application_required', '1', !empty($settings['identity_author_application_required']), '사용'); ?>
                <p class="form-help">사용하면 콘텐츠 작성자 신청 전에 본인확인을 요구합니다.</p>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">작성자 신청 성인 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_identity_author_application_adult_required', 'identity_author_application_adult_required', '1', !empty($settings['identity_author_application_adult_required']), '사용'); ?>
                <p class="form-help">사용하면 성인 여부가 확인된 본인확인 결과가 있어야 콘텐츠 작성자 신청을 받을 수 있습니다.</p>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">기본 검수</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_default_review_required', 'member_submission_default_review_required', '1', !empty($settings['member_submission_default_review_required']), '필수'); ?>
            </div>
        </div>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <span class="form-label">리워드</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('content_admin_settings_member_submission_author_reward_enabled', 'member_submission_author_reward_enabled', '1', !empty($settings['member_submission_author_reward_enabled']), '지급'); ?>
                <p class="form-help">제출본이 승인되어 콘텐츠로 공개될 때 제출 회원에게 한 번만 지급합니다. 지급 실패는 로그에 남기고 승인 처리는 유지합니다.</p>
            </div>
        </div>
        <?php $authorRewardAssetSelected = (string) ($settings['member_submission_author_reward_asset_module'] ?? '') !== ''; ?>
        <div class="form-row" data-admin-visible-when-checked="#content_admin_settings_member_submission_enabled"<?php echo $memberSubmissionEnabled ? '' : ' hidden'; ?>>
            <label class="form-label" for="content_admin_settings_member_submission_author_reward_asset_module">리워드 설정</label>
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
            <label class="form-label" for="content_admin_settings_member_submission_author_reward_amount">작성자 리워드 금액 <span class="sr-required-label" data-admin-required-label-when-visible<?php echo $memberSubmissionEnabled && $authorRewardAssetSelected ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <?php echo sr_content_asset_single_amount_input_group_html('member_submission_author_reward_amount', (int) ($settings['member_submission_author_reward_amount'] ?? 0), $assetModuleOptions, (string) ($settings['member_submission_author_reward_asset_module'] ?? ''), '작성자 리워드 금액', 'content_admin_settings_member_submission_author_reward_amount', false, 'member_submission_author_reward_asset_module', ' data-admin-required-when-visible data-admin-clear-when-hidden="1"' . ($memberSubmissionEnabled && $authorRewardAssetSelected ? ' required' : '')); ?>
            </div>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php echo sr_admin_help_modal_html($contentOnceHistoryPolicyHelpId, '기존 이용자 재결제 기준', $contentOnceHistoryPolicyHelpBody); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
