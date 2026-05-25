<?php

$pageTitle = sr_t('community::ui.nickname.setup.title');
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/nickname',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <p><a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><?php echo sr_e(sr_t('community::ui.nickname.setup.body')); ?></p>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/community/nickname')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="next" value="<?php echo sr_e($nextPath); ?>">
            <p>
                <label for="modules_community_nickname_setup_nickname">
                    <span><?php echo sr_e(sr_t('community::ui.nickname')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_community_nickname_setup_nickname" type="text" name="nickname" value="<?php echo sr_e((string) ($values['nickname'] ?? '')); ?>" maxlength="80" required>
                </label>
            </p>
            <p>
                <button type="submit"><?php echo sr_e(sr_t('community::ui.nickname.setup.submit')); ?></button>
            </p>
        </form>
    </main>
<?php sr_public_layout_end(); ?>
