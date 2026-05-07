<?php

$adminPageTitle = '팝업레이어';
$editing = is_array($editPopup);
$selectedTargetOption = '';
if ($editing) {
    $selectedTargetOption = (string) ($editPopup['module_key'] ?? '') . '|' . (string) ($editPopup['point_key'] ?? '') . '|' . (string) ($editPopup['slot_key'] ?? '');
}

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
    <h2><?php echo $editing ? '팝업 수정' : '팝업 추가'; ?></h2>
    <?php if ($availableTargets === []) { ?>
        <p>팝업을 노출할 수 있는 모듈 계약 파일이 없습니다.</p>
    <?php } else { ?>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/popup-layers')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="intent" value="save">
            <input type="hidden" name="popup_id" value="<?php echo $editing ? toy_e((string) $editPopup['id']) : '0'; ?>">

            <p>
                <label>제목<br>
                    <input type="text" name="title" value="<?php echo $editing ? toy_e((string) $editPopup['title']) : ''; ?>" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>내용<br>
                    <textarea name="body_text" maxlength="5000"><?php echo $editing ? toy_e((string) $editPopup['body_text']) : ''; ?></textarea>
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <?php $currentStatus = $editing ? (string) $editPopup['status'] : 'draft'; ?>
                            <option value="<?php echo toy_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo toy_e($status); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>노출 대상<br>
                    <select name="target_option">
                        <?php foreach ($availableTargets as $target) { ?>
                            <?php $optionValue = toy_popup_layer_target_option_value($target); ?>
                            <option value="<?php echo toy_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                <?php echo toy_e(toy_popup_layer_target_option_label($target)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>매칭 방식<br>
                    <select name="match_type">
                        <?php foreach ($allowedMatchTypes as $matchType) { ?>
                            <?php $currentMatchType = $editing ? (string) ($editPopup['match_type'] ?? 'all') : 'all'; ?>
                            <option value="<?php echo toy_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                <?php echo toy_e($matchType); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>특정 subject ID<br>
                    <input type="text" name="subject_id" value="<?php echo $editing ? toy_e((string) ($editPopup['subject_id'] ?? '')) : ''; ?>" maxlength="80">
                </label>
            </p>
            <p>
                <label>시작 시각<br>
                    <input type="datetime-local" name="starts_at" value="<?php echo $editing ? toy_e(toy_popup_layer_admin_datetime_value($editPopup['starts_at'] ?? null)) : ''; ?>">
                </label>
            </p>
            <p>
                <label>종료 시각<br>
                    <input type="datetime-local" name="ends_at" value="<?php echo $editing ? toy_e(toy_popup_layer_admin_datetime_value($editPopup['ends_at'] ?? null)) : ''; ?>">
                </label>
            </p>
            <p>
                <label>닫기 유지일<br>
                    <input type="number" name="dismiss_cookie_days" value="<?php echo $editing ? toy_e((string) $editPopup['dismiss_cookie_days']) : '1'; ?>" min="0" max="365">
                </label>
            </p>
            <button type="submit">저장</button>
            <?php if ($editing) { ?>
                <a href="<?php echo toy_e(toy_url('/admin/popup-layers')); ?>">새 팝업 추가</a>
            <?php } ?>
        </form>
    <?php } ?>
</section>

<section>
    <h2>팝업 목록</h2>
    <?php if ($popups === []) { ?>
        <p>등록된 팝업이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>제목</th>
                    <th>상태</th>
                    <th>대상</th>
                    <th>기간</th>
                    <th>닫기 유지일</th>
                    <th>수정일</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($popups as $popup) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $popup['title']); ?></td>
                        <td><?php echo toy_e((string) $popup['status']); ?></td>
                        <td>
                            <?php echo toy_e((string) $popup['module_key'] . ' / ' . (string) $popup['point_key'] . ' / ' . (string) $popup['slot_key']); ?><br>
                            <?php echo toy_e((string) $popup['match_type'] . ((string) ($popup['subject_id'] ?? '') !== '' ? ': ' . (string) $popup['subject_id'] : '')); ?>
                        </td>
                        <td>
                            <?php echo toy_e((string) ($popup['starts_at'] ?? '-')); ?><br>
                            <?php echo toy_e((string) ($popup['ends_at'] ?? '-')); ?>
                        </td>
                        <td><?php echo toy_e((string) $popup['dismiss_cookie_days']); ?></td>
                        <td><?php echo toy_e((string) $popup['updated_at']); ?></td>
                        <td>
        <a href="<?php echo toy_e(toy_url('/admin/popup-layers?edit_id=' . (string) $popup['id'])); ?>">수정</a>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/popup-layers')); ?>" style="display:inline">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete">
                                <input type="hidden" name="popup_id" value="<?php echo toy_e((string) $popup['id']); ?>">
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
