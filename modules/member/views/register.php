<?php

$pageTitle = '회원가입';
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
                    <label>
                    <span>이메일</span>
                        <input type="email" name="email" value="<?php echo sr_e($values['email']); ?>" required>
                    </label>
                </p>
                <?php if ($loginIdentifierMode === 'login_id') { ?>
                    <p>
                        <label>
                    <span>로그인 아이디</span>
                            <input type="text" name="login_id" value="<?php echo sr_e($values['login_id']); ?>" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username" required>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label>
                    <span>표시 이름</span>
                        <input type="text" name="display_name" value="<?php echo sr_e($values['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <?php if (!empty($profilePolicies['nickname']['visible'])) { ?>
                    <p>
                        <label>
                    <span>닉네임</span>
                            <input type="text" name="nickname" value="<?php echo sr_e((string) $profileValues['nickname']); ?>" maxlength="80"<?php echo !empty($profilePolicies['nickname']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                    <p>
                        <label>
                    <span>전화번호</span>
                            <input type="text" name="phone" value="<?php echo sr_e((string) $profileValues['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                    <p>
                        <label>
                    <span>생년월일</span>
                            <input type="date" name="birth_date" value="<?php echo sr_e((string) $profileValues['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                        </label>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                    <p>
                        <label>
                    <span>아바타</span>
                            <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) ? ' required' : ''; ?>>
                        </label>
                        <small>JPG, PNG, WebP / 최대 <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                    </p>
                <?php } ?>
                <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                    <p>
                        <label>
                    <span>소개</span>
                            <textarea name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e((string) $profileValues['profile_text']); ?></textarea>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label>
                    <span>비밀번호</span>
                        <input type="password" name="password" required>
                    </label>
                </p>
                <p>
                    <label>
                    <span>비밀번호 확인</span>
                        <input type="password" name="password_confirm" required>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="terms_consent" value="1" class="form-checkbox" required>
                        필수 약관에 동의합니다.
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="privacy_consent" value="1" class="form-checkbox" required>
                        개인정보 처리방침에 동의합니다.
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="marketing_consent" value="1" class="form-checkbox"<?php echo $marketingConsent ? ' checked' : ''; ?>>
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
