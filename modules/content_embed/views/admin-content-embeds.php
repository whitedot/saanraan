<?php

$adminPageTitle = '콘텐츠 임베드';
$selectedStatuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-section-heading">
    <h2><?php echo sr_e('임베드 참조'); ?></h2>
</div>

<section class="admin-card admin-list-card card">
    <form method="get" action="<?php echo sr_e(sr_url('/admin/content-embeds')); ?>" class="admin-filter-form ui-form-theme">
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="content_embed_status"><?php echo sr_e('상태'); ?></label>
                <div class="admin-form-field">
                    <select id="content_embed_status" name="status" class="form-select">
                        <option value=""><?php echo sr_e('전체'); ?></option>
                        <?php foreach (sr_content_embed_allowed_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo in_array($status, $selectedStatuses, true) ? ' selected' : ''; ?>>
                                <?php echo sr_e($status); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_embed_q"><?php echo sr_e('검색어'); ?></label>
                <div class="admin-form-field">
                    <input id="content_embed_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input">
                </div>
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('검색'); ?></button>
            <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/content-embeds')); ?>"><?php echo sr_e('초기화'); ?></a>
        </div>
    </form>
</section>

<?php if (!$tableReady) { ?>
    <section class="admin-card admin-list-card card">
        <p><?php echo sr_e('콘텐츠 임베드 테이블이 아직 준비되지 않았습니다. 모듈 설치 또는 업데이트 상태를 확인하세요.'); ?></p>
    </section>
<?php } elseif ($refs === []) { ?>
    <section class="admin-card admin-list-card card">
        <p><?php echo sr_e('조건에 맞는 임베드 참조가 없습니다.'); ?></p>
    </section>
<?php } else { ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?php echo sr_e('참조'); ?></th>
                    <th><?php echo sr_e('소유 대상'); ?></th>
                    <th><?php echo sr_e('임베드 대상'); ?></th>
                    <th><?php echo sr_e('상태'); ?></th>
                    <th><?php echo sr_e('수정일'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($refs as $ref) { ?>
                    <tr>
                        <td>
                            <strong><?php echo sr_e((string) ($ref['ref_key'] ?? '')); ?></strong>
                            <span class="admin-muted-text"><?php echo sr_e((string) ($ref['variant'] ?? '')); ?></span>
                        </td>
                        <td><?php echo sr_e((string) ($ref['owner_module'] ?? '')); ?> / <?php echo sr_e((string) ($ref['owner_type'] ?? '')); ?> #<?php echo sr_e((string) ($ref['owner_id'] ?? '')); ?></td>
                        <td>
                            <?php echo sr_e((string) ($ref['target_module'] ?? '')); ?> / <?php echo sr_e((string) ($ref['target_type'] ?? '')); ?> #<?php echo sr_e((string) ($ref['target_id'] ?? '')); ?>
                            <?php if ((string) ($ref['label_snapshot'] ?? '') !== '') { ?>
                                <span class="admin-muted-text"><?php echo sr_e((string) $ref['label_snapshot']); ?></span>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e((string) ($ref['status'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) ($ref['updated_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
