<?php

$adminPageTitle = '회원 OAuth 설정';
$adminPageSubtitle = '';
$callbackUrl = sr_absolute_url($site ?? [], '/oauth/callback');
$memberOauthProfileExtraFieldDefinitions = is_array($profileExtraFieldDefinitions ?? null) ? $profileExtraFieldDefinitions : [];
$memberOauthProfileSyncTargets = sr_member_oauth_profile_sync_targets($memberOauthProfileExtraFieldDefinitions);
$memberOauthProfileSettingsUrl = sr_url('/admin/member-settings#member-settings-section-profile');
$memberOauthCanEditProfileSettings = is_array($account ?? null)
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/member-settings', 'edit');
$memberOauthExternalProviders = [];
foreach ($providers as $provider) {
    if (!empty($provider['mock'])) {
        continue;
    }
    $providerKey = (string) ($provider['provider_key'] ?? '');
    if ($providerKey === '') {
        continue;
    }
    $memberOauthExternalProviders[] = $provider;
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
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="회원 OAuth 설정 섹션">
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
            <span class="form-label">Callback URL</span>
            <div class="form-field">
                <div class="form-actions">
                    <p class="admin-form-static"><?php echo sr_e($callbackUrl); ?></p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-copy-value="<?php echo sr_e($callbackUrl); ?>" title="<?php echo sr_e('Callback URL 복사'); ?>" aria-label="<?php echo sr_e('Callback URL 복사'); ?>">
                        <?php echo sr_material_icon_html('content_copy'); ?>
                        <span><?php echo sr_e('복사'); ?></span>
                    </button>
                </div>
                <p class="form-help">외부 OAuth/OIDC 제공자 콘솔에 등록할 redirect URI입니다. 공개 기준 URL이 바뀌면 이 값도 함께 바뀝니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="member_oauth_mock_enabled"><?php echo sr_e('Mock 제공자'); ?></label>
            <div class="form-field">
                <label class="form-check form-label" for="member_oauth_mock_enabled">
                    <input id="member_oauth_mock_enabled" type="checkbox" name="mock_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['mock_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="member_oauth_mock_label"><?php echo sr_e('Mock 제공자 라벨'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="member_oauth_mock_label" type="text" name="mock_label" maxlength="80" value="<?php echo sr_e((string) $settings['mock_label']); ?>" required class="form-input form-control-full">
                <p class="form-help">로그인 화면의 Mock 제공자 버튼에 표시됩니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="member_oauth_state_ttl_seconds"><?php echo sr_e('State 유효 시간'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_oauth_state_ttl_seconds" type="number" name="state_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['state_ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">제공자 로그인/연결 callback을 기다리는 시간입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="member_oauth_completion_ttl_seconds"><?php echo sr_e('가입 완료 유효 시간'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_oauth_completion_ttl_seconds" type="number" name="completion_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['completion_ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">OAuth 신규 가입 완료 state를 유지하는 시간입니다.</p>
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
        $providerStatus = sr_member_oauth_provider_admin_status($provider, $callbackUrl);
        $providerEnabled = !empty($provider['enabled']);
        $providerSecretExists = sr_member_oauth_provider_value($provider, 'client_secret') !== '';
        $scopeItems = sr_member_oauth_scope_items($provider['scope'] ?? ($provider['scopes'] ?? []));
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
                <span class="form-label"><?php echo sr_e('노출 진단'); ?></span>
                <div class="form-field">
                    <ul class="form-help">
                        <?php foreach ($providerStatus['items'] as $statusItem) { ?>
                            <li>
                                <?php echo sr_e((string) $statusItem['label']); ?>:
                                <?php echo sr_e(!empty($statusItem['ok']) ? '정상' : '필요'); ?>
                                - <?php echo sr_e((string) $statusItem['message']); ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label"><?php echo sr_e('Callback URL'); ?></span>
                <div class="form-field">
                    <div class="form-actions">
                        <p class="admin-form-static"><?php echo sr_e($callbackUrl); ?></p>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-copy-value="<?php echo sr_e($callbackUrl); ?>" title="<?php echo sr_e('Callback URL 복사'); ?>" aria-label="<?php echo sr_e('Callback URL 복사'); ?>">
                            <?php echo sr_material_icon_html('content_copy'); ?>
                            <span><?php echo sr_e('복사'); ?></span>
                        </button>
                    </div>
                    <p class="form-help">외부 OAuth/OIDC 제공자 콘솔의 redirect URI 또는 callback URL 항목에 같은 값을 등록합니다. Client ID와 secret은 같은 콘솔의 앱 자격 증명 화면에서 발급받습니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>"><?php echo sr_e('라벨'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>" type="text" name="<?php echo sr_e($labelKey); ?>" maxlength="80" value="<?php echo sr_e((string) ($provider['label'] ?? $providerKey)); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input form-control-full" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>"><?php echo sr_e('Client ID'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>" type="text" name="<?php echo sr_e($clientIdKey); ?>" maxlength="255" value="<?php echo sr_e((string) ($provider['client_id'] ?? '')); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input form-control-full" autocomplete="off" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                    <p class="form-help">사용을 켠 제공자는 Client ID가 있어야 로그인 화면에 노출할 수 있습니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>"><?php echo sr_e('Client secret'); ?></label>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>" type="password" name="<?php echo sr_e($secretKey); ?>" maxlength="512" value="" placeholder="<?php echo sr_e(sr_member_oauth_secret_display((string) ($provider['client_secret'] ?? ''))); ?>" class="form-input form-control-full" autocomplete="new-password">
                    <p class="admin-form-static member-oauth-secret-status">
                        <span class="badge <?php echo $providerSecretExists ? 'badge-soft-success' : 'badge-soft-secondary'; ?>"><?php echo sr_e($providerSecretExists ? '저장됨' : '미입력'); ?></span>
                        <span><?php echo sr_e($providerSecretExists ? '기존 secret이 저장되어 있습니다.' : '저장된 secret이 없습니다.'); ?></span>
                    </p>
                    <p class="form-help">비워 두면 기존 secret을 유지합니다. 저장 후 화면에는 원문을 표시하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label"><?php echo sr_e('Scope'); ?></span>
                <div class="form-field">
                    <div data-oauth-scope-list="<?php echo sr_e($providerKey); ?>" data-oauth-scope-name="<?php echo sr_e($scopeKey); ?>">
                        <?php foreach ($scopeItems as $scopeIndex => $scopeItem) { ?>
                            <div class="member-oauth-repeat-row" data-oauth-scope-row>
                                <input type="text" name="<?php echo sr_e($scopeKey); ?>[]" maxlength="120" value="<?php echo sr_e($scopeItem); ?>" class="form-input" aria-label="<?php echo sr_e('Scope 항목'); ?>">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger member-oauth-repeat-delete" data-oauth-remove-row title="<?php echo sr_e('Scope 항목 삭제'); ?>" aria-label="<?php echo sr_e('Scope 항목 삭제'); ?>">
                                    <?php echo sr_material_icon_html('delete'); ?>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-add-scope="<?php echo sr_e($providerKey); ?>">
                            <?php echo sr_material_icon_html('add'); ?>
                            <span><?php echo sr_e('Scope 추가'); ?></span>
                        </button>
                    </div>
                    <p class="form-help">비워 두면 scope 파라미터를 보내지 않습니다. 제공자별 구분자는 저장된 항목을 기준으로 자동 적용됩니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label"><?php echo sr_e('프로필 동기화'); ?></span>
                <div class="form-field">
                    <?php if ($memberOauthProfileExtraFieldDefinitions === []) { ?>
                        <div class="alert alert-warning">
                            <p><?php echo sr_e('추가 claim을 저장할 선택 프로필 항목이 아직 없습니다.'); ?></p>
                            <?php if ($memberOauthCanEditProfileSettings) { ?>
                                <div class="form-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e($memberOauthProfileSettingsUrl); ?>">
                                        <?php echo sr_material_icon_html('tune'); ?>
                                        <span><?php echo sr_e('선택 프로필 항목 관리'); ?></span>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div data-oauth-profile-sync-list="<?php echo sr_e($providerKey); ?>" data-oauth-profile-sync-name="<?php echo sr_e($profileSyncKey); ?>">
                        <?php foreach ($profileSyncRules as $profileSyncIndex => $profileSyncRule) { ?>
                            <?php $profileSyncTarget = (string) ($profileSyncRule['target'] ?? ''); ?>
                            <?php $profileSyncLocked = in_array($profileSyncTarget, ['email', 'display_name'], true); ?>
                            <div class="member-oauth-repeat-row member-oauth-sync-row" data-oauth-profile-sync-row>
                                <?php if ($profileSyncLocked) { ?>
                                    <input type="hidden" name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][target]" value="<?php echo sr_e($profileSyncTarget); ?>" data-oauth-profile-sync-target-hidden>
                                    <span class="admin-form-static member-oauth-sync-target-static"><?php echo sr_e((string) ($memberOauthProfileSyncTargets[$profileSyncTarget] ?? $profileSyncTarget)); ?></span>
                                <?php } else { ?>
                                    <select name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][target]" class="form-select" aria-label="<?php echo sr_e('회원 필드'); ?>" data-oauth-profile-sync-target-select>
                                        <?php foreach ($memberOauthProfileSyncTargets as $syncTarget => $syncLabel) { ?>
                                            <option value="<?php echo sr_e((string) $syncTarget); ?>"<?php echo $profileSyncTarget === (string) $syncTarget ? ' selected' : ''; ?>><?php echo sr_e((string) $syncLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                                <select name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][scope]" class="form-select" data-oauth-profile-sync-scope-select aria-label="<?php echo sr_e('Scope'); ?>">
                                    <option value=""><?php echo sr_e('Scope 선택 안 함'); ?></option>
                                    <?php foreach ($scopeItems as $syncScopeItem) { ?>
                                        <option value="<?php echo sr_e($syncScopeItem); ?>"<?php echo (string) ($profileSyncRule['scope'] ?? '') === $syncScopeItem ? ' selected' : ''; ?>><?php echo sr_e($syncScopeItem); ?></option>
                                    <?php } ?>
                                </select>
                                <input type="text" name="<?php echo sr_e($profileSyncKey); ?>[<?php echo sr_e((string) $profileSyncIndex); ?>][claim]" maxlength="120" value="<?php echo sr_e((string) ($profileSyncRule['claim'] ?? '')); ?>" class="form-input" aria-label="<?php echo sr_e('Claim path'); ?>">
                                <?php if ($profileSyncLocked) { ?>
                                    <span class="btn btn-sm btn-icon btn-solid-light member-oauth-repeat-locked" aria-label="<?php echo sr_e('필수 동기화 항목'); ?>" title="<?php echo sr_e('필수 동기화 항목'); ?>" aria-disabled="true">
                                        <?php echo sr_material_icon_html('lock'); ?>
                                    </span>
                                <?php } else { ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger member-oauth-repeat-delete" data-oauth-remove-row title="<?php echo sr_e('동기화 항목 삭제'); ?>" aria-label="<?php echo sr_e('동기화 항목 삭제'); ?>">
                                        <?php echo sr_material_icon_html('delete'); ?>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-oauth-add-profile-sync="<?php echo sr_e($providerKey); ?>">
                            <?php echo sr_material_icon_html('add'); ?>
                            <span><?php echo sr_e('동기화 항목 추가'); ?></span>
                        </button>
                        <?php if ($memberOauthCanEditProfileSettings) { ?>
                            <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e($memberOauthProfileSettingsUrl); ?>">
                                <?php echo sr_material_icon_html('tune'); ?>
                                <span><?php echo sr_e('선택 프로필 항목 관리'); ?></span>
                            </a>
                        <?php } ?>
                    </div>
                    <p class="form-help">이메일과 이름은 회원 기본 필드에 반영합니다. 그 외 항목은 회원 설정에 정의된 선택 프로필 항목에만 저장합니다.</p>
                </div>
            </div>
            <div class="form-row" data-oauth-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>"><?php echo sr_e('정렬 순서'); ?> <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>" type="number" name="<?php echo sr_e($sortOrderKey); ?>" min="-9999" max="9999" value="<?php echo sr_e((string) ((int) ($provider['sort_order'] ?? 0))); ?>"<?php echo $providerEnabled ? ' required' : ''; ?> class="form-input" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
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
                        <li><?php echo sr_e('Google, Kakao, Naver, GitHub, Apple ID 같은 제공자 플러그인을 설치하고 활성화합니다.'); ?></li>
                        <li><?php echo sr_e('이 화면에 생긴 제공자 카드에서 사용을 켜고 Client ID를 저장합니다.'); ?></li>
                        <li><?php echo sr_e('Callback URL을 외부 OAuth/OIDC 제공자 콘솔에 등록하고 로그인 화면 버튼 상태를 확인합니다.'); ?></li>
                    </ol>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('권장 후보'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static"><?php echo sr_e('Google, Kakao, Naver, GitHub, Apple ID'); ?></p>
                    <p class="form-help">제공자 플러그인은 `oauth-providers.php` 계약을 제공해야 이 화면에 표시됩니다. 기본 제공 플러그인은 Google, Kakao, Naver, GitHub, Apple ID 계약을 포함합니다.</p>
                </div>
            </div>
        </section>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('외부 제공자'); ?></h2>
            </div>
            <p class="admin-empty-state"><?php echo sr_e('설치된 외부 OAuth 제공자 계약이 없습니다. 회원 OAuth 제공자 플러그인을 설치/활성화하면 Google, Kakao, Naver, GitHub, Apple ID 설정 카드가 표시됩니다.'); ?></p>
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
<script type="application/json" data-oauth-profile-sync-targets><?php echo sr_js_json_encode($memberOauthProfileSyncTargets); ?></script>
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
function srMemberOauthRenumberProfileSync(list) {
    var baseName = list.getAttribute('data-oauth-profile-sync-name') || '';
    list.querySelectorAll('[data-oauth-profile-sync-row]').forEach(function (row, index) {
        var target = row.querySelector('[data-oauth-profile-sync-target-select]');
        var targetHidden = row.querySelector('[data-oauth-profile-sync-target-hidden]');
        var scope = row.querySelector('[data-oauth-profile-sync-scope-select]');
        var claim = row.querySelector('input');
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
        empty.textContent = 'Scope 선택 안 함';
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
        input.setAttribute('aria-label', 'Scope 항목');
        row.appendChild(input);
        row.appendChild(srMemberOauthCreateButton('delete', 'Scope 항목 삭제', 'data-oauth-remove-row'));
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
        var row = document.createElement('div');
        row.className = 'member-oauth-repeat-row member-oauth-sync-row';
        row.setAttribute('data-oauth-profile-sync-row', '');
        var select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('aria-label', '회원 필드');
        Object.keys(targets).forEach(function (targetKey) {
            var option = document.createElement('option');
            option.value = targetKey;
            option.textContent = targets[targetKey];
            select.appendChild(option);
        });
        var scopeSelect = document.createElement('select');
        scopeSelect.className = 'form-select';
        scopeSelect.setAttribute('data-oauth-profile-sync-scope-select', '');
        scopeSelect.setAttribute('aria-label', 'Scope');
        var input = document.createElement('input');
        input.type = 'text';
        input.maxLength = 120;
        input.className = 'form-input';
        input.setAttribute('aria-label', 'Claim path');
        row.appendChild(select);
        row.appendChild(scopeSelect);
        row.appendChild(input);
        row.appendChild(srMemberOauthCreateButton('delete', '동기화 항목 삭제', 'data-oauth-remove-row'));
        list.appendChild(row);
        srMemberOauthRenumberProfileSync(list);
        srMemberOauthSyncScopeSelectOptions(providerKey);
        input.focus();
    });
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
    row.remove();
    if (list.hasAttribute('data-oauth-profile-sync-list')) {
        srMemberOauthRenumberProfileSync(list);
    }
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
