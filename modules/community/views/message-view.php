<?php

$pageTitle = '쪽지 보기';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/message?id=' . (string) $message['id'],
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
        <p>
            <a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a>
            /
            <a href="<?php echo toy_e(toy_url($messageBox === 'sent' ? '/community/messages?box=sent' : '/community/messages')); ?>">
                <?php echo $messageBox === 'sent' ? '보낸 쪽지함' : '받은 쪽지함'; ?>
            </a>
        </p>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <dl>
            <dt>보낸 회원</dt>
            <dd><?php echo toy_e(toy_community_message_account_label(
                is_string($message['sender_display_name'] ?? null) ? $message['sender_display_name'] : null,
                (int) $message['sender_account_id']
            )); ?></dd>
            <dt>받는 회원</dt>
            <dd><?php echo toy_e(toy_community_message_account_label(
                is_string($message['recipient_display_name'] ?? null) ? $message['recipient_display_name'] : null,
                (int) $message['recipient_account_id']
            )); ?></dd>
            <dt>보낸 시각</dt>
            <dd><?php echo toy_e((string) $message['created_at']); ?></dd>
            <dt>읽은 시각</dt>
            <dd><?php echo toy_e((string) ($message['read_at'] ?? '')); ?></dd>
        </dl>
        <div>
            <?php echo toy_community_plain_text_html((string) $message['body_text']); ?>
        </div>

        <?php if ($reportNotice !== '') { ?>
            <p><?php echo toy_e($reportNotice); ?></p>
        <?php } ?>

        <?php if ($reportErrors !== []) { ?>
            <ul>
                <?php foreach ($reportErrors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo toy_e(toy_url('/community/report')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="target_type" value="message">
            <input type="hidden" name="target_id" value="<?php echo toy_e((string) $message['id']); ?>">
            <p>
                <label>신고 사유<br>
                    <select name="reason_key" required>
                        <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                            <option value="<?php echo toy_e($reasonKey); ?>"><?php echo toy_e(toy_community_report_reason_label($reasonKey)); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>신고 메모<br>
                    <textarea name="memo_text" rows="3" cols="60"></textarea>
                </label>
            </p>
            <button type="submit">쪽지 신고</button>
        </form>

        <?php if ($replyAccountHash !== '') { ?>
            <p><a href="<?php echo toy_e(toy_url('/community/message/write?to_account=' . rawurlencode($replyAccountHash))); ?>">답장 쓰기</a></p>
        <?php } ?>
        <form method="post" action="<?php echo toy_e(toy_url('/community/message/delete')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="message_id" value="<?php echo toy_e((string) $message['id']); ?>">
            <button type="submit">삭제</button>
        </form>
    </main>
</body>
</html>
