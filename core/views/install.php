<?php

$pageTitle = sr_t('ui.saanraan.878daf3c');
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
    <?php echo sr_material_icon_bootstrap_script(); ?>
</head>
<body class="sr-install-page">
    <main class="sr-install-shell">
        <section class="sr-install-intro">
            <div>
                <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.settings.7abc12e1')); ?></p>
                <h1><?php echo sr_e($pageTitle); ?></h1>
                <p><?php echo sr_e(sr_t('ui.saanraan.db.admin.settings.c039e13f')); ?></p>
            </div>
            <ol class="sr-install-steps" aria-label="<?php echo sr_e(sr_t('ui.text.4421f64e')); ?>">
                <li><?php echo sr_e(sr_t('ui.text.3971a7b8')); ?></li>
                <li><?php echo sr_e(sr_t('ui.db.bdaa9695')); ?></li>
                <li><?php echo sr_e(sr_t('ui.settings.4738c9b6')); ?></li>
                <li><?php echo sr_e(sr_t('ui.admin.07f66a66')); ?></li>
                <li><?php echo sr_e(sr_t('ui.text.50c8b5b0')); ?></li>
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
            <section class="sr-install-alert sr-install-alert-error">
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

        <section class="sr-install-panel">
            <div class="sr-install-panel-head">
                <div>
                    <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.text.3971a7b8')); ?></p>
                    <h2><?php echo sr_e(sr_t('ui.status.8ec133e8')); ?></h2>
                </div>
                <p><?php echo sr_e(sr_t('ui.text.c754b4d6')); ?></p>
            </div>
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
            <div class="sr-install-permission-guide">
                <h3><?php echo sr_e(sr_t('ui.settings.848e94fa')); ?></h3>
                <p>
                    <?php echo sr_e(sr_t('ui.text.91850b91')); ?> <code>config</code><?php echo sr_e(sr_t('ui.text.d536e625')); ?> <code>storage</code> <?php echo sr_e(sr_t('ui.admin.09db1e62')); ?> <code>755</code><?php echo sr_e(sr_t('ui.settings.9a99606d')); ?>
                </p>
                <p>
                    <?php echo sr_e(sr_t('ui.text.6b72e57d')); ?> <code>775</code> <?php echo sr_e(sr_t('ui.text.82d047b9')); ?> <code>777</code><?php echo sr_e(sr_t('ui.text.df2c2877')); ?> <code>755</code><?php echo sr_e(sr_t('ui.text.9083af79')); ?> <code>config/config.php</code><?php echo sr_e(sr_t('ui.text.6ef5924e')); ?> <code>644</code> <?php echo sr_e(sr_t('ui.text.2f925894')); ?>
                </p>
                <p>
                    <code>config</code><?php echo sr_e(sr_t('ui.db.password.save.1dd78ffa')); ?> <code>storage</code><?php echo sr_e(sr_t('ui.save.settings.98d5defa')); ?>
                </p>
            </div>
        </section>

        <form method="post" action="<?php echo sr_e(sr_url('/')); ?>" class="sr-install-form">
            <?php echo sr_csrf_field(); ?>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.text.cb53ca6f')); ?></p>
                        <h2><?php echo sr_e(sr_t('ui.db.1ec96d5d')); ?></h2>
                    </div>
                    <p><?php echo sr_e(sr_t('ui.db.saanraan.active.af3cd57e')); ?> <code>sr_</code><?php echo sr_e(sr_t('ui.text.66341384')); ?></p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <label for="db_host">DB host <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="db_host" type="text" name="db_host" value="<?php echo sr_e($values['db_host']); ?>" autocomplete="off" required>
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.active.d13269d0')); ?></span>
                    </p>
                    <p>
                        <label for="db_name">DB name <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="db_name" type="text" name="db_name" value="<?php echo sr_e($values['db_name']); ?>" autocomplete="off" required>
                    </p>
                    <p>
                        <label for="db_user">DB user <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="db_user" type="text" name="db_user" value="<?php echo sr_e($values['db_user']); ?>" autocomplete="off" required>
                    </p>
                    <p>
                        <label for="db_password">DB password</label>
                        <input id="db_password" type="password" name="db_password" autocomplete="new-password">
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.password.215ab9d4')); ?></span>
                    </p>
                    <p>
                        <label for="db_table_prefix"><?php echo sr_e(sr_t('ui.prefix.49bc3888')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="db_table_prefix" type="text" name="db_table_prefix" value="<?php echo sr_e($values['db_table_prefix']); ?>" pattern="[a-z][a-z0-9]{0,20}_" inputmode="latin" autocapitalize="none" spellcheck="false" required data-install-table-prefix-input>
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.sr.sr.site1.c9aaa2e0')); ?></span>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.text.b2c8d45c')); ?></p>
                        <h2><?php echo sr_e(sr_t('ui.text.601b9971')); ?></h2>
                    </div>
                    <p><?php echo sr_e(sr_t('ui.admin.settings.ca628f95')); ?></p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <label for="site_name"><?php echo sr_e(sr_t('ui.name.51f4c6af')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="site_name" type="text" name="site_name" value="<?php echo sr_e($values['site_name']); ?>" required>
                    </p>
                    <p>
                        <label for="base_url"><?php echo sr_e(sr_t('ui.url.3f618a18')); ?></label>
                        <input id="base_url" type="url" name="base_url" value="<?php echo sr_e($values['base_url']); ?>" placeholder="https://example.com">
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.canonical.og.url.https.e51da311')); ?></span>
                    </p>
                    <p>
                        <label for="timezone">timezone <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <select id="timezone" name="timezone" required>
                            <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                                <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($timezoneOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </p>
                    <p>
                        <label for="default_locale"><?php echo sr_e(sr_t('ui.locale.c7cd39b4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <select id="default_locale" name="default_locale" required>
                            <?php foreach ($localeOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </p>
                    <p>
                        <span class="sr-install-field-label"><?php echo sr_e(sr_t('ui.text.214b5fb8')); ?></span>
                        <input type="hidden" name="main_page_path" value="/">
                        <span class="sr-install-home-default"><?php echo sr_e(sr_t('ui.page.018d240a')); ?></span>
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.community.settings.12c9fe17')); ?></span>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.admin.78496a61')); ?></p>
                        <h2><?php echo sr_e(sr_t('ui.admin.7954926f')); ?></h2>
                    </div>
                    <p><?php echo sr_e(sr_t('ui.text.f039b723')); ?></p>
                </div>

                <div class="sr-install-field-grid">
                    <p>
                        <span class="sr-install-field-label"><?php echo sr_e(sr_t('ui.login.2a15bdbd')); ?></span>
                        <strong><?php echo sr_e(sr_t('ui.email.login.d1f22b60')); ?></strong>
                        <input type="hidden" name="member_login_identifier" value="both">
                        <span class="sr-install-help"><?php echo sr_e(sr_t('ui.email.login.login.login.44f3662f')); ?></span>
                    </p>
                    <p>
                        <label for="admin_email"><?php echo sr_e(sr_t('ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('ui.required.1f227c67')); ?></span></label>
                        <input id="admin_email" type="email" name="admin_email" value="<?php echo sr_e($values['admin_email']); ?>" autocomplete="email" required>
                    </p>
                    <p>
                        <label for="admin_login_id"><?php echo sr_e(sr_t('ui.login.0cdb28b5')); ?></label>
                        <input id="admin_login_id" type="text" name="admin_login_id" value="<?php echo sr_e($values['admin_login_id']); ?>" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="username" data-install-key-input>
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
                        <input id="admin_display_name" type="text" name="admin_display_name" value="<?php echo sr_e($values['admin_display_name']); ?>" required>
                    </p>
                </div>
            </section>

            <section class="sr-install-panel">
                <div class="sr-install-panel-head">
                    <div>
                        <p class="sr-install-kicker"><?php echo sr_e(sr_t('ui.text.6d2d8bf4')); ?></p>
                        <h2><?php echo sr_e(sr_t('ui.text.e58a52d0')); ?></h2>
                    </div>
                    <p><?php echo sr_e(sr_t('ui.select.admin.b336c7a1')); ?></p>
                </div>

                <h3><?php echo sr_e(sr_t('ui.required.9b1c157b')); ?></h3>
                <div class="sr-install-module-grid">
                    <?php foreach ($requiredModules as $moduleKey => $module) { ?>
                        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                        <div class="sr-install-module">
                            <span class="sr-install-status sr-install-status-<?php echo $moduleErrors === [] ? 'ok' : 'error'; ?>"><?php echo $moduleErrors === [] ? sr_t('ui.required.9825053d') : sr_t('ui.text.84dd6e38'); ?></span>
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

                <h3><?php echo sr_e(sr_t('ui.select.d37edab7')); ?></h3>
                <?php if ($optionalModules === []) { ?>
                    <p><?php echo sr_e(sr_t('ui.select.select.feaab9be')); ?> <code>modules/{module_key}</code><?php echo sr_e(sr_t('ui.admin.2253b218')); ?></p>
                <?php } else { ?>
                    <div class="sr-install-module-grid">
                        <?php foreach ($optionalModules as $moduleKey => $module) { ?>
                            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
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
                                        <?php echo isset($selectedOptionalModuleMap[$moduleKey]) ? 'checked' : ''; ?>
                                        <?php echo $moduleErrors === [] ? '' : 'disabled'; ?>
                                    >
                                    <label for="<?php echo sr_e($moduleCheckboxId); ?>"><strong><?php echo sr_e((string) $module['label']); ?></strong></label>
                                </span>
                                <?php if ($moduleErrors !== []) { ?>
                                    <span class="sr-install-status sr-install-status-error"><?php echo sr_e(sr_t('ui.text.b4052951')); ?></span>
                                <?php } ?>
                                <code><?php echo sr_e((string) $moduleKey); ?></code>
                                <p><?php echo sr_e((string) $module['description']); ?></p>
                                <?php if (is_array($moduleMainPageOption) && $moduleErrors === []) { ?>
                                    <label class="sr-install-main-page-option" for="<?php echo sr_e($moduleHomeCheckboxId); ?>">
                                        <input
                                            id="<?php echo sr_e($moduleHomeCheckboxId); ?>"
                                            type="checkbox"
                                            name="main_page_candidate_path"
                                            value="<?php echo sr_e((string) $moduleMainPageOption['path']); ?>"
                                            class="form-checkbox"
                                            data-sr-install-main-page
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
            </section>

            <div class="sr-install-actions">
                <p><?php echo sr_e(sr_t('ui.settings.db.admin.login.e8b89000')); ?></p>
                <button type="submit"><?php echo sr_e(sr_t('ui.text.99d5ac5c')); ?></button>
            </div>
        </form>
    </main>
    <script>
        (function () {
            function syncRestrictedInput(input, normalizeValue) {
                if (!input || input.readOnly || input.disabled) {
                    return;
                }

                var previousValue = input.value;
                var nextValue = normalizeValue(previousValue);
                if (previousValue === nextValue) {
                    return;
                }

                var selectionStart = input.selectionStart;
                var beforeSelection = typeof selectionStart === 'number' ? previousValue.slice(0, selectionStart) : '';
                var nextSelectionStart = typeof selectionStart === 'number' ? normalizeValue(beforeSelection).length : nextValue.length;
                input.value = nextValue;
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(nextSelectionStart, nextSelectionStart);
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

            document.querySelectorAll('[data-install-key-input]').forEach(function (input) {
                syncRestrictedInput(input, normalizeKeyValue);
                input.addEventListener('input', function () {
                    syncRestrictedInput(input, normalizeKeyValue);
                });
            });

            document.querySelectorAll('[data-install-table-prefix-input]').forEach(function (input) {
                syncRestrictedInput(input, normalizeTablePrefixValue);
                input.addEventListener('input', function () {
                    syncRestrictedInput(input, normalizeTablePrefixValue);
                });
            });

            var mainPageInputs = document.querySelectorAll('[data-sr-install-main-page]');
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
                    });
                }

                input.addEventListener('change', function () {
                    if (!input.checked) {
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
                });
            });
        }());
    </script>
</body>
</html>
