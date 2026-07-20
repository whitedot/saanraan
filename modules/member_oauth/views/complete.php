<?php

$pageTitle = 'OAuth 가입 완료';
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, ['title' => $pageTitle, 'robots' => 'noindex, nofollow'], sr_member_skin_layout_context($memberSkinKey));
?>
    <main class="member-skin-basic-page member-skin-basic-page-narrow">
        <?php echo sr_member_feedback_toasts('', $errors); ?>
        <section class="card">
            <div class="card-header">
                <h1 class="card-title"><?php echo sr_e($pageTitle); ?></h1>
            </div>
            <div class="card-body member-skin-basic-stack">
                <form method="post" action="<?php echo sr_e(sr_url('/oauth/complete')); ?>" class="member-skin-basic-form" data-sr-validate-form data-member-autofocus-form>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="state" value="<?php echo sr_e($stateToken); ?>">
                    <p>
                        <label for="member_oauth_complete_email">
                            <span>이메일 <span class="sr-required-label">(필수)</span></span>
                            <input id="member_oauth_complete_email" type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required class="form-input">
                        </label>
                    </p>
                    <p>
                        <label for="member_oauth_complete_display_name">
                            <span>이름 <span class="sr-required-label">(필수)</span></span>
                            <input id="member_oauth_complete_display_name" type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" required class="form-input">
                        </label>
                    </p>
                    <p>
                        <label for="member_oauth_complete_password">
                            <span>비밀번호 <span class="sr-required-label">(필수)</span></span>
                            <input id="member_oauth_complete_password" type="password" name="password" required class="form-input">
                        </label>
                    </p>
                    <p>
                        <label for="member_oauth_complete_password_confirm">
                            <span>비밀번호 확인 <span class="sr-required-label">(필수)</span></span>
                            <input id="member_oauth_complete_password_confirm" type="password" name="password_confirm" required class="form-input">
                        </label>
                    </p>
                    <?php echo sr_member_registration_policy_consent_section_html($policyDocuments, $registrationConsentValues ?? [], 'oauth_complete'); ?>
                    <button type="submit" class="btn btn-solid-primary btn-block">가입 완료</button>
                </form>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
