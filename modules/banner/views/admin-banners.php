<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? '배너 수정' : '배너 추가') : '배너';
$adminPageSubtitle = $bannerAdminPage === 'form' ? '배너 내용, 출력 위치, 노출 기간을 관리합니다.' : '배너 상태를 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = $bannerAdminPage === 'form' ? 'admin-page-banner-form admin-ui-scope' : 'admin-page-banner-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$bannerStatusCounts = isset($bannerStatusCounts) && is_array($bannerStatusCounts) ? $bannerStatusCounts : [];
$totalBanners = (int) ($bannerStatusCounts['total'] ?? count($banners ?? []));
$selectedTargetOption = sr_banner_public_target_option_value();
if ($editing && (string) ($editBanner['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '');
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? '배너 수정' : '배너 추가'; ?></h2>
            <p>배너 스킨은 배너를 화면에 그리는 템플릿입니다. 선택한 스킨이 지원하는 출력 방식과 배너 출력 위치가 맞아야 사용자 화면에 표시됩니다.</p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="banner_id" value="<?php echo $editing ? sr_e((string) $editBanner['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_title">제목 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editBanner['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_body_text">내용</label>
                <div class="admin-form-field">
                    <textarea id="banner_admin_banners_body_text" name="body_text" maxlength="3000" class="form-textarea"><?php echo $editing ? sr_e((string) $editBanner['body_text']) : ''; ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_link_url">링크 URL (외부 http/https 링크는 새 창으로 열림)</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_link_url" type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_image_url">이미지 URL (/ 내부 경로 또는 http/https URL)</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_url" type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_image_upload">이미지 업로드</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_upload" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                    <br>
                                    <small>JPEG, PNG, WebP / 최대 <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?>. 업로드하면 이미지 URL보다 우선 적용됩니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_target_option">출력 위치</label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_target_option" name="target_option" class="form-select">
                                            <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $selectedTargetOption === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>
                                                공용 배너
                                            </option>
                                            <?php foreach ($availableTargets as $target) { ?>
                                                <?php $optionValue = sr_banner_target_option_value($target); ?>
                                                <option value="<?php echo sr_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $target['label']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                    <br>
                                    <small>공용 배너는 자동 출력되지 않고, 게시판 같은 모듈의 개별 설정에서 선택해 사용합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_match_type">매칭 방식</label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_match_type" name="match_type" class="form-select">
                                            <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                <?php $currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all'; ?>
                                                <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_subject_id">특정 subject ID</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_status">상태</label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <?php $currentStatus = $editing ? (string) $editBanner['status'] : 'draft'; ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                    <br>
                                    <small>사용 상태이고 기간 조건에 맞을 때만 사용자 화면에 노출됩니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_skin_key">배너 스킨</label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_skin_key" name="skin_key" class="form-select">
                                            <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                                <?php $currentSkinKey = $editing ? (string) ($editBanner['skin_key'] ?? $bannerSkinKey) : $bannerSkinKey; ?>
                                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                    (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                    <br>
                                    <small>저장 시 출력 위치와 호환되는 스킨인지 확인합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_starts_at">시작 시각</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_starts_at" type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_ends_at">종료 시각</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_ends_at" type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_sort_order">정렬</label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_sort_order" type="number" name="sort_order" value="<?php echo $editing ? sr_e((string) $editBanner['sort_order']) : '100'; ?>" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총배너 <strong><?php echo sr_e((string) $totalBanners); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($bannerStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=draft')); ?>" class="admin-summary-meta">초안 <?php echo sr_e((string) ($bannerStatusCounts['draft'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=disabled')); ?>" class="admin-summary-meta">중지 <?php echo sr_e((string) ($bannerStatusCounts['disabled'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="admin-filter admin-banner-filter ui-form-theme">
        <div class="admin-filter-grid admin-banner-search-grid">
            <div class="admin-filter-field admin-banner-filter-status">
                <label for="modules_banner_admin_banners_status" class="admin-filter-label">상태</label>
                <select id="modules_banner_admin_banners_status" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-banner-filter-target">
                <label for="modules_banner_admin_banners_target" class="admin-filter-label">출력 위치</label>
                <select id="modules_banner_admin_banners_target" name="target" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['target'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo (string) ($filters['target'] ?? '') === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>공용 배너</option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_banner_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo (string) ($filters['target'] ?? '') === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $target['label']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-banner-filter-field">
                <label for="modules_banner_admin_banners_field" class="admin-filter-label">검색 조건</label>
                <select id="modules_banner_admin_banners_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'link' => '링크'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-banner-filter-keyword">
                <label for="modules_banner_admin_banners_q" class="admin-filter-label">검색어</label>
                <input id="modules_banner_admin_banners_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, 링크">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">배너 목록</h2>
                <p class="admin-dashboard-meta">사용 상태이고 기간 조건에 맞는 배너만 사용자 화면에 노출됩니다.</p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>" class="btn btn-sm btn-solid-light">새 배너 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-banner-table">
            <caption class="sr-only">배너 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>제목</th>
                    <th>상태</th>
                    <th>스킨</th>
                    <th>링크</th>
                    <th>클릭</th>
                    <th>출력 위치</th>
                    <th>기간</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($banners === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">등록된 배너가 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($banners as $banner) { ?>
                        <?php
                        if ((string) ($banner['module_key'] ?? '') === '') {
                            $bannerTargetLabel = '공용 배너';
                        } else {
                            $bannerTargetOption = (string) $banner['module_key'] . '|' . (string) $banner['point_key'] . '|' . (string) $banner['slot_key'];
                            $bannerTargetLabel = (string) ($targetLabels[$bannerTargetOption] ?? ('선언이 사라진 출력 위치 / ' . (string) $banner['module_key'] . ' / ' . (string) $banner['point_key'] . ' / ' . (string) $banner['slot_key']));
                        }
                        $bannerStatus = (string) $banner['status'];
                        $statusClass = match ($bannerStatus) {
                            'enabled' => 'is-normal',
                            'draft' => 'is-blocked',
                            default => 'is-left',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-break admin-banner-title-cell"><?php echo sr_e((string) $banner['title']); ?></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($bannerStatus, 'content_status')); ?></span>
                                <?php if ($bannerStatus !== 'enabled') { ?>
                                    <br><small>사용자 화면 미노출</small>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')])); ?></td>
                            <td class="admin-table-break admin-banner-link-cell">
                                <?php echo sr_e(sr_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?><br>
                                <?php echo sr_e((string) ($banner['link_url'] ?? '')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($banner['click_count'] ?? 0))); ?></td>
                            <td class="admin-table-break admin-banner-target-cell"><?php echo sr_e($bannerTargetLabel); ?></td>
                            <td class="admin-table-nowrap admin-banner-date-cell">
                                <?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $banner['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
