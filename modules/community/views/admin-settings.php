<?php

$adminPageTitle = '커뮤니티 설정';
$messageWriteGroupKeysValue = implode(', ', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : []);
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
    <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>">게시판 관리</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>">게시판 그룹 관리</a>
</p>

<?php if ($enabledMemberGroups !== []) { ?>
    <section>
        <h2>사용 가능한 회원 그룹 key</h2>
        <ul>
            <?php foreach ($enabledMemberGroups as $memberGroup) { ?>
                <li>
                    <?php echo sr_e((string) $memberGroup['group_key']); ?>
                    - <?php echo sr_e((string) $memberGroup['title']); ?>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section>
        <h2>레벨</h2>
        <p>
            <label>
                <input type="checkbox" name="level_enabled" value="1"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?>>
                커뮤니티 레벨 사용
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="level_auto_recalculate" value="1"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?>>
                게시글/댓글 활동 후 레벨 자동 재계산
            </label>
        </p>
        <p>
            <label>게시글 점수<br>
                <input type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>">
            </label>
        </p>
        <p>
            <label>댓글 점수<br>
                <input type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>">
            </label>
        </p>
        <p>
            <label>그룹+레벨 판정<br>
                <select name="access_condition_priority">
                    <?php foreach (sr_community_access_condition_priority_values() as $priority) { ?>
                        <option value="<?php echo sr_e($priority); ?>"<?php echo $priority === (string) $settings['access_condition_priority'] ? ' selected' : ''; ?>><?php echo sr_e($priority); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
    </section>

    <section>
        <h2>쪽지</h2>
        <p>
            <label>발송 정책<br>
                <select name="message_write_policy">
                    <?php foreach (sr_community_message_write_policy_values() as $policy) { ?>
                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === (string) $settings['message_write_policy'] ? ' selected' : ''; ?>><?php echo sr_e($policy); ?></option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <p>
            <label>발송 그룹 key<br>
                <input type="text" name="message_write_group_keys" maxlength="1000" value="<?php echo sr_e($messageWriteGroupKeysValue); ?>" placeholder="regular_member, vip">
            </label>
        </p>
        <p>
            <label>발송 최소 레벨<br>
                <input type="number" name="message_write_min_level" min="0" max="<?php echo sr_e((string) sr_community_max_level_value()); ?>" value="<?php echo sr_e((string) $settings['message_write_min_level']); ?>">
            </label>
        </p>
    </section>

    <section>
        <h2>화면</h2>
        <p>
            <label>커뮤니티 테마<br>
                <select name="theme_key">
                    <?php foreach ($communityThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) $settings['theme_key'] === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
    </section>

    <button type="submit">설정 저장</button>
</form>

<section>
    <h2>레벨 정의</h2>
    <?php if ($levels === []) { ?>
        <p>레벨 테이블이 없거나 정의된 레벨이 없습니다.</p>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_level_definitions">
            <table>
                <thead>
                    <tr>
                        <th>레벨</th>
                        <th>이름</th>
                        <th>최소 점수</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($levels as $level) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $level['level_value']); ?></td>
                            <td><?php echo sr_e((string) $level['title']); ?></td>
                            <td>
                                <input
                                    type="number"
                                    name="level_min_score[<?php echo sr_e((string) $level['id']); ?>]"
                                    min="0"
                                    max="1000000000"
                                    value="<?php echo sr_e((string) $level['min_score']); ?>"
                                >
                            </td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $level['status'], 'content_status')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <button type="submit">레벨 정의 저장</button>
        </form>
    <?php } ?>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="recalculate_levels">
        <button type="submit">최근 회원 레벨 재계산</button>
    </form>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
