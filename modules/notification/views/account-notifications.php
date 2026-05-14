<?php

$pageTitle = '알림';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/notifications'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1>알림</h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <section>
            <h2>요약</h2>
            <dl>
                <dt>전체</dt>
                <dd><?php echo sr_e((string) $notificationSummary['total']); ?></dd>
                <dt>읽지 않음</dt>
                <dd><?php echo sr_e((string) $notificationSummary['unread']); ?></dd>
            </dl>
        </section>

        <form method="get" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
            <p>
                <label>
                    <span>상태</span>
                    <select name="status">
                        <?php foreach (['' => '전체', 'unread' => '읽지 않음', 'read' => '읽음'] as $value => $label) { ?>
                            <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['status'] === (string) $value ? ' selected' : ''; ?>>
                                <?php echo sr_e($label); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <button type="submit">조회</button>
        </form>

        <?php if ($notifications === []) { ?>
            <p>알림이 없습니다.</p>
        <?php } else { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="mark_all_read">
                <button type="submit">모두 읽음</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>내용</th>
                        <th>상태</th>
                        <th>생성일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification) { ?>
                        <?php $notificationLinkAttributes = sr_notification_link_attributes((string) ($notification['link_url'] ?? '')); ?>
                        <tr>
                            <td>
                                <?php if ($notificationLinkAttributes !== '') { ?>
                                    <a<?php echo $notificationLinkAttributes; ?>><?php echo sr_e((string) $notification['title']); ?></a>
                                <?php } else { ?>
                                    <?php echo sr_e((string) $notification['title']); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo nl2br(sr_e((string) ($notification['body_text'] ?? ''))); ?></td>
                            <td><?php echo sr_e((string) $notification['status']); ?></td>
                            <td><?php echo sr_e((string) $notification['created_at']); ?></td>
                            <td>
                                <?php if ($notification['read_at'] === null) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/account/notifications')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notification['id']); ?>">
                                        <button type="submit">읽음</button>
                                    </form>
                                <?php } else { ?>
                                    읽음
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
