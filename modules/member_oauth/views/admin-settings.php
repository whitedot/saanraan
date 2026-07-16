<?php

$adminPageTitle = '외부 로그인 설정';
$adminPageSubtitle = '';
$callbackUrl = sr_absolute_url($site ?? [], '/oauth/callback');
$memberOauthProfileExtraFieldDefinitions = is_array($profileExtraFieldDefinitions ?? null) ? $profileExtraFieldDefinitions : [];
$memberOauthProfileSyncTargets = sr_member_oauth_profile_sync_targets($memberOauthProfileExtraFieldDefinitions);
$memberOauthProfileSettingsUrl = sr_url('/admin/member-settings#member-settings-section-profile');
$memberOauthCanEditProfileSettings = is_array($account ?? null)
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/member-settings', 'edit');
$memberOauthHelpOpenLabel = '도움말 보기';
$memberOauthHelpButtonHtml = static function (string $label, string $modalId) use ($memberOauthHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $memberOauthHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$memberOauthHelp = [
    'callback' => [
        'id' => 'member-oauth-help-callback',
        'title' => '되돌아올 URL 도움말',
        'body' => '<p>외부 로그인 서비스가 인증을 마친 뒤 회원을 다시 이 사이트로 보낼 주소입니다. 이 화면에 표시된 값을 제공자 관리 화면의 Redirect URI 또는 Callback URL 항목에 그대로 등록하세요.</p>'
            . '<p>사이트 기본 URL이 바뀌면 이 주소도 바뀝니다. 도메인이나 HTTPS 설정을 변경한 뒤에는 모든 제공자 관리 화면의 등록값도 함께 수정해야 로그인이 계속 동작합니다.</p>',
    ],
    'mock' => [
        'id' => 'member-oauth-help-mock',
        'title' => '테스트 로그인 도움말',
        'body' => '<p>실제 외부 서비스 인증 없이 고정된 테스트 회원 정보로 로그인과 신규 가입 흐름을 확인하는 기능입니다.</p>'
            . '<p>로그인 화면을 볼 수 있는 사람이라면 이 버튼을 사용할 수 있으므로 운영 사이트에서는 반드시 끄세요. Google, Kakao 같은 실제 제공자 연결을 시험하는 기능은 아닙니다.</p>',
    ],
    'state_ttl' => [
        'id' => 'member-oauth-help-state-ttl',
        'title' => '외부 로그인 대기 시간 도움말',
        'body' => '<p>회원이 외부 로그인이나 계정 연결을 시작한 뒤 제공자 인증을 마치고 돌아올 때까지 요청을 유효하게 둘 시간입니다.</p>'
            . '<p>시간이 지나면 이전 요청은 사용할 수 없으며 로그인을 처음부터 다시 시작해야 합니다. 60초부터 3,600초까지 입력합니다.</p>',
    ],
    'completion_ttl' => [
        'id' => 'member-oauth-help-completion-ttl',
        'title' => '신규 가입 완료 시간 도움말',
        'body' => '<p>외부 인증에는 성공했지만 아직 사이트 가입 약관 확인과 필수 입력을 끝내지 않은 신규 회원의 가입 정보를 유지할 시간입니다.</p>'
            . '<p>시간이 지나면 임시 가입 정보를 사용할 수 없으며 외부 로그인을 다시 시작해야 합니다. 60초부터 3,600초까지 입력합니다.</p>',
    ],
    'credentials' => [
        'id' => 'member-oauth-help-credentials',
        'title' => '외부 로그인 연결 정보 도움말',
        'body' => '<p>클라이언트 ID와 클라이언트 비밀값은 외부 로그인 제공자의 앱 관리 화면에서 발급받는 연결 정보입니다. 위 되돌아올 URL을 등록한 같은 앱의 값을 사용하세요.</p>'
            . '<p>클라이언트 비밀값은 외부에 공개하면 안 되는 값입니다. 이미 저장된 경우 입력란을 비워 두면 기존 값을 유지하며, 변경할 때만 새 값을 입력하세요.</p>',
    ],
    'scope' => [
        'id' => 'member-oauth-help-scope',
        'title' => '가져올 정보 권한 도움말',
        'body' => '<p>외부 로그인 서비스에 요청할 회원 정보 권한입니다. 제공자 문서에서는 Scope라고 부릅니다.</p>'
            . '<p>기본 항목은 로그인과 필수 회원 정보에 필요해 삭제할 수 없습니다. 항목을 추가하면 제공자 앱 관리 화면에서 같은 권한을 허용하거나 별도 심사를 받아야 할 수 있습니다.</p>',
    ],
    'profile_sync' => [
        'id' => 'member-oauth-help-profile-sync',
        'title' => '회원 정보 가져오기 도움말',
        'body' => '<p>외부 계정을 연결하거나 해당 제공자로 로그인할 때, 외부 서비스가 돌려준 값 중 어떤 값을 사이트 회원 정보에 반영할지 정합니다.</p>'
            . '<p>이름은 사이트 형식에 맞는 값만 갱신합니다. 이메일은 제공자가 확인된 이메일이라고 응답하고 다른 회원이 사용하지 않을 때만 갱신합니다. 그 밖의 값은 회원 환경설정에 같은 선택 프로필 항목이 있을 때만 저장합니다.</p>'
            . '<p>가져올 값 위치는 제공자 응답에서 값을 찾는 경로이며 제공자 문서에서는 Claim path라고 부릅니다. 잘못 지정하면 해당 회원 정보가 갱신되지 않습니다.</p>',
    ],
    'sort_order' => [
        'id' => 'member-oauth-help-sort-order',
        'title' => '로그인 버튼 순서 도움말',
        'body' => '<p>로그인 화면에 외부 로그인 버튼이 여러 개 있을 때 표시할 순서를 정하는 숫자입니다. 숫자가 작을수록 먼저 표시됩니다.</p>'
            . '<p>같은 숫자를 입력한 제공자는 제공자 키 순서로 정렬됩니다.</p>',
    ],
];
$memberOauthExternalProviders = [];
$memberOauthClaimPathOptionsByProvider = [];
foreach ($providers as $provider) {
    if (!empty($provider['mock'])) {
        continue;
    }
    $providerKey = (string) ($provider['provider_key'] ?? '');
    if ($providerKey === '') {
        continue;
    }
    $memberOauthExternalProviders[] = $provider;
    $memberOauthClaimPathOptionsByProvider[$providerKey] = sr_member_oauth_claim_path_options($provider);
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$memberOauthSectionNavItems = [
    'member-oauth-section-basic' => '기본 설정',
];
foreach ($memberOauthExternalProviders as $memberOauthNavProvider) {
    $memberOauthNavProviderKey = (string) ($memberOauthNavProvider['provider_key'] ?? '');
    $memberOauthSectionNavItems['member-oauth-section-provider-' . str_replace('_', '-', $memberOauthNavProviderKey)] = (string) ($memberOauthNavProvider['label'] ?? $memberOauthNavProviderKey);
}
if ($memberOauthExternalProviders === []) {
    $memberOauthSectionNavItems['member-oauth-section-providers-empty'] = '외부 제공자';
}
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="외부 로그인 설정 섹션">
    <?php $memberOauthSectionNavIndex = 0; ?>
    <?php foreach ($memberOauthSectionNavItems as $memberOauthSectionId => $memberOauthSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $memberOauthSectionId); ?>" class="tab-trigger-underline-justified<?php echo $memberOauthSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $memberOauthSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $memberOauthSectionLabel); ?>
        </a>
        <?php $memberOauthSectionNavIndex++; ?>
    <?php } ?>
</nav>

<form method="post" action="<?php echo sr_e(sr_url('/admin/member-oauth')); ?>" class="admin-form ui-form-theme member-oauth-admin-form" data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section id="member-oauth-section-basic" class="card" data-admin-section-anchor>
        <h2>기본 설정</h2>
        <div class="form-row">
            <span class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml('되돌아올 URL', $memberOauthHelp['callback']['id']); ?><span>되돌아올 URL</span></span>
            <div class="form-field">
                <div class="form-actions">
                    <p class="admin-form-static"><?php echo sr_e($callbackUrl); ?></p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-copy-value="<?php echo sr_e($callbackUrl); ?>" title="<?php echo sr_e('되돌아올 URL 복사'); ?>" aria-label="<?php echo sr_e('되돌아올 URL 복사'); ?>">
                        <?php echo sr_material_icon_html('content_copy'); ?>
                        <span><?php echo sr_e('복사'); ?></span>
                    </button>
                </div>
                <p class="form-help">외부 로그인 서비스 관리 화면의 Redirect URI 또는 Callback URL 항목에 등록합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_oauth_mock_enabled', '테스트 로그인', $memberOauthHelp['mock']['id'], $memberOauthHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="member_oauth_mock_enabled">
                    <input id="member_oauth_mock_enabled" type="checkbox" name="mock_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['mock_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
                <p class="form-help">외부 인증 없이 로그인 흐름을 확인합니다. 운영 사이트에서는 끄세요.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="member_oauth_mock_label"><?php echo sr_e('테스트 로그인 표시명'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="member_oauth_mock_label" type="text" name="mock_label" maxlength="80" value="<?php echo sr_e((string) $settings['mock_label']); ?>" required class="form-input form-control-full">
                <p class="form-help">로그인 화면의 테스트 로그인 버튼에 표시됩니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_oauth_state_ttl_seconds', '외부 로그인 대기 시간', $memberOauthHelp['state_ttl']['id'], $memberOauthHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_oauth_state_ttl_seconds" type="number" name="state_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['state_ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">외부 로그인 또는 계정 연결을 시작한 뒤 인증 결과를 기다릴 시간입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_oauth_completion_ttl_seconds', '신규 가입 완료 시간', $memberOauthHelp['completion_ttl']['id'], $memberOauthHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_oauth_completion_ttl_seconds" type="number" name="completion_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['completion_ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">외부 인증 뒤 약관 확인과 필수 입력을 마칠 수 있는 시간입니다.</p>
            </div>
        </div>
    </section>

    <?php $externalProviderCount = 0; ?>
    <?php foreach ($memberOauthExternalProviders as $provider) { ?>
        <?php
        $externalProviderCount++;
        $providerKey = (string) $provider['provider_key'];
        $providerLabel = (string) ($provider['label'] ?? $providerKey);
        $enabledKey = sr_member_oauth_provider_setting_key($providerKey, 'enabled');
        $labelKey = sr_member_oauth_provider_setting_key($providerKey, 'label');
        $clientIdKey = sr_member_oauth_provider_setting_key($providerKey, 'client_id');
        $secretKey = sr_member_oauth_provider_setting_key($providerKey, 'client_secret');
        $scopeKey = sr_member_oauth_provider_setting_key($providerKey, 'scope');
        $profileSyncKey = sr_member_oauth_provider_setting_key($providerKey, 'profile_sync_json');
        $sortOrderKey = sr_member_oauth_provider_setting_key($providerKey, 'sort_order');
        $providerEnabled = !empty($provider['enabled']);
        $providerHasStoredSecret = trim((string) ($provider['client_secret'] ?? '')) !== '';
        $providerSecretRequired = $providerEnabled && !$providerHasStoredSecret;
        $requiredScopeItems = sr_member_oauth_required_scope_items($provider);
        $requiredScopeItemMap = array_fill_keys($requiredScopeItems, true);
        $scopeItems = sr_member_oauth_scope_items_with_required($provider['scope'] ?? ($provider['scopes'] ?? []), $provider);
        $claimPathOptions = sr_member_oauth_claim_path_options($provider);
        $profileSyncRules = sr_member_oauth_profile_sync_rules($provider);
        ?>
        <section id="<?php echo sr_e('member-oauth-section-provider-' . str_replace('_', '-', $providerKey)); ?>" class="card" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e($providerLabel); ?></h2>
                <label class="form-check" for="<?php echo sr_e('member_oauth_' . $providerKey . '_enabled'); ?>">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_enabled'); ?>" type="checkbox" name="<?php echo sr_e($enabledKey); ?>" value="1" class="form-switch form-switch-light"<?php echo $providerEnabled ? ' checked' : ''; ?> data-oauth-provider-toggle="<?php echo sr_e($providerKey); ?>">
                    <span class="sr-only"><?php echo sr_e($providerLabel . ' 사용'); ?></span>
                </label>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 되돌아올 URL', $memberOauthHelp['callback']['id']); ?><span><?php echo sr_e('되돌아올 URL'); ?></span></span>
                <div class="form-field">
                    <div class="form-actions">
                        <p class="admin-form-static"><?php echo sr_e($callbackUrl); ?></p>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-copy-value="<?php echo sr_e($callbackUrl); ?>" title="<?php echo sr_e('되돌아올 URL 복사'); ?>" aria-label="<?php echo sr_e('되돌아올 URL 복사'); ?>">
                            <?php echo sr_material_icon_html('content_copy'); ?>
                            <span><?php echo sr_e('복사'); ?></span>
                        </button>
                    </div>
                    <p class="form-help">외부 로그인 서비스 관리 화면에 이 주소와 아래 연결 정보를 등록합니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>"><?php echo sr_e('버튼 표시명'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>" type="text" name="<?php echo sr_e($labelKey); ?>" maxlength="80" value="<?php echo sr_e((string) ($provider['label'] ?? $providerKey)); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input form-control-full" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <div class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 클라이언트 ID', $memberOauthHelp['credentials']['id']); ?><label for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>"><?php echo sr_e('클라이언트 ID'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label></div>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>" type="text" name="<?php echo sr_e($clientIdKey); ?>" maxlength="255" value="<?php echo sr_e((string) ($provider['client_id'] ?? '')); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input form-control-full" autocomplete="off" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                    <p class="form-help">외부 로그인 앱에서 발급한 공개 식별값입니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <div class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 클라이언트 비밀값', $memberOauthHelp['credentials']['id']); ?><label for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>"><?php echo sr_e('클라이언트 비밀값'); ?> <span class="sr-required-label"<?php echo $providerSecretRequired ? '' : ' hidden'; ?> data-oauth-required-secret-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label></div>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>" type="password" name="<?php echo sr_e($secretKey); ?>" maxlength="512" value="" placeholder="<?php echo sr_e(sr_member_oauth_secret_display((string) ($provider['client_secret'] ?? ''))); ?>"<?php echo $providerSecretRequired ? ' required' : ''; ?> class="form-input form-control-full" autocomplete="new-password" data-oauth-secret-provider="<?php echo sr_e($providerKey); ?>" data-oauth-has-stored-secret="<?php echo $providerHasStoredSecret ? '1' : '0'; ?>">
                    <p class="form-help">이미 저장된 값은 비워 두면 유지합니다. 변경할 때만 입력하세요.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 가져올 정보 권한', $memberOauthHelp['scope']['id']); ?><span><?php echo sr_e('가져올 정보 권한'); ?></span></span>
                <div class="form-field">
                    <div data-oauth-scope-list="<?php echo sr_e($providerKey); ?>" data-oauth-scope-name="<?php echo sr_e($scopeKey); ?>" data-oauth-required-scopes="<?php echo sr_e(implode("\n", $requiredScopeItems)); ?>">
                        <?php foreach ($scopeItems as $scopeIndex => $scopeItem) { ?>
                            <?php $scopeRequired = isset($requiredScopeItemMap[$scopeItem]); ?>
                            <div class="member-oauth-repeat-row" data-oauth-scope-row<?php echo $scopeRequired ? ' data-oauth-required-scope-row' : ''; ?>>
                                <input type="text" name="<?php echo sr_e($scopeKey); ?>[]" maxlength="120" value="<?php echo sr_e($scopeItem); ?>" class="form-input" aria-label="<?php echo sr_e('가져올 정보 권한 항목'); ?>"<?php echo $scopeRequired ? ' readonly' : ''; ?>>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger member-oauth-repeat-delete" data-oauth-remove-row title="<?php echo sr_e($scopeRequired ? '기본 권한은 삭제할 수 없습니다.' : '정보 권한 항목 삭제'); ?>" aria-label="<?php echo sr_e($scopeRequired ? '기본 권한은 삭제할 수 없습니다.' : '정보 권한 항목 삭제'); ?>"<?php echo $scopeRequired ? ' disabled aria-disabled="true"' : ''; ?>>
                                    <?php echo sr_material_icon_html('delete'); ?>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-add-scope="<?php echo sr_e($providerKey); ?>">
                            <?php echo sr_material_icon_html('add'); ?>
                            <span><?php echo sr_e('정보 권한 추가'); ?></span>
                        </button>
                    </div>
                    <p class="form-help">기본 항목은 로그인에 필요해 삭제할 수 없습니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 회원 정보 가져오기', $memberOauthHelp['profile_sync']['id']); ?><span><?php echo sr_e('회원 정보 가져오기'); ?></span></span>
                <div class="form-field">
                    <?php if ($memberOauthProfileExtraFieldDefinitions === []) { ?>
                        <div class="alert alert-warning">
                            <p><?php echo sr_e('추가 프로필 값을 저장할 선택 프로필 항목이 아직 없습니다.'); ?></p>
                            <?php if ($memberOauthCanEditProfileSettings) { ?>
                                <div class="form-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e($memberOauthProfileSettingsUrl); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo sr_material_icon_html('tune'); ?>
                                        <span><?php echo sr_e('선택 프로필 항목 관리'); ?></span>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div data-oauth-profile-sync-list="<?php echo sr_e($providerKey); ?>" data-oauth-profile-sync-name="<?php echo sr_e($profileSyncKey); ?>">
                        <div class="member-oauth-sync-header" aria-hidden="true">
                            <span><?php echo sr_e('저장할 회원 항목'); ?></span>
                            <span><?php echo sr_e('필요 권한'); ?></span>
                            <span><?php echo sr_e('가져올 값 위치'); ?></span>
                            <span><?php echo sr_e('동작'); ?></span>
                        </div>
                        <?php foreach ($profileSyncRules as $profileSyncIndex => $profileSyncRule) { ?>
                            <?php $profileSyncTarget = (string) ($profileSyncRule['target'] ?? ''); ?>
                            <?php $profileSyncLocked = in_array($profileSyncTarget, ['email', 'display_name'], true); ?>
                            <div class="member-oauth-repeat-row member-oauth-sync-row" data-oauth-profile-sync-row>
                                <?php if ($profileSyncLocked) { ?>
                                    <input type="hidden" name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][target]" value="<?php echo sr_e($profileSyncTarget); ?>" data-oauth-profile-sync-target-hidden>
                                    <span class="admin-form-static member-oauth-sync-target-static"><?php echo sr_e((string) ($memberOauthProfileSyncTargets[$profileSyncTarget] ?? $profileSyncTarget)); ?></span>
                                <?php } else { ?>
                                    <select name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][target]" class="form-select" aria-label="<?php echo sr_e('저장할 회원 항목'); ?>" data-oauth-profile-sync-target-select>
                                        <?php foreach ($memberOauthProfileSyncTargets as $syncTarget => $syncLabel) { ?>
                                            <option value="<?php echo sr_e((string) $syncTarget); ?>"<?php echo $profileSyncTarget === (string) $syncTarget ? ' selected' : ''; ?>><?php echo sr_e((string) $syncLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                                <select name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][scope]" class="form-select" data-oauth-profile-sync-scope-select aria-label="<?php echo sr_e('필요 권한'); ?>">
                                    <option value=""><?php echo sr_e('필요 권한 없음'); ?></option>
                                    <?php foreach ($scopeItems as $syncScopeItem) { ?>
                                        <option value="<?php echo sr_e($syncScopeItem); ?>"<?php echo (string) ($profileSyncRule['scope'] ?? '') === $syncScopeItem ? ' selected' : ''; ?>><?php echo sr_e($syncScopeItem); ?></option>
                                    <?php } ?>
                                </select>
                                <?php $profileSyncClaim = (string) ($profileSyncRule['claim'] ?? ''); ?>
                                <?php $profileSyncCustomClaim = $profileSyncClaim !== '' && !in_array($profileSyncClaim, $claimPathOptions, true); ?>
                                <select class="form-select" data-oauth-profile-sync-claim-select aria-label="<?php echo sr_e('가져올 값 위치 선택'); ?>">
                                    <option value=""><?php echo sr_e('가져올 값 선택'); ?></option>
                                    <?php foreach ($claimPathOptions as $claimPathOption) { ?>
                                        <option value="<?php echo sr_e($claimPathOption); ?>"<?php echo $profileSyncClaim === $claimPathOption ? ' selected' : ''; ?>><?php echo sr_e($claimPathOption); ?></option>
                                    <?php } ?>
                                    <option value="__custom__"<?php echo $profileSyncCustomClaim ? ' selected' : ''; ?>><?php echo sr_e('직접 입력'); ?></option>
                                </select>
                                <input type="hidden" name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][claim]" value="<?php echo sr_e($profileSyncClaim); ?>" data-oauth-profile-sync-claim-value>
                                <input type="text" maxlength="120" value="<?php echo sr_e($profileSyncCustomClaim ? $profileSyncClaim : ''); ?>" class="form-input" aria-label="<?php echo sr_e('가져올 값 위치 직접 입력'); ?>" data-oauth-profile-sync-claim-custom<?php echo $profileSyncCustomClaim ? '' : ' hidden'; ?>>
                                <?php if ($profileSyncLocked) { ?>
                                    <span class="btn btn-sm btn-icon btn-solid-light member-oauth-repeat-locked" aria-label="<?php echo sr_e('필수 회원 정보 항목'); ?>" title="<?php echo sr_e('필수 회원 정보 항목'); ?>" aria-disabled="true">
                                        <?php echo sr_material_icon_html('lock'); ?>
                                    </span>
                                <?php } else { ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger member-oauth-repeat-delete" data-oauth-remove-row title="<?php echo sr_e('회원 정보 항목 삭제'); ?>" aria-label="<?php echo sr_e('회원 정보 항목 삭제'); ?>">
                                        <?php echo sr_material_icon_html('delete'); ?>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-add-profile-sync="<?php echo sr_e($providerKey); ?>">
                            <?php echo sr_material_icon_html('add'); ?>
                            <span><?php echo sr_e('회원 정보 항목 추가'); ?></span>
                        </button>
                        <?php if ($memberOauthCanEditProfileSettings) { ?>
                            <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e($memberOauthProfileSettingsUrl); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo sr_material_icon_html('tune'); ?>
                                <span><?php echo sr_e('선택 프로필 항목 관리'); ?></span>
                            </a>
                        <?php } ?>
                    </div>
                    <p class="form-help">외부 계정을 연결하거나 로그인할 때 선택한 값을 사이트 회원 정보에 반영합니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <div class="form-label form-label-help"><?php echo $memberOauthHelpButtonHtml($providerLabel . ' 로그인 버튼 순서', $memberOauthHelp['sort_order']['id']); ?><label for="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>"><?php echo sr_e('로그인 버튼 순서'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label></div>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>" type="number" name="<?php echo sr_e($sortOrderKey); ?>" min="-9999" max="9999" value="<?php echo sr_e((string) ((int) ($provider['sort_order'] ?? 0))); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                    <p class="form-help">숫자가 작을수록 로그인 화면에서 먼저 표시됩니다.</p>
                </div>
            </div>
        </section>
    <?php } ?>
    <?php if ($externalProviderCount < 1) { ?>
        <section id="member-oauth-section-providers-empty" class="card" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('외부 제공자 활성화'); ?></h2>
                <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e(sr_url('/admin/modules')); ?>"><?php echo sr_e('모듈 화면'); ?></a>
            </div>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('진행 순서'); ?></span>
                <div class="form-field">
                    <ol class="form-help">
                        <li><?php echo sr_e('Google, Kakao, Naver, GitHub, Apple ID 같은 외부 로그인 제공자 모듈을 설치하고 활성화합니다.'); ?></li>
                        <li><?php echo sr_e('이 화면에 생긴 제공자 카드에서 사용을 켜고 클라이언트 ID를 저장합니다.'); ?></li>
                        <li><?php echo sr_e('되돌아올 URL을 외부 로그인 서비스 관리 화면에 등록하고 로그인 버튼을 확인합니다.'); ?></li>
                    </ol>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('권장 후보'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static"><?php echo sr_e('Google, Kakao, Naver, GitHub, Apple ID'); ?></p>
                    <p class="form-help">제공자 모듈을 활성화하면 이 화면에 해당 서비스의 설정 카드가 표시됩니다.</p>
                </div>
            </div>
        </section>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('외부 제공자'); ?></h2>
            </div>
            <p class="admin-empty-state"><?php echo sr_e('활성화된 외부 로그인 제공자 모듈이 없습니다. 제공자 모듈을 설치하고 활성화하면 이 화면에 설정 카드가 표시됩니다.'); ?></p>
            <div class="form-actions">
                <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/modules')); ?>"><?php echo sr_e('모듈 화면으로 이동'); ?></a>
            </div>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary form-actions-split">
        <?php if ($externalProviderCount > 0) { ?>
            <div class="admin-form-secondary-actions">
                <button type="button" class="btn btn-outline-secondary" data-oauth-provider-toggle-all="1"><?php echo sr_e('전체 사용'); ?></button>
                <button type="button" class="btn btn-solid-light" data-oauth-provider-toggle-all="0"><?php echo sr_e('전체 해제'); ?></button>
            </div>
        <?php } ?>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>
<?php foreach ($memberOauthHelp as $memberOauthHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberOauthHelpModal['id'], (string) $memberOauthHelpModal['title'], (string) $memberOauthHelpModal['body']); ?>
<?php } ?>
<script type="application/json" data-oauth-profile-sync-targets><?php echo sr_js_json_encode($memberOauthProfileSyncTargets); ?></script>
<script type="application/json" data-oauth-profile-sync-claims><?php echo sr_js_json_encode($memberOauthClaimPathOptionsByProvider); ?></script>
<script>
function srMemberOauthCreateButton(icon, label, action) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-icon btn-outline-danger member-oauth-repeat-delete';
    button.setAttribute(action, '');
    button.setAttribute('title', label);
    button.setAttribute('aria-label', label);
    button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + icon + '</span>';
    return button;
}
function srMemberOauthProfileSyncUsedTargets(list) {
    var used = {};
    if (!list) {
        return used;
    }
    list.querySelectorAll('[data-oauth-profile-sync-row]').forEach(function (row) {
        var target = row.querySelector('[data-oauth-profile-sync-target-select]');
        var targetHidden = row.querySelector('[data-oauth-profile-sync-target-hidden]');
        var value = target ? target.value : (targetHidden ? targetHidden.value : '');
        if (value) {
            used[value] = true;
        }
    });
    return used;
}
function srMemberOauthAvailableProfileSyncTargets(list, targets) {
    var used = srMemberOauthProfileSyncUsedTargets(list);
    var available = {};
    Object.keys(targets || {}).forEach(function (targetKey) {
        if (!used[targetKey]) {
            available[targetKey] = targets[targetKey];
        }
    });
    return available;
}
function srMemberOauthSyncProfileTargetOptions(list) {
    if (!list) {
        return;
    }
    var used = srMemberOauthProfileSyncUsedTargets(list);
    list.querySelectorAll('[data-oauth-profile-sync-target-select]').forEach(function (select) {
        Array.prototype.forEach.call(select.options, function (option) {
            option.disabled = option.value !== select.value && !!used[option.value];
        });
    });
}
function srMemberOauthSyncAddProfileButtons() {
    document.querySelectorAll('[data-oauth-add-profile-sync]').forEach(function (button) {
        var providerKey = button.getAttribute('data-oauth-add-profile-sync') || '';
        var list = document.querySelector('[data-oauth-profile-sync-list="' + providerKey + '"]');
        var targetsScript = document.querySelector('[data-oauth-profile-sync-targets]');
        var targets = {};
        try {
            targets = targetsScript ? JSON.parse(targetsScript.textContent || '{}') : {};
        } catch (error) {
            targets = {};
        }
        var available = srMemberOauthAvailableProfileSyncTargets(list, targets);
        var disabled = Object.keys(available).length === 0;
        srMemberOauthSyncProfileTargetOptions(list);
        button.disabled = disabled;
        button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        button.title = disabled ? '추가할 수 있는 회원 정보 항목이 없습니다.' : '';
    });
}
function srMemberOauthRenumberProfileSync(list) {
    var baseName = list.getAttribute('data-oauth-profile-sync-name') || '';
    list.querySelectorAll('[data-oauth-profile-sync-row]').forEach(function (row, index) {
        var target = row.querySelector('[data-oauth-profile-sync-target-select]');
        var targetHidden = row.querySelector('[data-oauth-profile-sync-target-hidden]');
        var scope = row.querySelector('[data-oauth-profile-sync-scope-select]');
        var claim = row.querySelector('[data-oauth-profile-sync-claim-value]');
        if (target) {
            target.name = baseName + '[' + index + '][target]';
        }
        if (targetHidden) {
            targetHidden.name = baseName + '[' + index + '][target]';
        }
        if (scope) {
            scope.name = baseName + '[' + index + '][scope]';
        }
        if (claim) {
            claim.name = baseName + '[' + index + '][claim]';
        }
    });
}
function srMemberOauthClaimOptions(providerKey) {
    var script = document.querySelector('[data-oauth-profile-sync-claims]');
    var map = {};
    try {
        map = script ? JSON.parse(script.textContent || '{}') : {};
    } catch (error) {
        map = {};
    }
    return Array.isArray(map[providerKey]) ? map[providerKey] : [];
}
function srMemberOauthSyncClaimControls(row) {
    var select = row ? row.querySelector('[data-oauth-profile-sync-claim-select]') : null;
    var hidden = row ? row.querySelector('[data-oauth-profile-sync-claim-value]') : null;
    var custom = row ? row.querySelector('[data-oauth-profile-sync-claim-custom]') : null;
    if (!select || !hidden || !custom) {
        return;
    }
    if (select.value === '__custom__') {
        custom.hidden = false;
        hidden.value = String(custom.value || '').trim();
    } else {
        custom.hidden = true;
        hidden.value = select.value;
    }
}
function srMemberOauthCreateClaimControls(providerKey) {
    var fragment = document.createDocumentFragment();
    var select = document.createElement('select');
    select.className = 'form-select';
    select.setAttribute('data-oauth-profile-sync-claim-select', '');
    select.setAttribute('aria-label', '가져올 값 위치 선택');
    var empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '가져올 값 선택';
    select.appendChild(empty);
    srMemberOauthClaimOptions(providerKey).forEach(function (claimPath) {
        var option = document.createElement('option');
        option.value = claimPath;
        option.textContent = claimPath;
        select.appendChild(option);
    });
    var customOption = document.createElement('option');
    customOption.value = '__custom__';
    customOption.textContent = '직접 입력';
    select.appendChild(customOption);
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.setAttribute('data-oauth-profile-sync-claim-value', '');
    var custom = document.createElement('input');
    custom.type = 'text';
    custom.maxLength = 120;
    custom.className = 'form-input';
    custom.hidden = true;
    custom.setAttribute('aria-label', '가져올 값 위치 직접 입력');
    custom.setAttribute('data-oauth-profile-sync-claim-custom', '');
    fragment.appendChild(select);
    fragment.appendChild(hidden);
    fragment.appendChild(custom);
    select.addEventListener('change', function () {
        srMemberOauthSyncClaimControls(select.closest('[data-oauth-profile-sync-row]'));
    });
    custom.addEventListener('input', function () {
        srMemberOauthSyncClaimControls(custom.closest('[data-oauth-profile-sync-row]'));
    });
    return fragment;
}
function srMemberOauthScopeItems(providerKey) {
    var list = document.querySelector('[data-oauth-scope-list="' + providerKey + '"]');
    var items = [];
    if (!list) {
        return items;
    }
    list.querySelectorAll('input').forEach(function (input) {
        var value = String(input.value || '').trim();
        if (value !== '' && items.indexOf(value) === -1) {
            items.push(value);
        }
    });
    return items;
}
function srMemberOauthSyncScopeSelectOptions(providerKey) {
    var syncList = document.querySelector('[data-oauth-profile-sync-list="' + providerKey + '"]');
    if (!syncList) {
        return;
    }
    var items = srMemberOauthScopeItems(providerKey);
    syncList.querySelectorAll('[data-oauth-profile-sync-scope-select]').forEach(function (select) {
        var current = select.value;
        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '필요 권한 없음';
        select.appendChild(empty);
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item;
            option.textContent = item;
            select.appendChild(option);
        });
        select.value = items.indexOf(current) !== -1 ? current : '';
    });
}
document.querySelectorAll('[data-oauth-provider-toggle]').forEach(function (toggle) {
    var providerKey = toggle.getAttribute('data-oauth-provider-toggle') || '';
    function syncRequired() {
        document.querySelectorAll('[data-oauth-provider-field-row="' + providerKey + '"]').forEach(function (row) {
            row.hidden = !toggle.checked;
        });
        document.querySelectorAll('[data-oauth-required-provider="' + providerKey + '"]').forEach(function (input) {
            input.required = toggle.checked;
        });
        document.querySelectorAll('[data-oauth-required-for="' + providerKey + '"]').forEach(function (label) {
            label.hidden = !toggle.checked;
        });
        document.querySelectorAll('[data-oauth-secret-provider="' + providerKey + '"]').forEach(function (input) {
            var required = toggle.checked && input.getAttribute('data-oauth-has-stored-secret') !== '1';
            input.required = required;
            document.querySelectorAll('[data-oauth-required-secret-for="' + providerKey + '"]').forEach(function (label) {
                label.hidden = !required;
            });
        });
    }
    toggle.addEventListener('change', syncRequired);
    syncRequired();
});
document.querySelectorAll('[data-oauth-provider-toggle-all]').forEach(function (button) {
    button.addEventListener('click', function () {
        var checked = button.getAttribute('data-oauth-provider-toggle-all') === '1';
        document.querySelectorAll('[data-oauth-provider-toggle]').forEach(function (toggle) {
            toggle.checked = checked;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});
document.querySelectorAll('[data-oauth-add-scope]').forEach(function (button) {
    button.addEventListener('click', function () {
        var providerKey = button.getAttribute('data-oauth-add-scope') || '';
        var list = document.querySelector('[data-oauth-scope-list="' + providerKey + '"]');
        if (!list) {
            return;
        }
        var baseName = list.getAttribute('data-oauth-scope-name') || '';
        var row = document.createElement('div');
        row.className = 'member-oauth-repeat-row';
        row.setAttribute('data-oauth-scope-row', '');
        var input = document.createElement('input');
        input.type = 'text';
        input.name = baseName + '[]';
        input.maxLength = 120;
        input.className = 'form-input';
        input.setAttribute('aria-label', '가져올 정보 권한 항목');
        row.appendChild(input);
        row.appendChild(srMemberOauthCreateButton('delete', '정보 권한 항목 삭제', 'data-oauth-remove-row'));
        list.appendChild(row);
        input.addEventListener('input', function () {
            srMemberOauthSyncScopeSelectOptions(providerKey);
        });
        srMemberOauthSyncScopeSelectOptions(providerKey);
        input.focus();
    });
});
document.querySelectorAll('[data-oauth-add-profile-sync]').forEach(function (button) {
    button.addEventListener('click', function () {
        var providerKey = button.getAttribute('data-oauth-add-profile-sync') || '';
        var list = document.querySelector('[data-oauth-profile-sync-list="' + providerKey + '"]');
        var targetsScript = document.querySelector('[data-oauth-profile-sync-targets]');
        if (!list || !targetsScript) {
            return;
        }
        var targets = {};
        try {
            targets = JSON.parse(targetsScript.textContent || '{}');
        } catch (error) {
            targets = {};
        }
        targets = srMemberOauthAvailableProfileSyncTargets(list, targets);
        if (Object.keys(targets).length === 0) {
            srMemberOauthSyncAddProfileButtons();
            return;
        }
        var row = document.createElement('div');
        row.className = 'member-oauth-repeat-row member-oauth-sync-row';
        row.setAttribute('data-oauth-profile-sync-row', '');
        var select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('data-oauth-profile-sync-target-select', '');
        select.setAttribute('aria-label', '회원 필드');
        select.addEventListener('change', srMemberOauthSyncAddProfileButtons);
        Object.keys(targets).forEach(function (targetKey) {
            var option = document.createElement('option');
            option.value = targetKey;
            option.textContent = targets[targetKey];
            select.appendChild(option);
        });
        var scopeSelect = document.createElement('select');
        scopeSelect.className = 'form-select';
        scopeSelect.setAttribute('data-oauth-profile-sync-scope-select', '');
        scopeSelect.setAttribute('aria-label', '필요 권한');
        row.appendChild(select);
        row.appendChild(scopeSelect);
        row.appendChild(srMemberOauthCreateClaimControls(providerKey));
        row.appendChild(srMemberOauthCreateButton('delete', '회원 정보 항목 삭제', 'data-oauth-remove-row'));
        list.appendChild(row);
        srMemberOauthRenumberProfileSync(list);
        srMemberOauthSyncScopeSelectOptions(providerKey);
        srMemberOauthSyncAddProfileButtons();
        var claimSelect = row.querySelector('[data-oauth-profile-sync-claim-select]');
        if (claimSelect) {
            claimSelect.focus();
        }
    });
});
document.querySelectorAll('[data-oauth-profile-sync-row]').forEach(function (row) {
    var select = row.querySelector('[data-oauth-profile-sync-claim-select]');
    var custom = row.querySelector('[data-oauth-profile-sync-claim-custom]');
    if (select) {
        select.addEventListener('change', function () {
            srMemberOauthSyncClaimControls(row);
        });
    }
    if (custom) {
        custom.addEventListener('input', function () {
            srMemberOauthSyncClaimControls(row);
        });
    }
    srMemberOauthSyncClaimControls(row);
});
document.querySelectorAll('[data-oauth-scope-list]').forEach(function (list) {
    var providerKey = list.getAttribute('data-oauth-scope-list') || '';
    list.addEventListener('input', function () {
        srMemberOauthSyncScopeSelectOptions(providerKey);
    });
    srMemberOauthSyncScopeSelectOptions(providerKey);
});
document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-oauth-remove-row]');
    if (!button) {
        return;
    }
    var row = button.closest('[data-oauth-scope-row], [data-oauth-profile-sync-row]');
    var list = row ? row.parentElement : null;
    if (!row || !list) {
        return;
    }
    if (row.hasAttribute('data-oauth-required-scope-row')) {
        return;
    }
    row.remove();
    if (list.hasAttribute('data-oauth-profile-sync-list')) {
        srMemberOauthRenumberProfileSync(list);
        srMemberOauthSyncAddProfileButtons();
    } else if (list.hasAttribute('data-oauth-scope-list')) {
        srMemberOauthSyncScopeSelectOptions(list.getAttribute('data-oauth-scope-list') || '');
    }
});
srMemberOauthSyncAddProfileButtons();
document.querySelectorAll('[data-oauth-profile-sync-target-select]').forEach(function (select) {
    select.addEventListener('change', srMemberOauthSyncAddProfileButtons);
});
document.querySelectorAll('[data-oauth-copy-value]').forEach(function (button) {
    button.addEventListener('click', function () {
        var value = button.getAttribute('data-oauth-copy-value') || '';
        if (!value) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () {
                button.setAttribute('data-oauth-copy-done', '1');
            });
            return;
        }
        var input = document.createElement('input');
        input.value = value;
        input.setAttribute('readonly', 'readonly');
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        button.setAttribute('data-oauth-copy-done', '1');
    });
});
</script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
