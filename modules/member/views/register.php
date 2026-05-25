<?php

$pageTitle = sr_t('member::ui.member.e668cc2b');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'before_form']); ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if ($registrationAllowed) { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/register')); ?>"<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                <?php echo sr_csrf_field(); ?>
                <p>
                    <label for="modules_member_register_email">
                    <span><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_register_email" type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_login_id">
                    <span><?php echo sr_e(sr_t('member::ui.login.0cdb28b5')); ?></span>
                        <input id="modules_member_register_login_id" type="text" name="login_id" value="<?php echo sr_e($values['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="username" data-member-login-id-input>
                    </label>
                    <small><?php echo sr_e(sr_t('member::ui.email.login.email.active.eb627985')); ?></small>
                </p>
                <p>
                    <label for="modules_member_register_display_name">
                    <span><?php echo sr_e(sr_t('member::ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_register_display_name" type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <?php if (!empty($profilePolicies['nickname']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_nickname">
                    <span><?php echo sr_e(sr_t('member::ui.text.6211d967')); ?><?php echo !empty($profilePolicies['nickname']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                            <input id="modules_member_register_nickname" type="text" name="nickname" value="<?php echo sr_e((string) $profileValues['nickname']); ?>" maxlength="80"<?php echo !empty($profilePolicies['nickname']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_phone">
                    <span><?php echo sr_e(sr_t('member::ui.text.4edc9439')); ?><?php echo !empty($profilePolicies['phone']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                            <input id="modules_member_register_phone" type="text" name="phone" value="<?php echo sr_e((string) $profileValues['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_birth_date">
                    <span><?php echo sr_e(sr_t('member::ui.text.f7ea9e33')); ?><?php echo !empty($profilePolicies['birth_date']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                            <input id="modules_member_register_birth_date" type="date" name="birth_date" value="<?php echo sr_e((string) $profileValues['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_avatar_file">
                    <span><?php echo sr_e(sr_t('member::ui.text.8ec77a49')); ?><?php echo !empty($profilePolicies['avatar_path']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                            <input id="modules_member_register_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) ? ' required' : ''; ?>>
                        </label>
                        <small><?php echo sr_e(sr_t('member::ui.jpg.png.webp.2fd448bf')); ?> <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_profile_text">
                    <span><?php echo sr_e(sr_t('member::ui.text.7367283c')); ?><?php echo !empty($profilePolicies['profile_text']['required']) ? sr_t('member::ui.span.class.sr.required.label.07a9346b') : ''; ?></span>
                            <textarea id="modules_member_register_profile_text" name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e((string) $profileValues['profile_text']); ?></textarea>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label for="modules_member_register_password">
                    <span><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_register_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_password_confirm">
                    <span><?php echo sr_e(sr_t('member::ui.password.61081c91')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_member_register_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_terms_consent">
                        <input id="modules_member_register_terms_consent" type="checkbox" name="terms_consent" value="1" class="form-checkbox" required>
                        <?php echo sr_e(sr_t('member::ui.required.057abc7f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_privacy_consent">
                        <input id="modules_member_register_privacy_consent" type="checkbox" name="privacy_consent" value="1" class="form-checkbox" required>
                        <?php echo sr_e(sr_t('member::ui.privacy.ae1af6ad')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_marketing_consent">
                        <input id="modules_member_register_marketing_consent" type="checkbox" name="marketing_consent" value="1" class="form-checkbox"<?php echo $marketingConsent ? ' checked' : ''; ?>>
                        <?php echo sr_e(sr_t('member::ui.text.be6df05e')); ?>
                    </label>
                </p>
                <button type="submit"><?php echo sr_e(sr_t('member::ui.text.ac31175f')); ?></button>
            </form>
        <?php } else { ?>
            <p><?php echo sr_e(sr_t('member::ui.member.active.7c7e897d')); ?></p>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'after_form']); ?>

        <p><a href="<?php echo sr_e(sr_url('/login')); ?>"><?php echo sr_e(sr_t('member::ui.login.6d253673')); ?></a></p>
    </main>
    <script>
        (function () {
            var input = document.querySelector('[data-member-login-id-input]');
            if (!input) {
                return;
            }

            function normalizeLoginId(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '');
            }

            function syncLoginId() {
                var previousValue = input.value;
                var nextValue = normalizeLoginId(previousValue);
                if (previousValue === nextValue) {
                    return;
                }

                var selectionStart = input.selectionStart;
                var beforeSelection = typeof selectionStart === 'number' ? previousValue.slice(0, selectionStart) : '';
                var nextSelectionStart = typeof selectionStart === 'number' ? normalizeLoginId(beforeSelection).length : nextValue.length;
                input.value = nextValue;
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(nextSelectionStart, nextSelectionStart);
                }
            }

            syncLoginId();
            input.addEventListener('input', syncLoginId);
        }());
    </script>
<?php sr_public_layout_end(); ?>
