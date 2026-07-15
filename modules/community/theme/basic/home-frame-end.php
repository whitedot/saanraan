        </section>
        <?php if (!empty($communityFrameSummaryEnabled)) { ?>
            <?php if (!empty($communityFrameSummaryDeferred)) { ?>
                <?php include SR_ROOT . '/modules/community/theme/basic/home-summary-deferred.php'; ?>
            <?php } else { ?>
                <?php include SR_ROOT . '/modules/community/theme/basic/home-summary-aside.php'; ?>
            <?php } ?>
        <?php } ?>
    </div>
</main>
