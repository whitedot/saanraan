<?php

$pageTitle = sr_t('member::ui.member.e668cc2b');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
$memberRegisterIdentityBirthDateLocked = !empty($registrationIdentityFieldsLocked) && !empty($registrationIdentityUseBirthDate);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page">
        <?php echo sr_member_feedback_toasts('', $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">

        <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'before_form']); ?>

        <?php if ($registrationReady) { ?>
            <?php
            $memberRegisterProfileExtraDefinitions = is_array($profileExtraFieldDefinitions ?? null) ? $profileExtraFieldDefinitions : [];
            $memberRegisterProfileOrderItems = sr_member_profile_field_order_items($memberSettings, $memberRegisterProfileExtraDefinitions);
            $memberRegisterProfileExtraByKey = [];
            foreach ($memberRegisterProfileExtraDefinitions as $memberRegisterProfileExtraDefinition) {
                $memberRegisterProfileExtraByKey[(string) ($memberRegisterProfileExtraDefinition['key'] ?? '')] = $memberRegisterProfileExtraDefinition;
            }
            ?>
            <?php if (($registrationIdentityMode ?? 'disabled') !== 'disabled') { ?>
                <div class="alert <?php echo !empty($registrationIdentitySatisfied) ? 'alert-success' : (!empty($registrationIdentityRequired) ? 'alert-warning' : 'alert-info'); ?>">
                    <?php if (!empty($registrationIdentitySatisfied)) { ?>
                        <p><strong><?php echo sr_e('본인확인 완료'); ?></strong></p>
                        <p><?php echo sr_e('가입 전 본인확인이 확인되었습니다. 이제 아래 회원가입 정보를 입력해 주세요.'); ?></p>
                    <?php } elseif (($registrationIdentityReturnStatus ?? '') === 'expired') { ?>
                        <p><strong><?php echo sr_e('본인확인 시간이 만료되었습니다'); ?></strong></p>
                        <p><?php echo sr_e('테스트 모드에서는 인증번호 안내를 확인한 뒤 제한 시간 안에 다시 완료해 주세요.'); ?></p>
                    <?php } elseif (in_array(($registrationIdentityReturnStatus ?? ''), ['failed', 'canceled', 'duplicate'], true)) { ?>
                        <p><strong><?php echo sr_e('본인확인을 완료하지 못했습니다'); ?></strong></p>
                        <p><?php echo sr_e('다시 시도해 주세요.'); ?></p>
                    <?php } else { ?>
                        <p><?php echo sr_e(!empty($registrationIdentityRequired) ? '회원가입 전 본인확인이 필요합니다.' : '회원가입 전 본인확인을 선택할 수 있습니다.'); ?></p>
                    <?php } ?>
                    <?php if (empty($registrationIdentitySatisfied) && !empty($registrationIdentityStartUrl)) { ?>
                        <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $registrationIdentityStartUrl); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <form method="post" action="<?php echo sr_e(sr_url('/register')); ?>" class="member-skin-basic-form" data-sr-validate-form data-member-autofocus-form<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                <?php echo sr_csrf_field(); ?>
                <?php if (!empty($registrationIdentitySatisfied) && !empty($registrationIdentityReturnToken)) { ?>
                    <input type="hidden" name="identity_verification_token" value="<?php echo sr_e((string) $registrationIdentityReturnToken); ?>">
                <?php } ?>
                <p>
                    <label for="modules_member_register_email">
                    <span><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_register_email" type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_login_id">
                    <span><?php echo sr_e(sr_t('member::ui.login.0cdb28b5')); ?></span>
                        <input class="form-input" id="modules_member_register_login_id" type="text" name="login_id" value="<?php echo sr_e($values['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="username" data-member-login-id-input>
                    </label>
                    <small><?php echo sr_e(sr_t('member::ui.email.login.email.active.eb627985')); ?></small>
                </p>
                <p>
                    <label for="modules_member_register_display_name">
                    <span><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_register_display_name" type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" maxlength="120" required<?php echo !empty($registrationIdentityFieldsLocked) ? ' readonly data-member-identity-locked-field="name"' : ''; ?>>
                    </label>
                </p>
                <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                    <p>
                        <label for="modules_member_register_nickname">
                        <span><?php echo sr_e(sr_t('member::ui.nickname')); ?><?php echo !empty($memberSettings['nickname_required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                            <input class="form-input" id="modules_member_register_nickname" type="text" name="nickname" value="<?php echo sr_e((string) ($values['nickname'] ?? '')); ?>" maxlength="80"<?php echo !empty($memberSettings['nickname_required']) ? ' required' : ''; ?>>
                        </label>
                        <small><?php echo sr_e(sr_t('member::ui.nickname.help')); ?></small>
                    </p>
                <?php } ?>
                <?php foreach (($registrationExtensionFields ?? []) as $registrationExtensionField) { ?>
                    <?php
                    $registrationExtensionKey = (string) ($registrationExtensionField['key'] ?? '');
                    $registrationExtensionInputId = 'modules_member_register_extension_' . $registrationExtensionKey;
                    ?>
                    <p>
                        <label for="<?php echo sr_e($registrationExtensionInputId); ?>">
                    <span><?php echo sr_e((string) ($registrationExtensionField['label'] ?? '')); ?><?php echo !empty($registrationExtensionField['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                            <input id="<?php echo sr_e($registrationExtensionInputId); ?>" type="text" name="registration_extensions[<?php echo sr_e($registrationExtensionKey); ?>]" value="<?php echo sr_e((string) (($registrationExtensionValues ?? [])[$registrationExtensionKey] ?? '')); ?>" class="form-input" maxlength="<?php echo sr_e((string) (int) ($registrationExtensionField['maxlength'] ?? 120)); ?>"<?php echo !empty($registrationExtensionField['required']) ? ' required' : ''; ?>>
                        </label>
                        <?php if ((string) ($registrationExtensionField['help'] ?? '') !== '') { ?>
                            <small><?php echo sr_e((string) $registrationExtensionField['help']); ?></small>
                        <?php } ?>
                    </p>
                <?php } ?>
                <?php foreach ($memberRegisterProfileOrderItems as $memberRegisterProfileOrderItem) { ?>
                    <?php if ((string) ($memberRegisterProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberRegisterProfileOrderItem['key'] ?? '') === 'birth_date' && !empty($profilePolicies['birth_date']['visible'])) { ?>
                        <p>
                            <label for="modules_member_register_birth_date">
                        <span><?php echo sr_e(sr_t('member::ui.text.f7ea9e33')); ?><?php echo !empty($profilePolicies['birth_date']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <input class="form-input" id="modules_member_register_birth_date" type="date" name="birth_date" value="<?php echo sr_e((string) $profileValues['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?><?php echo !empty($memberRegisterIdentityBirthDateLocked) ? ' readonly data-member-identity-locked-field="birth_date"' : ''; ?>>
                            </label>
                        </p>
                    <?php } elseif ((string) ($memberRegisterProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberRegisterProfileOrderItem['key'] ?? '') === 'is_adult' && !empty($profilePolicies['is_adult']['visible'])) { ?>
                        <p>
                            <label for="modules_member_register_is_adult">
                        <span><?php echo sr_e(sr_t('member::ui.is_adult')); ?><?php echo !empty($profilePolicies['is_adult']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <?php if (!empty($memberRegisterIdentityBirthDateLocked)) { ?>
                                    <input type="hidden" name="is_adult" value="<?php echo sr_e((string) ($profileValues['is_adult'] ?? '')); ?>" data-member-identity-locked-hidden="is_adult">
                                <?php } ?>
                                <select class="form-select" id="modules_member_register_is_adult"<?php echo !empty($memberRegisterIdentityBirthDateLocked) ? '' : ' name="is_adult"'; ?><?php echo !empty($profilePolicies['is_adult']['required']) ? ' required' : ''; ?><?php echo !empty($memberRegisterIdentityBirthDateLocked) ? ' disabled data-member-identity-locked-field="is_adult"' : ''; ?>>
                                    <option value=""><?php echo sr_e(sr_t('member::ui.select.default')); ?></option>
                                    <option value="1"<?php echo (string) ($profileValues['is_adult'] ?? '') === '1' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.yes')); ?></option>
                                    <option value="0"<?php echo (string) ($profileValues['is_adult'] ?? '') === '0' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('member::ui.no')); ?></option>
                                </select>
                            </label>
                        </p>
                    <?php } elseif ((string) ($memberRegisterProfileOrderItem['kind'] ?? '') === 'fixed' && (string) ($memberRegisterProfileOrderItem['key'] ?? '') === 'avatar_path' && !empty($profilePolicies['avatar_path']['visible'])) { ?>
                        <p>
                            <label for="modules_member_register_avatar_file">
                        <span><?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?><?php echo !empty($profilePolicies['avatar_path']['required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                                <input class="form-input" id="modules_member_register_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) ? ' required' : ''; ?>>
                            </label>
                            <small><?php echo sr_e(sr_t('member::ui.jpg.png.webp.2fd448bf')); ?> <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                        </p>
                    <?php } elseif ((string) ($memberRegisterProfileOrderItem['kind'] ?? '') === 'extra') { ?>
                        <?php echo sr_member_profile_extra_fields_form_html(
                            [$memberRegisterProfileExtraByKey[(string) ($memberRegisterProfileOrderItem['key'] ?? '')] ?? []],
                            is_array($profileExtraValues ?? null) ? $profileExtraValues : [],
                            'modules_member_register_profile_extra',
                            false,
                            ['locked_keys' => !empty($registrationIdentityFieldsLocked) && is_array($registrationIdentityLockedProfileExtraKeys ?? null) ? $registrationIdentityLockedProfileExtraKeys : []]
                        ); ?>
                    <?php } ?>
                <?php } ?>
                <p>
                    <label for="modules_member_register_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_register_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.61081c91')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input class="form-input" id="modules_member_register_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <?php echo sr_member_registration_policy_consent_section_html($registrationPolicyDocuments, $registrationConsentValues ?? [], 'register'); ?>
                <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                    <?php echo sr_antispam_challenge_render($pdo, 'member.register', 'member_register', $antispamRegisterContext ?? ['account' => null]); ?>
                <?php } ?>
                <button class="btn btn-solid-primary" type="submit"><?php echo sr_e(sr_t('member::ui.text.ac31175f')); ?></button>
            </form>
        <?php } elseif ($registrationAllowed) { ?>
            <p><?php echo sr_e(sr_t('member::ui.policy_documents_unavailable')); ?></p>
        <?php } else { ?>
            <p><?php echo sr_e(sr_t('member::ui.member.active.7c7e897d')); ?></p>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'after_form']); ?>

                <div class="member-skin-basic-actions">
                    <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></a>
                </div>
            </div>
        </section>
    </main>
    <script>
        (function () {
            var identityLocked = <?php echo !empty($registrationIdentityFieldsLocked) ? 'true' : 'false'; ?>;
            var identityBirthDateLocked = <?php echo !empty($memberRegisterIdentityBirthDateLocked) ? 'true' : 'false'; ?>;
            var identityTokenPresent = <?php echo !empty($registrationIdentityReturnToken) ? 'true' : 'false'; ?>;
            if (identityTokenPresent && window.history && typeof window.history.replaceState === 'function') {
                try {
                    var cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('identity_verification');
                    cleanUrl.searchParams.delete('identity_verification_token');
                    window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
                } catch (error) {
                }
            }
            if (!identityLocked) {
                try {
                    var staleRawIdentity = window.sessionStorage.getItem('sr_identity_verification_result');
                    var staleIdentityPayload = staleRawIdentity ? JSON.parse(staleRawIdentity) : null;
                    if (staleIdentityPayload && staleIdentityPayload.purpose === 'member.registration') {
                        window.sessionStorage.removeItem('sr_identity_verification_result');
                    }
                } catch (error) {
                }
            }
            if (identityTokenPresent) {
                window.addEventListener('pagehide', function () {
                    try {
                        window.sessionStorage.removeItem('sr_identity_verification_result');
                    } catch (error) {
                    }
                    document.querySelectorAll('input[name="identity_verification_token"]').forEach(function (tokenInput) {
                        tokenInput.value = '';
                    });
                });
                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        window.location.replace('/register');
                    }
                });
            }
            if (identityLocked) {
                try {
                    var serverIdentity = <?php echo sr_js_json_encode(is_array($registrationIdentitySnapshot ?? null) ? $registrationIdentitySnapshot : []); ?>;
                    var rawIdentity = window.sessionStorage.getItem('sr_identity_verification_result');
                    var identityPayload = rawIdentity ? JSON.parse(rawIdentity) : null;
                    var identity = identityPayload && identityPayload.result === 'success' && identityPayload.purpose === 'member.registration'
                        ? identityPayload.identity || {}
                        : serverIdentity || {};
                    var displayName = document.getElementById('modules_member_register_display_name');
                    var birthDate = document.getElementById('modules_member_register_birth_date');
                    var isAdult = document.getElementById('modules_member_register_is_adult');
                    var isAdultHidden = document.querySelector('[data-member-identity-locked-hidden="is_adult"]');
                    if (displayName && !displayName.value && identity.name) {
                        displayName.value = identity.name;
                    }
                    if (identityBirthDateLocked && birthDate && identity.birth_date) {
                        birthDate.value = identity.birth_date;
                    }
                    if (identityBirthDateLocked && isAdult && identity.age_over_19 !== '') {
                        isAdult.value = identity.age_over_19 === '1' ? '1' : '0';
                    }
                    if (isAdultHidden && isAdult) {
                        isAdultHidden.value = isAdult.value;
                    }
                    if (identity.phone) {
                        ['phone', 'mobile', 'mobile_phone', 'phone_number'].forEach(function (key) {
                            var phoneInput = document.querySelector('[name="member_profile_fields[' + key + ']"]');
                            if (phoneInput && phoneInput.tagName === 'INPUT') {
                                phoneInput.value = identity.phone;
                                phoneInput.readOnly = true;
                                phoneInput.setAttribute('data-member-identity-locked-field', 'phone');
                            }
                        });
                    }
                } catch (error) {
                }
            }

            var input = document.querySelector('[data-member-login-id-input]');
            if (!input) {
                return;
            }

            function normalizeLoginId(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '');
            }

            function clearLoginIdValidation() {
                if (input.getAttribute('data-restricted-input-validation-active') !== '1') {
                    return;
                }

                input.removeAttribute('data-restricted-input-validation-active');
                if (typeof input.setCustomValidity === 'function') {
                    input.setCustomValidity('');
                }
            }

            function showLoginIdValidation() {
                if (typeof input.setCustomValidity !== 'function') {
                    return;
                }

                window.clearTimeout(input._memberLoginIdValidationTimer);
                input.setAttribute('data-restricted-input-validation-active', '1');
                input.setCustomValidity('영문, 숫자, 밑줄만 입력 가능합니다.');
                if (typeof input.reportValidity === 'function') {
                    input.reportValidity();
                }
                input._memberLoginIdValidationTimer = window.setTimeout(clearLoginIdValidation, 1800);
            }

            function hasBlockedLoginIdData(value) {
                return /[^a-zA-Z0-9_]/.test(String(value || ''));
            }

            function syncLoginId(reportBlockedInput) {
                var previousValue = input.value;
                var nextValue = normalizeLoginId(previousValue);
                if (previousValue === nextValue) {
                    clearLoginIdValidation();
                    return;
                }

                var selectionStart = input.selectionStart;
                var beforeSelection = typeof selectionStart === 'number' ? previousValue.slice(0, selectionStart) : '';
                var nextSelectionStart = typeof selectionStart === 'number' ? normalizeLoginId(beforeSelection).length : nextValue.length;
                input.value = nextValue;
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(nextSelectionStart, nextSelectionStart);
                }
                if (reportBlockedInput && hasBlockedLoginIdData(previousValue)) {
                    showLoginIdValidation();
                }
            }

            syncLoginId();
            input.addEventListener('beforeinput', function (event) {
                if (!String(event.inputType || '').startsWith('insert') || !event.data || !hasBlockedLoginIdData(event.data)) {
                    return;
                }
                event.preventDefault();
                showLoginIdValidation();
            });
            input.addEventListener('input', function () {
                syncLoginId(true);
            });
        }());
    </script>
<?php sr_public_layout_end(); ?>
