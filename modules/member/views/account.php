<?php

$pageTitle = '내 계정';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <dl>
            <dt>이메일</dt>
            <dd><?php echo sr_e((string) $account['email']); ?></dd>
            <dt>표시 이름</dt>
            <dd><?php echo sr_e((string) $account['display_name']); ?></dd>
            <dt>상태</dt>
            <dd><?php echo sr_e((string) $account['status']); ?></dd>
            <dt>이메일 인증</dt>
            <dd><?php echo $account['email_verified_at'] === null ? '미인증' : sr_e((string) $account['email_verified_at']); ?></dd>
        </dl>

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

        <section>
            <h2>계정 정보</h2>
            <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="basics">
                <p>
                    <label for="modules_member_account_display_name">
                    <span>표시 이름 <span class="sr-required-label">(필수)</span></span>
                        <input id="modules_member_account_display_name" type="text" name="display_name" value="<?php echo sr_e((string) $account['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_locale">
                    <span>선호 locale <span class="sr-required-label">(필수)</span></span>
                        <select id="modules_member_account_locale" name="locale" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) $account['locale'] === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
                <button type="submit">계정 정보 저장</button>
            </form>
        </section>

        <?php if ($emailVerificationEnabled) { ?>
            <section>
                <h2>이메일 인증</h2>
                <?php if ($account['email_verified_at'] === null) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/email-verification')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <button type="submit">인증 메일 다시 보내기</button>
                    </form>
                    <?php if ($emailVerificationUrl !== '') { ?>
                        <p><a href="<?php echo sr_e($emailVerificationUrl); ?>">이메일 인증 링크</a></p>
                    <?php } ?>
                <?php } else { ?>
                    <p>이메일 인증이 완료되었습니다.</p>
                <?php } ?>
            </section>
        <?php } ?>

        <section>
            <h2>비밀번호 변경</h2>
            <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="password">
                <p>
                    <label for="modules_member_account_current_password">
                    <span>현재 비밀번호 <span class="sr-required-label">(필수)</span></span>
                        <input id="modules_member_account_current_password" type="password" name="current_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password">
                    <span>새 비밀번호 <span class="sr-required-label">(필수)</span></span>
                        <input id="modules_member_account_new_password" type="password" name="new_password" required>
                    </label>
                </p>
                <p>
                    <label for="modules_member_account_new_password_confirm">
                    <span>새 비밀번호 확인 <span class="sr-required-label">(필수)</span></span>
                        <input id="modules_member_account_new_password_confirm" type="password" name="new_password_confirm" required>
                    </label>
                </p>
                <button type="submit">비밀번호 변경</button>
            </form>
        </section>

        <?php if ($profileFieldsEnabled) { ?>
            <section>
                <h2>선택 프로필</h2>
                <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>"<?php echo !empty($profilePolicies['avatar_path']['visible']) ? ' enctype="multipart/form-data"' : ''; ?>>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="profile">
                    <?php if (!empty($profilePolicies['nickname']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_nickname">
                                <span>닉네임<?php echo !empty($profilePolicies['nickname']['required']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></span>
                                <input id="modules_member_account_nickname" type="text" name="nickname" value="<?php echo sr_e($profile['nickname']); ?>" maxlength="80"<?php echo !empty($profilePolicies['nickname']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['phone']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_phone">
                                <span>전화번호<?php echo !empty($profilePolicies['phone']['required']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></span>
                                <input id="modules_member_account_phone" type="text" name="phone" value="<?php echo sr_e($profile['phone']); ?>" maxlength="40"<?php echo !empty($profilePolicies['phone']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['birth_date']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_birth_date">
                                <span>생년월일<?php echo !empty($profilePolicies['birth_date']['required']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></span>
                                <input id="modules_member_account_birth_date" type="date" name="birth_date" value="<?php echo sr_e($profile['birth_date']); ?>"<?php echo !empty($profilePolicies['birth_date']['required']) ? ' required' : ''; ?>>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['avatar_path']['visible'])) { ?>
                        <?php $avatarSrc = sr_member_avatar_src((string) $profile['avatar_path']); ?>
                        <p>
                            <label for="modules_member_account_avatar_file">
                                <span>아바타<?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></span>
                                <input id="modules_member_account_avatar_file" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp"<?php echo !empty($profilePolicies['avatar_path']['required']) && $avatarSrc === '' ? ' required' : ''; ?>>
                            </label>
                            <small>JPG, PNG, WebP / 최대 <?php echo sr_e(sr_member_format_bytes(sr_member_avatar_upload_max_bytes())); ?></small>
                        </p>
                        <?php if ($avatarSrc !== '') { ?>
                            <p>
                                <img src="<?php echo sr_e($avatarSrc); ?>" alt="아바타" width="96" height="96">
                            </p>
                            <?php if (empty($profilePolicies['avatar_path']['required'])) { ?>
                                <p>
                                    <label for="modules_member_account_avatar_delete">
                                        <input id="modules_member_account_avatar_delete" type="checkbox" name="avatar_delete" value="1" class="form-checkbox">
                                        아바타 삭제
                                    </label>
                                </p>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                    <?php if (!empty($profilePolicies['profile_text']['visible'])) { ?>
                        <p>
                            <label for="modules_member_account_profile_text">
                                <span>소개<?php echo !empty($profilePolicies['profile_text']['required']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></span>
                                <textarea id="modules_member_account_profile_text" name="profile_text" maxlength="1000"<?php echo !empty($profilePolicies['profile_text']['required']) ? ' required' : ''; ?>><?php echo sr_e($profile['profile_text']); ?></textarea>
                            </label>
                        </p>
                    <?php } ?>
                    <button type="submit">프로필 저장</button>
                </form>
            </section>
        <?php } ?>

        <section>
            <h2>동의 기록</h2>
            <?php if ($consents === []) { ?>
                <p>기록된 동의가 없습니다.</p>
            <?php } else { ?>
                <dl>
                    <?php foreach ($consents as $consent) { ?>
                        <dt><?php echo sr_e((string) $consent['consent_key']); ?></dt>
                        <dd>
                            <?php echo !empty($consent['consented']) ? '동의' : '미동의'; ?>
                            <?php echo sr_e((string) $consent['consent_version']); ?>
                            <?php echo sr_e((string) $consent['created_at']); ?>
                        </dd>
                    <?php } ?>
                </dl>
            <?php } ?>
        </section>

        <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>">
            <?php echo sr_csrf_field(); ?>
            <button type="submit">로그아웃</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/privacy-requests')); ?>">개인정보 처리 요청</a></p>
        <?php if (isset($pdo) && $pdo instanceof PDO && sr_module_enabled($pdo, 'notification')) { ?>
            <p><a href="<?php echo sr_e(sr_url('/account/notifications')); ?>">알림</a></p>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-export')); ?>">
            <?php echo sr_csrf_field(); ?>
            <label for="modules_member_account_current_password_2">
                    <span>현재 비밀번호 <span class="sr-required-label">(필수)</span></span>
                <input id="modules_member_account_current_password_2" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <button type="submit">개인정보 사본 내려받기</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/withdraw')); ?>">회원 탈퇴</a></p>
    </main>
<?php sr_public_layout_end(); ?>
