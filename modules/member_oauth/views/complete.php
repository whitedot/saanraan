<?php

$pageTitle = 'OAuth 가입 완료';
$memberSkinKey = isset($memberSettings) && is_array($memberSettings) ? sr_member_skin_key($memberSettings) : 'basic';
sr_public_layout_begin($pdo ?? null, $site ?? null, ['title' => $pageTitle, 'robots' => 'noindex, nofollow'], sr_member_skin_layout_context($memberSkinKey));
?>
<main class="ui-page">
    <?php echo sr_member_feedback_toasts('', $errors); ?>
    <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
    <section class="card">
        <div class="card-body ui-card-body-stack">
            <form method="post" action="<?php echo sr_e(sr_url('/oauth/complete')); ?>" class="ui-card-body-stack" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="state" value="<?php echo sr_e($stateToken); ?>">
                <p>
                    <label for="member_oauth_complete_email">이메일 <span class="sr-required-label">(필수)</span></label>
                    <input id="member_oauth_complete_email" type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required class="form-input">
                </p>
                <p>
                    <label for="member_oauth_complete_display_name">이름 <span class="sr-required-label">(필수)</span></label>
                    <input id="member_oauth_complete_display_name" type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" required class="form-input">
                </p>
                <p>
                    <label for="member_oauth_complete_password">비밀번호 <span class="sr-required-label">(필수)</span></label>
                    <input id="member_oauth_complete_password" type="password" name="password" required class="form-input">
                </p>
                <p>
                    <label for="member_oauth_complete_password_confirm">비밀번호 확인 <span class="sr-required-label">(필수)</span></label>
                    <input id="member_oauth_complete_password_confirm" type="password" name="password_confirm" required class="form-input">
                </p>
                <?php echo sr_member_registration_policy_consent_section_html($policyDocuments, $registrationConsentValues ?? [], 'oauth_complete'); ?>
                <button type="submit" class="btn btn-solid-primary">가입 완료</button>
            </form>
        </div>
    </section>
</main>
<?php sr_public_layout_end(); ?>
