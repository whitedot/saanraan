<?php

$pageTitle = $box === 'sent' ? '보낸 쪽지' : '받은 쪽지';
$seo = [
    'title' => $pageTitle,
    'canonical' => $box === 'sent' ? '/messages?box=sent' : '/messages',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'consumer_domain' => 'message',
    'module_home_url' => sr_url('/messages'),
    'module_label' => '쪽지',
]);
?>
    <main class="message-screen">
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p>
            <a href="<?php echo sr_e(sr_url('/messages')); ?>">받은 쪽지</a>
            /
            <a href="<?php echo sr_e(sr_url('/messages?box=sent')); ?>">보낸 쪽지</a>
            /
            <a href="<?php echo sr_e(sr_url('/message/write')); ?>">쪽지 쓰기</a>
        </p>

        <?php echo sr_public_feedback_toasts('message', $notice, []); ?>

        <?php if ($messages === []) { ?>
            <p>표시할 쪽지가 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo sr_e($box === 'sent' ? '수신자' : '발신자'); ?></th>
                        <th>상태</th>
                        <th>작성일</th>
                        <th>보기</th>
                        <th>삭제</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message) { ?>
                        <tr>
                            <td>
                                <?php echo sr_e(sr_message_account_label(
                                    is_string($message['other_display_name'] ?? null) ? $message['other_display_name'] : null,
                                    $box === 'sent' ? (int) $message['recipient_account_id'] : (int) $message['sender_account_id'],
                                    $canViewMemberIdentifiers,
                                    $config,
                                    is_string($message['other_account_status'] ?? null) ? $message['other_account_status'] : null
                                )); ?>
                            </td>
                            <td><?php echo sr_e($box === 'sent' ? ((string) ($message['read_at'] ?? '') === '' ? '읽지 않음' : '읽음') : ((string) ($message['read_at'] ?? '') === '' ? '새 쪽지' : '읽음')); ?></td>
                            <td><?php echo sr_message_time_html((string) $message['created_at']); ?></td>
                            <td><a href="<?php echo sr_e(sr_url('/message?id=' . (string) $message['id'])); ?>">보기</a></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/message/delete')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="message_id" value="<?php echo sr_e((string) $message['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
