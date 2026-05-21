<?php

$pageTitle = '회원가입';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
$loginIdRequired = sr_member_login_id_required($memberSettings ?? []);
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
                    <span>이메일</span>
                        <input id="modules_member_register_email" type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_login_id">
                    <span>로그인 아이디</span>
                        <input id="modules_member_register_login_id" type="text" name="login_id" value="<?php echo sr_e($values['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username"<?php echo $loginIdRequired ? ' required' : ''; ?>>
                    </label>
                    <small><?php echo $loginIdRequired ? '이 설정에서는 로그인 아이디로만 로그인할 수 있습니다.' : '비워두면 이메일로 로그인하고, 입력하면 이메일과 아이디를 모두 사용할 수 있습니다.'; ?></small>
                </p>
                <p>
                    <label for="modules_member_register_display_name">
                    <span>표시 이름</span>
                        <input id="modules_member_register_display_name" type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <?php if (!empty($profilePolicies['nickname']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_nickname">
                    <span>닉네임</span>
                            <input id="modules_member_register_nickname" type="text" name="nickname" value="<?php echo sr_e((string) $profileValues['nickname']); ?>" maxlength="80"<?php echo !empty($profilePolicies['nickname']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_phone">
                    <span>전화번호</span>
                            <input id="modules_member_register_phone" type="text" name="phone" value="<?php echo sr_e((string) $profileValues['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_birth_date">
                    <span>생년월일</span>
                            <input id="modules_member_register_birth_date" type="date" name="birth_date" value="<?php echo sr_e((string) $profileValues['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_avatar_file">
                    <span>아바타</span>
                            <input id="modules_member_register_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) ? ' required' : ''; ?>>
                        </label>
                        <small>JPG, PNG, WebP / 최대 <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                    <p>
                        <label for="modules_member_register_profile_text">
                    <span>소개</span>
                            <textarea id="modules_member_register_profile_text" name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e((string) $profileValues['profile_text']); ?></textarea>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label for="modules_member_register_password">
                    <span>비밀번호</span>
                        <input id="modules_member_register_password" type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_password_confirm">
                    <span>비밀번호 확인</span>
                        <input id="modules_member_register_password_confirm" type="password" name="password_confirm" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_terms_consent">
                        <input id="modules_member_register_terms_consent" type="checkbox" name="terms_consent" value="1" class="form-checkbox" required>
                        필수 약관에 동의합니다.
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_privacy_consent">
                        <input id="modules_member_register_privacy_consent" type="checkbox" name="privacy_consent" value="1" class="form-checkbox" required>
                        개인정보 처리방침에 동의합니다.
                    </label>
                </p>
                <p>
                    <label for="modules_member_register_marketing_consent">
                        <input id="modules_member_register_marketing_consent" type="checkbox" name="marketing_consent" value="1" class="form-checkbox"<?php echo $marketingConsent ? ' checked' : ''; ?>>
                        마케팅 수신에 동의합니다.
                    </label>
                </p>
                <button type="submit">가입</button>
            </form>
        <?php } else { ?>
            <p>현재 회원가입을 사용할 수 없습니다.</p>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, ['module_key' => 'member', 'point_key' => 'member.register', 'slot_key' => 'after_form']); ?>

        <p><a href="<?php echo sr_e(sr_url('/login')); ?>">로그인</a></p>
    </main>
<?php sr_public_layout_end(); ?>
