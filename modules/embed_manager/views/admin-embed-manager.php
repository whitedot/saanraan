<?php

$adminPageTitle = '임베드 매니저';
$adminPageSubtitle = '본문에 삽입된 외부 콘텐츠 참조가 정상인지 확인합니다.';
$adminContainerClass = 'admin-page-embed-manager-list admin-ui-scope';
$selectedStatuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
$detailFilterOpen = $selectedStatuses !== [];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/embed-manager');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/embed-manager')); ?>" class="filtering-form admin-embed-manager-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $detailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-embed-manager-filter-grid">
            <div class="filtering-field filtering-field-fill">
                <label class="filtering-label" for="embed_manager_q"><?php echo sr_e('검색어'); ?></label>
                <input id="embed_manager_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e('참조 관리용 키, 소유 모듈, 대상 모듈, 대상 ID'); ?>">
            </div>
        </div>
        <div id="embed_manager_detail_filters" class="filtering-body" data-filtering-body<?php echo $detailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label"><?php echo sr_e('상태'); ?></span>
                <?php echo sr_admin_filter_radio_toggle_group_html('embed_manager_status_filter', 'status', sr_admin_code_label_options(sr_embed_manager_allowed_statuses(), 'embed_manager_status'), $selectedStatuses, '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $detailFilterOpen ? 'true' : 'false'; ?>" aria-controls="embed_manager_detail_filters"><?php echo sr_e('상세검색'); ?></button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?><?php echo sr_e('초기화'); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e('검색'); ?></button>
        </div>
    </div>
</form>

<?php if (!$tableReady) { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e('임베드 참조'); ?></h2>
        </div>
        <p class="admin-empty-state"><?php echo sr_e('임베드 매니저 테이블이 아직 준비되지 않았습니다. 모듈 설치 또는 업데이트 상태를 확인하세요.'); ?></p>
    </section>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e('임베드 참조'); ?></h2>
        </div>
        <div class="admin-list-summary-row">
            <div class="admin-list-summary">
                <span><?php echo sr_e('표시'); ?> <strong><?php echo sr_e((string) count($refs)); ?></strong><?php echo sr_e('건'); ?></span>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table admin-embed-manager-table">
                <caption class="sr-only"><?php echo sr_e('임베드 매니저 참조 목록'); ?></caption>
                <thead class="ui-table-head">
                    <tr>
                        <th><?php echo sr_e('참조'); ?></th>
                        <th><?php echo sr_e('소유 대상'); ?></th>
                        <th><?php echo sr_e('임베드 대상'); ?></th>
                        <th><?php echo sr_e('상태'); ?></th>
                        <th><?php echo sr_e('수정일'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($refs === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state"><?php echo sr_e('조건에 맞는 임베드 참조가 없습니다.'); ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($refs as $ref) { ?>
                        <tr>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e((string) ($ref['ref_key'] ?? '')); ?></strong>
                                <span class="admin-muted-text"><?php echo sr_e((string) ($ref['variant'] ?? '')); ?></span>
                            </td>
                            <td class="admin-table-break"><?php echo sr_e(sr_embed_manager_ref_target_label((string) ($ref['owner_module'] ?? ''), (string) ($ref['owner_type'] ?? ''), (string) ($ref['owner_id'] ?? ''))); ?></td>
                            <td class="admin-table-break">
                                <?php echo sr_e(sr_embed_manager_ref_target_label((string) ($ref['target_module'] ?? ''), (string) ($ref['target_type'] ?? ''), (string) ($ref['target_id'] ?? ''))); ?>
                                <?php if ((string) ($ref['label_snapshot'] ?? '') !== '') { ?>
                                    <span class="admin-muted-text"><?php echo sr_e((string) $ref['label_snapshot']); ?></span>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e((string) ((string) ($ref['status'] ?? '') === 'active' ? 'is-normal' : 'is-blocked')); ?>"><?php echo sr_e(sr_admin_code_label((string) ($ref['status'] ?? ''), 'embed_manager_status')); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($ref['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
