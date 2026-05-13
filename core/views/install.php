<?php

$pageTitle = 'Saanraan 설치';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
$selectedOptionalModuleMap = array_fill_keys($selectedOptionalModuleKeys, true);
?>
<!doctype html>
<html lang="ko" data-color-scheme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, null); ?>
    <?php echo sr_stylesheet_tag(); ?>
</head>
<body class="sr-install-page">
    <main class="sr-install-shell">
        <section class="sr-install-intro">
            <div>
                <p class="sr-install-kicker">초기 설정</p>
                <h1><?php echo sr_e($pageTitle); ?></h1>
                <p>Saanraan 실행에 필요한 DB 연결, 사이트 기본값, 최초 관리자 계정, 기본 제공 모듈을 한 번에 설정합니다.</p>
            </div>
            <ol class="sr-install-steps" aria-label="설치 단계">
                <li>환경 확인</li>
                <li>DB 연결</li>
                <li>사이트 설정</li>
                <li>관리자 생성</li>
                <li>모듈 설치</li>
            </ol>
        </section>

        <?php if ($previousInstallFailure !== null) { ?>
            <section class="sr-install-alert sr-install-alert-warning">
                <h2>이전 설치 시도 기록</h2>
                <p>
                    단계:
                    <code><?php echo sr_e($previousInstallFailure['stage']); ?></code>
                    <?php if ($previousInstallFailure['recorded_at'] !== '') { ?>
                        <span>기록 시각: <?php echo sr_e($previousInstallFailure['recorded_at']); ?></span>
                    <?php } ?>
                </p>
                <p>
                    config 생성:
                    <?php echo $previousInstallFailure['config_written'] ? '예' : '아니오'; ?>,
                    설치 잠금:
                    <?php echo $previousInstallFailure['installed_lock_written'] ? '예' : '아니오'; ?>
                </p>
                <?php if ((string) ($previousInstallFailure['message'] ?? '') !== '') { ?>
                    <p>오류 요약: <?php echo sr_e((string) $previousInstallFailure['message']); ?></p>
                <?php } ?>
                <ul>
                    <li><code>storage/install-failed.json</code>의 stage와 message를 확인하세요.</li>
                    <li>DB 접속 정보, 쓰기 권한, 이미 생성된 테이블 상태를 확인한 뒤 다시 설치하세요.</li>
                    <li><code>storage/installed.lock</code>이 없으면 아직 설치 완료 상태가 아닙니다.</li>
                </ul>
            </section>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <section class="sr-install-alert sr-install-alert-error">
                <h2>확인 필요</h2>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($installWarnings !== []) { ?>
            <section class="sr-install-alert sr-install-alert-warning">
                <h2>주의 안내</h2>
                <ul>
                    <?php foreach ($installWarnings as $warning) { ?>
                        <li><?php echo sr_e($warning); ?></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <section class="sr-install-panel">
            <div class="sr-install-panel-head">
                <div>
                    <p class="sr-install-kicker">환경 확인</p>
                    <h2>설치 전 상태</h2>
                </div>
                <p>테스트 설치는 HTTP로 진행할 수 있지만, 운영 전에는 HTTPS와 내부 파일 직접 접근 차단을 확인하세요.</p>
            </div>
            <div class="sr-install-check-grid">
                <?php foreach ($installChecks as $check) { ?>
                    <div class="sr-install-check">
                        <span class="sr-install-status sr-install-status-<?php echo sr_e((string) $check['status']); ?>">
                            <?php echo ((string) $check['status'] === 'ok') ? '확인됨' : (((string) $check['status'] === 'warning') ? '주의' : '필요'); ?>
                        </span>
                        <strong><?php echo sr_e((string) $check['label']); ?></strong>
                        <p><?php echo sr_e((string) $check['message']); ?></p>
                        <?php if ((string) ($check['guide'] ?? '') !== '') { ?>
                            <p class="sr-install-check-guide">
                                <span>조치</span>
                                <?php echo sr_e((string) $check['guide']); ?>
                            </p>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <div class="sr-install-permission-guide">
                <h3>쓰기 권한 설정 방법</h3>
                <p>
                    설치 전에 <code>config</code>와 <code>storage</code> 디렉터리가 있어야 하며, PHP가 이 두 디렉터리에 파일을 만들 수 있어야 합니다.
                    보통 호스팅 파일 관리자나 FTP에서 권한을 <code>755</code>로 설정하면 됩니다.
                </p>
                <p>
                    계속 실패하면 설치하는 동안만 <code>775</code> 또는 <code>777</code>을 임시로 적용한 뒤,
                    설치가 끝나면 다시 <code>755</code>로 되돌리세요. 설치 후 생성되는 <code>config/config.php</code>는 <code>644</code> 권한을 권장합니다.
                </p>
                <p>
                    <code>config</code>에는 DB 비밀번호가 저장되고 <code>storage</code>에는 로그와 설치 잠금 파일이 저장됩니다.
                    두 디렉터리는 웹 주소로 직접 열리지 않도록 호스팅 패널이나 서버 설정에서 접근을 차단하세요.
                </p>
            </div>
        </section>

        <form method="post" action="<?php echo sr_e(sr_url('/')); ?>" class="sr-install-form">
            <?php echo sr_csrf_field(); ?>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">데이터베이스</p>
                        <h2>DB 연결 정보</h2>
                    </div>
                    <p>빈 DB 또는 Saanraan 전용 DB를 사용하세요. 테이블 prefix는 기본값 <code>sr_</code>를 권장합니다.</p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <label for="db_host">DB host</label>
                        <input id="db_host" type="text" name="db_host" value="<?php echo sr_e($values['db_host']); ?>" autocomplete="off" required>
                        <span class="sr-install-help">일반 웹호스팅은 보통 localhost를 사용합니다.</span>
                    </p>
                    <p>
                        <label for="db_name">DB name</label>
                        <input id="db_name" type="text" name="db_name" value="<?php echo sr_e($values['db_name']); ?>" autocomplete="off" required>
                    </p>
                    <p>
                        <label for="db_user">DB user</label>
                        <input id="db_user" type="text" name="db_user" value="<?php echo sr_e($values['db_user']); ?>" autocomplete="off" required>
                    </p>
                    <p>
                        <label for="db_password">DB password</label>
                        <input id="db_password" type="password" name="db_password" autocomplete="new-password">
                        <span class="sr-install-help">보안을 위해 오류 후에도 비밀번호는 다시 표시하지 않습니다.</span>
                    </p>
                    <p>
                        <label for="db_table_prefix">테이블 prefix</label>
                        <input id="db_table_prefix" type="text" name="db_table_prefix" value="<?php echo sr_e($values['db_table_prefix']); ?>" pattern="[a-z][a-z0-9]{0,20}_" required>
                        <span class="sr-install-help">기본값은 sr_입니다. 예: sr_, site1_</span>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">사이트</p>
                        <h2>기본 정보</h2>
                    </div>
                    <p>설치 후 일반 사이트 정보는 관리자 설정에서 다시 변경할 수 있습니다.</p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <label for="site_name">사이트 이름</label>
                        <input id="site_name" type="text" name="site_name" value="<?php echo sr_e($values['site_name']); ?>" required>
                    </p>
                    <p>
                        <label for="base_url">공개 기준 URL</label>
                        <input id="base_url" type="url" name="base_url" value="<?php echo sr_e($values['base_url']); ?>" placeholder="https://example.com">
                        <span class="sr-install-help">canonical, OG URL, 인증 메일 링크처럼 외부에 노출되는 절대 URL의 기준입니다. 운영 사이트는 HTTPS URL을 권장합니다.</span>
                    </p>
                    <p>
                        <label for="timezone">timezone</label>
                        <select id="timezone" name="timezone" required>
                            <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                                <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($timezoneOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </p>
                    <p>
                        <label for="default_locale">기본 locale</label>
                        <select id="default_locale" name="default_locale" required>
                            <?php foreach ($localeOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">관리자</p>
                        <h2>최초 관리자 계정</h2>
                    </div>
                    <p>이 계정에 소유자 권한이 부여됩니다.</p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <label for="admin_email">이메일</label>
                        <input id="admin_email" type="email" name="admin_email" value="<?php echo sr_e($values['admin_email']); ?>" autocomplete="email" required>
                    </p>
                    <p>
                        <label for="admin_login_id">로그인 아이디</label>
                        <input id="admin_login_id" type="text" name="admin_login_id" value="<?php echo sr_e($values['admin_login_id']); ?>" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username">
                        <span class="sr-install-help">비우면 이메일로 로그인하고, 입력하면 이 아이디로 로그인합니다. 예: admin, site_admin</span>
                    </p>
                    <p>
                        <label for="admin_password">비밀번호</label>
                        <input id="admin_password" type="password" name="admin_password" autocomplete="new-password" minlength="8" required>
                        <span class="sr-install-help">8자 이상 입력하세요.</span>
                    </p>
                    <p>
                        <label for="admin_password_confirm">비밀번호 확인</label>
                        <input id="admin_password_confirm" type="password" name="admin_password_confirm" autocomplete="new-password" minlength="8" required>
                    </p>
                    <p>
                        <label for="admin_display_name">표시 이름</label>
                        <input id="admin_display_name" type="text" name="admin_display_name" value="<?php echo sr_e($values['admin_display_name']); ?>" required>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">모듈</p>
                        <h2>설치할 기능</h2>
                    </div>
                    <p>선택하지 않은 기본 제공 모듈은 설치 후 관리자 모듈 화면에서 추가할 수 있습니다.</p>
                </div>

                <h3>필수 모듈</h3>
                <div class="sr-install-module-grid">
                    <?php foreach ($requiredModules as $moduleKey => $module) { ?>
                        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                        <div class="sr-install-module">
                            <span class="sr-install-status sr-install-status-<?php echo $moduleErrors === [] ? 'ok' : 'error'; ?>"><?php echo $moduleErrors === [] ? '필수' : '확인 필요'; ?></span>
                            <strong><?php echo sr_e((string) $module['label']); ?></strong>
                            <code><?php echo sr_e((string) $moduleKey); ?></code>
                            <p><?php echo sr_e((string) $module['description']); ?></p>
                            <?php if ($moduleErrors !== []) { ?>
                                <ul>
                                    <?php foreach ($moduleErrors as $moduleError) { ?>
                                        <li><?php echo sr_e((string) $moduleError); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <h3>선택 모듈</h3>
                <?php if ($optionalModules === []) { ?>
                    <p>현재 코드에는 설치 화면에서 선택할 수 있는 선택 모듈이 없습니다. 설치 후 필요한 모듈 zip을 <code>modules/{module_key}</code>에 업로드하고 관리자 모듈 화면에서 설치하세요.</p>
                <?php } else { ?>
                    <div class="sr-install-module-grid">
                        <?php foreach ($optionalModules as $moduleKey => $module) { ?>
                            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                            <label class="sr-install-module sr-install-module-option">
                                <span class="sr-install-module-title">
                                    <input
                                        type="checkbox"
                                        name="optional_modules[]"
                                        value="<?php echo sr_e((string) $moduleKey); ?>"
                                        <?php echo isset($selectedOptionalModuleMap[$moduleKey]) ? 'checked' : ''; ?>
                                        <?php echo $moduleErrors === [] ? '' : 'disabled'; ?>
                                    >
                                    <strong><?php echo sr_e((string) $module['label']); ?></strong>
                                </span>
                                <?php if ($moduleErrors !== []) { ?>
                                    <span class="sr-install-status sr-install-status-error">설치 불가</span>
                                <?php } ?>
                                <code><?php echo sr_e((string) $moduleKey); ?></code>
                                <p><?php echo sr_e((string) $module['description']); ?></p>
                                <?php if ($moduleErrors !== []) { ?>
                                    <ul>
                                        <?php foreach ($moduleErrors as $moduleError) { ?>
                                            <li><?php echo sr_e((string) $moduleError); ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </label>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>

            <div class="sr-install-actions">
                <p>설치하면 설정 파일과 DB 테이블을 생성하고, 완료 후 관리자 로그인 화면으로 이동합니다.</p>
                <button type="submit">설치 시작</button>
            </div>
        </form>
    </main>
</body>
</html>
