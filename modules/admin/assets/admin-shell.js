// 관리자 공통 shell 동작.
// sidebar, profile menu, mobile overlay 같은 레이아웃 상태만 담당하고, 화면별 업무 로직은 각 admin-*.js로 분리한다.
window.AdminShell = {
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        const menuStorageKey = 'sr_admin_sidebar_collapsed';
        const menuCollapseStorageKey = 'sr_admin_menu_editor_collapsed';
        const mobileQuery = window.matchMedia('(max-width: 1023px)');
        const body = document.body;
        const gnb = document.getElementById('gnb');
        const container = document.getElementById('container');
        const desktopToggle = document.getElementById('btn_gnb');
        const mobileToggle = document.getElementById('btn_gnb_mobile');
        const sidebarBackdrop = document.getElementById('adminSidebarBackdrop');
        const scrollWrap = document.querySelector('#gnb .gnb_menu_scroll_wrap');
        const menuScroll = document.getElementById('gnbMenuScroll');
        const scrollbar = scrollWrap ? scrollWrap.querySelector('.gnb_scrollbar') : null;
        const scrollThumb = scrollWrap ? scrollWrap.querySelector('.gnb_scrollbar_thumb') : null;
        const themeToggle = document.getElementById('admin_theme_toggle');
        const themeToggleIcon = document.getElementById('admin_theme_toggle_icon');
        const navRoot = document.getElementById('adminNavList');
        const scrollTopButton = document.querySelector('.admin-footer-scroll-top');
        const toastStack = document.querySelector('[data-admin-toast-stack]');
        const colorSchemeSelect = document.querySelector('[data-admin-color-scheme-select]');
        const menuResetButton = document.querySelector('[data-admin-menu-reset-confirm]');
        const menuResetConfirmedInput = document.querySelector('[data-admin-menu-reset-confirmed]');
        const sortableRows = Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));
        const tabRoot = document.querySelector('[data-admin-tabs]');
        const memberRuleDefinitions = Array.prototype.slice.call(document.querySelectorAll('[data-member-rule-definition]'));
        const dateQuickButtons = Array.prototype.slice.call(document.querySelectorAll('[data-datetime-target]'));
        const dashboardSectionsRoot = document.querySelector('[data-admin-dashboard-sections]');
        const assetEnablePreviousValues = new WeakMap();
        const assetEnableTouchedRoots = new WeakSet();
        let hideScrollbarTimer = null;
        let themeSaving = false;

        const normalizeKeyInputValue = value => value.toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '');
        const normalizeSlugInputValue = value => value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+/, '');
        const assetAmountDigits = value => value.replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '').slice(0, 9);
        const formatAssetAmountValue = value => {
            const digits = assetAmountDigits(value);
            return digits === '' ? '' : digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        const syncAssetAmountInputValue = input => {
            if (!input || input.readOnly || input.disabled) {
                return;
            }

            const nextValue = formatAssetAmountValue(input.value);
            if (input.value !== nextValue) {
                input.value = nextValue;
            }
        };

        const stripAssetAmountInputValue = input => {
            if (!input) {
                return;
            }

            const digits = assetAmountDigits(input.value);
            input.value = digits === '' ? '0' : digits;
        };

        const assetEnableControl = target => {
            if (!target || !target.closest || !target.matches) {
                return null;
            }

            if (target.matches('[data-admin-asset-enable-target], [data-admin-asset-enable-target] input, [data-admin-asset-enable-target] select')) {
                return target.matches('input, select') ? target : null;
            }

            return null;
        };

        const assetEnableRoot = control => control
            ? (control.matches('[data-admin-asset-enable-target]')
                ? control
                : control.closest('[data-admin-asset-enable-target]'))
            : null;

        const markAssetEnableTouched = control => {
            const root = assetEnableRoot(control);
            if (root) {
                assetEnableTouchedRoots.add(root);
            }
        };

        const assetEnableTargetInput = control => {
            const root = assetEnableRoot(control);
            const selector = root
                ? root.getAttribute('data-admin-asset-enable-target')
                : control.getAttribute('data-admin-asset-enable-target');

            return selector ? document.querySelector(selector) : null;
        };

        const rememberAssetEnableValue = control => {
            if (!control || control.tagName !== 'SELECT') {
                return;
            }

            assetEnablePreviousValues.set(control, control.value);
        };

        const restoreAssetEnableSelection = control => {
            if (!control) {
                return;
            }

            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = false;
                return;
            }

            if (control.tagName === 'SELECT') {
                control.value = assetEnablePreviousValues.has(control)
                    ? String(assetEnablePreviousValues.get(control))
                    : '';
            }
        };

        const assetEnableSelectionActive = control => {
            if (!control) {
                return false;
            }

            if (control.type === 'checkbox' || control.type === 'radio') {
                return control.checked;
            }

            if (control.tagName === 'SELECT') {
                return control.value !== '';
            }

            return false;
        };

        const assetEnableRootSelectionActive = root => {
            if (!root) {
                return false;
            }

            const controls = root.matches('input, select')
                ? [root]
                : Array.prototype.slice.call(root.querySelectorAll('input, select'));
            return controls.some(control => !control.disabled && assetEnableSelectionActive(control));
        };

        const markAssetEnableTargetTouched = target => {
            if (!target || !target.matches || !target.matches('input')) {
                return;
            }

            Array.prototype.slice.call(document.querySelectorAll('[data-admin-asset-enable-target]')).forEach(root => {
                if (assetEnableTargetInput(root) === target) {
                    assetEnableTouchedRoots.add(root);
                }
            });
        };

        const confirmAssetEnableSelection = control => {
            markAssetEnableTouched(control);
            const enabledInput = assetEnableTargetInput(control);
            const root = assetEnableRoot(control);
            if (!enabledInput) {
                rememberAssetEnableValue(control);
                return;
            }

            if (!assetEnableSelectionActive(control)) {
                if (enabledInput.checked && !assetEnableRootSelectionActive(root)) {
                    enabledInput.checked = false;
                    enabledInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                rememberAssetEnableValue(control);
                return;
            }

            if (enabledInput.checked) {
                rememberAssetEnableValue(control);
                return;
            }

            const message = (root ? root.getAttribute('data-admin-asset-enable-confirm') : '')
                || '포인트/금액 항목을 선택하면 이 항목의 사용/과금 체크가 함께 켜집니다. 계속할까요?';
            if (!window.confirm(message)) {
                restoreAssetEnableSelection(control);
                rememberAssetEnableValue(control);
                return;
            }

            enabledInput.checked = true;
            enabledInput.dispatchEvent(new Event('change', { bubbles: true }));
            rememberAssetEnableValue(control);
        };

        const confirmAssetEnableSubmit = form => {
            if (!form || !form.querySelectorAll) {
                return true;
            }

            const roots = Array.prototype.slice.call(form.querySelectorAll('[data-admin-asset-enable-target]'));
            const hasDisabledAssetSelection = roots.some(root => {
                const enabledInput = assetEnableTargetInput(root);
                const alwaysCheck = root.getAttribute('data-admin-asset-enable-submit-check') === 'always';
                return (alwaysCheck || assetEnableTouchedRoots.has(root))
                    && enabledInput
                    && !enabledInput.checked
                    && assetEnableRootSelectionActive(root);
            });
            if (!hasDisabledAssetSelection) {
                return true;
            }

            const message = '사용/과금 체크가 꺼진 항목에 선택된 포인트/금액 항목이 있습니다. 저장하면 해당 선택은 적용되지 않습니다. 그래도 저장할까요?';
            return window.confirm(message);
        };

        const ensureToastStack = () => {
            let stack = document.querySelector('[data-admin-toast-stack]');
            if (stack) {
                return stack;
            }

            stack = document.createElement('div');
            stack.setAttribute('data-admin-toast-stack', '');
            stack.setAttribute('role', 'status');
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'false');
            document.body.appendChild(stack);
            return stack;
        };

        const showAdminToast = message => {
            if (!message) {
                return;
            }

            const stack = ensureToastStack();
            const toast = document.createElement('div');
            toast.className = 'admin-flash-message admin-flash-message-notice alert alert-secondary';
            toast.setAttribute('data-admin-toast', '');

            const text = document.createElement('span');
            text.textContent = message;
            toast.appendChild(text);

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn btn-sm btn-icon';
            closeButton.setAttribute('data-admin-toast-close', '');
            closeButton.setAttribute('aria-label', '닫기');
            closeButton.innerHTML = '<span class="sr-icon admin-toast-close-icon" aria-hidden="true" data-sr-material-icon>close</span>';
            closeButton.addEventListener('click', () => {
                toast.classList.add('is-hiding');
                window.setTimeout(() => {
                    toast.remove();
                    if (stack.children.length === 0) {
                        stack.remove();
                    }
                }, 180);
            });
            toast.appendChild(closeButton);

            stack.appendChild(toast);
            window.setTimeout(() => {
                toast.classList.add('is-hiding');
                window.setTimeout(() => {
                    toast.remove();
                    if (stack.children.length === 0) {
                        stack.remove();
                    }
                }, 180);
            }, 4500);
        };

        const assetSingleSelectionActive = root => {
            if (!root || !root.querySelectorAll) {
                return false;
            }

            const selector = root.getAttribute('data-admin-asset-single-when-selector') || '';
            const value = root.getAttribute('data-admin-asset-single-when-value') || '';
            if (!selector || !value) {
                return false;
            }

            const source = document.querySelector(selector);
            return !!source && source.value === value;
        };

        const syncAssetSingleSelectionControls = root => {
            const singleMode = assetSingleSelectionActive(root);
            Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"], input[type="radio"]')).forEach(control => {
                const nextType = singleMode ? 'radio' : 'checkbox';
                if (control.type !== nextType) {
                    control.type = nextType;
                }
                control.classList.toggle('form-radio', singleMode);
                control.classList.toggle('form-checkbox', !singleMode);
            });
        };

        const enforceAssetSingleSelection = (root, changedControl) => {
            if (!root || !root.querySelectorAll) {
                return;
            }

            syncAssetSingleSelectionControls(root);
            if (!assetSingleSelectionActive(root)) {
                return;
            }

            const checkedControls = Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked'));
            if (checkedControls.length <= 1) {
                return;
            }

            const keepControl = changedControl && changedControl.nodeType === 1 && root.contains(changedControl) && changedControl.matches('input[type="checkbox"], input[type="radio"]') && changedControl.checked
                ? changedControl
                : checkedControls[0];
            checkedControls.forEach(control => {
                if (control !== keepControl) {
                    control.checked = false;
                }
            });
        };

        const syncAssetAmountGroup = (root, changedControl) => {
            if (!root || !root.querySelectorAll || !root.closest) {
                return;
            }

            enforceAssetSingleSelection(root, changedControl);

            const line = root.closest('.admin-asset-setting-line');
            const targetRoot = root.closest('.admin-asset-setting-target');
            const context = line || targetRoot || root;
            const selectedModules = new Set();
            if (context) {
                Array.prototype.slice.call(context.querySelectorAll('.admin-asset-setting-target input, .admin-asset-setting-target select, input, select')).forEach(control => {
                    if (control.disabled || !control.value) {
                        return;
                    }

                    if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) {
                        return;
                    }

                    if (control.tagName !== 'SELECT' && control.type !== 'checkbox' && control.type !== 'radio') {
                        return;
                    }

                    selectedModules.add(String(control.value));
                });
            }

            Array.prototype.slice.call(root.querySelectorAll('[data-admin-asset-amount-field]')).forEach(field => {
                const moduleKey = field.getAttribute('data-admin-asset-module') || '';
                field.classList.toggle('is-selected', selectedModules.has(moduleKey));
            });
        };

        const syncAssetAmountGroupsNear = target => {
            const line = target && target.closest ? target.closest('.admin-asset-setting-line') : null;
            const roots = line
                ? Array.prototype.slice.call(line.querySelectorAll('[data-admin-asset-amount-sync]'))
                : Array.prototype.slice.call(document.querySelectorAll('[data-admin-asset-amount-sync]'));
            roots.forEach(root => syncAssetAmountGroup(root, target));
        };

        const syncAssetUnitGroup = root => {
            if (!root || !root.querySelector) {
                return;
            }

            const line = root.closest('.admin-asset-setting-line') || root.parentElement;
            const targetRoot = root.closest('[data-admin-asset-enable-target]');
            let enabledInput = targetRoot ? assetEnableTargetInput(targetRoot) : null;
            let enabled = !enabledInput || enabledInput.checked;
            const select = line ? line.querySelector('[data-admin-asset-unit-select]') : null;
            const label = root.querySelector('[data-admin-asset-unit-label]');
            if (!label) {
                return;
            }

            if (select) {
                const option = select.selectedOptions && select.selectedOptions.length > 0 ? select.selectedOptions[0] : null;
                label.textContent = option ? (option.getAttribute('data-admin-asset-unit') || '') : '';
                root.hidden = !enabled || !select.value;
                return;
            }

            const sourceName = root.getAttribute('data-admin-asset-unit-source') || '';
            let unitOptions = {};
            try {
                unitOptions = JSON.parse(root.getAttribute('data-admin-asset-unit-options') || '{}');
            } catch (error) {
                unitOptions = {};
            }
            const form = root.closest('form') || document;
            const controls = sourceName !== ''
                ? Array.prototype.slice.call(form.querySelectorAll('input, select')).filter(control => control.name === sourceName)
                : [];
            const selected = controls.find(control => {
                if (control.disabled || !control.value) {
                    return false;
                }

                if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) {
                    return false;
                }

                return true;
            });
            const selectedTargetRoot = selected && selected.closest ? selected.closest('[data-admin-asset-enable-target]') : null;
            if (!enabledInput && selectedTargetRoot) {
                enabledInput = assetEnableTargetInput(selectedTargetRoot);
                enabled = !enabledInput || enabledInput.checked;
            }
            label.textContent = selected ? (unitOptions[selected.value] || '') : '';
            root.hidden = !enabled || !selected;
        };

        const syncAssetUnitGroupsNear = target => {
            const line = target && target.closest ? target.closest('.admin-asset-setting-line') : null;
            const form = target && target.closest ? target.closest('form') : null;
            const roots = line
                ? Array.prototype.slice.call(line.querySelectorAll('[data-admin-asset-unit-group]'))
                : (form
                    ? Array.prototype.slice.call(form.querySelectorAll('[data-admin-asset-unit-group]'))
                    : Array.prototype.slice.call(document.querySelectorAll('[data-admin-asset-unit-group]')));
            if (target && target.matches && target.matches('input')) {
                const linkedRoots = Array.prototype.slice.call(document.querySelectorAll('[data-admin-asset-enable-target] [data-admin-asset-unit-group]')).filter(root => {
                    const targetRoot = root.closest('[data-admin-asset-enable-target]');
                    return targetRoot && assetEnableTargetInput(targetRoot) === target;
                });
                const sourceLinkedRoots = Array.prototype.slice.call((form || document).querySelectorAll('[data-admin-asset-unit-group][data-admin-asset-unit-source]')).filter(root => {
                    const sourceName = root.getAttribute('data-admin-asset-unit-source') || '';
                    if (sourceName === '') {
                        return false;
                    }

                    const rootForm = root.closest('form') || document;
                    return Array.prototype.slice.call(rootForm.querySelectorAll('input, select')).some(control => {
                        const sourceRoot = control.name === sourceName && control.closest ? control.closest('[data-admin-asset-enable-target]') : null;
                        return sourceRoot && assetEnableTargetInput(sourceRoot) === target;
                    });
                });
                sourceLinkedRoots.forEach(root => {
                    if (!linkedRoots.includes(root)) {
                        linkedRoots.push(root);
                    }
                });
                linkedRoots.forEach(root => {
                    if (!roots.includes(root)) {
                        roots.push(root);
                    }
                });
            }
            roots.forEach(syncAssetUnitGroup);
        };

        const syncSettingSourceGroup = root => {
            if (!root || !root.querySelectorAll) {
                return;
            }

            const checked = root.querySelector('[data-admin-setting-source-master]:checked');
            if (!checked) {
                return;
            }

            Array.prototype.slice.call(root.querySelectorAll('[data-admin-setting-source-mirror]')).forEach(input => {
                input.value = checked.value;
            });
        };

        const syncRestrictedInputValue = (input, normalizeValue) => {
            if (!input || input.readOnly || input.disabled) {
                return;
            }

            const previousValue = input.value;
            const nextValue = normalizeValue(previousValue);
            if (previousValue === nextValue) {
                return;
            }

            const selectionStart = input.selectionStart;
            const beforeSelection = typeof selectionStart === 'number' ? previousValue.slice(0, selectionStart) : '';
            const nextSelectionStart = typeof selectionStart === 'number'
                ? normalizeValue(beforeSelection).length
                : nextValue.length;
            input.value = nextValue;
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(nextSelectionStart, nextSelectionStart);
            }
        };

        const syncKeyInputValue = input => syncRestrictedInputValue(input, normalizeKeyInputValue);
        const syncSlugInputValue = input => syncRestrictedInputValue(input, normalizeSlugInputValue);

        const isMobileViewport = () => mobileQuery.matches;
        const systemColorSchemeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        const systemPrefersDark = () => systemColorSchemeQuery && systemColorSchemeQuery.matches;

        const resolvedThemeForScheme = scheme => {
            if (scheme === 'dark') {
                return 'dark';
            }

            if (scheme === 'system' && systemPrefersDark()) {
                return 'dark';
            }

            return 'light';
        };

        const applyColorScheme = scheme => {
            const nextScheme = ['light', 'dark', 'system'].includes(scheme) ? scheme : 'light';
            const nextTheme = resolvedThemeForScheme(nextScheme);
            document.documentElement.setAttribute('data-color-scheme', nextScheme);
            document.documentElement.setAttribute('data-theme', nextTheme);
            syncThemeUI();
        };

        const updateMenuScrollbar = () => {
            if (!scrollWrap || !menuScroll || !scrollbar || !scrollThumb) {
                return;
            }

            const scrollHeight = menuScroll.scrollHeight;
            const clientHeight = menuScroll.clientHeight;
            const canScroll = scrollHeight > clientHeight + 1;

            scrollWrap.classList.toggle('is-scrollable', canScroll);

            if (!canScroll) {
                scrollThumb.style.height = '0';
                scrollThumb.style.transform = 'translateY(0)';
                return;
            }

            const trackHeight = scrollbar.getBoundingClientRect().height;
            const thumbHeight = Math.max(28, Math.round(trackHeight * (clientHeight / scrollHeight)));
            const maxThumbTop = Math.max(0, trackHeight - thumbHeight);
            const maxScrollTop = Math.max(1, scrollHeight - clientHeight);
            const thumbTop = Math.round((menuScroll.scrollTop / maxScrollTop) * maxThumbTop);

            scrollThumb.style.height = `${thumbHeight}px`;
            scrollThumb.style.transform = `translateY(${thumbTop}px)`;
        };

        const syncDesktopSidebarState = () => {
            if (!gnb || !container || !desktopToggle) {
                return;
            }

            const collapsed = gnb.classList.contains('gnb_small');
            const desktopCollapsed = !isMobileViewport() && collapsed;
            body.classList.toggle('admin-sidebar-condensed', desktopCollapsed);
            container.classList.toggle('container-small', desktopCollapsed);
            desktopToggle.classList.toggle('btn_gnb_open', desktopCollapsed);
            desktopToggle.setAttribute('aria-pressed', desktopCollapsed ? 'true' : 'false');
        };

        const setDesktopCollapsed = nextCollapsed => {
            try {
                localStorage.setItem(menuStorageKey, nextCollapsed ? '1' : '0');
            } catch (err) {}

            if (gnb) {
                gnb.classList.toggle('gnb_small', nextCollapsed);
            }
            syncDesktopSidebarState();
        };

        const clearSidebarRestoring = () => {
            body.classList.remove('admin-sidebar-restoring');
        };

        const setMobileSidebar = opened => {
            if (!isMobileViewport()) {
                return;
            }

            body.classList.toggle('admin-sidebar-open', opened);
            body.classList.toggle('overflow-hidden', opened);

            if (mobileToggle) {
                mobileToggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('hidden', !opened);
            }
        };

        const showMenuScrollbar = () => {
            if (!scrollWrap || !scrollWrap.classList.contains('is-scrollable')) {
                return;
            }

            clearTimeout(hideScrollbarTimer);
            scrollWrap.classList.add('is-scrollbar-visible');
        };

        const hideMenuScrollbar = delay => {
            if (!scrollWrap) {
                return;
            }

            clearTimeout(hideScrollbarTimer);
            hideScrollbarTimer = window.setTimeout(() => {
                scrollWrap.classList.remove('is-scrollbar-visible');
            }, delay || 140);
        };

        const syncThemeUI = () => {
            if (!themeToggle || !themeToggleIcon) {
                return;
            }

            const dark = document.documentElement.getAttribute('data-theme') === 'dark';
            const nextModeLabel = dark ? '라이트 모드' : '다크 모드';
            const iconName = dark ? 'light_mode' : 'dark_mode';
            themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
            themeToggle.setAttribute('aria-label', `${nextModeLabel} 전환`);
            themeToggle.setAttribute('title', `${nextModeLabel} 전환`);
            themeToggle.disabled = themeSaving;
            themeToggleIcon.textContent = iconName;
        };

        const setNavItemState = (item, opened) => {
            if (!item || !item.querySelector('.admin-nav-panel')) {
                return;
            }

            item.classList.toggle('is-open', opened);

            const panel = item.querySelector('.admin-nav-panel');
            if (panel) {
                panel.classList.toggle('hidden', !opened);
            }

            const trigger = item.querySelector('.admin-nav-trigger');
            if (trigger) {
                trigger.setAttribute('aria-expanded', opened ? 'true' : 'false');
            }
        };

        const closeProfileDropdown = () => {
            const profileDropdown = document.querySelector('#tnb .admin-profile-dropdown[open]');
            if (profileDropdown) {
                profileDropdown.removeAttribute('open');
            }
        };

        document.addEventListener('click', event => {
            const profileDropdown = document.querySelector('#tnb .admin-profile-dropdown[open]');
            if (profileDropdown && !profileDropdown.contains(event.target)) {
                closeProfileDropdown();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeProfileDropdown();
            }
        });

        if (desktopToggle) {
            desktopToggle.addEventListener('click', () => {
                const nextCollapsed = !(gnb && gnb.classList.contains('gnb_small'));
                setDesktopCollapsed(nextCollapsed);
            });
        }

        if (mobileToggle) {
            mobileToggle.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                if (isMobileViewport()) {
                    setMobileSidebar(!body.classList.contains('admin-sidebar-open'));
                    return;
                }

                if (gnb && gnb.classList.contains('gnb_small')) {
                    setDesktopCollapsed(false);
                }
            });
        }

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', () => {
                setMobileSidebar(false);
            });
        }

        window.addEventListener('resize', () => {
            if (!isMobileViewport()) {
                body.classList.remove('admin-sidebar-open', 'overflow-hidden');
                if (mobileToggle) {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                }
                if (sidebarBackdrop) {
                    sidebarBackdrop.classList.add('hidden');
                }
            }

            syncDesktopSidebarState();
            updateMenuScrollbar();
        });

        if (gnb) {
            gnb.addEventListener('click', event => {
                if (isMobileViewport() && event.target.closest('a')) {
                    setMobileSidebar(false);
                }
            });
        }

        if (menuScroll) {
            menuScroll.addEventListener('scroll', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
                hideMenuScrollbar(420);
            });
        }

        if (scrollWrap) {
            scrollWrap.addEventListener('mouseenter', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
            });

            scrollWrap.addEventListener('mouseleave', () => {
                hideMenuScrollbar(120);
            });

            scrollWrap.addEventListener('focusin', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
            });

            scrollWrap.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!scrollWrap.contains(document.activeElement)) {
                        hideMenuScrollbar(120);
                    }
                }, 0);
            });
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                if (themeSaving) {
                    return;
                }

                const previousScheme = document.documentElement.getAttribute('data-color-scheme') || 'light';
                const dark = document.documentElement.getAttribute('data-theme') === 'dark';
                const nextScheme = dark ? 'light' : 'dark';
                const endpoint = themeToggle.getAttribute('data-admin-theme-url') || '';
                const csrfToken = themeToggle.getAttribute('data-admin-theme-csrf') || '';

                applyColorScheme(nextScheme);
                if (colorSchemeSelect) {
                    colorSchemeSelect.value = nextScheme;
                }

                if (!endpoint || !csrfToken || !window.fetch) {
                    return;
                }

                themeSaving = true;
                syncThemeUI();

                const bodyParams = new URLSearchParams();
                bodyParams.set('csrf_token', csrfToken);
                bodyParams.set('ui_color_scheme', nextScheme);

                window.fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: bodyParams.toString(),
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to save color scheme.');
                    }

                    return response.json();
                }).then(payload => {
                    const savedScheme = payload && payload.ui_color_scheme ? payload.ui_color_scheme : nextScheme;
                    applyColorScheme(savedScheme);
                    if (colorSchemeSelect) {
                        colorSchemeSelect.value = savedScheme;
                    }
                }).catch(() => {
                    applyColorScheme(previousScheme);
                    if (colorSchemeSelect) {
                        colorSchemeSelect.value = previousScheme;
                    }
                }).finally(() => {
                    themeSaving = false;
                    syncThemeUI();
                });
            });
        }

        if (colorSchemeSelect) {
            colorSchemeSelect.addEventListener('change', () => {
                applyColorScheme(colorSchemeSelect.value);
            });
        }

        if (systemColorSchemeQuery) {
            const syncSystemColorScheme = () => {
                if (document.documentElement.getAttribute('data-color-scheme') === 'system') {
                    applyColorScheme('system');
                }
            };

            if (typeof systemColorSchemeQuery.addEventListener === 'function') {
                systemColorSchemeQuery.addEventListener('change', syncSystemColorScheme);
            } else if (typeof systemColorSchemeQuery.addListener === 'function') {
                systemColorSchemeQuery.addListener(syncSystemColorScheme);
            }
        }

        if (navRoot) {
            const navItems = Array.prototype.slice.call(navRoot.querySelectorAll('.admin-nav-item'));
            const navToggleItems = navItems.filter(item => item.querySelector('.admin-nav-panel'));
            navToggleItems.forEach(item => {
                setNavItemState(item, item.classList.contains('is-open'));
            });

            navRoot.addEventListener('click', event => {
                const trigger = event.target.closest('.admin-nav-trigger');
                if (!trigger || !navRoot.contains(trigger)) {
                    return;
                }

                if (trigger.classList.contains('admin-nav-direct-link')) {
                    return;
                }

                const activeItem = trigger.closest('.admin-nav-item');
                if (!activeItem) {
                    return;
                }

                const willOpen = !activeItem.classList.contains('is-open');
                navToggleItems.forEach(item => {
                    setNavItemState(item, item === activeItem ? willOpen : false);
                });

                updateMenuScrollbar();
            });
        }

        if (scrollTopButton) {
            scrollTopButton.addEventListener('click', event => {
                event.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth',
                });
            });
        }

        if (toastStack) {
            const closeToast = toast => {
                if (!toast) {
                    return;
                }

                toast.classList.add('is-hiding');
                window.setTimeout(() => {
                    toast.remove();
                    if (toastStack.children.length === 0) {
                        toastStack.remove();
                    }
                }, 180);
            };

            toastStack.addEventListener('click', event => {
                const closeButton = event.target.closest('[data-admin-toast-close]');
                if (!closeButton) {
                    return;
                }

                closeToast(closeButton.closest('[data-admin-toast]'));
            });

            Array.prototype.slice.call(toastStack.querySelectorAll('[data-admin-toast]')).forEach(toast => {
                window.setTimeout(() => closeToast(toast), 6500);
            });
        }

        if (menuResetButton && menuResetConfirmedInput) {
            menuResetButton.addEventListener('click', event => {
                menuResetConfirmedInput.value = '0';
                const message = menuResetButton.getAttribute('data-confirm-message') || '설정을 기본값으로 초기화할까요?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }

                menuResetConfirmedInput.value = '1';
            });
        }

        document.addEventListener('input', event => {
            const assetAmountInput = event.target && event.target.closest
                ? event.target.closest('[data-admin-asset-amount-input]')
                : null;
            if (assetAmountInput) {
                syncAssetAmountInputValue(assetAmountInput);
                return;
            }

            const keyInput = event.target && event.target.closest
                ? event.target.closest('[data-admin-key-input], [data-admin-login-id-input]')
                : null;
            if (keyInput) {
                syncKeyInputValue(keyInput);
                return;
            }

            const slugInput = event.target && event.target.closest
                ? event.target.closest('[data-admin-slug-input]')
                : null;
            if (slugInput) {
                syncSlugInputValue(slugInput);
            }
        });
        document.querySelectorAll('[data-admin-key-input]').forEach(syncKeyInputValue);
        document.querySelectorAll('[data-admin-login-id-input]').forEach(syncKeyInputValue);
        document.querySelectorAll('[data-admin-slug-input]').forEach(syncSlugInputValue);
        document.querySelectorAll('[data-admin-asset-amount-input]').forEach(syncAssetAmountInputValue);

        document.addEventListener('focusin', event => {
            const control = assetEnableControl(event.target);
            if (control) {
                rememberAssetEnableValue(control);
            }
        });

        document.addEventListener('pointerdown', event => {
            const control = assetEnableControl(event.target);
            if (control) {
                rememberAssetEnableValue(control);
            }
        });

        document.addEventListener('change', event => {
            const control = assetEnableControl(event.target);
            if (control) {
                confirmAssetEnableSelection(control);
                syncAssetAmountGroupsNear(control);
                syncAssetUnitGroupsNear(control);
                return;
            }

            const scopeToastControl = event.target && event.target.closest
                ? event.target.closest('[data-admin-scope-toast]')
                : null;
            if (scopeToastControl && scopeToastControl.checked) {
                showAdminToast(scopeToastControl.getAttribute('data-admin-scope-toast') || '');
            }

            const sourceGroup = event.target && event.target.closest
                ? event.target.closest('[data-admin-setting-source-group]')
                : null;
            if (sourceGroup) {
                syncSettingSourceGroup(sourceGroup);
            }

            markAssetEnableTargetTouched(event.target);
            syncAssetAmountGroupsNear(event.target);
            syncAssetUnitGroupsNear(event.target);
        });

        document.querySelectorAll('[data-admin-asset-amount-sync]').forEach(root => syncAssetAmountGroup(root));
        document.querySelectorAll('[data-admin-asset-unit-group]').forEach(syncAssetUnitGroup);
        document.querySelectorAll('[data-admin-setting-source-group]').forEach(syncSettingSourceGroup);

        document.addEventListener('submit', event => {
            if (!confirmAssetEnableSubmit(event.target)) {
                event.preventDefault();
                return;
            }

            if (event.target && event.target.querySelectorAll) {
                event.target.querySelectorAll('[data-admin-asset-amount-input]').forEach(stripAssetAmountInputValue);
            }
        });

        if (sortableRows.length > 0) {
            let draggedRow = null;
            let draggedRows = [];
            let placeholderRow = null;
            let collapsedMenuRows = new Set();

            try {
                const storedCollapsedRows = JSON.parse(window.localStorage.getItem(menuCollapseStorageKey) || '[]');
                if (Array.isArray(storedCollapsedRows)) {
                    collapsedMenuRows = new Set(storedCollapsedRows.filter(value => typeof value === 'string'));
                }
            } catch (error) {
                collapsedMenuRows = new Set();
            }

            const sortableRowKey = row => row
                ? `${row.dataset.sortScope || ''}|${row.dataset.sortKey || ''}`
                : '';

            const currentSortableRows = () => Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));

            const renumberRows = (scope, parent) => {
                const rows = currentSortableRows().filter(row => {
                    return row.dataset.sortScope === scope && row.dataset.sortParent === parent;
                });
                rows.forEach((row, index) => {
                    const input = row.querySelector('[data-admin-sort-order]');
                    if (input) {
                        input.value = String((index + 1) * 10);
                    }
                });
            };

            const rowDepth = row => {
                const depth = Number.parseInt(row.dataset.sortDepth || '0', 10);
                return Number.isFinite(depth) ? depth : 0;
            };

            const sortablePeers = (scope, parent) => {
                return Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]')).filter(row => {
                    return row.dataset.sortScope === scope
                        && row.dataset.sortParent === parent
                        && !row.hidden
                        && !draggedRows.includes(row);
                });
            };

            const visibleSortableRows = () => currentSortableRows().filter(row => !row.hidden);

            const sortableRowBlock = row => {
                const rows = [row];
                const depth = rowDepth(row);
                let next = row.nextElementSibling;
                while (next && next.matches('[data-admin-sortable-row]') && rowDepth(next) > depth) {
                    rows.push(next);
                    next = next.nextElementSibling;
                }

                return rows;
            };

            const sortableContainers = Array.from(new Set(sortableRows.map(row => row.parentNode))).filter(Boolean);

            const targetInsertionReference = row => {
                let reference = row;
                const depth = rowDepth(row);
                let next = row.nextElementSibling;
                while (next && next.matches('[data-admin-sortable-row]') && rowDepth(next) > depth) {
                    reference = next;
                    next = next.nextElementSibling;
                }

                return reference.nextSibling;
            };

            const saveCollapsedMenuRows = () => {
                try {
                    window.localStorage.setItem(menuCollapseStorageKey, JSON.stringify(Array.from(collapsedMenuRows)));
                } catch (error) {
                    return;
                }
            };

            const syncMenuToggleButton = row => {
                const toggle = row.querySelector('[data-admin-menu-children-toggle]');
                if (!toggle) {
                    return;
                }

                const collapsed = collapsedMenuRows.has(sortableRowKey(row));
                const label = toggle.getAttribute(collapsed ? 'data-expand-label' : 'data-collapse-label') || '';
                const rowLabel = row.querySelector('.admin-menu-target-label');
                const icon = toggle.querySelector('[data-sr-material-icon]');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', `${rowLabel ? rowLabel.textContent.trim() : ''} ${label}`.trim());
                toggle.setAttribute('title', label);
                toggle.classList.toggle('is-collapsed', collapsed);
                if (icon) {
                    icon.textContent = collapsed ? 'unfold_more' : 'unfold_less';
                }
            };

            const syncMenuCollapsedRows = () => {
                const collapsedAncestors = [];
                currentSortableRows().forEach(row => {
                    const depth = rowDepth(row);
                    while (collapsedAncestors.length > 0 && depth <= collapsedAncestors[collapsedAncestors.length - 1]) {
                        collapsedAncestors.pop();
                    }

                    row.hidden = collapsedAncestors.length > 0;
                    if (collapsedMenuRows.has(sortableRowKey(row))) {
                        collapsedAncestors.push(depth);
                    }

                    syncMenuToggleButton(row);
                });

                document.querySelectorAll('[data-admin-menu-toggle-all]').forEach(button => {
                    const hasCollapsedRows = currentSortableRows().some(row => {
                        return row.dataset.sortHasChildren === '1' && collapsedMenuRows.has(sortableRowKey(row));
                    });
                    const label = button.getAttribute(hasCollapsedRows ? 'data-expand-label' : 'data-collapse-label') || '';
                    const icon = button.querySelector('[data-sr-material-icon]');
                    const labelNode = button.querySelector('[data-admin-menu-toggle-all-label]');
                    if (icon) {
                        icon.textContent = hasCollapsedRows ? 'unfold_more' : 'unfold_less';
                    }
                    if (labelNode) {
                        labelNode.textContent = label;
                    }
                    button.setAttribute('aria-label', label);
                    button.setAttribute('title', label);
                });
            };

            const setAllMenuRowsCollapsed = collapsed => {
                collapsedMenuRows = new Set();
                if (collapsed) {
                    currentSortableRows().forEach(row => {
                        if (row.dataset.sortHasChildren === '1') {
                            collapsedMenuRows.add(sortableRowKey(row));
                        }
                    });
                }

                saveCollapsedMenuRows();
                syncMenuCollapsedRows();
                refreshMoveButtons();
            };

            const refreshMoveButtons = () => {
                currentSortableRows().forEach(row => {
                    const scope = row.dataset.sortScope || '';
                    const parent = row.dataset.sortParent || '';
                    const peers = visibleSortableRows().filter(peer => {
                        return peer.dataset.sortScope === scope && peer.dataset.sortParent === parent;
                    });
                    const index = peers.indexOf(row);
                    const up = row.querySelector('[data-admin-sort-move="up"]');
                    const down = row.querySelector('[data-admin-sort-move="down"]');
                    if (up) {
                        up.disabled = index <= 0;
                    }
                    if (down) {
                        down.disabled = index === -1 || index >= peers.length - 1;
                    }
                });
            };

            const refreshSortableState = (scope, parent) => {
                if (scope !== undefined && parent !== undefined) {
                    renumberRows(scope, parent);
                }
                syncMenuCollapsedRows();
                refreshMoveButtons();
            };

            const moveSortableRow = (row, direction) => {
                if (!row || row.hidden) {
                    return;
                }

                const scope = row.dataset.sortScope || '';
                const parent = row.dataset.sortParent || '';
                const peers = visibleSortableRows().filter(peer => {
                    return peer.dataset.sortScope === scope && peer.dataset.sortParent === parent;
                });
                const index = peers.indexOf(row);
                const target = direction === 'up' ? peers[index - 1] : peers[index + 1];
                if (!target) {
                    return;
                }

                const container = row.parentNode;
                const block = sortableRowBlock(row);
                const reference = direction === 'up' ? target : targetInsertionReference(target);
                block.forEach(blockRow => {
                    container.insertBefore(blockRow, reference);
                });
                refreshSortableState(scope, parent);
            };

            const removePlaceholder = () => {
                if (placeholderRow && placeholderRow.parentNode) {
                    placeholderRow.parentNode.removeChild(placeholderRow);
                }
                placeholderRow = null;
            };

            const ensurePlaceholder = () => {
                if (placeholderRow) {
                    return placeholderRow;
                }

                const columnCount = draggedRow && draggedRow.cells ? Math.max(1, draggedRow.cells.length) : 1;
                placeholderRow = document.createElement('tr');
                placeholderRow.className = 'admin-sort-placeholder-row';
                placeholderRow.setAttribute('aria-hidden', 'true');

                const cell = document.createElement('td');
                cell.className = 'admin-sort-placeholder-cell';
                cell.colSpan = columnCount;
                placeholderRow.appendChild(cell);

                return placeholderRow;
            };

            const placePlaceholder = (container, reference) => {
                const placeholder = ensurePlaceholder();
                if (placeholder.parentNode !== container || placeholder.nextSibling !== reference) {
                    container.insertBefore(placeholder, reference);
                }
            };

            const updatePlaceholder = event => {
                if (!draggedRow) {
                    return false;
                }

                const scope = draggedRow.dataset.sortScope || '';
                const parent = draggedRow.dataset.sortParent || '';
                const peers = sortablePeers(scope, parent);
                if (peers.length === 0) {
                    removePlaceholder();
                    return false;
                }

                let targetPeer = peers[peers.length - 1];
                let reference = targetInsertionReference(targetPeer);

                for (const peer of peers) {
                    const rect = peer.getBoundingClientRect();
                    if (event.clientY < rect.top + rect.height / 2) {
                        targetPeer = peer;
                        reference = peer;
                        break;
                    }
                }

                placePlaceholder(targetPeer.parentNode, reference);
                return true;
            };

            const finishSortableDrag = row => {
                draggedRows.forEach(blockRow => blockRow.classList.remove('is-dragging'));

                const movedScope = draggedRow ? draggedRow.dataset.sortScope || '' : row.dataset.sortScope || '';
                const movedParent = draggedRow ? draggedRow.dataset.sortParent || '' : row.dataset.sortParent || '';
                if (placeholderRow && placeholderRow.parentNode && draggedRow) {
                    const parent = placeholderRow.parentNode;
                    draggedRows.forEach(blockRow => {
                        parent.insertBefore(blockRow, placeholderRow);
                    });
                }

                removePlaceholder();
                draggedRow = null;
                draggedRows = [];
                refreshSortableState(movedScope, movedParent);
            };

            sortableRows.forEach(row => {
                const handle = row.querySelector('.admin-drag-handle');
                if (!handle) {
                    return;
                }

                handle.addEventListener('dragstart', event => {
                    draggedRow = row;
                    draggedRows = sortableRowBlock(row);
                    draggedRows.forEach(blockRow => blockRow.classList.add('is-dragging'));
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', '');
                });

                handle.addEventListener('dragend', () => {
                    finishSortableDrag(row);
                });
            });

            sortableRows.forEach(row => {
                row.querySelectorAll('[data-admin-sort-move]').forEach(button => {
                    button.addEventListener('click', () => {
                        moveSortableRow(row, button.getAttribute('data-admin-sort-move') || '');
                    });
                });

                const toggle = row.querySelector('[data-admin-menu-children-toggle]');
                if (toggle) {
                    toggle.addEventListener('click', () => {
                        const key = sortableRowKey(row);
                        if (collapsedMenuRows.has(key)) {
                            collapsedMenuRows.delete(key);
                        } else {
                            collapsedMenuRows.add(key);
                        }

                        saveCollapsedMenuRows();
                        syncMenuCollapsedRows();
                        refreshMoveButtons();
                    });
                }
            });

            document.querySelectorAll('[data-admin-menu-toggle-all]').forEach(button => {
                button.addEventListener('click', () => {
                    const hasCollapsedRows = currentSortableRows().some(row => {
                        return row.dataset.sortHasChildren === '1' && collapsedMenuRows.has(sortableRowKey(row));
                    });
                    setAllMenuRowsCollapsed(!hasCollapsedRows);
                });
            });

            sortableContainers.forEach(container => {
                container.addEventListener('dragover', event => {
                    if (!updatePlaceholder(event)) {
                        return;
                    }

                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                });

                container.addEventListener('drop', event => {
                    if (!draggedRow) {
                        return;
                    }

                    event.preventDefault();
                    finishSortableDrag(draggedRow);
                });

                container.addEventListener('dragleave', event => {
                    if (!draggedRow || container.contains(event.relatedTarget)) {
                        return;
                    }

                    removePlaceholder();
                });
            });

            syncMenuCollapsedRows();
            refreshMoveButtons();
        }

        if (tabRoot) {
            const buttons = Array.prototype.slice.call(tabRoot.querySelectorAll('[data-admin-tab-target]'));
            const panels = Array.prototype.slice.call(document.querySelectorAll('[data-admin-tab-panel]'));
            const enabledButtons = () => buttons.filter(button => !button.disabled && button.getAttribute('aria-disabled') !== 'true');
            const panelForTab = tabName => panels.find(panel => panel.dataset.adminTabPanel === tabName);
            const activateTab = tabName => {
                buttons.forEach(button => {
                    const active = button.dataset.adminTabTarget === tabName;
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                    button.tabIndex = active ? 0 : -1;
                });
                panels.forEach(panel => {
                    panel.hidden = panel.dataset.adminTabPanel !== tabName;
                });
            };
            const focusRelativeTab = (button, offset) => {
                const availableButtons = enabledButtons();
                const currentIndex = availableButtons.indexOf(button);
                if (currentIndex === -1 || availableButtons.length === 0) {
                    return;
                }

                const nextIndex = (currentIndex + offset + availableButtons.length) % availableButtons.length;
                const nextButton = availableButtons[nextIndex];
                nextButton.focus();
                activateTab(nextButton.dataset.adminTabTarget || '');
            };
            const focusEdgeTab = index => {
                const availableButtons = enabledButtons();
                const nextButton = availableButtons[index];
                if (!nextButton) {
                    return;
                }

                nextButton.focus();
                activateTab(nextButton.dataset.adminTabTarget || '');
            };

            panels.forEach((panel, index) => {
                const panelName = panel.dataset.adminTabPanel || String(index);
                if (!panel.id) {
                    panel.id = 'admin-tab-panel-' + panelName;
                }
                panel.setAttribute('role', 'tabpanel');
            });

            buttons.forEach((button, index) => {
                const tabName = button.dataset.adminTabTarget || String(index);
                const panel = panelForTab(tabName);
                if (!button.id) {
                    button.id = 'admin-tab-trigger-' + tabName;
                }
                button.setAttribute('role', 'tab');
                if (panel) {
                    button.setAttribute('aria-controls', panel.id);
                    panel.setAttribute('aria-labelledby', button.id);
                }
                button.addEventListener('click', () => activateTab(button.dataset.adminTabTarget || ''));
                button.addEventListener('keydown', event => {
                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusRelativeTab(button, 1);
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        focusRelativeTab(button, -1);
                    } else if (event.key === 'Home') {
                        event.preventDefault();
                        focusEdgeTab(0);
                    } else if (event.key === 'End') {
                        event.preventDefault();
                        focusEdgeTab(enabledButtons().length - 1);
                    }
                });
            });
            tabRoot.setAttribute('role', 'tablist');
            const selectedButton = buttons.find(button => button.getAttribute('aria-selected') === 'true' || button.classList.contains('active')) || buttons[0];
            if (selectedButton) {
                activateTab(selectedButton.dataset.adminTabTarget || '');
            }
        }

        memberRuleDefinitions.forEach(memberRuleDefinition => {
            const root = memberRuleDefinition.closest('form') || document;
            const panels = Array.prototype.slice.call(root.querySelectorAll('[data-rule-param-panel]'));
            const syncRuleParamPanel = () => {
                panels.forEach(panel => {
                    const active = panel.dataset.ruleParamPanel === memberRuleDefinition.value;
                    panel.hidden = !active;
                    Array.prototype.slice.call(panel.querySelectorAll('input, select, textarea')).forEach(input => {
                        input.disabled = !active;
                    });
                });
            };
            memberRuleDefinition.addEventListener('change', syncRuleParamPanel);
            syncRuleParamPanel();
        });

        if (dateQuickButtons.length > 0) {
            const toLocalDatetimeValue = date => {
                const pad = value => String(value).padStart(2, '0');
                return [
                    date.getFullYear(),
                    pad(date.getMonth() + 1),
                    pad(date.getDate()),
                ].join('-') + 'T' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
            };

            dateQuickButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const target = document.getElementById(button.dataset.datetimeTarget || '');
                    if (!target) {
                        return;
                    }

                    const days = Number(button.dataset.datetimeQuickDays || '0');
                    const date = new Date();
                    if (button.dataset.datetimeQuick !== 'now' && Number.isFinite(days)) {
                        date.setDate(date.getDate() + days);
                    }
                    target.value = toLocalDatetimeValue(date);
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }

        if (dashboardSectionsRoot) {
            const orderStorageKey = 'sr_admin_dashboard_section_order_v2';
            const visibilityStorageKey = 'sr_admin_dashboard_section_visibility';
            const managerToggle = document.querySelector('[data-admin-dashboard-manager-toggle]');
            const managerPanel = document.querySelector('[data-admin-dashboard-manager]');
            const managerClose = document.querySelector('[data-admin-dashboard-manager-close]');
            const managerList = document.querySelector('[data-admin-dashboard-manager-list]');
            const visibilityReset = document.querySelector('[data-admin-dashboard-visibility-reset]');
            let draggedSection = null;
            let currentDropPosition = null;
            const dropLine = document.createElement('div');
            dropLine.className = 'admin-dashboard-drop-line';
            dropLine.setAttribute('aria-hidden', 'true');

            const sections = () => Array.prototype.slice.call(dashboardSectionsRoot.querySelectorAll('[data-admin-dashboard-section]'));
            const visibleSections = () => sections().filter(section => !section.hidden);
            const sectionKey = section => section ? (section.dataset.adminDashboardSection || '') : '';
            const sectionLabel = section => section ? (section.dataset.adminDashboardLabel || sectionKey(section)) : '';
            const sectionDefaultVisible = section => !section || section.dataset.adminDashboardDefaultVisible !== '0';
            const loadVisibilityState = () => {
                try {
                    const savedState = JSON.parse(localStorage.getItem(visibilityStorageKey) || '{}');
                    return savedState && typeof savedState === 'object' && !Array.isArray(savedState) ? savedState : {};
                } catch (err) {
                    return {};
                }
            };
            let visibilityState = loadVisibilityState();
            const applySectionSpan = (section, span, auto) => {
                if (!section) {
                    return;
                }

                if (span === 'full') {
                    section.dataset.adminDashboardSpan = 'full';
                } else if (span === 'half') {
                    section.dataset.adminDashboardSpan = 'half';
                } else if (span === 'third') {
                    section.dataset.adminDashboardSpan = 'third';
                } else {
                    delete section.dataset.adminDashboardSpan;
                }

                if (auto) {
                    section.dataset.adminDashboardAutoSpan = '1';
                } else {
                    delete section.dataset.adminDashboardAutoSpan;
                }
            };
            const saveSectionOrder = () => {
                try {
                    localStorage.setItem(orderStorageKey, JSON.stringify({
                        items: sections().map(section => ({
                            key: sectionKey(section),
                            span: ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
                                ? section.dataset.adminDashboardSpan
                                : '',
                            auto_span: section.dataset.adminDashboardAutoSpan === '1'
                        }))
                    }));
                } catch (err) {}
            };
            const dashboardColumnCount = () => {
                if (window.matchMedia('(max-width: 767px)').matches) {
                    return 1;
                }

                if (window.matchMedia('(max-width: 1279px)').matches) {
                    return 2;
                }

                return 4;
            };
            const normalizeSectionRun = (run, columnCount) => {
                if (run.length === 0 || columnCount <= 1) {
                    return;
                }

                for (let index = 0; index < run.length; index += columnCount) {
                    const chunk = run.slice(index, index + columnCount);
                    const span = chunk.length === 1
                        ? 'full'
                        : (chunk.length === 2 && columnCount >= 3
                            ? 'half'
                            : (chunk.length === 3 && columnCount >= 4 ? 'third' : ''));

                    chunk.forEach(section => {
                        applySectionSpan(section, span, true);
                    });
                }
            };
            const normalizeVisibleSectionLayout = () => {
                const columnCount = dashboardColumnCount();
                let run = [];

                if (columnCount <= 1) {
                    return;
                }

                visibleSections().forEach(section => {
                    if (section.dataset.adminDashboardSpan === 'full' && section.dataset.adminDashboardAutoSpan !== '1') {
                        normalizeSectionRun(run, columnCount);
                        run = [];
                        return;
                    }

                    run.push(section);
                });

                normalizeSectionRun(run, columnCount);
            };
            const sectionIsVisible = section => {
                const key = sectionKey(section);
                if (Object.prototype.hasOwnProperty.call(visibilityState, key)) {
                    return visibilityState[key] !== false;
                }

                return sectionDefaultVisible(section);
            };
            const applySectionVisibility = () => {
                sections().forEach(section => {
                    section.hidden = !sectionIsVisible(section);
                });
            };
            const saveVisibilityState = () => {
                try {
                    localStorage.setItem(visibilityStorageKey, JSON.stringify(visibilityState));
                } catch (err) {}
            };
            const setSectionVisible = (section, visible) => {
                const key = sectionKey(section);
                const wasHidden = section.hidden;
                visibilityState[key] = visible;

                if (visible && wasHidden) {
                    applySectionSpan(section, 'full');
                    dashboardSectionsRoot.appendChild(section);
                }

                section.hidden = !visible;
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
            };
            const renderVisibilityManager = () => {
                if (!managerList) {
                    return;
                }

                managerList.innerHTML = '';
                sections().forEach(section => {
                    const label = document.createElement('label');
                    const input = document.createElement('input');
                    const text = document.createElement('span');

                    label.className = 'admin-dashboard-manager-item form-label';
                    input.type = 'checkbox';
                    input.className = 'form-checkbox';
                    input.checked = sectionIsVisible(section);
                    text.textContent = sectionLabel(section);

                    input.addEventListener('change', () => {
                        setSectionVisible(section, input.checked);
                    });

                    label.appendChild(input);
                    label.appendChild(text);
                    managerList.appendChild(label);
                });
            };
            const clearDropLine = () => {
                if (dropLine.parentNode) {
                    dropLine.parentNode.removeChild(dropLine);
                }
                dropLine.classList.remove('is-horizontal', 'is-vertical');
                dropLine.removeAttribute('style');
            };
            const dashboardRows = availableSections => {
                const rowTolerance = 8;
                const rows = [];
                const items = availableSections
                    .map(section => ({
                        rect: section.getBoundingClientRect(),
                        section
                    }))
                    .sort((left, right) => left.rect.top - right.rect.top || left.rect.left - right.rect.left);

                items.forEach(item => {
                    const row = rows[rows.length - 1];
                    if (row && Math.abs(item.rect.top - row.top) <= rowTolerance) {
                        row.items.push(item);
                        row.left = Math.min(row.left, item.rect.left);
                        row.right = Math.max(row.right, item.rect.right);
                        row.top = Math.min(row.top, item.rect.top);
                        row.bottom = Math.max(row.bottom, item.rect.bottom);
                        return;
                    }

                    rows.push({
                        bottom: item.rect.bottom,
                        items: [item],
                        left: item.rect.left,
                        right: item.rect.right,
                        top: item.rect.top
                    });
                });

                return rows;
            };
            const rowIndexForSection = (rows, section) => rows.findIndex(row => (
                row.items.some(item => item.section === section)
            ));
            const verticalDropLineX = (rows, position) => {
                const rect = position.rect;
                if (!rect) {
                    return null;
                }

                const row = rows.find(candidate => (
                    candidate.items.some(item => item.section === position.section)
                ));
                const sortedItems = row
                    ? row.items.slice().sort((left, right) => left.rect.left - right.rect.left)
                    : [];
                const itemIndex = sortedItems.findIndex(item => item.section === position.section);
                const previousItem = position.side === 'left'
                    ? sortedItems[itemIndex - 1]
                    : sortedItems[itemIndex];
                const nextItem = position.side === 'left'
                    ? sortedItems[itemIndex]
                    : sortedItems[itemIndex + 1];

                if (previousItem && nextItem) {
                    return (previousItem.rect.right + nextItem.rect.left) / 2;
                }

                return position.side === 'left' ? rect.left : rect.right;
            };
            const horizontalDropPosition = (rows, rowIndex, after) => {
                const nextRowIndex = after ? rowIndex + 1 : rowIndex;
                const previousRow = rows[nextRowIndex - 1] || null;
                const nextRow = rows[nextRowIndex] || null;
                const reference = nextRow && nextRow.items[0] ? nextRow.items[0].section : null;
                const fallbackY = previousRow
                    ? previousRow.bottom + 8
                    : (nextRow ? nextRow.top - 8 : dashboardSectionsRoot.getBoundingClientRect().top);
                const lineY = previousRow && nextRow
                    ? (previousRow.bottom + nextRow.top) / 2
                    : fallbackY;

                return {
                    reference,
                    rect: {
                        bottom: lineY,
                        left: 0,
                        right: 0,
                        top: lineY
                    },
                    orientation: 'horizontal',
                    side: 'slot',
                    span: 'full'
                };
            };
            const getDropPosition = event => {
                const availableSections = visibleSections().filter(section => section !== draggedSection);
                const rows = dashboardRows(availableSections);

                for (let index = 0; index < availableSections.length; index += 1) {
                    const section = availableSections[index];
                    const nextSection = availableSections[index + 1] || null;
                    const rect = section.getBoundingClientRect();

                    if (event.clientY > rect.bottom || event.clientX < rect.left || event.clientX > rect.right) {
                        continue;
                    }

                    const distances = {
                        top: Math.abs(event.clientY - rect.top),
                        right: Math.abs(rect.right - event.clientX),
                        bottom: Math.abs(rect.bottom - event.clientY),
                        left: Math.abs(event.clientX - rect.left)
                    };
                    const side = Object.keys(distances).reduce((closest, key) => (
                        distances[key] < distances[closest] ? key : closest
                    ), 'top');
                    const rowIndex = rowIndexForSection(rows, section);

                    if (side === 'left') {
                        return {
                            reference: section,
                            rect,
                            section,
                            side: 'left',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    if (side === 'right') {
                        return {
                            reference: nextSection,
                            rect,
                            section,
                            side: 'right',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    return horizontalDropPosition(rows, rowIndex, side === 'bottom');
                }

                let closest = null;
                for (let index = 0; index < availableSections.length; index += 1) {
                    const section = availableSections[index];
                    const rect = section.getBoundingClientRect();
                    const xDistance = event.clientX < rect.left
                        ? rect.left - event.clientX
                        : Math.max(0, event.clientX - rect.right);
                    const yDistance = event.clientY < rect.top
                        ? rect.top - event.clientY
                        : Math.max(0, event.clientY - rect.bottom);
                    const score = xDistance * xDistance + yDistance * yDistance;

                    if (!closest || score < closest.score) {
                        closest = {
                            index,
                            rect,
                            score,
                            section,
                            xDistance,
                            yDistance
                        };
                    }
                }

                if (closest && closest.xDistance > closest.yDistance) {
                    return {
                        reference: event.clientX < closest.rect.left
                            ? closest.section
                            : (availableSections[closest.index + 1] || null),
                        rect: closest.rect,
                        section: closest.section,
                        side: event.clientX < closest.rect.left ? 'left' : 'right',
                        orientation: 'vertical',
                        span: ''
                    };
                }

                if (closest) {
                    const rowIndex = rowIndexForSection(rows, closest.section);
                    return horizontalDropPosition(rows, rowIndex, event.clientY > (closest.rect.top + closest.rect.height / 2));
                }

                return {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
            };
            const placeDropLine = position => {
                const nextPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const reference = nextPosition.reference;
                const orientation = nextPosition.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const rootRect = dashboardSectionsRoot.getBoundingClientRect();
                const rect = nextPosition.rect || null;
                const lineBoxSize = 16;

                currentDropPosition = nextPosition;
                dropLine.classList.toggle('is-vertical', orientation === 'vertical');
                dropLine.classList.toggle('is-horizontal', orientation !== 'vertical');

                if (!dropLine.parentNode) {
                    dashboardSectionsRoot.appendChild(dropLine);
                }

                if (orientation === 'vertical' && rect) {
                    const lineX = verticalDropLineX(dashboardRows(visibleSections().filter(section => section !== draggedSection)), nextPosition)
                        || (nextPosition.side === 'left' ? rect.left : rect.right);
                    dropLine.style.left = `${Math.round(lineX - rootRect.left - lineBoxSize / 2)}px`;
                    dropLine.style.top = `${Math.round(rect.top - rootRect.top)}px`;
                    dropLine.style.width = `${lineBoxSize}px`;
                    dropLine.style.height = `${Math.max(48, Math.round(rect.height))}px`;
                } else if (rect) {
                    const lineY = nextPosition.side === 'top' ? rect.top : rect.bottom;
                    dropLine.style.left = '0px';
                    dropLine.style.top = `${Math.round(lineY - rootRect.top - lineBoxSize / 2)}px`;
                    dropLine.style.width = `${Math.round(rootRect.width)}px`;
                    dropLine.style.height = `${lineBoxSize}px`;
                } else {
                    dropLine.style.left = '0px';
                    dropLine.style.top = `${Math.round(rootRect.height - lineBoxSize / 2)}px`;
                    dropLine.style.width = `${Math.round(rootRect.width)}px`;
                    dropLine.style.height = `${lineBoxSize}px`;
                }
            };
            const insertSectionAtDropLine = (section, dropPosition) => {
                const position = dropPosition || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const targetSection = position.section || null;
                const isSideDropOnFullSection = position.orientation === 'vertical'
                    && targetSection
                    && targetSection.dataset.adminDashboardSpan === 'full'
                    && dashboardColumnCount() > 1;

                if (isSideDropOnFullSection) {
                    const reference = position.side === 'left'
                        ? targetSection
                        : targetSection.nextSibling;

                    applySectionSpan(section, 'half', true);
                    if (reference && reference.parentNode === dashboardSectionsRoot) {
                        dashboardSectionsRoot.insertBefore(section, reference);
                    } else {
                        dashboardSectionsRoot.appendChild(section);
                    }
                    normalizeVisibleSectionLayout();
                    applySectionSpan(targetSection, 'half', true);
                    applySectionSpan(section, 'half', true);
                    return;
                }

                applySectionSpan(section, position.span || '');
                if (position.reference && position.reference.parentNode === dashboardSectionsRoot) {
                    dashboardSectionsRoot.insertBefore(section, position.reference);
                } else {
                    dashboardSectionsRoot.appendChild(section);
                }
                normalizeVisibleSectionLayout();
            };
            const finishDashboardDrag = commit => {
                if (commit && draggedSection) {
                    insertSectionAtDropLine(draggedSection, currentDropPosition);
                    saveSectionOrder();
                }

                if (draggedSection) {
                    draggedSection.classList.remove('is-dragging');
                }

                clearDropLine();
                currentDropPosition = null;
                draggedSection = null;
            };

            try {
                const savedState = JSON.parse(localStorage.getItem(orderStorageKey) || '[]');
                const savedItems = Array.isArray(savedState)
                    ? savedState.map(key => ({ key: String(key), span: '' }))
                    : (Array.isArray(savedState.items) ? savedState.items : []);
                if (savedItems.length > 0) {
                    savedItems.forEach(item => {
                        const key = typeof item === 'string' ? item : String(item.key || '');
                        const section = sections().find(candidate => sectionKey(candidate) === key);
                        if (!section) {
                            return;
                        }

                        applySectionSpan(section, ['full', 'half', 'third'].includes(item.span || '') ? item.span : '', item.auto_span === true);
                        dashboardSectionsRoot.appendChild(section);
                    });
                }
            } catch (err) {}

            applySectionVisibility();
            normalizeVisibleSectionLayout();
            renderVisibilityManager();

            if (managerToggle && managerPanel) {
                managerToggle.addEventListener('click', () => {
                    const nextExpanded = managerPanel.hidden;
                    managerPanel.hidden = !nextExpanded;
                    managerToggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
                });
            }

            if (managerClose && managerPanel) {
                managerClose.addEventListener('click', () => {
                    managerPanel.hidden = true;
                    if (managerToggle) {
                        managerToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            if (visibilityReset) {
                visibilityReset.addEventListener('click', () => {
                    visibilityState = {};
                    try {
                        localStorage.removeItem(visibilityStorageKey);
                    } catch (err) {}
                    applySectionVisibility();
                    normalizeVisibleSectionLayout();
                    saveSectionOrder();
                    renderVisibilityManager();
                });
            }

            window.addEventListener('resize', () => {
                normalizeVisibleSectionLayout();
                saveSectionOrder();
            });

            sections().forEach(section => {
                const handle = section.querySelector('.admin-dashboard-section-handle');

                if (!handle) {
                    return;
                }

                handle.addEventListener('dragstart', event => {
                    draggedSection = section;
                    section.classList.add('is-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', '');
                });

                handle.addEventListener('dragend', () => {
                    finishDashboardDrag(currentDropPosition !== null);
                });
            });

            dashboardSectionsRoot.addEventListener('dragover', event => {
                if (!draggedSection) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                placeDropLine(getDropPosition(event));
            });

            dashboardSectionsRoot.addEventListener('drop', event => {
                if (!draggedSection) {
                    return;
                }

                event.preventDefault();
                finishDashboardDrag(true);
            });

            document.addEventListener('dragover', event => {
                if (!draggedSection || currentDropPosition === null) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            });

            document.addEventListener('drop', event => {
                if (!draggedSection || currentDropPosition === null) {
                    return;
                }

                event.preventDefault();
                finishDashboardDrag(true);
            });
        }

        try {
            if (!isMobileViewport() && localStorage.getItem(menuStorageKey) === '1' && gnb) {
                gnb.classList.add('gnb_small');
            }
        } catch (err) {}
        syncDesktopSidebarState();
        try {
            if (!isMobileViewport() && localStorage.getItem(menuStorageKey) === '1') {
                setDesktopCollapsed(true);
            }
        } catch (err) {}
        window.requestAnimationFrame(clearSidebarRestoring);
        syncThemeUI();
        document.querySelectorAll('.table-wrapper').forEach(wrapper => {
            if (!wrapper.hasAttribute('tabindex')) {
                wrapper.setAttribute('tabindex', '0');
            }
            if (!wrapper.hasAttribute('aria-label') && !wrapper.hasAttribute('aria-labelledby')) {
                wrapper.setAttribute('aria-label', 'Scrollable table');
            }
        });
        updateMenuScrollbar();
        window.requestAnimationFrame(updateMenuScrollbar);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AdminShell.init();
});
