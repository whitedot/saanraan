<?php

$adminPageTitle = '스킨·테마 패키지';
$adminPageSubtitle = [
    'sr-packages에 배치된 외부 스킨과 테마 후보를 검증합니다.',
    '패키지는 적용 시 애플리케이션 권한으로 실행되는 특권 PHP 코드입니다.',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';

$packageStatusLabel = static function (array $candidate): string {
    return !empty($candidate['is_valid']) ? '유효' : '검증 실패';
};
$packageStatusClass = static function (array $candidate): string {
    return !empty($candidate['is_valid']) ? 'badge-soft-success' : 'badge-soft-danger';
};
$packageListText = static function (array $values): string {
    $items = [];
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $items[] = $value;
        }
    }

    return $items !== [] ? implode(', ', $items) : '-';
};
$packageErrorGroups = static function (array $candidate): array {
    $groups = is_array($candidate['error_groups'] ?? null) ? $candidate['error_groups'] : [];
    $labels = [
        'structure' => '구조',
        'version' => '버전',
        'path' => '경로',
        'asset' => '자산',
        'contract' => '계약',
    ];
    $rows = [];
    foreach ($labels as $groupKey => $label) {
        $messages = isset($groups[$groupKey]) && is_array($groups[$groupKey]) ? $groups[$groupKey] : [];
        if ($messages !== []) {
            $rows[] = $label . ': ' . implode(' / ', array_slice(array_map('strval', $messages), 0, 3));
        }
    }

    return $rows;
};
$packageReferenceHtml = static function (array $summary): string {
    $rows = is_array($summary['rows'] ?? null) ? $summary['rows'] : [];
    if ($rows === []) {
        return '<span class="badge badge-soft-secondary">참조 0건</span>';
    }

    ob_start();
    ?>
    <ul class="admin-operations-target-list">
        <?php foreach ($rows as $row) { ?>
            <li><?php echo sr_e((string) ($row['label'] ?? '참조')); ?> <?php echo sr_e(number_format((int) ($row['count'] ?? 0))); ?>건</li>
        <?php } ?>
    </ul>
    <?php
    return (string) ob_get_clean();
};
?>

<?php if ($packageLayoutHealthWarnings !== []) { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">레이아웃 fallback health</h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>Scope</th>
                        <th>선택 layout</th>
                        <th>미지원 도메인</th>
                        <th>Fallback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packageLayoutHealthWarnings as $warning) { ?>
                        <tr>
                            <td><code><?php echo sr_e((string) ($warning['scope'] ?? '')); ?></code></td>
                            <td><code><?php echo sr_e((string) ($warning['layout_key'] ?? '')); ?></code></td>
                            <td><code><?php echo sr_e((string) ($warning['unsupported_domain'] ?? '')); ?></code></td>
                            <td><code><?php echo sr_e((string) ($warning['fallback_layout_key'] ?? sr_public_layout_default_key())); ?></code></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">외부 테마</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
                <tr>
                    <th>상태</th>
                    <th>Key</th>
                    <th>Preview</th>
                    <th>지원 범위</th>
                    <th>계약</th>
                    <th>출처</th>
                    <th>자산</th>
                    <th>참조</th>
                    <th>오류</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($packageThemeCandidates === []) { ?>
                    <tr><td colspan="9" class="admin-empty-state">외부 테마 후보가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($packageThemeCandidates as $themeKey => $candidate) { ?>
                    <?php
                    $previewAsset = is_array($candidate['assets']['preview'] ?? null) ? $candidate['assets']['preview'] : null;
                    $referenceSummary = sr_package_reference_summary($pdo, 'theme', (string) $themeKey);
                    $errorRows = $packageErrorGroups($candidate);
                    ?>
                    <tr>
                        <td><span class="badge <?php echo sr_e($packageStatusClass($candidate)); ?>"><?php echo sr_e($packageStatusLabel($candidate)); ?></span></td>
                        <td>
                            <code><?php echo sr_e((string) $themeKey); ?></code>
                            <div class="type-caption"><?php echo sr_e((string) ($candidate['label'] ?? '')); ?></div>
                            <div class="type-caption"><?php echo sr_e((string) ($candidate['trust_warning'] ?? '')); ?></div>
                        </td>
                        <td>
                            <?php if (is_array($previewAsset)) { ?>
                                <a href="<?php echo sr_e(sr_url((string) ($previewAsset['url'] ?? ''))); ?>" target="_blank" rel="noopener noreferrer">미리보기</a>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e($packageListText((array) ($candidate['supports_domains'] ?? []))); ?></td>
                        <td>
                            <div>theme <?php echo sr_e((string) ($candidate['contract_version'] ?? '-')); ?></div>
                            <div class="type-caption">min <?php echo sr_e((string) ($candidate['saanraan_min_version'] ?? '-')); ?></div>
                        </td>
                        <td>
                            <div><?php echo sr_e((string) ($candidate['provider_label'] ?? '외부 패키지')); ?></div>
                            <div class="type-caption"><?php echo sr_e((string) ($candidate['author'] ?? '')); ?> <?php echo sr_e((string) ($candidate['license'] ?? '')); ?></div>
                            <?php if ((string) ($candidate['source_url'] ?? '') !== '') { ?>
                                <div class="type-caption"><?php echo sr_e((string) $candidate['source_url']); ?></div>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e($packageListText((array) ($candidate['asset_ids'] ?? []))); ?></td>
                        <td><?php echo $packageReferenceHtml($referenceSummary); ?></td>
                        <td>
                            <?php if ($errorRows === []) { ?>
                                -
                            <?php } else { ?>
                                <ul class="admin-operations-target-list">
                                    <?php foreach ($errorRows as $errorRow) { ?>
                                        <li><?php echo sr_e($errorRow); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($packageSkinCandidatesByModule as $skinModuleKey => $skinCandidates) { ?>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_admin_code_label((string) $skinModuleKey, 'module_key')); ?> 외부 스킨</h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>상태</th>
                        <th>Key</th>
                        <th>View 계약</th>
                        <th>계약</th>
                        <th>출처</th>
                        <th>자산</th>
                        <th>참조</th>
                        <th>오류</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($skinCandidates === []) { ?>
                        <tr><td colspan="8" class="admin-empty-state">외부 스킨 후보가 없습니다.</td></tr>
                    <?php } ?>
                    <?php foreach ($skinCandidates as $skinKey => $candidate) { ?>
                        <?php
                        $referenceSummary = sr_package_reference_summary($pdo, 'skin', (string) $skinKey, (string) $skinModuleKey);
                        $errorRows = $packageErrorGroups($candidate);
                        ?>
                        <tr>
                            <td><span class="badge <?php echo sr_e($packageStatusClass($candidate)); ?>"><?php echo sr_e($packageStatusLabel($candidate)); ?></span></td>
                            <td>
                                <code><?php echo sr_e((string) $skinKey); ?></code>
                                <div class="type-caption"><?php echo sr_e((string) ($candidate['label'] ?? '')); ?></div>
                                <div class="type-caption"><?php echo sr_e((string) ($candidate['trust_warning'] ?? '')); ?></div>
                            </td>
                            <td><?php echo sr_e($packageListText(array_keys((array) ($candidate['views'] ?? [])))); ?></td>
                            <td>
                                <div>skin <?php echo sr_e((string) ($candidate['contract_version'] ?? '-')); ?></div>
                                <div class="type-caption">min <?php echo sr_e((string) ($candidate['saanraan_min_version'] ?? '-')); ?></div>
                            </td>
                            <td>
                                <div><?php echo sr_e((string) ($candidate['author'] ?? '')); ?> <?php echo sr_e((string) ($candidate['license'] ?? '')); ?></div>
                                <?php if ((string) ($candidate['source_url'] ?? '') !== '') { ?>
                                    <div class="type-caption"><?php echo sr_e((string) $candidate['source_url']); ?></div>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e($packageListText((array) ($candidate['asset_ids'] ?? []))); ?></td>
                            <td><?php echo $packageReferenceHtml($referenceSummary); ?></td>
                            <td>
                                <?php if ($errorRows === []) { ?>
                                    -
                                <?php } else { ?>
                                    <ul class="admin-operations-target-list">
                                        <?php foreach ($errorRows as $errorRow) { ?>
                                            <li><?php echo sr_e($errorRow); ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
