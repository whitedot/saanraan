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
                    <label>
                    <span>표시 이름</span>
                        <input type="text" name="display_name" value="<?php echo sr_e((string) $account['display_name']); ?>" maxlength="120" required>
                    </label>
                </p>
                <p>
                    <label>
                    <span>선호 locale</span>
                        <input type="text" name="locale" value="<?php echo sr_e((string) $account['locale']); ?>" maxlength="20" required>
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
                    <label>
                    <span>현재 비밀번호</span>
                        <input type="password" name="current_password" required>
                    </label>
                </p>
                <p>
                    <label>
                    <span>새 비밀번호</span>
                        <input type="password" name="new_password" required>
                    </label>
                </p>
                <p>
                    <label>
                    <span>새 비밀번호 확인</span>
                        <input type="password" name="new_password_confirm" required>
                    </label>
                </p>
                <button type="submit">비밀번호 변경</button>
            </form>
        </section>

        <?php if ($profileFieldsEnabled) { ?>
            <section>
                <h2>선택 프로필</h2>
                <form method="post" action="<?php echo sr_e(sr_url('/account')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="profile">
                    <?php if ($profileFields['nickname']) { ?>
                        <p>
                            <label>
                    <span>닉네임</span>
                                <input type="text" name="nickname" value="<?php echo sr_e($profile['nickname']); ?>" maxlength="80">
                            </label>
                        </p>
                    <?php } ?>
                    <?php if ($profileFields['phone']) { ?>
                        <p>
                            <label>
                    <span>전화번호</span>
                                <input type="text" name="phone" value="<?php echo sr_e($profile['phone']); ?>" maxlength="40">
                            </label>
                        </p>
                    <?php } ?>
                    <?php if ($profileFields['birth_date']) { ?>
                        <p>
                            <label>
                    <span>생년월일</span>
                                <input type="date" name="birth_date" value="<?php echo sr_e($profile['birth_date']); ?>">
                            </label>
                        </p>
                    <?php } ?>
                    <?php if ($profileFields['avatar_path']) { ?>
                        <p>
                            <label>
                    <span>아바타 경로</span>
                                <input type="text" name="avatar_path" value="<?php echo sr_e($profile['avatar_path']); ?>" maxlength="255">
                            </label>
                        </p>
                    <?php } ?>
                    <?php if ($profileFields['profile_text']) { ?>
                        <p>
                            <label>
                    <span>소개</span>
                                <textarea name="profile_text" maxlength="1000"><?php echo sr_e($profile['profile_text']); ?></textarea>
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
            <label>
                    <span>현재 비밀번호</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <button type="submit">개인정보 사본 내려받기</button>
        </form>
        <p><a href="<?php echo sr_e(sr_url('/account/withdraw')); ?>">회원 탈퇴</a></p>
    </main>
<?php sr_public_layout_end(); ?>
