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
        const colorSchemeControls = Array.prototype.slice.call(document.querySelectorAll('[data-admin-color-scheme-select]'));
        const menuResetButton = document.querySelector('[data-admin-menu-reset-confirm]');
        const menuResetConfirmedInput = document.querySelector('[data-admin-menu-reset-confirmed]');
        const sortableRows = Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));
        const memberRuleDefinitions = Array.prototype.slice.call(document.querySelectorAll('[data-member-rule-definition]'));
        const dateQuickButtons = Array.prototype.slice.call(document.querySelectorAll('[data-datetime-target]'));
        const dashboardSectionsRoot = document.querySelector('[data-admin-dashboard-sections]');
        const anchorTabs = Array.prototype.slice.call(document.querySelectorAll('.sticky-tabs.anchor-tabs'));
        const assetEnablePreviousValues = new WeakMap();
        const assetEnableTouchedRoots = new WeakSet();
        let hideScrollbarTimer = null;
        let themeSaving = false;

        const restrictedKeyInputSelector = '[data-admin-key-input], [data-admin-login-id-input]';
        const restrictedVersionKeyInputSelector = '[data-admin-version-key-input]';
        const normalizeKeyInputValue = value => value.toLowerCase().replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '');
        const normalizeVersionKeyInputValue = value => String(value || '').replace(/[^A-Za-z0-9._-]/g, '');
        const normalizeSlugInputValue = value => value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+/, '');
        const assetAmountDigits = value => value.replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '').slice(0, 9);
        const formatAssetAmountValue = value => {
            const digits = assetAmountDigits(value);
            return digits === '' ? '' : digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        const keySuggestionScope = input => {
            const scopeSelector = input ? input.getAttribute('data-admin-key-suggest-scope') || '' : '';
            return scopeSelector ? input.closest(scopeSelector) : document;
        };

        const keySuggestionSource = input => {
            const sourceSelector = input ? input.getAttribute('data-admin-key-suggest-source') || '' : '';
            const scope = keySuggestionScope(input);
            return sourceSelector && scope ? scope.querySelector(sourceSelector) : null;
        };

        const keySuggestionValue = input => {
            const source = keySuggestionSource(input);
            const sourceValue = source ? normalizeKeyInputValue(source.value || '') : '';
            const fallback = normalizeKeyInputValue(input.getAttribute('data-admin-key-suggest-fallback') || '');
            return sourceValue !== '' ? sourceValue : fallback;
        };

        const applyKeySuggestion = input => {
            if (!input || input.disabled || input.readOnly || input.getAttribute('data-admin-key-touched') === '1') {
                return;
            }

            if (input.value !== '' && input.getAttribute('data-admin-key-suggested') !== '1') {
                return;
            }

            const nextValue = keySuggestionValue(input);
            if (nextValue === '') {
                return;
            }

            input.value = nextValue.slice(0, input.maxLength > 0 ? input.maxLength : nextValue.length);
            syncKeyInputValue(input);
            input.setAttribute('data-admin-key-suggested', '1');
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

        const validationControls = form => Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(control => {
            const type = String(control.type || '').toLowerCase();
            return !control.disabled && !['hidden', 'button', 'submit', 'reset'].includes(type);
        });

        const validationInvalidClass = control => {
            if (control.tagName === 'SELECT') {
                return 'form-select-invalid';
            }
            if (control.tagName === 'TEXTAREA') {
                return 'form-textarea-invalid';
            }
            if (control.type === 'checkbox' || control.type === 'radio') {
                return 'form-choice-invalid';
            }
            return 'form-input-invalid';
        };

        const validationFieldRoot = control => control.closest('.admin-form-field') || control.closest('.form-field') || control.parentElement;

        const validationNoteId = control => {
            const existing = control.getAttribute('data-validation-error-id');
            if (existing) {
                return existing;
            }

            const base = control.id || control.name || 'field';
            const id = 'sr_validation_error_' + base.replace(/[^A-Za-z0-9_-]/g, '_');
            control.setAttribute('data-validation-error-id', id);
            return id;
        };

        const updateValidationDescription = (control, noteId, add) => {
            const ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            const hasNote = ids.includes(noteId);
            if (add && !hasNote) {
                ids.push(noteId);
            }
            if (!add && hasNote) {
                ids.splice(ids.indexOf(noteId), 1);
            }
            if (ids.length > 0) {
                control.setAttribute('aria-describedby', ids.join(' '));
            } else {
                control.removeAttribute('aria-describedby');
            }
        };

        const validationMessage = control => {
            const custom = control.getAttribute('data-validation-message') || '';
            if (custom !== '') {
                return custom;
            }
            if (control.validity && control.validity.valueMissing) {
                return '필수 항목입니다.';
            }
            if (control.validity && control.validity.patternMismatch) {
                return '입력 형식을 확인해 주세요.';
            }
            if (control.validity && (control.validity.rangeUnderflow || control.validity.rangeOverflow)) {
                return '허용 범위를 확인해 주세요.';
            }
            return control.validationMessage || '입력값을 확인해 주세요.';
        };

        const clearValidationState = control => {
            const noteId = control.getAttribute('data-validation-error-id');
            ['form-input-invalid', 'form-select-invalid', 'form-textarea-invalid', 'form-choice-invalid'].forEach(className => {
                control.classList.remove(className);
            });
            control.removeAttribute('aria-invalid');
            if (noteId) {
                updateValidationDescription(control, noteId, false);
                const note = document.getElementById(noteId);
                if (note) {
                    note.remove();
                }
            }
        };

        const markValidationState = control => {
            const noteId = validationNoteId(control);
            const field = validationFieldRoot(control);
            control.classList.add(validationInvalidClass(control));
            control.setAttribute('aria-invalid', 'true');
            updateValidationDescription(control, noteId, true);

            if (!field) {
                return;
            }

            let note = document.getElementById(noteId);
            if (!note) {
                note = document.createElement('p');
                note.id = noteId;
                note.className = 'validation-error-note';
                note.setAttribute('role', 'alert');
                field.appendChild(note);
            }
            note.textContent = validationMessage(control);
        };

        const refreshValidationControl = control => {
            if (!control.closest('[data-sr-validate-form]')) {
                return;
            }
            if (control.validity && control.validity.valid) {
                clearValidationState(control);
            } else if (control.getAttribute('aria-invalid') === 'true') {
                markValidationState(control);
            }
        };

        const validateSrForm = (form, focusFirstInvalid) => {
            const invalidControls = validationControls(form).filter(control => !(control.validity && control.validity.valid));
            validationControls(form).forEach(control => {
                if (invalidControls.includes(control)) {
                    markValidationState(control);
                } else {
                    clearValidationState(control);
                }
            });
            if (focusFirstInvalid && invalidControls.length > 0 && typeof invalidControls[0].focus === 'function') {
                invalidControls[0].focus({ preventScroll: false });
            }
            return invalidControls.length === 0;
        };

        const cssNumberValue = value => {
            const number = parseFloat(String(value || '0'));
            return Number.isFinite(number) ? number : 0;
        };

        const adminStickyOffset = () => {
            const rootStyle = window.getComputedStyle(document.documentElement);
            const shellBarHeight = cssNumberValue(rootStyle.getPropertyValue('--admin-shell-bar-height'));
            const tabsHeight = cssNumberValue(rootStyle.getPropertyValue('--config-tabs-height')) || 52;
            return shellBarHeight + tabsHeight + 12;
        };

        const scrollAnchorTabIntoView = (tabs, activeLink) => {
            if (!tabs || !activeLink || typeof tabs.scrollTo !== 'function') {
                return;
            }

            const tabsRect = tabs.getBoundingClientRect();
            const linkRect = activeLink.getBoundingClientRect();
            const overflowLeft = linkRect.left - tabsRect.left;
            const overflowRight = linkRect.right - tabsRect.right;
            if (overflowLeft < 0) {
                tabs.scrollTo({ left: tabs.scrollLeft + overflowLeft - 8, behavior: 'smooth' });
            } else if (overflowRight > 0) {
                tabs.scrollTo({ left: tabs.scrollLeft + overflowRight + 8, behavior: 'smooth' });
            }
        };

        const setAnchorTabActive = (tabs, activeLink, options = {}) => {
            Array.prototype.slice.call(tabs.querySelectorAll('a[href^="#"]')).forEach(link => {
                const active = link === activeLink;
                link.classList.toggle('active', active);
                if (active) {
                    link.setAttribute('aria-current', 'location');
                    if (options.scrollTabIntoView) {
                        scrollAnchorTabIntoView(tabs, link);
                    }
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        const initAnchorTabsScrollSpy = tabs => {
            const links = Array.prototype.slice.call(tabs.querySelectorAll('a[href^="#"]'));
            const pairs = links.map(link => {
                const hash = link.getAttribute('href') || '';
                let section = null;
                try {
                    section = hash.length > 1 ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;
                } catch (error) {
                    section = hash.length > 1 ? document.getElementById(hash.slice(1)) : null;
                }

                return section ? { link, section } : null;
            }).filter(Boolean);

            if (pairs.length === 0) {
                return;
            }

            const pairByLink = new Map(pairs.map(pair => [pair.link, pair]));
            const scrollBehavior = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            const activePairFromScroll = () => {
                const probeY = adminStickyOffset() + Math.min(96, window.innerHeight * 0.25);
                let activePair = pairs[0];
                pairs.forEach(pair => {
                    const rect = pair.section.getBoundingClientRect();
                    if (rect.top <= probeY) {
                        activePair = pair;
                    }
                });

                if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                    activePair = pairs[pairs.length - 1];
                }

                return activePair;
            };

            let ticking = false;
            const sync = () => {
                ticking = false;
                const activePair = activePairFromScroll();
                setAnchorTabActive(tabs, activePair ? activePair.link : pairs[0].link);
            };
            const requestSync = () => {
                if (ticking) {
                    return;
                }
                ticking = true;
                window.requestAnimationFrame(sync);
            };

            links.forEach(link => {
                link.addEventListener('click', event => {
                    const pair = pairByLink.get(link);
                    if (!pair) {
                        return;
                    }

                    event.preventDefault();
                    setAnchorTabActive(tabs, link, { scrollTabIntoView: true });
                    pair.section.scrollIntoView({ block: 'start', behavior: scrollBehavior });
                });
            });

            sync();
            window.addEventListener('scroll', requestSync, { passive: true });
            window.addEventListener('resize', requestSync);
            window.addEventListener('hashchange', requestSync);
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

        const syncConditionalSelectSection = root => {
            if (!root || !root.getAttribute) {
                return;
            }

            const selector = root.getAttribute('data-admin-visible-when-select') || '';
            const form = root.closest('form') || document;
            const source = selector !== '' ? form.querySelector(selector) || document.querySelector(selector) : null;
            const visible = !!(source && source.value);
            root.hidden = !visible;
            Array.prototype.slice.call(root.querySelectorAll('[data-admin-required-when-visible]')).forEach(control => {
                control.required = visible;
                if (!visible && control.getAttribute('data-admin-clear-when-hidden') === '1') {
                    control.value = '0';
                    syncAssetAmountInputValue(control);
                }
            });
            Array.prototype.slice.call(root.querySelectorAll('[data-admin-required-label-when-visible]')).forEach(label => {
                label.hidden = !visible;
            });
        };

        const restrictedInputMessage = input => {
            const custom = input ? input.getAttribute('data-validation-message') || input.getAttribute('data-restricted-input-message') || '' : '';
            return custom !== '' ? custom : '영문, 숫자, 밑줄만 입력 가능합니다.';
        };

        const clearRestrictedInputValidation = input => {
            if (!input || input.getAttribute('data-restricted-input-validation-active') !== '1') {
                return;
            }

            input.removeAttribute('data-restricted-input-validation-active');
            if (typeof input.setCustomValidity === 'function') {
                input.setCustomValidity('');
            }
            refreshValidationControl(input);
        };

        const showRestrictedInputValidation = input => {
            if (!input || typeof input.setCustomValidity !== 'function') {
                return;
            }

            window.clearTimeout(input._adminRestrictedInputValidationTimer);
            input.setAttribute('data-restricted-input-validation-active', '1');
            input.setCustomValidity(restrictedInputMessage(input));
            refreshValidationControl(input);
            if (typeof input.reportValidity === 'function') {
                input.reportValidity();
            }
            input._adminRestrictedInputValidationTimer = window.setTimeout(() => {
                clearRestrictedInputValidation(input);
            }, 1800);
        };

        const restrictedKeyInputHasBlockedData = value => /[^a-zA-Z0-9_]/.test(String(value || ''));

        const syncRestrictedInputValue = (input, normalizeValue, reportBlockedInput) => {
            if (!input || input.readOnly || input.disabled) {
                return;
            }

            const previousValue = input.value;
            const nextValue = normalizeValue(previousValue);
            if (previousValue === nextValue) {
                clearRestrictedInputValidation(input);
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
            if (reportBlockedInput) {
                showRestrictedInputValidation(input);
            }
        };

        const syncKeyInputValue = (input, reportBlockedInput) => syncRestrictedInputValue(
            input,
            normalizeKeyInputValue,
            !!reportBlockedInput && restrictedKeyInputHasBlockedData(input ? input.value : '')
        );
        const restrictedVersionKeyInputHasBlockedData = value => /[^A-Za-z0-9._-]/.test(String(value || ''));
        const syncVersionKeyInputValue = (input, reportBlockedInput) => syncRestrictedInputValue(
            input,
            normalizeVersionKeyInputValue,
            !!reportBlockedInput && restrictedVersionKeyInputHasBlockedData(input ? input.value : '')
        );
        const syncSlugInputValue = input => syncRestrictedInputValue(input, normalizeSlugInputValue);

        const syncFilteringToggleGroup = group => {
            if (!group || !group.querySelectorAll) {
                return;
            }

            const allInput = group.querySelector('[data-filtering-toggle-all]');
            const choiceInputs = Array.prototype.slice.call(group.querySelectorAll('[data-filtering-toggle-choice]'));
            const checkedChoices = choiceInputs.filter(input => input.checked);
            if (!allInput) {
                return;
            }

            if (checkedChoices.length === 0) {
                allInput.checked = true;
                return;
            }

            if (choiceInputs.length > 1 && checkedChoices.length === choiceInputs.length) {
                choiceInputs.forEach(input => {
                    input.checked = false;
                });
                allInput.checked = true;
                return;
            }

            allInput.checked = false;
        };

        const resetFilteringForm = form => {
            if (!form || !form.querySelectorAll) {
                return;
            }

            Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).forEach(control => {
                if (control.disabled) {
                    return;
                }

                if (control.matches('[data-filtering-toggle-all]')) {
                    control.checked = true;
                    return;
                }

                if (control.matches('[data-filtering-toggle-choice]')) {
                    control.checked = false;
                    return;
                }

                if (control.matches('[data-filtering-radio-toggle-choice]')) {
                    control.checked = control.value === '';
                    return;
                }

                if (control.type === 'hidden' || control.type === 'submit' || control.type === 'button' || control.type === 'reset') {
                    return;
                }

                if (control.type === 'checkbox' || control.type === 'radio') {
                    control.checked = false;
                    return;
                }

                if (control.tagName === 'SELECT') {
                    control.selectedIndex = 0;
                    return;
                }

                control.value = '';
            });
            Array.prototype.slice.call(form.querySelectorAll('[data-filtering-toggle-group]')).forEach(syncFilteringToggleGroup);
        };

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

        const closeToolbarDropdowns = except => {
            Array.prototype.slice.call(document.querySelectorAll('#tnb .admin-profile-dropdown[open], #tnb .admin-notification-dropdown[open]')).forEach(dropdown => {
                if (except && dropdown === except) {
                    return;
                }

                dropdown.removeAttribute('open');
            });
        };

        const adminNotificationNumber = text => {
            const digits = String(text || '').replace(/[^0-9]/g, '');
            return digits === '' ? 0 : parseInt(digits, 10);
        };

        const syncAdminNotificationCounts = menu => {
            if (!menu) {
                return;
            }

            const remainingItems = Array.prototype.slice.call(menu.querySelectorAll('.admin-notification-menu-item'));
            const countLabel = menu.querySelector('[data-admin-notification-count]');
            const badge = document.querySelector('[data-admin-notification-badge]');
            const currentCount = countLabel ? adminNotificationNumber(countLabel.textContent) : remainingItems.length;
            const nextCount = Math.max(0, currentCount - 1);
            const emptyItem = menu.querySelector('.admin-notification-menu-empty');

            if (countLabel) {
                countLabel.textContent = nextCount.toLocaleString('ko-KR') + '건';
            }
            if (badge) {
                if (nextCount > 0) {
                    badge.textContent = String(Math.min(99, nextCount));
                } else {
                    badge.remove();
                }
            }
            if (emptyItem && remainingItems.length === 0 && nextCount === 0) {
                emptyItem.hidden = false;
            }
        };

        const markAdminNotificationReadInMenu = form => {
            if (!form || !window.fetch || !window.FormData) {
                return false;
            }

            const item = form.closest('.admin-notification-menu-item');
            const menu = form.closest('.admin-notification-menu');
            const submitButton = form.querySelector('button[type="submit"], button:not([type])');
            if (!item || !menu) {
                return false;
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            window.fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(response => {
                if (!response.ok) {
                    throw new Error('admin notification read request failed');
                }

                item.remove();
                syncAdminNotificationCounts(menu);
            }).catch(() => {
                form.submit();
            });

            return true;
        };

        document.addEventListener('click', event => {
            const filteringReset = event.target && event.target.closest
                ? event.target.closest('[data-filtering-reset]')
                : null;
            if (filteringReset) {
                const form = filteringReset.closest('form');
                if (form) {
                    event.preventDefault();
                    resetFilteringForm(form);
                }
            }

            const activeToolbarDropdown = event.target && event.target.closest
                ? event.target.closest('#tnb .admin-profile-dropdown, #tnb .admin-notification-dropdown')
                : null;
            closeToolbarDropdowns(activeToolbarDropdown);
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeToolbarDropdowns(null);
            }
        });

        document.addEventListener('submit', event => {
            const readForm = event.target && event.target.closest
                ? event.target.closest('[data-admin-notification-read-form]')
                : null;
            if (!readForm) {
                return;
            }

            if (markAdminNotificationReadInMenu(readForm)) {
                event.preventDefault();
            }
        });

        document.addEventListener('invalid', event => {
            const validationControl = event.target && event.target.closest
                ? event.target.closest('[data-sr-validate-form] input, [data-sr-validate-form] select, [data-sr-validate-form] textarea')
                : null;
            if (!validationControl) {
                return;
            }

            event.preventDefault();
            const validationForm = validationControl.closest('[data-sr-validate-form]');
            if (validationForm) {
                validateSrForm(validationForm, true);
            }
        }, true);

        document.addEventListener('beforeinput', event => {
            const keyInput = event.target && event.target.closest
                ? event.target.closest(restrictedKeyInputSelector)
                : null;
            if (!keyInput || keyInput.readOnly || keyInput.disabled || !String(event.inputType || '').startsWith('insert')) {
                return;
            }

            if (event.data && restrictedKeyInputHasBlockedData(event.data)) {
                event.preventDefault();
                showRestrictedInputValidation(keyInput);
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
                colorSchemeControls.forEach(control => {
                    if (control.type === 'radio') {
                        control.checked = control.value === nextScheme;
                    } else {
                        control.value = nextScheme;
                    }
                });

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
                    colorSchemeControls.forEach(control => {
                        if (control.type === 'radio') {
                            control.checked = control.value === savedScheme;
                        } else {
                            control.value = savedScheme;
                        }
                    });
                }).catch(() => {
                    applyColorScheme(previousScheme);
                    colorSchemeControls.forEach(control => {
                        if (control.type === 'radio') {
                            control.checked = control.value === previousScheme;
                        } else {
                            control.value = previousScheme;
                        }
                    });
                }).finally(() => {
                    themeSaving = false;
                    syncThemeUI();
                });
            });
        }

        colorSchemeControls.forEach(control => {
            control.addEventListener('change', () => {
                if (control.type === 'radio' && !control.checked) {
                    return;
                }
                applyColorScheme(control.value);
            });
        });

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

        anchorTabs.forEach(initAnchorTabsScrollSpy);

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
            const validationControl = event.target && event.target.closest
                ? event.target.closest('[data-sr-validate-form] input, [data-sr-validate-form] textarea')
                : null;
            if (validationControl) {
                refreshValidationControl(validationControl);
            }

            const assetAmountInput = event.target && event.target.closest
                ? event.target.closest('[data-admin-asset-amount-input]')
                : null;
            if (assetAmountInput) {
                syncAssetAmountInputValue(assetAmountInput);
                return;
            }

            const keyInput = event.target && event.target.closest
                ? event.target.closest(restrictedKeyInputSelector)
                : null;
            if (keyInput) {
                if (keyInput.hasAttribute('data-admin-key-suggest-source')) {
                    keyInput.setAttribute('data-admin-key-touched', '1');
                    keyInput.removeAttribute('data-admin-key-suggested');
                }
                syncKeyInputValue(keyInput, true);
                return;
            }

            const versionKeyInput = event.target && event.target.closest
                ? event.target.closest(restrictedVersionKeyInputSelector)
                : null;
            if (versionKeyInput) {
                syncVersionKeyInputValue(versionKeyInput, true);
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
        document.querySelectorAll('[data-admin-version-key-input]').forEach(syncVersionKeyInputValue);
        document.querySelectorAll('[data-admin-slug-input]').forEach(syncSlugInputValue);
        document.querySelectorAll('[data-admin-asset-amount-input]').forEach(syncAssetAmountInputValue);
        document.querySelectorAll('[data-admin-key-input][data-admin-key-suggest-source]').forEach(input => {
            const source = keySuggestionSource(input);
            if (!source) {
                return;
            }

            source.addEventListener('input', () => applyKeySuggestion(input));
            source.addEventListener('change', () => applyKeySuggestion(input));
        });

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
            const validationControl = event.target && event.target.closest
                ? event.target.closest('[data-sr-validate-form] input, [data-sr-validate-form] select, [data-sr-validate-form] textarea')
                : null;
            if (validationControl) {
                refreshValidationControl(validationControl);
            }

            const filteringToggleControl = event.target && event.target.closest
                ? event.target.closest('[data-filtering-toggle-all], [data-filtering-toggle-choice]')
                : null;
            if (filteringToggleControl) {
                const filteringToggleGroup = filteringToggleControl.closest('[data-filtering-toggle-group]');
                if (filteringToggleGroup) {
                    if (filteringToggleControl.matches('[data-filtering-toggle-all]') && filteringToggleControl.checked) {
                        filteringToggleGroup.querySelectorAll('[data-filtering-toggle-choice]').forEach(input => {
                            input.checked = false;
                        });
                    } else if (filteringToggleControl.matches('[data-filtering-toggle-choice]') && filteringToggleControl.checked) {
                        const allInput = filteringToggleGroup.querySelector('[data-filtering-toggle-all]');
                        if (allInput) {
                            allInput.checked = false;
                        }
                    }
                    syncFilteringToggleGroup(filteringToggleGroup);
                }
            }

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
            document.querySelectorAll('[data-admin-visible-when-select]').forEach(syncConditionalSelectSection);
        });

        document.querySelectorAll('[data-filtering-toggle-group]').forEach(syncFilteringToggleGroup);
        document.querySelectorAll('[data-admin-asset-amount-sync]').forEach(root => syncAssetAmountGroup(root));
        document.querySelectorAll('[data-admin-asset-unit-group]').forEach(syncAssetUnitGroup);
        document.querySelectorAll('[data-admin-setting-source-group]').forEach(syncSettingSourceGroup);
        document.querySelectorAll('[data-admin-visible-when-select]').forEach(syncConditionalSelectSection);

        document.addEventListener('submit', event => {
            const validationForm = event.target && event.target.closest
                ? event.target.closest('[data-sr-validate-form]')
                : null;
            if (validationForm && (!validationForm.checkValidity() || !validateSrForm(validationForm, true))) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

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
            const orderStorageKey = 'sr_admin_dashboard_section_order_v3';
            const visibilityStorageKey = 'sr_admin_dashboard_section_visibility';
            const managerToggle = document.querySelector('[data-admin-dashboard-manager-toggle]');
            const managerPanel = document.querySelector('[data-admin-dashboard-manager]');
            const managerCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-admin-dashboard-manager-close]'));
            const managerList = document.querySelector('[data-admin-dashboard-manager-list]');
            const changeCancel = document.querySelector('[data-admin-dashboard-change-cancel]');
            let draggedSection = null;
            let currentDropPosition = null;
            let managerDraggedItem = null;
            let managerDraggedSection = null;
            let managerCurrentDropPosition = null;
            let managerPreviousFocus = null;
            let managerOpenSnapshot = null;
            const dropLine = document.createElement('div');
            dropLine.className = 'admin-dashboard-drop-line';
            dropLine.setAttribute('aria-hidden', 'true');
            const managerDropLine = document.createElement('div');
            managerDropLine.className = 'admin-dashboard-drop-line';
            managerDropLine.setAttribute('aria-hidden', 'true');
            const managerDragGhost = document.createElement('span');
            managerDragGhost.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;';
            managerDragGhost.setAttribute('aria-hidden', 'true');
            document.body.appendChild(managerDragGhost);

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
            const sectionWidthUnits = section => {
                const span = section ? (section.dataset.adminDashboardSpan || '') : '';
                if (span === 'full') {
                    return 12;
                }
                if (span === 'half') {
                    return 6;
                }
                if (span === 'third') {
                    return 4;
                }
                return 3;
            };
            const layoutRowsFromSections = sectionList => {
                const rows = [];
                let row = [];
                let rowUnits = 0;

                sectionList.forEach(section => {
                    const widthUnits = sectionWidthUnits(section);
                    if (row.length > 0 && rowUnits + widthUnits > 12) {
                        rows.push(row);
                        row = [];
                        rowUnits = 0;
                    }

                    row.push(section);
                    rowUnits += widthUnits;
                    if (rowUnits >= 12) {
                        rows.push(row);
                        row = [];
                        rowUnits = 0;
                    }
                });

                if (row.length > 0) {
                    rows.push(row);
                }

                return rows;
            };
            const normalizedLayoutRows = rows => {
                const nextRows = [];

                rows.forEach(row => {
                    const items = row.filter(Boolean);
                    if (items.length === 0) {
                        return;
                    }

                    for (let index = 0; index < items.length; index += 4) {
                        nextRows.push(items.slice(index, index + 4));
                    }
                });

                return nextRows;
            };
            const applyLayoutRows = rows => {
                const normalizedRows = normalizedLayoutRows(rows);

                normalizedRows.forEach(row => {
                    const span = row.length === 1
                        ? 'full'
                        : (row.length === 2
                            ? 'half'
                            : (row.length === 3 ? 'third' : ''));

                    row.forEach(section => {
                        applySectionSpan(section, span, true);
                        dashboardSectionsRoot.appendChild(section);
                    });
                });

                sections().filter(section => section.hidden).forEach(section => {
                    dashboardSectionsRoot.appendChild(section);
                });

                return normalizedRows;
            };
            const normalizeVisibleSectionLayout = () => {
                applyLayoutRows(layoutRowsFromSections(visibleSections()));
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
            const dashboardStateSnapshot = () => ({
                items: sections().map(section => ({
                    auto_span: section.dataset.adminDashboardAutoSpan === '1',
                    key: sectionKey(section),
                    span: ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
                        ? section.dataset.adminDashboardSpan
                        : '',
                    visible: sectionIsVisible(section)
                }))
            });
            const dashboardSnapshotsEqual = (left, right) => JSON.stringify(left || null) === JSON.stringify(right || null);
            const updateChangeCancelVisibility = () => {
                if (!changeCancel) {
                    return;
                }

                changeCancel.hidden = !managerOpenSnapshot || dashboardSnapshotsEqual(managerOpenSnapshot, dashboardStateSnapshot());
            };
            const restoreDashboardSnapshot = snapshot => {
                if (!snapshot || !Array.isArray(snapshot.items)) {
                    return;
                }

                const nextVisibilityState = {};
                snapshot.items.forEach(item => {
                    const section = sections().find(candidate => sectionKey(candidate) === String(item.key || ''));
                    if (!section) {
                        return;
                    }

                    nextVisibilityState[sectionKey(section)] = item.visible !== false;
                    applySectionSpan(section, ['full', 'half', 'third'].includes(item.span || '') ? item.span : '', item.auto_span === true);
                    section.hidden = item.visible === false;
                    dashboardSectionsRoot.appendChild(section);
                });

                visibilityState = nextVisibilityState;
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
                clearManagerDropLine();
                renderVisibilityManager();
                updateChangeCancelVisibility();
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
                if (!visible) {
                    dashboardSectionsRoot.appendChild(section);
                }
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
                updateChangeCancelVisibility();
            };
            const renderVisibilityManager = () => {
                if (!managerList) {
                    return;
                }

                managerList.innerHTML = '';
                sections().forEach(section => {
                    const item = document.createElement('div');
                    const handle = document.createElement('button');
                    const handleIcon = document.createElement('span');
                    const title = document.createElement('span');
                    const toggleLabel = document.createElement('label');
                    const input = document.createElement('input');
                    const key = sectionKey(section);
                    const label = sectionLabel(section);
                    const span = ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
                        ? section.dataset.adminDashboardSpan
                        : '';

                    item.className = 'admin-dashboard-manager-item';
                    item.classList.toggle('is-hidden-section', !sectionIsVisible(section));
                    item.dataset.adminDashboardManagerItem = key;
                    if (span !== '' && sectionIsVisible(section)) {
                        item.dataset.adminDashboardSpan = span;
                    }
                    handle.type = 'button';
                    handle.className = 'admin-dashboard-manager-handle';
                    handle.draggable = sectionIsVisible(section);
                    handle.setAttribute('aria-label', `${label} 순서 이동`);
                    handleIcon.className = 'sr-icon material-symbols-outlined admin-dashboard-manager-handle-icon';
                    handleIcon.setAttribute('aria-hidden', 'true');
                    handleIcon.setAttribute('data-sr-material-icon', '');
                    handleIcon.textContent = 'apps';
                    title.className = 'admin-dashboard-manager-title';
                    title.textContent = label;
                    toggleLabel.className = 'admin-dashboard-manager-toggle';
                    input.type = 'checkbox';
                    input.className = 'form-switch form-choice-dark';
                    input.checked = sectionIsVisible(section);
                    input.setAttribute('aria-label', `${label} 표시`);

                    input.addEventListener('change', () => {
                        setSectionVisible(section, input.checked);
                        renderVisibilityManager();
                    });

                    handle.addEventListener('dragstart', event => {
                        if (!sectionIsVisible(section)) {
                            event.preventDefault();
                            return;
                        }

                        managerDraggedItem = item;
                        managerDraggedSection = section;
                        item.classList.add('is-dragging');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', key);
                        event.dataTransfer.setDragImage(managerDragGhost, 0, 0);
                    });

                    handle.addEventListener('dragend', () => {
                        finishManagerDrag(managerCurrentDropPosition !== null);
                    });

                    handle.appendChild(handleIcon);
                    toggleLabel.appendChild(input);
                    item.appendChild(handle);
                    item.appendChild(title);
                    item.appendChild(toggleLabel);
                    managerList.appendChild(item);
                });
            };
            const clearLine = line => {
                if (line.parentNode) {
                    line.parentNode.removeChild(line);
                }
                line.classList.remove('is-horizontal', 'is-vertical');
                line.removeAttribute('style');
            };
            const clearDropLine = () => {
                clearLine(dropLine);
            };
            const clearManagerDropLine = () => {
                clearLine(managerDropLine);
            };
            const dashboardRows = availableItems => {
                const rowTolerance = 8;
                const rows = [];
                const items = availableItems
                    .map(item => ({
                        item,
                        rect: item.getBoundingClientRect()
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
            const rowIndexForItem = (rows, targetItem) => rows.findIndex(row => (
                row.items.some(item => item.item === targetItem)
            ));
            const verticalDropLineX = (rows, position) => {
                const rect = position.rect;
                if (!rect) {
                    return null;
                }

                const row = rows.find(candidate => (
                    candidate.items.some(item => item.item === position.item)
                ));
                const sortedItems = row
                    ? row.items.slice().sort((left, right) => left.rect.left - right.rect.left)
                    : [];
                const itemIndex = sortedItems.findIndex(item => item.item === position.item);
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
            const horizontalDropPosition = (root, rows, rowIndex, after, referenceForItem) => {
                const nextRowIndex = after ? rowIndex + 1 : rowIndex;
                const previousRow = rows[nextRowIndex - 1] || null;
                const nextRow = rows[nextRowIndex] || null;
                const referenceItem = nextRow && nextRow.items[0] ? nextRow.items[0].item : null;
                const reference = referenceItem ? referenceForItem(referenceItem) : null;
                const fallbackY = previousRow
                    ? previousRow.bottom + 8
                    : (nextRow ? nextRow.top - 8 : root.getBoundingClientRect().top);
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
            const getDropPositionForItems = (root, availableItems, draggedItem, event, referenceForItem) => {
                const items = availableItems.filter(item => item !== draggedItem);
                const rows = dashboardRows(items);

                for (let index = 0; index < items.length; index += 1) {
                    const item = items[index];
                    const nextItem = items[index + 1] || null;
                    const rect = item.getBoundingClientRect();

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
                    const rowIndex = rowIndexForItem(rows, item);
                    const section = referenceForItem(item);
                    const nextSection = nextItem ? referenceForItem(nextItem) : null;

                    if (side === 'left') {
                        return {
                            reference: section,
                            rect,
                            item,
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
                            item,
                            section,
                            side: 'right',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    return horizontalDropPosition(root, rows, rowIndex, side === 'bottom', referenceForItem);
                }

                let closest = null;
                for (let index = 0; index < items.length; index += 1) {
                    const item = items[index];
                    const rect = item.getBoundingClientRect();
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
                            item,
                            rect,
                            score,
                            xDistance,
                            yDistance
                        };
                    }
                }

                if (closest && closest.xDistance > closest.yDistance) {
                    const closestSection = referenceForItem(closest.item);
                    const nextSection = items[closest.index + 1] ? referenceForItem(items[closest.index + 1]) : null;
                    return {
                        reference: event.clientX < closest.rect.left
                            ? closestSection
                            : nextSection,
                        rect: closest.rect,
                        item: closest.item,
                        section: closestSection,
                        side: event.clientX < closest.rect.left ? 'left' : 'right',
                        orientation: 'vertical',
                        span: ''
                    };
                }

                if (closest) {
                    const rowIndex = rowIndexForItem(rows, closest.item);
                    return horizontalDropPosition(root, rows, rowIndex, event.clientY > (closest.rect.top + closest.rect.height / 2), referenceForItem);
                }

                return {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
            };
            const placeDropLineInRoot = (root, line, availableItems, draggedItem, position) => {
                const nextPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const orientation = nextPosition.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const rootRect = root.getBoundingClientRect();
                const rect = nextPosition.rect || null;
                const lineBoxSize = 16;

                line.classList.toggle('is-vertical', orientation === 'vertical');
                line.classList.toggle('is-horizontal', orientation !== 'vertical');

                if (!line.parentNode) {
                    root.appendChild(line);
                }

                if (orientation === 'vertical' && rect) {
                    const lineX = verticalDropLineX(dashboardRows(availableItems.filter(item => item !== draggedItem)), nextPosition)
                        || (nextPosition.side === 'left' ? rect.left : rect.right);
                    line.style.left = `${Math.round(lineX - rootRect.left - lineBoxSize / 2)}px`;
                    line.style.top = `${Math.round(rect.top - rootRect.top)}px`;
                    line.style.width = `${lineBoxSize}px`;
                    line.style.height = `${Math.max(48, Math.round(rect.height))}px`;
                } else if (rect) {
                    const lineY = nextPosition.side === 'top' ? rect.top : rect.bottom;
                    line.style.left = '0px';
                    line.style.top = `${Math.round(lineY - rootRect.top - lineBoxSize / 2)}px`;
                    line.style.width = `${Math.round(rootRect.width)}px`;
                    line.style.height = `${lineBoxSize}px`;
                } else {
                    line.style.left = '0px';
                    line.style.top = `${Math.round(rootRect.height - lineBoxSize / 2)}px`;
                    line.style.width = `${Math.round(rootRect.width)}px`;
                    line.style.height = `${lineBoxSize}px`;
                }
            };
            const getDropPosition = event => getDropPositionForItems(
                dashboardSectionsRoot,
                visibleSections(),
                draggedSection,
                event,
                item => item
            );
            const placeDropLine = position => {
                currentDropPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                placeDropLineInRoot(dashboardSectionsRoot, dropLine, visibleSections(), draggedSection, currentDropPosition);
            };
            const insertSectionAtDropLine = (section, dropPosition) => {
                const position = dropPosition || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };

                if (!section) {
                    return;
                }

                const rows = layoutRowsFromSections(visibleSections());
                const findSectionRow = target => {
                    for (let rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
                        const columnIndex = rows[rowIndex].indexOf(target);
                        if (columnIndex !== -1) {
                            return { rowIndex, columnIndex };
                        }
                    }

                    return null;
                };
                const currentPosition = findSectionRow(section);
                if (currentPosition) {
                    rows[currentPosition.rowIndex].splice(currentPosition.columnIndex, 1);
                }

                if (position.orientation === 'horizontal') {
                    if (position.reference) {
                        const referencePosition = findSectionRow(position.reference);
                        rows.splice(referencePosition ? referencePosition.rowIndex : rows.length, 0, [section]);
                    } else {
                        rows.push([section]);
                    }
                } else if (position.section) {
                    const targetPosition = findSectionRow(position.section);
                    if (targetPosition) {
                        rows[targetPosition.rowIndex].splice(
                            position.side === 'left' ? targetPosition.columnIndex : targetPosition.columnIndex + 1,
                            0,
                            section
                        );
                    } else {
                        rows.push([section]);
                    }
                } else if (position.reference) {
                    const referencePosition = findSectionRow(position.reference);
                    if (referencePosition) {
                        rows[referencePosition.rowIndex].splice(referencePosition.columnIndex, 0, section);
                    } else {
                        rows.push([section]);
                    }
                } else {
                    rows.push([section]);
                }

                applyLayoutRows(rows);
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
                updateChangeCancelVisibility();
            };
            const managerItems = () => managerList
                ? Array.prototype.slice.call(managerList.querySelectorAll('[data-admin-dashboard-manager-item]:not(.is-hidden-section)'))
                : [];
            const managerSectionForItem = item => {
                const key = item ? (item.dataset.adminDashboardManagerItem || '') : '';
                return sections().find(section => sectionKey(section) === key) || null;
            };
            const getManagerDropPosition = event => managerList
                ? getDropPositionForItems(managerList, managerItems(), managerDraggedItem, event, managerSectionForItem)
                : null;
            const placeManagerDropLine = position => {
                if (!managerList) {
                    return;
                }

                managerCurrentDropPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                placeDropLineInRoot(managerList, managerDropLine, managerItems(), managerDraggedItem, managerCurrentDropPosition);
            };
            const finishManagerDrag = commit => {
                if (commit && managerDraggedSection) {
                    insertSectionAtDropLine(managerDraggedSection, managerCurrentDropPosition);
                    saveSectionOrder();
                }

                if (managerDraggedItem) {
                    managerDraggedItem.classList.remove('is-dragging');
                }

                clearManagerDropLine();
                managerDraggedItem = null;
                managerDraggedSection = null;
                managerCurrentDropPosition = null;
                renderVisibilityManager();
                updateChangeCancelVisibility();
            };

            let hasSavedLayout = false;
            try {
                const savedState = JSON.parse(localStorage.getItem(orderStorageKey) || '[]');
                const savedItems = Array.isArray(savedState)
                    ? savedState.map(key => ({ key: String(key), span: '' }))
                    : (Array.isArray(savedState.items) ? savedState.items : []);
                hasSavedLayout = savedItems.length > 0;
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
            if (!hasSavedLayout) {
                sections().forEach(section => {
                    applySectionSpan(section, 'full', true);
                });
            }
            normalizeVisibleSectionLayout();
            renderVisibilityManager();

            const openDashboardManager = () => {
                if (!managerPanel) {
                    return;
                }

                managerPreviousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                managerOpenSnapshot = dashboardStateSnapshot();
                renderVisibilityManager();
                updateChangeCancelVisibility();
                managerPanel.hidden = false;
                managerPanel.classList.remove('hidden', 'pointer-events-none', 'opacity-0');
                managerPanel.removeAttribute('aria-hidden');
                window.requestAnimationFrame(() => {
                    managerPanel.classList.add('overlay-open');
                });
                if (managerToggle) {
                    managerToggle.setAttribute('aria-expanded', 'true');
                }
                managerPanel.focus({ preventScroll: true });
            };
            const closeDashboardManager = () => {
                if (!managerPanel) {
                    return;
                }

                managerPanel.classList.remove('overlay-open');
                managerPanel.classList.add('hidden', 'pointer-events-none', 'opacity-0');
                managerPanel.setAttribute('aria-hidden', 'true');
                managerPanel.hidden = true;
                if (managerToggle) {
                    managerToggle.setAttribute('aria-expanded', 'false');
                }
                if (managerPreviousFocus && typeof managerPreviousFocus.focus === 'function') {
                    managerPreviousFocus.focus({ preventScroll: true });
                }
                managerPreviousFocus = null;
            };

            if (managerToggle && managerPanel) {
                managerToggle.addEventListener('click', () => {
                    if (managerPanel.hidden) {
                        openDashboardManager();
                    } else {
                        closeDashboardManager();
                    }
                });
            }

            if (managerPanel) {
                managerCloseButtons.forEach(button => {
                    button.addEventListener('click', closeDashboardManager);
                });

                managerPanel.addEventListener('click', event => {
                    if (event.target === managerPanel) {
                        closeDashboardManager();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && !managerPanel.hidden) {
                        closeDashboardManager();
                    }
                });
            }

            if (changeCancel) {
                changeCancel.addEventListener('click', () => {
                    restoreDashboardSnapshot(managerOpenSnapshot);
                });
            }

            if (managerList) {
                managerList.addEventListener('dragover', event => {
                    if (!managerDraggedSection) {
                        return;
                    }

                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    placeManagerDropLine(getManagerDropPosition(event));
                });

                managerList.addEventListener('drop', event => {
                    if (!managerDraggedSection) {
                        return;
                    }

                    event.preventDefault();
                    finishManagerDrag(true);
                });
            }

            window.addEventListener('resize', () => {
                normalizeVisibleSectionLayout();
                saveSectionOrder();
                renderVisibilityManager();
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
            if (wrapper.getAttribute('tabindex') === '0') {
                wrapper.removeAttribute('tabindex');
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
