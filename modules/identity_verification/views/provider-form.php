<?php
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo sr_e('본인확인 이동'); ?></title>
    <?php echo sr_stylesheet_tag(); ?>
</head>
<body>
    <main class="ui-page identity-verification-transfer">
        <section class="card">
        <div class="card-header">
            <h1 class="card-title"><?php echo sr_e('본인확인으로 이동합니다'); ?></h1>
        </div>
        <div class="card-body ui-card-body-stack">
        <p><?php echo sr_e('잠시만 기다려 주세요. 자동으로 이동하지 않으면 아래 버튼을 눌러 주세요.'); ?></p>
        <form id="identity-provider-form" name="form_auth" method="<?php echo sr_e(strtolower($method)); ?>" action="<?php echo sr_e($action); ?>" data-identity-provider-form>
            <?php foreach ($fields as $name => $value) { ?>
                <?php if (is_scalar($value)) { ?>
                    <input type="hidden" name="<?php echo sr_e((string) $name); ?>" value="<?php echo sr_e((string) $value); ?>">
                <?php } ?>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary" data-identity-provider-submit><?php echo sr_e('본인확인 계속'); ?></button>
        </form>
        <noscript>
            <p><?php echo sr_e('브라우저에서 JavaScript가 꺼져 있으면 본인확인 계속 버튼을 눌러 주세요.'); ?></p>
        </noscript>
        </div>
        </section>
    </main>
    <script>
    (function () {
        var form = document.forms.form_auth || document.getElementById('identity-provider-form');
        var submitButton = document.querySelector('[data-identity-provider-submit]');

        function submitProviderForm() {
            if (!form) {
                return;
            }

            HTMLFormElement.prototype.submit.call(form);
        }

        if (submitButton) {
            submitButton.addEventListener('click', function (event) {
                event.preventDefault();
                submitProviderForm();
            });
        }

        window.setTimeout(submitProviderForm, 120);
    })();
    </script>
</body>
</html>
