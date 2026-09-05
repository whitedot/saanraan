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

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest ? event.target.closest('[data-overlay]') : null;
            var selector = trigger ? trigger.getAttribute('data-overlay') : '';
            if (!selector || selector.indexOf('#site_menu_edit_item_') !== 0) {
                return;
            }
            var modal = document.querySelector(selector);
            var id = selector.slice('#site_menu_edit_item_'.length);
            var order = document.querySelector('[name="item_sort_order[' + id + ']"]');
            var modalOrder = modal ? modal.querySelector('[name="sort_order"]') : null;
            if (order && modalOrder) {
                modalOrder.value = order.value;
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-admin-sort-order]')) {
                var pending = document.querySelector('[data-site-menu-pending]');
                if (pending) {
                    pending.textContent = '임시저장되지 않은 변경이 있습니다.';
                }
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches('form[data-site-menu-editor-form]') || event.defaultPrevented) {
                return;
            }
            var intent = form.querySelector('[name="intent"]');
            if (intent && intent.value === 'discard_changes') {
                return;
            }
            if (intent && intent.value === 'delete_draft') {
                var draftConfirm = form.querySelector('[data-site-menu-delete-draft-confirm]');
                if (!draftConfirm) {
                    event.preventDefault();
                    return;
                }
                if (draftConfirm.value !== '1') {
                    if (!window.confirm(form.getAttribute('data-site-menu-delete-message'))) {
                        event.preventDefault();
                        return;
                    }
                    draftConfirm.value = '1';
                }
            }
            var confirmInput = form.querySelector('[data-site-menu-delete-confirm-input]');
            if (form.hasAttribute('data-site-menu-delete-descendants') && confirmInput && confirmInput.value !== '1') {
                var message = form.getAttribute('data-site-menu-delete-message') || '하위 항목도 함께 편집 목록에서 삭제할까요?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
                confirmInput.value = '1';
            }

            // Every operation carries the current list order through its POST/redirect.
            Array.prototype.slice.call(form.querySelectorAll('[data-site-menu-order-copy]')).forEach(function (input) {
                input.remove();
            });
            if (form.id !== 'site-menu-draft-form' && (!intent || intent.value !== 'delete_draft')) {
                Array.prototype.slice.call(document.querySelectorAll('[data-admin-sort-order]')).forEach(function (input) {
                    if (!input.name || input.disabled) {
                        return;
                    }
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = input.name;
                    hidden.value = input.value;
                    hidden.setAttribute('data-site-menu-order-copy', '1');
                    form.appendChild(hidden);
                });
            }
            // Keep the submitted payload intact, then prevent accidental double submits.
            window.setTimeout(function () {
                if (event.defaultPrevented) {
                    return;
                }
                if (event.submitter && intent && ['save_draft', 'publish_site_menus', 'delete_draft'].indexOf(intent.value) !== -1) {
                    event.submitter.textContent = intent.value === 'delete_draft' ? '삭제 중…' : '저장 중…';
                }
                Array.prototype.slice.call(document.querySelectorAll('button[type="submit"]')).forEach(function (button) {
                    button.disabled = true;
                });
            }, 0);
        });
    });
})();
