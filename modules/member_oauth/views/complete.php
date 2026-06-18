<?php

$pageTitle = 'OAuth 가입 완료';
sr_public_layout_begin($pdo ?? null, $site ?? null, ['title' => $pageTitle, 'robots' => 'noindex, nofollow'], []);
?>
<main class="ui-page">
    <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
    <section class="card">
        <div class="card-body ui-card-body-stack">
            <?php if ($errors !== []) { ?>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <form method="post" action="<?php echo sr_e(sr_url('/oauth/complete')); ?>" class="ui-card-body-stack">
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
                <?php foreach (['terms', 'privacy', 'marketing'] as $consentKey) { ?>
                    <?php if (!empty($policyDocuments[$consentKey])) { ?>
                        <p>
                            <label>
                                <input type="checkbox" name="<?php echo $consentKey === 'terms' ? 'terms_consent' : ($consentKey === 'privacy' ? 'privacy_consent' : 'marketing_consent'); ?>" value="1"<?php echo !empty($policyDocuments[$consentKey]['required']) ? ' required' : ''; ?>>
                                <?php echo sr_e((string) $policyDocuments[$consentKey]['title']); ?><?php if (!empty($policyDocuments[$consentKey]['required'])) { ?> <span class="sr-required-label">(필수)</span><?php } ?>
                            </label>
                            <?php if (!empty($policyDocuments[$consentKey]['body_html'])) { ?>
                                <details>
                                    <summary>문서 보기</summary>
                                    <div><?php echo (string) $policyDocuments[$consentKey]['body_html']; ?></div>
                                </details>
                            <?php } ?>
                        </p>
                    <?php } ?>
                <?php } ?>
                <button type="submit" class="btn btn-solid-primary">가입 완료</button>
            </form>
        </div>
    </section>
</main>
<?php sr_public_layout_end(); ?>
