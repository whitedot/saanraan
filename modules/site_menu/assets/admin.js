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
            var hasModuleTypes = false;
            Array.prototype.slice.call(assetTypeSelect.options).forEach(function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                var visible = selectedModule !== '' && option.dataset.siteMenuAssetModule === selectedModule;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) {
                    hasModuleTypes = true;
                    hasVisibleTypes = true;
                }
            });
            if (assetTypeSelect.options[0]) {
                assetTypeSelect.options[0].textContent = selectedModule
                    ? (hasModuleTypes ? '종류 선택' : '연결 가능한 대상 없음')
                    : '서비스를 먼저 선택';
            }

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
                    option.textContent = selectedAssetType ? '대상 선택' : '종류를 먼저 선택';
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

        document.addEventListener('submit', function (event) {
            var publishForm = event.target && event.target.closest ? event.target.closest('#site-menu-publish-form') : null;
            if (publishForm) {
                Array.prototype.slice.call(publishForm.querySelectorAll('[data-site-menu-publish-sort-input]')).forEach(function (input) {
                    input.parentNode.removeChild(input);
                });
                Array.prototype.slice.call(document.querySelectorAll('[data-admin-sort-order]')).forEach(function (input) {
                    if (!input.name) {
                        return;
                    }
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = input.name;
                    hidden.value = input.value;
                    hidden.setAttribute('data-site-menu-publish-sort-input', '1');
                    publishForm.appendChild(hidden);
                });
                return;
            }

            var form = event.target && event.target.closest ? event.target.closest('form[data-site-menu-delete-descendants]') : null;
            if (!form) {
                return;
            }

            var confirmInput = form.querySelector('[data-site-menu-delete-confirm-input]');
            if (confirmInput && confirmInput.value === '1') {
                return;
            }

            var message = form.getAttribute('data-site-menu-delete-message') || '이 항목의 하위 항목도 함께 삭제됩니다. 계속 삭제할까요?';
            if (!window.confirm(message)) {
                event.preventDefault();
                return;
            }

            if (confirmInput) {
                confirmInput.value = '1';
            }
        });
    });
})();
