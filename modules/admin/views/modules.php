<?php

$adminPageTitle = '모듈 관리';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<table>
    <thead>
        <tr>
            <th>키</th>
            <th>이름</th>
            <th>유형</th>
            <th>설치 버전</th>
            <th>코드 버전</th>
            <th>업데이트</th>
            <th>Toycore 최소</th>
            <th>Toycore 검증</th>
            <th>계약</th>
            <th>상태</th>
            <th>기본 포함</th>
            <th>설치일</th>
            <th>설명</th>
            <th>변경</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($modules as $module) { ?>
            <?php $isRequired = in_array((string) $module['module_key'], $requiredModules, true); ?>
            <tr>
                <td><?php echo toy_e((string) $module['module_key']); ?></td>
                <td><?php echo toy_e((string) $module['name']); ?></td>
                <td><?php echo toy_e((string) ($module['code_type'] ?? 'module')); ?></td>
                <td><?php echo toy_e((string) $module['version']); ?></td>
                <td><?php echo toy_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></td>
                <td>
                    <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                        <a href="<?php echo toy_e(toy_url('/admin/updates')); ?>"><?php echo toy_e((string) $module['pending_update_count']); ?>개 SQL 대기</a>
                    <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                        <?php if ($canManageModuleSources && $moduleSourcesEnabled) { ?>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="sync_module_version">
                                <input type="hidden" name="module_key" value="<?php echo toy_e((string) $module['module_key']); ?>">
                                <label>Owner 비밀번호<br>
                                    <input type="password" name="owner_password" autocomplete="current-password" required>
                                </label>
                                <button type="submit">파일 업데이트 반영</button>
                            </form>
                        <?php } elseif ($canManageModuleSources) { ?>
                            소스 반영 비활성화
                        <?php } else { ?>
                            owner 확인 필요
                        <?php } ?>
                    <?php } elseif (($module['version_state'] ?? '') === 'code_older') { ?>
                        코드 버전 낮음
                    <?php } else { ?>
                        -
                    <?php } ?>
                </td>
                <td><?php echo toy_e((string) ($module['toycore_min_version'] !== '' ? $module['toycore_min_version'] : '-')); ?></td>
                <td><?php echo toy_e((string) ($module['toycore_tested_with'] !== '' ? $module['toycore_tested_with'] : '-')); ?></td>
                <td><?php echo toy_e((string) (($module['toycore_module_contract'] ?? '') !== '' ? $module['toycore_module_contract'] : '-')); ?></td>
                <td><?php echo toy_e((string) $module['status']); ?></td>
                <td><?php echo !empty($module['is_bundled']) ? 'yes' : 'no'; ?></td>
                <td><?php echo toy_e((string) ($module['installed_at'] ?? '')); ?></td>
                <td><?php echo toy_e((string) ($module['description'] !== '' ? $module['description'] : '-')); ?></td>
                <td>
                    <?php if (in_array((string) $module['status'], ['failed', 'installing'], true)) { ?>
                        <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
                            <?php echo toy_csrf_field(); ?>
                            <input type="hidden" name="intent" value="install">
                            <input type="hidden" name="module_key" value="<?php echo toy_e((string) $module['module_key']); ?>">
                            <select name="status">
                                <?php foreach ($allowedInstallStatuses as $status) { ?>
                                    <option value="<?php echo toy_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                        <?php echo toy_e($status); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit">재설치</button>
                        </form>
                    <?php } else { ?>
                        <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
                            <?php echo toy_csrf_field(); ?>
                            <input type="hidden" name="intent" value="status">
                            <input type="hidden" name="module_key" value="<?php echo toy_e((string) $module['module_key']); ?>">
                            <select name="status"<?php echo $isRequired ? ' disabled' : ''; ?>>
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo toy_e($status); ?>"<?php echo $module['status'] === $status ? ' selected' : ''; ?>>
                                        <?php echo toy_e($status); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit"<?php echo $isRequired ? ' disabled' : ''; ?>>저장</button>
                        </form>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<section>
    <h2>모듈 zip 업로드</h2>
    <?php if (!$canManageModuleSources) { ?>
        <p>모듈 파일 업로드는 owner 권한이 필요합니다.</p>
    <?php } elseif (!$moduleSourcesEnabled) { ?>
        <p>현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. <code>admin.module_sources_enabled</code>를 bool true로 저장하면 owner 재인증 후 사용할 수 있습니다.</p>
    <?php } elseif (!$moduleUploadAvailable) { ?>
        <p>PHP ZipArchive 확장이 없어 이 서버에서는 zip 업로드를 사용할 수 없습니다. FTP로 <code>modules/{module_key}</code>에 업로드한 뒤 설치하세요.</p>
    <?php } else { ?>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>" enctype="multipart/form-data">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="intent" value="upload_module_zip">
            <p>
                <label>Module zip<br>
                    <input type="file" name="module_zip" accept=".zip,application/zip" required>
                </label>
            </p>
            <p>
                <label>Module key<br>
                    <input type="text" name="upload_module_key" maxlength="60" pattern="[a-z0-9_]*">
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="confirm_file_replace" value="1">
                    기존 모듈 파일 백업과 교체 확인
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="allow_downgrade" value="1">
                    낮은 버전 덮어쓰기 허용
                </label>
            </p>
            <p>
                <label>Owner 비밀번호<br>
                    <input type="password" name="owner_password" autocomplete="current-password" required>
                </label>
            </p>
            <p>최대 <?php echo toy_e($moduleUploadLimitLabel); ?>까지 업로드할 수 있습니다. 압축 해제 후 모듈 파일은 최대 <?php echo toy_e(toy_admin_format_bytes(toy_admin_module_uncompressed_limit_bytes())); ?>까지 허용합니다. zip은 <code>{module_key}/module.php</code> 구조를 권장하고, <code>module/module.php</code> 구조라면 module key를 입력하세요.</p>
            <button type="submit">zip 업로드</button>
        </form>
    <?php } ?>
</section>

<section>
    <h2>설치 가능한 모듈</h2>
    <?php if ($installableModules === []) { ?>
        <p>설치 가능한 새 모듈이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>키</th>
                    <th>이름</th>
                    <th>유형</th>
                    <th>코드 버전</th>
                    <th>Toycore 최소</th>
                    <th>Toycore 검증</th>
                    <th>계약</th>
                    <th>설명</th>
                    <th>설치</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installableModules as $module) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $module['module_key']); ?></td>
                        <td><?php echo toy_e((string) $module['name']); ?></td>
                        <td><?php echo toy_e((string) $module['type']); ?></td>
                        <td><?php echo toy_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></td>
                        <td><?php echo toy_e((string) ($module['toycore_min_version'] !== '' ? $module['toycore_min_version'] : '-')); ?></td>
                        <td><?php echo toy_e((string) ($module['toycore_tested_with'] !== '' ? $module['toycore_tested_with'] : '-')); ?></td>
                        <td><?php echo toy_e((string) (($module['toycore_module_contract'] ?? '') !== '' ? $module['toycore_module_contract'] : '-')); ?></td>
                        <td><?php echo toy_e((string) ($module['description'] !== '' ? $module['description'] : '-')); ?></td>
                        <td>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="install">
                                <input type="hidden" name="module_key" value="<?php echo toy_e((string) $module['module_key']); ?>">
                                <select name="status">
                                    <?php foreach ($allowedInstallStatuses as $status) { ?>
                                        <option value="<?php echo toy_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                            <?php echo toy_e($status); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <button type="submit">설치</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<section>
    <h2>모듈 설정 항목</h2>
    <p>이 영역은 전용 화면이 없는 낮은 수준의 고급 설정입니다. 저장과 삭제는 owner만 실행할 수 있습니다.</p>
    <?php if ($canManageAdvancedModuleSettings) { ?>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="intent" value="module_setting">
            <p>
                <label>Module<br>
                    <select name="module_key">
                        <?php foreach ($modules as $module) { ?>
                            <option value="<?php echo toy_e((string) $module['module_key']); ?>">
                                <?php echo toy_e((string) $module['module_key']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>Key<br>
                    <input type="text" name="setting_key" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>Value<br>
                    <textarea name="setting_value" maxlength="5000"></textarea>
                </label>
            </p>
            <p>
                <label>Type<br>
                    <select name="value_type">
                        <?php foreach ($allowedSettingTypes as $type) { ?>
                            <option value="<?php echo toy_e($type); ?>"><?php echo toy_e($type); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>Owner password<br>
                    <input type="password" name="owner_password" autocomplete="current-password">
                </label>
            </p>
            <button type="submit">항목 저장</button>
        </form>
    <?php } ?>

    <table>
        <thead>
            <tr>
                <th>Module</th>
                <th>Key</th>
                <th>Value</th>
                <th>Type</th>
                <th>Updated</th>
                <th>삭제</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($moduleSettings === []) { ?>
                <tr>
                    <td colspan="6">설정 항목이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($moduleSettings as $setting) { ?>
                <tr>
                    <td><?php echo toy_e((string) $setting['module_key']); ?></td>
                    <td><?php echo toy_e((string) $setting['setting_key']); ?></td>
                    <td><?php echo toy_e(toy_admin_module_setting_display_value($setting)); ?></td>
                    <td><?php echo toy_e((string) $setting['value_type']); ?></td>
                    <td><?php echo toy_e((string) $setting['updated_at']); ?></td>
                    <td>
                        <?php if ($canManageAdvancedModuleSettings) { ?>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/modules')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_module_setting">
                                <input type="hidden" name="module_key" value="<?php echo toy_e((string) $setting['module_key']); ?>">
                                <input type="hidden" name="setting_key" value="<?php echo toy_e((string) $setting['setting_key']); ?>">
                                <?php if (toy_admin_setting_value_is_secret((string) $setting['setting_key'])) { ?>
                                    <input type="password" name="owner_password" autocomplete="current-password" required>
                                <?php } ?>
                                <button type="submit">삭제</button>
                            </form>
                        <?php } else { ?>
                            owner 전용
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
