<?php

$pageTitle = $box === 'sent' ? '보낸 쪽지함' : '받은 쪽지함';
$seo = [
    'title' => $pageTitle,
    'canonical' => $box === 'sent' ? '/community/messages?box=sent' : '/community/messages',
    'robots' => 'noindex, nofollow',
];
?>
<!doctype html>
<html lang="<?php echo toy_e(toy_locale()); ?>">
<head>
    <meta charset="utf-8">
    <?php echo toy_seo_tags($seo, $site ?? null); ?>
    <?php echo toy_stylesheet_tag(); ?>
</head>
<body>
    <main>
        <p><a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a></p>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <p>
            <a href="<?php echo toy_e(toy_url('/community/messages')); ?>">받은 쪽지함</a>
            /
            <a href="<?php echo toy_e(toy_url('/community/messages?box=sent')); ?>">보낸 쪽지함</a>
            /
            <a href="<?php echo toy_e(toy_url('/community/message/write')); ?>">쪽지 쓰기</a>
        </p>

        <?php if ($notice !== '') { ?>
            <p><?php echo toy_e($notice); ?></p>
        <?php } ?>

        <?php if ($messages === []) { ?>
            <p>쪽지가 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $box === 'sent' ? '받는 회원' : '보낸 회원'; ?></th>
                        <th>상태</th>
                        <th>일시</th>
                        <th>보기</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message) { ?>
                        <tr>
                            <td>
                                <?php echo toy_e(toy_community_message_account_label(
                                    is_string($message['other_display_name'] ?? null) ? $message['other_display_name'] : null,
                                    $box === 'sent' ? (int) $message['recipient_account_id'] : (int) $message['sender_account_id']
                                )); ?>
                            </td>
                            <td><?php echo $box === 'sent' ? ((string) ($message['read_at'] ?? '') === '' ? '읽지 않음' : '읽음') : ((string) ($message['read_at'] ?? '') === '' ? '새 쪽지' : '읽음'); ?></td>
                            <td><?php echo toy_e((string) $message['created_at']); ?></td>
                            <td><a href="<?php echo toy_e(toy_url('/community/message?id=' . (string) $message['id'])); ?>">보기</a></td>
                            <td>
                                <form method="post" action="<?php echo toy_e(toy_url('/community/message/delete')); ?>">
                                    <?php echo toy_csrf_field(); ?>
                                    <input type="hidden" name="message_id" value="<?php echo toy_e((string) $message['id']); ?>">
                                    <button type="submit">삭제</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
</body>
</html>
