<?php

$adminPageTitle = '로고매니저';
$adminPageSubtitle = '용도별 로고 자산과 기간별 대체 적용을 관리합니다.';
$adminContainerClass = 'admin-page-logo-manager admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-card card logo-manager-current">
    <div class="card-header">
        <div>
            <h2 class="card-title">현재 적용 로고</h2>
            <p class="admin-dashboard-meta">사용 상태이고 현재 시각이 시작/종료 조건에 맞는 항목 중 정렬값이 가장 앞선 로고가 적용됩니다.</p>
        </div>
    </div>
    <div class="card-body logo-manager-current-grid">
        <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
            <?php $active = is_array($activeAssignments[$usageKey] ?? null) ? $activeAssignments[$usageKey] : null; ?>
            <article class="logo-manager-current-item">
                <strong><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></strong>
                <?php if ($active !== null) { ?>
                    <img src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($active))); ?>" alt="" loading="lazy" decoding="async">
                    <span><?php echo sr_e((string) ($active['title'] ?? '')); ?></span>
                    <small><?php echo sr_e((string) ($active['starts_at'] ?? '상시')); ?> - <?php echo sr_e((string) ($active['ends_at'] ?? '상시')); ?></small>
                <?php } else { ?>
                    <span class="logo-manager-empty">적용 항목 없음</span>
                    <small>공개 헤더와 관리자 브랜드는 사이트 이름으로 대체됩니다.</small>
                <?php } ?>
            </article>
        <?php } ?>
    </div>
</section>

<form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <div class="card-header">
            <div>
                <h2 class="card-title">로고 업로드</h2>
                <p class="admin-dashboard-meta">JPEG, PNG, WebP만 허용합니다. SVG는 스크립트/외부 참조 검증 기준이 정리될 때까지 보류합니다.</p>
            </div>
        </div>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="upload_asset">
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_usage_key">용도</label>
            <div class="admin-form-field">
                <select id="logo_manager_usage_key" name="usage_key" class="form-select">
                    <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
                        <option value="<?php echo sr_e((string) $usageKey); ?>"><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></option>
                    <?php } ?>
                </select>
                <small class="admin-form-help">용도는 기본 적용 위치와 권장 크기를 구분하는 기준입니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_title">로고 이름</label>
            <div class="admin-form-field">
                <input id="logo_manager_title" type="text" name="title" class="form-input form-control-full" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_alt_text">대체 텍스트</label>
            <div class="admin-form-field">
                <input id="logo_manager_alt_text" type="text" name="alt_text" class="form-input form-control-full" maxlength="160">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_link_url">링크 URL</label>
            <div class="admin-form-field">
                <input id="logo_manager_link_url" type="text" name="link_url" class="form-input form-control-full" maxlength="255" placeholder="/ 또는 https://example.com">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_logo_file">이미지 파일</label>
            <div class="admin-form-field">
                <input id="logo_manager_logo_file" type="file" name="logo_file" accept="image/jpeg,image/png,image/webp" class="form-input" required>
                <small class="admin-form-help">용도별 최대 용량은 1-5MB입니다. 가능하면 재인코딩 후 저장하고, 서버 확장이 없으면 검증된 원본을 저장합니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_starts_at">시작 시각</label>
            <div class="admin-form-field">
                <input id="logo_manager_starts_at" type="datetime-local" name="starts_at" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_ends_at">종료 시각</label>
            <div class="admin-form-field">
                <input id="logo_manager_ends_at" type="datetime-local" name="ends_at" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_sort_order">정렬</label>
            <div class="admin-form-field">
                <input id="logo_manager_sort_order" type="number" name="sort_order" value="100" class="form-input">
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions">
        <button type="submit" class="btn btn-solid-primary">업로드 및 적용</button>
    </div>
</form>

<form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <div class="card-header">
            <div>
                <h2 class="card-title">기존 자산 기간별 적용</h2>
                <p class="admin-dashboard-meta">이미 등록된 자산을 명절, 캠페인, 점검 기간처럼 정해진 기간에 대체 로고로 예약합니다.</p>
            </div>
        </div>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_assignment">
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_asset">로고 자산</label>
            <div class="admin-form-field">
                <select id="logo_manager_assignment_asset" name="asset_id" class="form-select" required>
                    <option value="">선택</option>
                    <?php foreach ($assets as $asset) { ?>
                        <?php if ((string) ($asset['status'] ?? '') !== 'active') { continue; } ?>
                        <option value="<?php echo sr_e((string) $asset['id']); ?>"><?php echo sr_e('#' . (string) $asset['id'] . ' ' . (string) $asset['title']); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_usage">용도</label>
            <div class="admin-form-field">
                <select id="logo_manager_assignment_usage" name="usage_key" class="form-select">
                    <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
                        <option value="<?php echo sr_e((string) $usageKey); ?>"><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_status">상태</label>
            <div class="admin-form-field">
                <select id="logo_manager_assignment_status" name="status" class="form-select">
                    <?php foreach ($assignmentStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"><?php echo sr_e(sr_logo_manager_status_label($status)); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_alt_text">대체 텍스트</label>
            <div class="admin-form-field">
                <input id="logo_manager_assignment_alt_text" type="text" name="alt_text" class="form-input form-control-full" maxlength="160">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_link_url">링크 URL</label>
            <div class="admin-form-field">
                <input id="logo_manager_assignment_link_url" type="text" name="link_url" class="form-input form-control-full" maxlength="255">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_starts_at">시작 시각</label>
            <div class="admin-form-field">
                <input id="logo_manager_assignment_starts_at" type="datetime-local" name="starts_at" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_ends_at">종료 시각</label>
            <div class="admin-form-field">
                <input id="logo_manager_assignment_ends_at" type="datetime-local" name="ends_at" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="logo_manager_assignment_sort_order">정렬</label>
            <div class="admin-form-field">
                <input id="logo_manager_assignment_sort_order" type="number" name="sort_order" value="100" class="form-input">
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions">
        <button type="submit" class="btn btn-solid-primary">적용 항목 추가</button>
    </div>
</form>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <div>
            <h2 class="card-title">로고 자산</h2>
            <p class="admin-dashboard-meta">보관 상태 자산은 새 기간별 적용에 사용할 수 없습니다.</p>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table logo-manager-assets-table">
            <caption class="sr-only">로고 자산 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>미리보기</th>
                    <th>이름</th>
                    <th>용도</th>
                    <th>크기</th>
                    <th>상태</th>
                    <th>등록일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assets === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">등록된 로고 자산이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($assets as $asset) { ?>
                        <tr>
                            <td><img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($asset))); ?>" alt="" loading="lazy" decoding="async"></td>
                            <td class="admin-table-break"><?php echo sr_e((string) $asset['title']); ?></td>
                            <td><?php echo sr_e(sr_logo_manager_usage_label((string) $asset['usage_key'])); ?></td>
                            <td><?php echo sr_e((string) $asset['width']); ?>x<?php echo sr_e((string) $asset['height']); ?><br><small><?php echo sr_e(sr_logo_manager_format_bytes((int) $asset['size_bytes'])); ?></small></td>
                            <td><span class="admin-status <?php echo (string) $asset['status'] === 'active' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_logo_manager_status_label((string) $asset['status'])); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) $asset['created_at']); ?></td>
                            <td class="text-end">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="asset_status">
                                    <input type="hidden" name="asset_id" value="<?php echo sr_e((string) $asset['id']); ?>">
                                    <input type="hidden" name="status" value="<?php echo (string) $asset['status'] === 'active' ? 'archived' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $asset['status'] === 'active' ? '보관' : '사용'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <div>
            <h2 class="card-title">기간별 적용 항목</h2>
            <p class="admin-dashboard-meta">동일 용도에서 기간이 겹치면 정렬값이 작은 항목, 시작일이 늦은 항목 순으로 우선합니다.</p>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table logo-manager-assignments-table">
            <caption class="sr-only">기간별 로고 적용 항목 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>용도</th>
                    <th>로고</th>
                    <th>상태</th>
                    <th>기간</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">등록된 적용 항목이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($assignments as $assignment) { ?>
                        <tr>
                            <td><?php echo sr_e(sr_logo_manager_usage_label((string) $assignment['usage_key'])); ?></td>
                            <td class="admin-table-break">
                                <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($assignment))); ?>" alt="" loading="lazy" decoding="async">
                                <?php echo sr_e((string) $assignment['title']); ?>
                            </td>
                            <td><span class="admin-status <?php echo (string) $assignment['status'] === 'active' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_logo_manager_status_label((string) $assignment['status'])); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($assignment['starts_at'] ?? '상시')); ?><br><?php echo sr_e((string) ($assignment['ends_at'] ?? '상시')); ?></td>
                            <td><?php echo sr_e((string) $assignment['sort_order']); ?></td>
                            <td class="text-end">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="assignment_status">
                                    <input type="hidden" name="assignment_id" value="<?php echo sr_e((string) $assignment['id']); ?>">
                                    <input type="hidden" name="status" value="<?php echo (string) $assignment['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $assignment['status'] === 'active' ? '중지' : '사용'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
