<?php

$adminPageTitle = '회원 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/member-settings')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2>가입과 인증</h2>
        <p>
            <label>
                <input type="checkbox" name="allow_registration" value="1"<?php echo !empty($settings['allow_registration']) ? ' checked' : ''; ?>>
                공개 회원가입 허용
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="email_verification_enabled" value="1"<?php echo !empty($settings['email_verification_enabled']) ? ' checked' : ''; ?>>
                이메일 인증 사용
            </label>
        </p>
        <p>
            <label>로그인 식별자<br>
                <select name="login_identifier">
                    <option value="email"<?php echo (string) $settings['login_identifier'] === 'email' ? ' selected' : ''; ?>>이메일</option>
                    <option value="login_id"<?php echo (string) $settings['login_identifier'] === 'login_id' ? ' selected' : ''; ?>>로그인 아이디</option>
                </select>
            </label>
        </p>
    </section>

    <section class="card">
        <h2>화면</h2>
        <p>
            <label>회원 스킨<br>
                <select name="member_skin_key">
                    <?php foreach (sr_member_skin_options() as $skinKey => $skinOption) { ?>
                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) $settings['member_skin_key'] === (string) $skinKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
    </section>

    <section class="card">
        <h2>선택 프로필 항목</h2>
        <?php foreach (sr_member_profile_field_setting_keys() as $key => $label) { ?>
            <p>
                <label>
                    <input type="checkbox" name="<?php echo sr_e($key); ?>" value="1"<?php echo !empty($settings[$key]) ? ' checked' : ''; ?>>
                    <?php echo sr_e($label); ?>
                </label>
            </p>
        <?php } ?>
    </section>

    <section class="card">
        <h2>로그인 시도 제한</h2>
        <p>
            <label>제한 시간(초)<br>
                <input type="number" name="login_throttle_window_seconds" value="<?php echo sr_e((string) $settings['login_throttle_window_seconds']); ?>" min="0" max="86400">
            </label>
        </p>
        <p>
            <label>계정 기준 제한 횟수<br>
                <input type="number" name="login_throttle_account_limit" value="<?php echo sr_e((string) $settings['login_throttle_account_limit']); ?>" min="0" max="1000">
            </label>
        </p>
        <p>
            <label>IP 기준 제한 횟수<br>
                <input type="number" name="login_throttle_ip_limit" value="<?php echo sr_e((string) $settings['login_throttle_ip_limit']); ?>" min="0" max="1000">
            </label>
        </p>
    </section>

    <section class="card">
        <h2>회원가입 제한</h2>
        <p>
            <label>제한 시간(초)<br>
                <input type="number" name="register_throttle_window_seconds" value="<?php echo sr_e((string) $settings['register_throttle_window_seconds']); ?>" min="0" max="86400">
            </label>
        </p>
        <p>
            <label>IP 기준 제한 횟수<br>
                <input type="number" name="register_throttle_ip_limit" value="<?php echo sr_e((string) $settings['register_throttle_ip_limit']); ?>" min="0" max="1000">
            </label>
        </p>
    </section>

    <section class="card">
        <h2>비밀번호 재설정 제한</h2>
        <p>
            <label>제한 시간(초)<br>
                <input type="number" name="password_reset_throttle_window_seconds" value="<?php echo sr_e((string) $settings['password_reset_throttle_window_seconds']); ?>" min="0" max="86400">
            </label>
        </p>
        <p>
            <label>계정 기준 제한 횟수<br>
                <input type="number" name="password_reset_throttle_account_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_account_limit']); ?>" min="0" max="1000">
            </label>
        </p>
        <p>
            <label>IP 기준 제한 횟수<br>
                <input type="number" name="password_reset_throttle_ip_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_ip_limit']); ?>" min="0" max="1000">
            </label>
        </p>
    </section>

    <section class="card">
        <h2>이메일 인증 제한</h2>
        <p>
            <label>제한 시간(초)<br>
                <input type="number" name="email_verification_throttle_window_seconds" value="<?php echo sr_e((string) $settings['email_verification_throttle_window_seconds']); ?>" min="0" max="86400">
            </label>
        </p>
        <p>
            <label>계정 기준 제한 횟수<br>
                <input type="number" name="email_verification_throttle_account_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_account_limit']); ?>" min="0" max="1000">
            </label>
        </p>
        <p>
            <label>IP 기준 제한 횟수<br>
                <input type="number" name="email_verification_throttle_ip_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_ip_limit']); ?>" min="0" max="1000">
            </label>
        </p>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
