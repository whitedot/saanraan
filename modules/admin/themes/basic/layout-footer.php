            <?php sr_admin_flush_content_capture(); ?>
        </div>

        <noscript>
            <p><?php echo sr_e(sr_t('admin::ui.page.24650d53')); ?></p>
        </noscript>

        <footer id="ft" class="admin-footer">
            <p class="admin-footer-inner">
                <span class="admin-footer-copy">&copy; <?php echo sr_e((string) ($adminShell['site_title'] ?? sr_t('admin::ui.text.7f03504e'))); ?>.</span>
                <button type="button" class="admin-footer-scroll-top scroll_top"><span>TOP</span></button>
            </p>
        </footer>
    </div>

    <div id="adminPopupContainer" class="admin-popup-container">
        <div id="popupOverlay" class="admin-popup-overlay is-hidden hidden">
            <div class="admin-popup-dialog">
                <div class="admin-popup-header">
                    <strong id="popupTitle" class="admin-popup-title"></strong>
                    <button type="button" class="admin-popup-close popup-close-btn" data-popup-close="popupOverlay">
                        <span><?php echo sr_e(sr_t('admin::ui.close.5eb0f352')); ?></span>
                    </button>
                </div>
                <div id="popupBody" class="admin-popup-body"></div>
                <div id="popupFooter" class="admin-popup-footer"></div>
            </div>
        </div>
    </div>

    <?php echo sr_admin_shell_script_tag(); ?>
</body>
</html>
