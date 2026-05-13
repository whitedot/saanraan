<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? '배너 수정' : '배너 추가') : '배너';
$selectedTargetOption = sr_banner_public_target_option_value();
if ($editing && (string) ($editBanner['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '');
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

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

<p>
    <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>">배너 목록</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>">배너 추가</a>
</p>

<?php if ($bannerAdminPage === 'form') { ?>
    <section>
        <h2><?php echo $editing ? '배너 수정' : '배너 추가'; ?></h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="banner_id" value="<?php echo $editing ? sr_e((string) $editBanner['id']) : '0'; ?>">
            <p>
                <label>제목<br>
                    <input type="text" name="title" value="<?php echo $editing ? sr_e((string) $editBanner['title']) : ''; ?>" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>내용<br>
                    <textarea name="body_text" maxlength="3000"><?php echo $editing ? sr_e((string) $editBanner['body_text']) : ''; ?></textarea>
                </label>
            </p>
            <p>
                <label>링크 URL (외부 http/https 링크는 새 창으로 열림)<br>
                    <input type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" maxlength="255">
                </label>
            </p>
            <p>
                <label>이미지 URL (/ 내부 경로 또는 http/https URL)<br>
                    <input type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" maxlength="255">
                </label>
            </p>
            <p>
                <label>이미지 업로드<br>
                    <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp">
                </label>
                <br>
                <small>JPEG, PNG, WebP / 최대 <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?>. 업로드하면 이미지 URL보다 우선 적용됩니다.</small>
            </p>
            <p>
                <label>출력 위치<br>
                    <select name="target_option">
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
                </label>
                <br>
                <small>공용 배너는 자동 출력되지 않고, 게시판 같은 모듈의 개별 설정에서 선택해 사용합니다.</small>
            </p>
            <p>
                <label>매칭 방식<br>
                    <select name="match_type">
                        <?php foreach ($allowedMatchTypes as $matchType) { ?>
                            <?php $currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all'; ?>
                            <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>특정 subject ID<br>
                    <input type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" maxlength="80">
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <?php $currentStatus = $editing ? (string) $editBanner['status'] : 'draft'; ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <br>
                <small>사용 상태이고 기간 조건에 맞을 때만 사용자 화면에 노출됩니다.</small>
            </p>
            <p>
                <label>배너 스킨<br>
                    <select name="skin_key">
                        <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                            <?php $currentSkinKey = $editing ? (string) ($editBanner['skin_key'] ?? $bannerSkinKey) : $bannerSkinKey; ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <br>
                <small>저장 시 출력 위치와 호환되는 스킨인지 확인합니다.</small>
            </p>
            <p>
                <label>시작 시각<br>
                    <input type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>">
                </label>
            </p>
            <p>
                <label>종료 시각<br>
                    <input type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>">
                </label>
            </p>
            <p>
                <label>정렬<br>
                    <input type="number" name="sort_order" value="<?php echo $editing ? sr_e((string) $editBanner['sort_order']) : '100'; ?>">
                </label>
            </p>
            <button type="submit">저장</button>
        </form>
    </section>
<?php } else { ?>
    <section>
        <h2>배너 설정</h2>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/banners')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_settings">
            <p>
                <label>배너 스킨<br>
                    <select name="banner_skin_key">
                        <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $bannerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">배너 설정 저장</button>
        </form>
    </section>

    <section>
        <h2>배너 목록</h2>
        <p><a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>">새 배너 추가</a></p>
        <p>사용 상태이고 기간 조건에 맞는 배너만 사용자 화면에 노출됩니다.</p>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>">
            <p>
                <label>상태<br>
                    <select name="status">
                        <option value=""<?php echo $filters['status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $filters['status'] === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>출력 위치<br>
                    <select name="target">
                        <option value=""<?php echo $filters['target'] === '' ? ' selected' : ''; ?>>전체</option>
                        <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $filters['target'] === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>공용 배너</option>
                        <?php foreach ($availableTargets as $target) { ?>
                            <?php $optionValue = sr_banner_target_option_value($target); ?>
                            <option value="<?php echo sr_e($optionValue); ?>"<?php echo $filters['target'] === $optionValue ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $target['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">조회</button>
        </form>
        <?php if ($banners === []) { ?>
            <p>등록된 배너가 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>상태</th>
                        <th>스킨</th>
                        <th>링크</th>
                        <th>클릭</th>
                        <th>출력 위치</th>
                        <th>기간</th>
                        <th>정렬</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner) { ?>
                        <?php
                        if ((string) ($banner['module_key'] ?? '') === '') {
                            $bannerTargetLabel = '공용 배너';
                        } else {
                            $bannerTargetOption = (string) $banner['module_key'] . '|' . (string) $banner['point_key'] . '|' . (string) $banner['slot_key'];
                            $bannerTargetLabel = (string) ($targetLabels[$bannerTargetOption] ?? ('선언이 사라진 출력 위치 / ' . (string) $banner['module_key'] . ' / ' . (string) $banner['point_key'] . ' / ' . (string) $banner['slot_key']));
                        }
                        ?>
                        <tr>
                            <td><?php echo sr_e((string) $banner['title']); ?></td>
                            <td>
                                <?php echo sr_e(sr_admin_code_label((string) $banner['status'], 'content_status')); ?>
                                <?php if ((string) $banner['status'] !== 'enabled') { ?>
                                    <br><small>사용자 화면 미노출</small>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e(sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')])); ?></td>
                            <td>
                                <?php echo sr_e(sr_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?><br>
                                <?php echo sr_e((string) ($banner['link_url'] ?? '')); ?>
                            </td>
                            <td><?php echo sr_e(number_format((int) ($banner['click_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e($bannerTargetLabel); ?></td>
                            <td>
                                <?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?>
                            </td>
                            <td><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td>
                                <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>">수정</a>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/delete')); ?>" style="display:inline">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $banner['id']); ?>">
                                    <button type="submit">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
