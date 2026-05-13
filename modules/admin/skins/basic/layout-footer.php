            <?php sr_admin_flush_content_capture(); ?>
        </div>

        <noscript>
            <p>이 페이지는 JavaScript가 활성화되어야 일부 기능이 정상 동작합니다.</p>
        </noscript>

        <footer id="ft" class="admin-footer">
            <p class="admin-footer-inner">
                <span class="admin-footer-copy">Copyright &copy; <?php echo sr_e((string) date('Y')); ?> <?php echo sr_e((string) ($adminShell['site_title'] ?? '산란')); ?>. All rights reserved.</span>
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
                        <span>팝업 닫기</span>
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
