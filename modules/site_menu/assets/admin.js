(function () {
    'use strict';

    var ready = function (callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    };

    ready(function () {
        var syncAssetTypeSelect = function (form, resetValue) {
            var moduleSelect = form.querySelector('[data-site-menu-module-select]');
            var assetTypeSelect = form.querySelector('[data-site-menu-asset-type-select]');
            if (!moduleSelect || !assetTypeSelect) {
                return;
            }

            var selectedModule = moduleSelect.value || '';
            var hasVisibleTypes = false;
            Array.prototype.slice.call(assetTypeSelect.options).forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    option.textContent = selectedModule ? '종류 선택' : '서비스를 먼저 선택';
                    return;
                }

                var visible = selectedModule !== '' && option.dataset.siteMenuAssetModule === selectedModule;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) {
                    hasVisibleTypes = true;
                }
            });

            assetTypeSelect.disabled = !selectedModule || !hasVisibleTypes;
            if (resetValue || assetTypeSelect.disabled || (assetTypeSelect.selectedOptions[0] && assetTypeSelect.selectedOptions[0].disabled)) {
                assetTypeSelect.value = '';
            }
        };

        var syncAssetSelect = function (form, resetValue) {
            var moduleSelect = form.querySelector('[data-site-menu-module-select]');
            var assetTypeSelect = form.querySelector('[data-site-menu-asset-type-select]');
            var assetSelect = form.querySelector('[data-site-menu-asset-select]');
            if (!moduleSelect || !assetTypeSelect || !assetSelect) {
                return;
            }

            var selectedModule = moduleSelect.value || '';
            var selectedAssetType = assetTypeSelect.value || '';
            var hasVisibleAssets = false;
            Array.prototype.slice.call(assetSelect.options).forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    option.textContent = selectedAssetType ? '자산 선택' : '종류를 먼저 선택';
                    return;
                }

                var visible = selectedModule !== ''
                    && selectedAssetType !== ''
                    && option.dataset.siteMenuAssetModule === selectedModule
                    && option.dataset.siteMenuAssetType === selectedAssetType;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) {
                    hasVisibleAssets = true;
                }
            });

            assetSelect.disabled = !selectedModule || !selectedAssetType || !hasVisibleAssets;
            if (resetValue || assetSelect.disabled || (assetSelect.selectedOptions[0] && assetSelect.selectedOptions[0].disabled)) {
                assetSelect.value = '';
            }
        };

        Array.prototype.slice.call(document.querySelectorAll('[data-site-menu-module-select]')).forEach(function (moduleSelect) {
            var form = moduleSelect.closest('form');
            if (form) {
                syncAssetTypeSelect(form, false);
                syncAssetSelect(form, false);
            }
        });

        document.addEventListener('change', function (event) {
            var moduleSelect = event.target && event.target.closest ? event.target.closest('[data-site-menu-module-select]') : null;
            if (moduleSelect) {
                var moduleForm = moduleSelect.closest('form');
                if (moduleForm) {
                    syncAssetTypeSelect(moduleForm, true);
                    syncAssetSelect(moduleForm, true);
                }
                return;
            }

            var assetTypeSelect = event.target && event.target.closest ? event.target.closest('[data-site-menu-asset-type-select]') : null;
            if (assetTypeSelect) {
                var assetTypeForm = assetTypeSelect.closest('form');
                if (assetTypeForm) {
                    syncAssetSelect(assetTypeForm, true);
                }
                return;
            }

            var select = event.target && event.target.closest ? event.target.closest('[data-site-menu-asset-select]') : null;
            if (!select) {
                return;
            }
            var option = select.options[select.selectedIndex];
            if (!option || !option.value) {
                return;
            }

            var form = select.closest('form');
            if (!form) {
                return;
            }

            var labelInput = form.querySelector('[data-site-menu-label-input]');
            var urlInput = form.querySelector('[data-site-menu-url-input]');
            if (labelInput && option.dataset.siteMenuAssetLabel) {
                labelInput.value = option.dataset.siteMenuAssetLabel;
            }
            if (urlInput && option.dataset.siteMenuAssetUrl) {
                urlInput.value = option.dataset.siteMenuAssetUrl;
            }
        });
    });
})();
