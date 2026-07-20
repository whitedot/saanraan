<?php
$finishResult = isset($finishResult) && is_string($finishResult) ? $finishResult : '';
$finishPurpose = isset($finishPurpose) && is_string($finishPurpose) ? $finishPurpose : '';
$finishIdentitySnapshot = isset($finishIdentitySnapshot) && is_array($finishIdentitySnapshot) ? sr_identity_verification_identity_snapshot($finishIdentitySnapshot) : [];
$finishTitle = '본인확인 결과';
$finishHeading = '본인확인 결과를 확인했습니다';
$finishMessage = '잠시만 기다려 주세요. 원래 화면으로 돌아갑니다.';
if ($finishResult === 'success') {
    $finishTitle = '본인확인 완료';
    $finishHeading = '본인확인이 완료되었습니다';
} elseif ($finishResult === 'expired') {
    $finishTitle = '본인확인 만료';
    $finishHeading = '본인확인 시간이 만료되었습니다';
    $finishMessage = '원래 화면으로 돌아가 다시 시도해 주세요.';
} elseif ($finishResult === 'canceled') {
    $finishTitle = '본인확인 취소';
    $finishHeading = '본인확인이 취소되었습니다';
    $finishMessage = '원래 화면으로 돌아갑니다.';
} elseif ($finishResult === 'duplicate') {
    $finishTitle = '본인확인 중복';
    $finishHeading = '이미 사용 중인 본인확인 정보입니다';
    $finishMessage = '원래 화면으로 돌아가 안내를 확인해 주세요.';
} elseif ($finishResult === 'failed') {
    $finishTitle = '본인확인 실패';
    $finishHeading = '본인확인을 완료하지 못했습니다';
    $finishMessage = '원래 화면으로 돌아가 다시 시도해 주세요.';
}
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo sr_e($finishTitle); ?></title>
    <?php echo sr_stylesheet_tag(); ?>
</head>
<body>
    <main class="ui-page identity-verification-transfer">
        <section class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo sr_e($finishHeading); ?></h1>
        </div>
        <div class="card-body ui-card-body-stack">
        <p><?php echo sr_e($finishMessage); ?></p>
        <p><a class="btn btn-solid-primary" href="<?php echo sr_e($finishUrl); ?>"><?php echo sr_e('원래 화면으로 돌아가기'); ?></a></p>
        </div>
        </section>
    </main>
    <script>
    (function () {
        var finishUrl = <?php echo sr_js_json_encode((string) $finishUrl); ?>;
        var resultPayload = <?php echo sr_js_json_encode([
            'result' => $finishResult,
            'purpose' => $finishPurpose,
            'identity' => $finishIdentitySnapshot,
            'created_at' => time(),
        ]); ?>;
        try {
            if (resultPayload.result === 'success') {
                window.sessionStorage.setItem('sr_identity_verification_result', JSON.stringify(resultPayload));
                if (window.opener && !window.opener.closed && window.opener.sessionStorage) {
                    window.opener.sessionStorage.setItem('sr_identity_verification_result', JSON.stringify(resultPayload));
                }
            }
        } catch (error) {
        }
        if (window.opener && !window.opener.closed) {
            window.opener.location.href = finishUrl;
            window.close();
            return;
        }

        window.location.replace(finishUrl);
    })();
    </script>
</body>
</html>
