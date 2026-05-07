<?php

$adminPageTitle = '배너';
$editing = is_array($editBanner);
$selectedTargetOption = $editing
    ? (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '')
    : '';
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

<section>
    <h2><?php echo $editing ? '배너 수정' : '배너 추가'; ?></h2>
    <form method="post" action="<?php echo toy_e(toy_url('/admin/banners')); ?>">
        <?php echo toy_csrf_field(); ?>
        <input type="hidden" name="intent" value="save">
        <input type="hidden" name="banner_id" value="<?php echo $editing ? toy_e((string) $editBanner['id']) : '0'; ?>">
        <p>
            <label>제목<br>
                <input type="text" name="title" value="<?php echo $editing ? toy_e((string) $editBanner['title']) : ''; ?>" maxlength="120" required>
            </label>
        </p>
        <p>
            <label>내용<br>
                <textarea name="body_text" maxlength="3000"><?php echo $editing ? toy_e((string) $editBanner['body_text']) : ''; ?></textarea>
            </label>
        </p>
        <p>
            <label>링크 URL (외부 http/https 링크는 새 창으로 열림)<br>
                <input type="text" name="link_url" value="<?php echo $editing ? toy_e((string) $editBanner['link_url']) : ''; ?>" maxlength="255">
            </label>
        </p>
        <p>
            <label>이미지 URL (/로 시작하는 내부 경로)<br>
                <input type="text" name="image_url" value="<?php echo $editing ? toy_e((string) $editBanner['image_url']) : ''; ?>" maxlength="255">
            </label>
        </p>
        <p>
            <label>모듈이 선언한 출력 위치<br>
                <select name="target_option">
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = toy_banner_target_option_value($target); ?>
                        <option value="<?php echo toy_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                            <?php echo toy_e((string) $target['label']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>매칭 방식<br>
                <select name="match_type">
                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                        <?php $currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all'; ?>
                        <option value="<?php echo toy_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                            <?php echo toy_e($matchType); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>특정 subject ID<br>
                <input type="text" name="subject_id" value="<?php echo $editing ? toy_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" maxlength="80">
            </label>
        </p>
        <p>
            <label>상태<br>
                <select name="status">
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <?php $currentStatus = $editing ? (string) $editBanner['status'] : 'draft'; ?>
                        <option value="<?php echo toy_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                            <?php echo toy_e($status); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>시작 시각<br>
                <input type="datetime-local" name="starts_at" value="<?php echo $editing ? toy_e(toy_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>">
            </label>
        </p>
        <p>
            <label>종료 시각<br>
                <input type="datetime-local" name="ends_at" value="<?php echo $editing ? toy_e(toy_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>">
            </label>
        </p>
        <p>
            <label>정렬<br>
                <input type="number" name="sort_order" value="<?php echo $editing ? toy_e((string) $editBanner['sort_order']) : '100'; ?>">
            </label>
        </p>
        <button type="submit">저장</button>
        <?php if ($editing) { ?>
            <a href="<?php echo toy_e(toy_url('/admin/banners')); ?>">새 배너 추가</a>
        <?php } ?>
    </form>
</section>

<section>
    <h2>배너 목록</h2>
    <form method="get" action="<?php echo toy_e(toy_url('/admin/banners')); ?>">
        <p>
            <label>상태<br>
                <select name="status">
                    <option value=""<?php echo $filters['status'] === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo toy_e($status); ?>"<?php echo $filters['status'] === $status ? ' selected' : ''; ?>>
                            <?php echo toy_e($status); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>출력 위치<br>
                <select name="target">
                    <option value=""<?php echo $filters['target'] === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = toy_banner_target_option_value($target); ?>
                        <option value="<?php echo toy_e($optionValue); ?>"<?php echo $filters['target'] === $optionValue ? ' selected' : ''; ?>>
                            <?php echo toy_e((string) $target['label']); ?>
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
                    <th>링크</th>
                    <th>출력 위치</th>
                    <th>기간</th>
                    <th>정렬</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banners as $banner) { ?>
                    <?php
                    $bannerTargetOption = (string) $banner['module_key'] . '|' . (string) $banner['point_key'] . '|' . (string) $banner['slot_key'];
                    $bannerTargetLabel = (string) ($targetLabels[$bannerTargetOption] ?? ('선언이 사라진 출력 위치 / ' . (string) $banner['module_key'] . ' / ' . (string) $banner['point_key'] . ' / ' . (string) $banner['slot_key']));
                    ?>
                    <tr>
                        <td><?php echo toy_e((string) $banner['title']); ?></td>
                        <td><?php echo toy_e((string) $banner['status']); ?></td>
                        <td>
                            <?php echo toy_e(toy_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?><br>
                            <?php echo toy_e((string) ($banner['link_url'] ?? '')); ?>
                        </td>
                        <td><?php echo toy_e($bannerTargetLabel); ?></td>
                        <td>
                            <?php echo toy_e((string) ($banner['starts_at'] ?? '-')); ?><br>
                            <?php echo toy_e((string) ($banner['ends_at'] ?? '-')); ?>
                        </td>
                        <td><?php echo toy_e((string) $banner['sort_order']); ?></td>
                        <td>
                            <a href="<?php echo toy_e(toy_url('/admin/banners?edit_id=' . (string) $banner['id'])); ?>">수정</a>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/banners')); ?>" style="display:inline">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete">
                                <input type="hidden" name="banner_id" value="<?php echo toy_e((string) $banner['id']); ?>">
                                <button type="submit">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
