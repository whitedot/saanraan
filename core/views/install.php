<?php

$pageTitle = sr_t('ui.saanraan.878daf3c');
$selectedOptionalModuleMap = array_fill_keys($selectedOptionalModuleKeys, true);
$selectedMainPageOption = $mainPageOptions[$values['main_page_path']] ?? $mainPageOptions['/'];
$installStepLabels = [
    'environment' => '환경 확인',
    'basic' => '기본 정보',
    'account_modules' => '관리자와 모듈',
    'confirm' => '확인 및 설치',
];
$firstErrorStepKey = 'environment';
if ($installErrorSteps !== []) {
    $firstErrorStepKey = (string) array_key_first($installErrorSteps);
}
$initialInstallStepKey = array_key_exists($firstErrorStepKey, $installStepLabels) ? $firstErrorStepKey : 'environment';
$installMetaTitleBase = 's a a n r a a n 설치';
$initialInstallStepNumber = array_search($initialInstallStepKey, array_keys($installStepLabels), true);
$initialInstallStepNumber = is_int($initialInstallStepNumber) ? $initialInstallStepNumber + 1 : 1;
$seo = [
    'title' => '(' . (string) $initialInstallStepNumber . '/' . (string) count($installStepLabels) . ') ' . $installMetaTitleBase,
    'robots' => 'noindex, nofollow',
];
$hasEnvironmentBlockingError = is_file($configPath) !== is_file($installedLockPath);
foreach ($installChecks as $check) {
    if ((string) $check['status'] === 'error') {
        $hasEnvironmentBlockingError = true;
        break;
    }
}
$selectedOptionalModuleLabels = [];
foreach ($selectedOptionalModuleKeys as $moduleKey) {
    if (isset($optionalModules[$moduleKey])) {
        $selectedOptionalModuleLabels[] = (string) $optionalModules[$moduleKey]['label'];
    }
}
$selectedAutoFoundationModuleLabels = [];
foreach (($selectedAutoFoundationModuleKeys ?? []) as $moduleKey) {
    if (isset($foundationModules[$moduleKey])) {
        $selectedAutoFoundationModuleLabels[] = (string) $foundationModules[$moduleKey]['label'] . ' (자동)';
    }
}
$selectedOptionalModuleLabels = array_values(array_unique(array_merge($selectedOptionalModuleLabels, $selectedAutoFoundationModuleLabels)));
?>
<!doctype html>
<html lang="ko" data-color-scheme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, null); ?>
    <?php echo sr_stylesheet_tag(['/assets/install.css'], null, ['style_profile' => 'install']); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
</head>
<body class="sr-install-page">
    <main class="sr-install-shell" data-install-current-step="<?php echo sr_e($initialInstallStepKey); ?>">
        <section class="sr-install-intro">
            <div>
                <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.settings.7abc12e1')); ?></p>
                <h1><?php echo sr_e($pageTitle); ?></h1>
                <p><?php echo sr_e(sr_t('ui.saanraan.db.admin.settings.c039e13f')); ?></p>
            </div>
            <ol class="sr-install-steps" aria-label="<?php echo sr_e(sr_t('ui.text.4421f64e')); ?>">
                <?php foreach ($installStepLabels as $stepKey => $stepLabel) { ?>
                    <?php $stepHasErrors = !empty($installErrorSteps[$stepKey]); ?>
                    <li data-install-step-indicator="<?php echo sr_e($stepKey); ?>"<?php echo $stepHasErrors ? ' data-install-step-error="1"' : ''; ?>>
                        <button type="button" data-install-step-target="<?php echo sr_e($stepKey); ?>">
                            <span class="sr-install-step-number"></span>
                            <span>
                                <strong><?php echo sr_e($stepLabel); ?></strong>
                                <small data-install-step-state><?php echo $stepHasErrors ? '오류 확인 필요' : '대기'; ?></small>
                            </span>
                        </button>
                    </li>
                <?php } ?>
            </ol>
        </section>

        <?php if ($previousInstallFailure !== null) { ?>
            <section class="sr-install-alert sr-install-alert-warning">
                <h2><?php echo sr_e(sr_t('ui.text.68ef21c9')); ?></h2>
                <p>
                    <?php echo sr_e(sr_t('ui.text.33ba7430')); ?>
                    <code><?php echo sr_e($previousInstallFailure['stage']); ?></code>
                    <?php if ($previousInstallFailure['recorded_at'] !== '') { ?>
                        <span><?php echo sr_e(sr_t('ui.text.2ca70229')); ?> <?php echo sr_e($previousInstallFailure['recorded_at']); ?></span>
                    <?php } ?>
                </p>
                <p>
                    <?php echo sr_e(sr_t('ui.config.afa3ac1d')); ?>
                    <?php echo $previousInstallFailure['config_written'] ? sr_t('ui.text.2eb73fba') : sr_t('ui.text.4c490f1c'); ?><?php echo sr_e(sr_t('ui.text.43d039ec')); ?>
                    <?php echo $previousInstallFailure['installed_lock_written'] ? sr_t('ui.text.2eb73fba') : sr_t('ui.text.4c490f1c'); ?>
                </p>
                <?php if ((string) ($previousInstallFailure['message'] ?? '') !== '') { ?>
                    <p><?php echo sr_e(sr_t('ui.text.1d0c7eec')); ?> <?php echo sr_e((string) $previousInstallFailure['message']); ?></p>
                <?php } ?>
                <ul>
                    <li><code>storage/install-failed.json</code><?php echo sr_e(sr_t('ui.text.474a3734')); ?></li>
                    <li><?php echo sr_e(sr_t('ui.db.status.ebbe75db')); ?></li>
                    <li><code>storage/installed.lock</code><?php echo sr_e(sr_t('ui.status.2ed4011b')); ?></li>
                </ul>
            </section>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <section class="sr-install-alert sr-install-alert-error" data-install-error-summary>
                <h2><?php echo sr_e(sr_t('ui.text.84dd6e38')); ?></h2>
                <ul>
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <?php if ($installWarnings !== []) { ?>
            <section class="sr-install-alert sr-install-alert-warning">
                <h2><?php echo sr_e(sr_t('ui.text.7aad277c')); ?></h2>
                <ul>
                    <?php foreach ($installWarnings as $warning) { ?>
                        <li><?php echo sr_e($warning); ?></li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/')); ?>" class="sr-install-form" data-install-form>
            <?php echo sr_csrf_field(); ?>

            <section class="sr-install-panel sr-install-step" data-install-step="environment" data-install-step-blocked="<?php echo $hasEnvironmentBlockingError ? '1' : '0'; ?>">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">1단계</p>
                        <h2>환경 확인</h2>
                    </div>
                    <p>설치 전에 PHP, DB 확장, 파일 권한, 현재 접속 URL을 확인합니다.</p>
                </div>

                <?php if (!empty($installErrorSteps['environment'])) { ?>
                    <div class="sr-install-step-errors">
                        <strong>이 단계에서 확인할 항목</strong>
                        <ul>
                            <?php foreach ($installErrorSteps['environment'] as $error) { ?>
                                <li><?php echo sr_e($error); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <div class="sr-install-check-grid">
                    <?php foreach ($installChecks as $check) { ?>
                        <div class="sr-install-check">
                            <span class="sr-install-status sr-install-status-<?php echo sr_e((string) $check['status']); ?>">
                                <?php echo ((string) $check['status'] === 'ok') ? sr_t('ui.text.f2c10bf5') : (((string) $check['status'] === 'warning') ? sr_t('ui.text.acc90fbf') : sr_t('ui.text.3ceb8b2c')); ?>
                            </span>
                            <strong><?php echo sr_e((string) $check['label']); ?></strong>
                            <p><?php echo sr_e((string) $check['message']); ?></p>
                            <?php if ((string) ($check['guide'] ?? '') !== '') { ?>
                                <p class="sr-install-check-guide">
                                    <span><?php echo sr_e(sr_t('ui.text.6530a534')); ?></span>
                                    <?php echo sr_e((string) $check['guide']); ?>
                                </p>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <details class="sr-install-permission-guide">
                    <summary><?php echo sr_e(sr_t('ui.settings.848e94fa')); ?></summary>
                    <p>
                        <?php echo sr_e(sr_t('ui.text.91850b91')); ?> <code>config</code><?php echo sr_e(sr_t('ui.text.d536e625')); ?> <code>storage</code> <?php echo sr_e(sr_t('ui.admin.09db1e62')); ?> <code>755</code><?php echo sr_e(sr_t('ui.settings.9a99606d')); ?>
                    </p>
                    <p>
                        <?php echo sr_e(sr_t('ui.text.6b72e57d')); ?> <code>775</code> <?php echo sr_e(sr_t('ui.text.82d047b9')); ?> <code>777</code><?php echo sr_e(sr_t('ui.text.df2c2877')); ?> <code>755</code><?php echo sr_e(sr_t('ui.text.9083af79')); ?> <code>config/config.php</code><?php echo sr_e(sr_t('ui.text.6ef5924e')); ?> <code>600</code> <?php echo sr_e(sr_t('ui.text.2f925894')); ?>
                    </p>
                    <p>
                        <code>config</code><?php echo sr_e(sr_t('ui.db.password.save.1dd78ffa')); ?> <code>storage</code><?php echo sr_e(sr_t('ui.save.settings.98d5defa')); ?>
                    </p>
                </details>

                <div class="sr-install-step-actions sr-install-step-action-js">
                    <span class="sr-install-step-note" data-install-blocked-message<?php echo $hasEnvironmentBlockingError ? '' : ' hidden'; ?>>환경 오류를 해결한 뒤 다음 단계로 이동할 수 있습니다.</span>
                    <button type="button" data-install-next<?php echo $hasEnvironmentBlockingError ? ' disabled' : ''; ?>>다음</button>
                </div>
            </section>

            <section class="sr-install-panel sr-install-step" data-install-step="basic">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">2단계</p>
                        <h2>기본 정보</h2>
                    </div>
                    <p>DB 연결 정보와 사이트 기본값을 한 번에 입력합니다.</p>
                </div>

                <?php if (!empty($installErrorSteps['basic'])) { ?>
                    <div class="sr-install-step-errors">
                        <strong>이 단계에서 확인할 항목</strong>
                        <ul>
                            <?php foreach ($installErrorSteps['basic'] as $error) { ?>
                                <li><?php echo sr_e($error); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <div class="sr-install-subsection">
                    <h3><?php echo sr_e(sr_t('ui.db.1ec96d5d')); ?></h3>
                    <p class="sr-install-subsection-help"><?php echo sr_e(sr_t('ui.db.saanraan.active.af3cd57e')); ?> <code>sr_</code><?php echo sr_e(sr_t('ui.text.66341384')); ?></p>
                    <div class="sr-install-field-grid">
                        <p>
                            <label for="db_host">DB 호스트 <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="db_host" type="text" name="db_host" value="<?php echo sr_e($values['db_host']); ?>" autocomplete="off" required data-summary-source="db_host">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.active.d13269d0')); ?></span>
                        </p>
                        <p>
                            <label for="db_name">DB 이름 <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="db_name" type="text" name="db_name" value="<?php echo sr_e($values['db_name']); ?>" autocomplete="off" required data-summary-source="db_name">
                        </p>
                        <p>
                            <label for="db_user">DB 사용자 <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="db_user" type="text" name="db_user" value="<?php echo sr_e($values['db_user']); ?>" autocomplete="off" required data-summary-source="db_user">
                        </p>
                        <p>
                            <label for="db_password">DB 비밀번호</label>
                            <input id="db_password" type="password" name="db_password" autocomplete="new-password">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.password.215ab9d4')); ?></span>
                        </p>
                        <p>
                            <label for="db_table_prefix"><?php echo sr_e(sr_t('ui.prefix.49bc3888')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="db_table_prefix" type="text" name="db_table_prefix" value="<?php echo sr_e($values['db_table_prefix']); ?>" pattern="[a-z][a-z0-9]{0,20}_" inputmode="latin" autocapitalize="none" spellcheck="false" required data-install-table-prefix-input data-summary-source="db_table_prefix">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.sr.sr.site1.c9aaa2e0')); ?></span>
                        </p>
                    </div>
                </div>

                <div class="sr-install-subsection">
                    <h3><?php echo sr_e(sr_t('ui.text.601b9971')); ?></h3>
                    <p class="sr-install-subsection-help"><?php echo sr_e(sr_t('ui.admin.settings.ca628f95')); ?></p>
                    <div class="sr-install-field-grid">
                        <p>
                            <label for="site_name"><?php echo sr_e(sr_t('ui.name.51f4c6af')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="site_name" type="text" name="site_name" value="<?php echo sr_e($values['site_name']); ?>" required data-summary-source="site_name">
                        </p>
                        <p>
                            <label for="base_url"><?php echo sr_e(sr_t('ui.url.3f618a18')); ?></label>
                            <input id="base_url" type="url" name="base_url" value="<?php echo sr_e($values['base_url']); ?>" placeholder="https://example.com" data-summary-source="base_url">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.canonical.og.url.https.e51da311')); ?></span>
                        </p>
                        <p>
                            <label for="timezone">timezone <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <select id="timezone" name="timezone" required data-summary-source="timezone">
                                <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                                    <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                                        <?php echo sr_e($timezoneOption); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </p>
                        <p>
                            <label for="default_locale"><?php echo sr_e(sr_t('ui.locale.c7cd39b4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <select id="default_locale" name="default_locale" required data-summary-source="default_locale">
                                <?php foreach ($localeOptions as $localeOption) { ?>
                                    <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                                        <?php echo sr_e($localeOption); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </p>
                        <p>
                            <label for="default_currency">기본 통화 <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <select id="default_currency" name="default_currency" required data-summary-source="default_currency">
                                <?php foreach (array_keys(sr_known_currency_min_units()) as $currencyCode) { ?>
                                    <option value="<?php echo sr_e($currencyCode); ?>"<?php echo $values['default_currency'] === $currencyCode ? ' selected' : ''; ?>>
                                        <?php echo sr_e($currencyCode); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <span class="sr-install-help">설치 후 일반 설정에서는 바꿀 수 없습니다. 기존 가격과 로그는 이 값으로 변환되지 않습니다.</span>
                        </p>
                        <p>
                            <span class="sr-install-field-label"><?php echo sr_e(sr_t('ui.text.214b5fb8')); ?></span>
                            <input type="hidden" name="main_page_path" value="/">
                            <span class="sr-install-home-default" data-install-main-page-summary><?php echo sr_e((string) $selectedMainPageOption['label']); ?> · <?php echo sr_e((string) $selectedMainPageOption['path']); ?></span>
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.community.settings.12c9fe17')); ?></span>
                        </p>
                    </div>
                </div>

                <div class="sr-install-step-actions sr-install-step-action-js">
                    <button type="button" class="sr-install-secondary-button" data-install-prev>이전</button>
                    <button type="button" data-install-next>다음</button>
                </div>
            </section>

            <section class="sr-install-panel sr-install-step" data-install-step="account_modules">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">3단계</p>
                        <h2>관리자와 모듈</h2>
                    </div>
                    <p>최초 매니저 계정과 설치할 기본 모듈 구성을 정합니다.</p>
                </div>

                <?php if (!empty($installErrorSteps['account_modules'])) { ?>
                    <div class="sr-install-step-errors">
                        <strong>이 단계에서 확인할 항목</strong>
                        <ul>
                            <?php foreach ($installErrorSteps['account_modules'] as $error) { ?>
                                <li><?php echo sr_e($error); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <div class="sr-install-subsection">
                    <h3><?php echo sr_e(sr_t('ui.admin.7954926f')); ?></h3>
                    <p class="sr-install-subsection-help"><?php echo sr_e(sr_t('ui.text.f039b723')); ?></p>
                    <div class="sr-install-field-grid">
                        <p>
                            <span class="sr-install-field-label"><?php echo sr_e(sr_t('ui.login.2a15bdbd')); ?></span>
                            <strong><?php echo sr_e(sr_t('ui.email.login.d1f22b60')); ?></strong>
                            <input type="hidden" name="member_login_identifier" value="both">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.email.login.login.login.44f3662f')); ?></span>
                        </p>
                        <p>
                            <label for="admin_email"><?php echo sr_e(sr_t('ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="admin_email" type="email" name="admin_email" value="<?php echo sr_e($values['admin_email']); ?>" autocomplete="email" required data-summary-source="admin_email">
                        </p>
                        <p>
                            <label for="admin_login_id"><?php echo sr_e(sr_t('ui.login.0cdb28b5')); ?></label>
                            <input id="admin_login_id" type="text" name="admin_login_id" value="<?php echo sr_e($values['admin_login_id']); ?>" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="username" data-install-key-input data-summary-source="admin_login_id">
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.select.email.login.active.admin.9a22f604')); ?></span>
                        </p>
                        <p>
                            <label for="admin_password"><?php echo sr_e(sr_t('ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="admin_password" type="password" name="admin_password" autocomplete="new-password" minlength="8" required>
                            <span class="sr-install-help"><?php echo sr_e(sr_t('ui.text.1e3d8fb2')); ?></span>
                        </p>
                        <p>
                            <label for="admin_password_confirm"><?php echo sr_e(sr_t('ui.password.61081c91')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="admin_password_confirm" type="password" name="admin_password_confirm" autocomplete="new-password" minlength="8" required>
                        </p>
                        <p>
                            <label for="admin_display_name"><?php echo sr_e(sr_t('ui.name.be0cd9bd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                            <input id="admin_display_name" type="text" name="admin_display_name" value="<?php echo sr_e($values['admin_display_name']); ?>" required data-summary-source="admin_display_name">
                        </p>
                    </div>
                </div>

                <div class="sr-install-subsection">
                    <h3><?php echo sr_e(sr_t('ui.required.9b1c157b')); ?></h3>
                    <div class="sr-install-module-grid">
                        <?php foreach ($requiredModules as $moduleKey => $module) { ?>
                            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                            <div class="sr-install-module">
                                <span class="sr-install-status sr-install-status-<?php echo $moduleErrors === [] ? 'ok' : 'error'; ?>"><?php echo $moduleErrors === [] ? sr_t('ui.required.9825053d') : sr_t('ui.text.84dd6e38'); ?></span>
                                <strong><?php echo sr_e((string) $module['label']); ?></strong>
                                <small>Key: <?php echo sr_e((string) $moduleKey); ?></small>
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

                    <h3><?php echo sr_e(sr_t('ui.select.d37edab7')); ?></h3>
                    <?php if ($optionalModules === []) { ?>
                        <p>설치 가능한 추가 모듈을 찾지 못했습니다. 모듈 폴더와 설정 파일을 확인하세요.</p>
                    <?php } else { ?>
                        <label class="sr-install-module-select-all" for="optional_modules_select_all">
                            <input id="optional_modules_select_all" type="checkbox" class="form-checkbox" data-install-module-select-all>
                            <span>
                                <strong>전체 선택</strong>
                                <small>선택 가능한 모듈과 플러그인을 모두 설치 대상으로 표시합니다.</small>
                            </span>
                        </label>
                        <div class="sr-install-module-grid">
                            <?php foreach ($optionalModules as $moduleKey => $module) { ?>
                                <?php $moduleOwnErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                                <?php $moduleFoundationErrors = isset($module['foundation_dependency_errors']) && is_array($module['foundation_dependency_errors']) ? $module['foundation_dependency_errors'] : []; ?>
                                <?php $moduleErrors = array_values(array_unique(array_merge(array_map('strval', $moduleOwnErrors), array_map('strval', $moduleFoundationErrors)))); ?>
                                <?php $moduleFoundationLabels = isset($module['foundation_dependency_labels']) && is_array($module['foundation_dependency_labels']) ? array_values(array_map('strval', $module['foundation_dependency_labels'])) : []; ?>
                                <?php $moduleMainPageOption = $mainPageOptionsByModule[(string) $moduleKey] ?? null; ?>
                                <?php $moduleCheckboxId = 'optional_module_' . preg_replace('/[^a-z0-9_]/', '_', (string) $moduleKey); ?>
                                <?php $moduleHomeCheckboxId = 'main_page_module_' . preg_replace('/[^a-z0-9_]/', '_', (string) $moduleKey); ?>
                                <div class="sr-install-module sr-install-module-option">
                                    <span class="sr-install-module-title">
                                        <input
                                            id="<?php echo sr_e($moduleCheckboxId); ?>"
                                            type="checkbox"
                                            name="optional_modules[]"
                                            value="<?php echo sr_e((string) $moduleKey); ?>"
                                            class="form-checkbox"
                                            data-install-module-option
                                            data-install-module-label="<?php echo sr_e((string) $module['label']); ?>"
                                            data-install-foundation-labels="<?php echo sr_e(sr_js_json_encode($moduleFoundationLabels)); ?>"
                                            <?php echo isset($selectedOptionalModuleMap[$moduleKey]) ? 'checked' : ''; ?>
                                            <?php echo $moduleErrors === [] ? '' : 'disabled'; ?>
                                        >
                                        <label for="<?php echo sr_e($moduleCheckboxId); ?>"><strong><?php echo sr_e((string) $module['label']); ?></strong></label>
                                    </span>
                                    <?php if ($moduleErrors !== []) { ?>
                                        <span class="sr-install-status sr-install-status-error"><?php echo sr_e(sr_t('ui.text.b4052951')); ?></span>
                                    <?php } ?>
                                    <small>Key: <?php echo sr_e((string) $moduleKey); ?></small>
                                    <p><?php echo sr_e((string) $module['description']); ?></p>
                                    <?php if ($moduleFoundationLabels !== []) { ?>
                                        <p class="sr-install-help">함께 설치됨: <?php echo sr_e(implode(', ', $moduleFoundationLabels)); ?></p>
                                    <?php } ?>
                                    <?php if (is_array($moduleMainPageOption) && $moduleErrors === []) { ?>
                                        <label class="sr-install-main-page-option" for="<?php echo sr_e($moduleHomeCheckboxId); ?>">
                                            <input
                                                id="<?php echo sr_e($moduleHomeCheckboxId); ?>"
                                                type="checkbox"
                                                name="main_page_candidate_path"
                                                value="<?php echo sr_e((string) $moduleMainPageOption['path']); ?>"
                                                class="form-checkbox"
                                                data-sr-install-main-page
                                                data-sr-install-main-page-label="<?php echo sr_e((string) $moduleMainPageOption['label']); ?>"
                                                data-sr-install-module-checkbox="<?php echo sr_e($moduleCheckboxId); ?>"
                                                <?php echo $values['main_page_path'] === (string) $moduleMainPageOption['path'] ? 'checked' : ''; ?>
                                            >
                                            <span>
                                                <?php echo sr_e(sr_t('ui.settings.a81574ca')); ?>
                                                <small><?php echo sr_e((string) $moduleMainPageOption['path']); ?></small>
                                            </span>
                                        </label>
                                    <?php } ?>
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
                    <?php } ?>
                </div>

                <div class="sr-install-step-actions sr-install-step-action-js">
                    <button type="button" class="sr-install-secondary-button" data-install-prev>이전</button>
                    <button type="button" data-install-next>다음</button>
                </div>
            </section>

            <section class="sr-install-panel sr-install-step" data-install-step="confirm">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker">4단계</p>
                        <h2>확인 및 설치</h2>
                    </div>
                    <p>입력값과 선택 모듈을 확인한 뒤 설치를 시작합니다.</p>
                </div>

                <?php if (!empty($installErrorSteps['confirm'])) { ?>
                    <div class="sr-install-step-errors">
                        <strong>이 단계에서 확인할 항목</strong>
                        <ul>
                            <?php foreach ($installErrorSteps['confirm'] as $error) { ?>
                                <li><?php echo sr_e($error); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <div class="sr-install-summary-grid">
                    <div>
                        <h3>DB</h3>
                        <dl>
                            <dt>호스트</dt>
                            <dd data-summary-target="db_host"><?php echo sr_e($values['db_host']); ?></dd>
                            <dt>데이터베이스명</dt>
                            <dd data-summary-target="db_name"><?php echo sr_e($values['db_name'] !== '' ? $values['db_name'] : '-'); ?></dd>
                            <dt>사용자</dt>
                            <dd data-summary-target="db_user"><?php echo sr_e($values['db_user'] !== '' ? $values['db_user'] : '-'); ?></dd>
                            <dt>테이블 접두어</dt>
                            <dd data-summary-target="db_table_prefix"><?php echo sr_e($values['db_table_prefix']); ?></dd>
                        </dl>
                    </div>
                    <div>
                        <h3>사이트</h3>
                        <dl>
                            <dt>이름</dt>
                            <dd data-summary-target="site_name"><?php echo sr_e($values['site_name']); ?></dd>
                            <dt>URL</dt>
                            <dd data-summary-target="base_url"><?php echo sr_e($values['base_url'] !== '' ? $values['base_url'] : '-'); ?></dd>
                            <dt>시간대</dt>
                            <dd data-summary-target="timezone"><?php echo sr_e($values['timezone']); ?></dd>
                            <dt>언어</dt>
                            <dd data-summary-target="default_locale"><?php echo sr_e($values['default_locale']); ?></dd>
                            <dt>통화</dt>
                            <dd data-summary-target="default_currency"><?php echo sr_e($values['default_currency']); ?></dd>
                            <dt>초기화면</dt>
                            <dd data-summary-target="main_page_path"><?php echo sr_e((string) $selectedMainPageOption['label']); ?> · <?php echo sr_e((string) $selectedMainPageOption['path']); ?></dd>
                        </dl>
                    </div>
                    <div>
                        <h3>관리자</h3>
                        <dl>
                            <dt>이메일</dt>
                            <dd data-summary-target="admin_email"><?php echo sr_e($values['admin_email'] !== '' ? $values['admin_email'] : '-'); ?></dd>
                            <dt>로그인 아이디</dt>
                            <dd data-summary-target="admin_login_id"><?php echo sr_e($values['admin_login_id'] !== '' ? $values['admin_login_id'] : '-'); ?></dd>
                            <dt>이름</dt>
                            <dd data-summary-target="admin_display_name"><?php echo sr_e($values['admin_display_name']); ?></dd>
                        </dl>
                    </div>
                    <div>
                        <h3>모듈</h3>
                        <dl>
                            <dt>필수</dt>
                            <dd><?php echo sr_e(implode(', ', array_map(static function (array $module): string { return (string) $module['label']; }, $requiredModules))); ?></dd>
                            <dt>선택</dt>
                            <dd data-summary-target="optional_modules"><?php echo sr_e($selectedOptionalModuleLabels !== [] ? implode(', ', $selectedOptionalModuleLabels) : '선택 없음'); ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="sr-install-progress" data-install-progress role="status" aria-live="polite" hidden>
                    <strong>설치 중입니다</strong>
                    <span class="sr-install-progress-message">설치 요청을 보내는 중입니다. 완료되면 관리자 로그인 화면으로 이동합니다.</span>
                    <span class="sr-install-progress-bar" aria-hidden="true"><span></span></span>
                    <ol>
                        <li>설정 파일 작성 준비</li>
                        <li>DB 연결 및 코어 스키마 설치</li>
                        <li>필수 모듈 설치: member → admin → policy_documents → privacy</li>
                        <li>선택 모듈과 필요한 기반 모듈 설치</li>
                        <li>사이트 설정 저장</li>
                        <li>관리자 계정과 매니저 권한 생성</li>
                        <li>설치 lock 작성</li>
                        <li>완료 후 관리자 로그인 화면으로 이동</li>
                    </ol>
                </div>

                <div class="sr-install-actions">
                    <p><?php echo sr_e(sr_t('ui.settings.db.admin.login.e8b89000')); ?></p>
                    <div class="sr-install-final-actions">
                        <button type="button" class="sr-install-secondary-button sr-install-step-action-js" data-install-prev>이전</button>
                        <button type="submit" data-install-submit><?php echo sr_e(sr_t('ui.text.99d5ac5c')); ?></button>
                    </div>
                </div>
            </section>
        </form>
    </main>
    <script>
        (function () {
            document.body.classList.add('sr-install-enhanced');

            function restrictedInputMessage(input) {
                return input.getAttribute('data-validation-message') || '영문, 숫자, 밑줄만 입력 가능합니다.';
            }

            function clearRestrictedInputValidation(input) {
                if (!input || input.getAttribute('data-restricted-input-validation-active') !== '1') {
                    return;
                }

                input.removeAttribute('data-restricted-input-validation-active');
                if (typeof input.setCustomValidity === 'function') {
                    input.setCustomValidity('');
                }
            }

            function showRestrictedInputValidation(input) {
                if (!input || typeof input.setCustomValidity !== 'function') {
                    return;
                }

                window.clearTimeout(input._installRestrictedInputValidationTimer);
                input.setAttribute('data-restricted-input-validation-active', '1');
                input.setCustomValidity(restrictedInputMessage(input));
                if (typeof input.reportValidity === 'function') {
                    input.reportValidity();
                }
                input._installRestrictedInputValidationTimer = window.setTimeout(function () {
                    clearRestrictedInputValidation(input);
                }, 1800);
            }

            function hasRestrictedInputBlockedData(value) {
                return /[^a-zA-Z0-9_]/.test(String(value || ''));
            }

            function syncRestrictedInput(input, normalizeValue, reportBlockedInput) {
                if (!input || input.readOnly || input.disabled) {
                    return;
                }

                var previousValue = input.value;
                var nextValue = normalizeValue(previousValue);
                if (previousValue === nextValue) {
                    clearRestrictedInputValidation(input);
                    return;
                }

                var selectionStart = input.selectionStart;
                var beforeSelection = typeof selectionStart === 'number' ? previousValue.slice(0, selectionStart) : '';
                var nextSelectionStart = typeof selectionStart === 'number' ? normalizeValue(beforeSelection).length : nextValue.length;
                input.value = nextValue;
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(nextSelectionStart, nextSelectionStart);
                }
                if (reportBlockedInput && hasRestrictedInputBlockedData(previousValue)) {
                    showRestrictedInputValidation(input);
                }
            }

            function normalizeKeyValue(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '');
            }

            function normalizeTablePrefixValue(value) {
                var nextValue = String(value || '').toLowerCase().replace(/[^a-z0-9_]/g, '');
                var hasTrailingUnderscore = /_$/.test(nextValue);
                nextValue = nextValue.replace(/_/g, '').replace(/^[^a-z]+/, '');
                return nextValue + (hasTrailingUnderscore ? '_' : '');
            }

            var stepOrder = ['environment', 'basic', 'account_modules', 'confirm'];
            var installMetaTitleBase = <?php echo sr_js_json_encode($installMetaTitleBase); ?>;
            var shell = document.querySelector('[data-install-current-step]');
            var form = document.querySelector('[data-install-form]');
            var currentStep = shell ? shell.getAttribute('data-install-current-step') : 'environment';

            function stepPanel(stepKey) {
                return document.querySelector('[data-install-step="' + stepKey + '"]');
            }

            function stepIndex(stepKey) {
                var index = stepOrder.indexOf(stepKey);
                return index === -1 ? 0 : index;
            }

            function updateMetaTitle(stepKey) {
                document.title = '(' + String(stepIndex(stepKey) + 1) + '/' + String(stepOrder.length) + ') ' + installMetaTitleBase;
            }

            function setStep(stepKey, shouldScroll) {
                if (stepOrder.indexOf(stepKey) === -1) {
                    stepKey = 'environment';
                }
                currentStep = stepKey;
                var activeIndex = stepIndex(stepKey);
                updateMetaTitle(stepKey);
                document.querySelectorAll('[data-install-step]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-install-step') !== stepKey;
                });
                document.querySelectorAll('[data-install-step-indicator]').forEach(function (indicator) {
                    var indicatorStep = indicator.getAttribute('data-install-step-indicator');
                    var indicatorIndex = stepIndex(indicatorStep);
                    var button = indicator.querySelector('button');
                    var state = indicator.querySelector('[data-install-step-state]');
                    indicator.classList.toggle('is-current', indicatorStep === stepKey);
                    indicator.classList.toggle('is-complete', indicatorIndex < activeIndex);
                    indicator.classList.toggle('is-waiting', indicatorIndex > activeIndex);
                    if (button) {
                        button.setAttribute('aria-current', indicatorStep === stepKey ? 'step' : 'false');
                    }
                    if (state) {
                        if (indicator.getAttribute('data-install-step-error') === '1') {
                            state.textContent = '오류 확인 필요';
                        } else if (indicatorStep === stepKey) {
                            state.textContent = '현재 단계';
                        } else if (indicatorIndex < activeIndex) {
                            state.textContent = '완료';
                        } else {
                            state.textContent = '대기';
                        }
                    }
                });
                updateSummary();
                var activePanel = stepPanel(stepKey);
                if (shouldScroll && activePanel) {
                    activePanel.scrollIntoView({block: 'start'});
                }
            }

            function inputStep(input) {
                var panel = input.closest('[data-install-step]');
                return panel ? panel.getAttribute('data-install-step') : 'environment';
            }

            function openFirstInvalidStep() {
                if (!form) {
                    return false;
                }
                var invalid = form.querySelector(':invalid');
                if (!invalid) {
                    return false;
                }
                setStep(inputStep(invalid), true);
                window.setTimeout(function () {
                    if (typeof invalid.reportValidity === 'function') {
                        invalid.reportValidity();
                    } else {
                        invalid.focus();
                    }
                }, 40);
                return true;
            }

            function updateTextSummary(key, value) {
                document.querySelectorAll('[data-summary-target="' + key + '"]').forEach(function (target) {
                    target.textContent = value || '-';
                });
            }

            function updateSummary() {
                document.querySelectorAll('[data-summary-source]').forEach(function (input) {
                    updateTextSummary(input.getAttribute('data-summary-source'), input.value);
                });

                var checkedHome = document.querySelector('[data-sr-install-main-page]:checked');
                if (checkedHome) {
                    var homeLabel = checkedHome.getAttribute('data-sr-install-main-page-label') || '선택 모듈';
                    updateTextSummary('main_page_path', homeLabel + ' · ' + checkedHome.value);
                    document.querySelectorAll('[data-install-main-page-summary]').forEach(function (target) {
                        target.textContent = homeLabel + ' · ' + checkedHome.value;
                    });
                } else {
                    updateTextSummary('main_page_path', '기본 홈 · /');
                    document.querySelectorAll('[data-install-main-page-summary]').forEach(function (target) {
                        target.textContent = '기본 홈 · /';
                    });
                }

                var moduleLabels = [];
                var foundationLabels = [];
                document.querySelectorAll('[data-install-module-option]:checked').forEach(function (input) {
                    moduleLabels.push(input.getAttribute('data-install-module-label') || input.value);
                    try {
                        JSON.parse(input.getAttribute('data-install-foundation-labels') || '[]').forEach(function (label) {
                            if (label && foundationLabels.indexOf(label + ' (자동)') === -1) {
                                foundationLabels.push(label + ' (자동)');
                            }
                        });
                    } catch (error) {
                    }
                });
                foundationLabels.forEach(function (label) {
                    if (moduleLabels.indexOf(label) === -1) {
                        moduleLabels.push(label);
                    }
                });
                updateTextSummary('optional_modules', moduleLabels.length ? moduleLabels.join(', ') : '선택 없음');
            }

            document.querySelectorAll('[data-install-key-input]').forEach(function (input) {
                syncRestrictedInput(input, normalizeKeyValue);
                input.addEventListener('beforeinput', function (event) {
                    if (!String(event.inputType || '').startsWith('insert') || !event.data || !hasRestrictedInputBlockedData(event.data)) {
                        return;
                    }
                    event.preventDefault();
                    showRestrictedInputValidation(input);
                });
                input.addEventListener('input', function () {
                    syncRestrictedInput(input, normalizeKeyValue, true);
                    updateSummary();
                });
            });

            document.querySelectorAll('[data-install-table-prefix-input]').forEach(function (input) {
                syncRestrictedInput(input, normalizeTablePrefixValue);
                input.addEventListener('beforeinput', function (event) {
                    if (!String(event.inputType || '').startsWith('insert') || !event.data || !hasRestrictedInputBlockedData(event.data)) {
                        return;
                    }
                    event.preventDefault();
                    showRestrictedInputValidation(input);
                });
                input.addEventListener('input', function () {
                    syncRestrictedInput(input, normalizeTablePrefixValue, true);
                    updateSummary();
                });
            });

            document.querySelectorAll('[data-summary-source]').forEach(function (input) {
                input.addEventListener('input', updateSummary);
                input.addEventListener('change', updateSummary);
            });

            var mainPageInputs = document.querySelectorAll('[data-sr-install-main-page]');
            var moduleSelectAllInput = document.querySelector('[data-install-module-select-all]');

            function moduleOptionInputs() {
                return Array.prototype.slice.call(document.querySelectorAll('[data-install-module-option]'));
            }

            function syncModuleSelectAll() {
                if (!moduleSelectAllInput) {
                    return;
                }

                var enabledInputs = moduleOptionInputs().filter(function (input) {
                    return !input.disabled;
                });
                var checkedInputs = enabledInputs.filter(function (input) {
                    return input.checked;
                });
                moduleSelectAllInput.checked = enabledInputs.length > 0 && checkedInputs.length === enabledInputs.length;
                moduleSelectAllInput.indeterminate = checkedInputs.length > 0 && checkedInputs.length < enabledInputs.length;
                moduleSelectAllInput.disabled = enabledInputs.length === 0;
            }

            mainPageInputs.forEach(function (input) {
                var moduleCheckboxId = input.getAttribute('data-sr-install-module-checkbox');
                var moduleCheckbox = moduleCheckboxId ? document.getElementById(moduleCheckboxId) : null;
                if (input.checked && moduleCheckbox) {
                    moduleCheckbox.checked = true;
                }
                if (moduleCheckbox) {
                    moduleCheckbox.addEventListener('change', function () {
                        if (!moduleCheckbox.checked) {
                            input.checked = false;
                        }
                        syncModuleSelectAll();
                        updateSummary();
                    });
                }

                input.addEventListener('change', function () {
                    if (!input.checked) {
                        updateSummary();
                        return;
                    }

                    mainPageInputs.forEach(function (otherInput) {
                        if (otherInput !== input) {
                            otherInput.checked = false;
                        }
                    });

                    if (moduleCheckbox) {
                        moduleCheckbox.checked = true;
                    }
                    syncModuleSelectAll();
                    updateSummary();
                });
            });

            if (moduleSelectAllInput) {
                moduleSelectAllInput.addEventListener('change', function () {
                    var checked = moduleSelectAllInput.checked;
                    moduleOptionInputs().forEach(function (input) {
                        if (!input.disabled) {
                            input.checked = checked;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    syncModuleSelectAll();
                    updateSummary();
                });
            }

            moduleOptionInputs().forEach(function (input) {
                input.addEventListener('change', function () {
                    syncModuleSelectAll();
                    updateSummary();
                });
            });

            document.querySelectorAll('[data-install-step-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    setStep(button.getAttribute('data-install-step-target'), true);
                });
            });

            document.querySelectorAll('[data-install-next]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var activePanel = stepPanel(currentStep);
                    if (activePanel && activePanel.getAttribute('data-install-step-blocked') === '1') {
                        return;
                    }
                    var nextIndex = Math.min(stepIndex(currentStep) + 1, stepOrder.length - 1);
                    setStep(stepOrder[nextIndex], true);
                });
            });

            document.querySelectorAll('[data-install-prev]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var previousIndex = Math.max(stepIndex(currentStep) - 1, 0);
                    setStep(stepOrder[previousIndex], true);
                });
            });

            if (form) {
                var installSubmitting = false;
                form.addEventListener('submit', function (event) {
                    if (installSubmitting) {
                        return;
                    }

                    if (openFirstInvalidStep()) {
                        event.preventDefault();
                        return;
                    }

                    event.preventDefault();
                    installSubmitting = true;
                    setStep('confirm', false);

                    var submitButton = form.querySelector('[data-install-submit]');
                    var progress = form.querySelector('[data-install-progress]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = '설치 중';
                        submitButton.setAttribute('aria-busy', 'true');
                    }
                    if (progress) {
                        progress.hidden = false;
                        progress.scrollIntoView({block: 'nearest'});
                    }
                    form.querySelectorAll('button').forEach(function (button) {
                        button.disabled = true;
                    });
                    document.querySelectorAll('[data-install-step-indicator]').forEach(function (indicator) {
                        var state = indicator.querySelector('[data-install-step-state]');
                        if (indicator.getAttribute('data-install-step-indicator') === 'confirm' && state) {
                            state.textContent = '설치 중';
                        }
                    });
                    form.setAttribute('aria-busy', 'true');

                    window.requestAnimationFrame(function () {
                        window.setTimeout(function () {
                            if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
                                HTMLFormElement.prototype.submit.call(form);
                                return;
                            }
                            form.submit();
                        }, 60);
                    });
                });
            }

            setStep(currentStep, false);
            syncModuleSelectAll();
        }());
    </script>
</body>
</html>
