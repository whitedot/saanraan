// 관리자 공통 shell과 폼 보조 동작.
// 목록 순서 변경과 대시보드 배치는 각각 admin-reordering.js, admin-dashboard.js가 담당한다.
window.AdminShell = {
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        const menuStorageKey = 'sr_admin_sidebar_collapsed';
        const menuCollapseStorageKey = 'sr_admin_menu_editor_collapsed';
        const colorSchemeStorageKey = 'sr_public_color_scheme';
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
        const layoutMenuLists = Array.prototype.slice.call(document.querySelectorAll('[data-admin-layout-menu-list]'));
        const memberRuleDefinitions = Array.prototype.slice.call(document.querySelectorAll('[data-member-rule-definition]'));
        const dateQuickButtons = Array.prototype.slice.call(document.querySelectorAll('[data-datetime-target]'));
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

        const layoutMenuRows = list => Array.prototype.slice.call(list.querySelectorAll('[data-admin-layout-menu-row]:not([data-admin-layout-menu-template])'));

        const layoutMenuRowFields = row => row ? Array.prototype.slice.call(row.querySelectorAll('[data-admin-layout-menu-field]')) : [];

        const layoutMenuRowKeyInput = row => row ? row.querySelector('[data-admin-layout-menu-key]') : null;

        const layoutMenuRowSelect = row => row ? row.querySelector('[data-admin-layout-menu-select]') : null;

        const randomLayoutMenuKey = () => {
            const bytes = new Uint8Array(6);
            if (window.crypto && window.crypto.getRandomValues) {
                window.crypto.getRandomValues(bytes);
            } else {
                for (let index = 0; index < bytes.length; index++) {
                    bytes[index] = Math.floor(Math.random() * 256);
                }
            }

            return Array.prototype.map.call(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        };

        const createLayoutMenuKey = usedKeys => {
            let key = '';
            do {
                key = randomLayoutMenuKey();
            } while (usedKeys.has(key));
            usedKeys.add(key);

            return key;
        };

        const syncLayoutMenuList = list => {
            if (!list) {
                return;
            }

            const rows = layoutMenuRows(list);
            const addButton = list.querySelector('[data-admin-layout-menu-add]');
            const template = list.querySelector('[data-admin-layout-menu-template]');
            const header = list.querySelector('[data-admin-layout-menu-header]');
            const usedKeys = new Set();
            if (header) {
                header.hidden = rows.length === 0;
            }
            rows.forEach(row => {
                const keyInput = layoutMenuRowKeyInput(row);
                if (keyInput) {
                    const keyValue = (keyInput.value || '').trim();
                    if (keyValue === '' || usedKeys.has(keyValue)) {
                        keyInput.value = createLayoutMenuKey(usedKeys);
                    } else {
                        usedKeys.add(keyValue);
                    }
                }
                layoutMenuRowFields(row).forEach(field => {
                    field.disabled = row.hidden;
                });
            });
            if (template) {
                template.hidden = true;
                layoutMenuRowFields(template).forEach(field => {
                    field.disabled = true;
                });
            }
            if (addButton) {
                addButton.disabled = !template;
            }
        };

        const addLayoutMenuRow = (list, options = {}) => {
            const template = list ? list.querySelector('[data-admin-layout-menu-template]') : null;
            if (!template) {
                return;
            }

            const row = template.cloneNode(true);
            row.removeAttribute('data-admin-layout-menu-template');
            row.hidden = false;
            layoutMenuRowFields(row).forEach(field => {
                field.disabled = false;
                field.value = '';
            });
            const usedKeys = new Set(layoutMenuRows(list).map(existingRow => {
                const keyInput = layoutMenuRowKeyInput(existingRow);
                return keyInput ? (keyInput.value || '').trim() : '';
            }).filter(Boolean));
            const keyInput = layoutMenuRowKeyInput(row);
            if (keyInput) {
                keyInput.value = createLayoutMenuKey(usedKeys);
            }
            const select = layoutMenuRowSelect(row);
            const actions = list.querySelector('[data-admin-layout-menu-actions]');
            list.insertBefore(row, actions || template);
            const firstField = layoutMenuRowFields(row)[0] || select;
            if (firstField && options.focus) {
                firstField.focus();
            }
        };

        const showLayoutMenuRow = (row, options = {}) => {
            if (!row) {
                return;
            }

            row.hidden = false;
            layoutMenuRowFields(row).forEach(field => {
                field.disabled = false;
            });
            const keyInput = layoutMenuRowKeyInput(row);
            if (keyInput && !keyInput.value) {
                const list = row.closest('[data-admin-layout-menu-list]');
                const usedKeys = new Set(layoutMenuRows(list).filter(existingRow => existingRow !== row).map(existingRow => {
                    const existingKeyInput = layoutMenuRowKeyInput(existingRow);
                    return existingKeyInput ? (existingKeyInput.value || '').trim() : '';
                }).filter(Boolean));
                keyInput.value = createLayoutMenuKey(usedKeys);
            }
            const firstField = layoutMenuRowFields(row)[0] || layoutMenuRowSelect(row);
            if (firstField && options.focus) {
                firstField.focus();
            }
        };

        const hideLayoutMenuRow = row => {
            if (!row) {
                return;
            }

            layoutMenuRowFields(row).forEach(field => {
                field.value = '';
                field.disabled = true;
            });
            if (row.hasAttribute('data-admin-layout-menu-template')) {
                row.hidden = true;
            } else {
                row.remove();
            }
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

        const validationFieldRoot = control => control.closest('.form-field') || control.parentElement;

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

        const validateConfirmPhraseFields = root => {
            if (!root || !root.querySelectorAll) {
                return true;
            }

            let valid = true;
            Array.prototype.slice.call(root.querySelectorAll('[data-confirm-phrase]')).forEach(input => {
                if (!input || typeof input.setCustomValidity !== 'function') {
                    return;
                }
                const expected = input.getAttribute('data-confirm-phrase') || '';
                const expectedValues = [expected].concat(String(input.getAttribute('data-confirm-phrase-alt') || '').split('|'))
                    .map(value => String(value || '').trim())
                    .filter(value => value !== '');
                const message = input.getAttribute('data-confirm-phrase-message') || '확인 문구가 일치하지 않습니다.';
                const matches = expectedValues.indexOf(String(input.value || '').trim()) !== -1;
                input.setCustomValidity(matches ? '' : message);
                if (!matches) {
                    valid = false;
                }
                refreshValidationControl(input);
            });

            return valid;
        };

        const adminFormShouldValidateRequiredSelection = form => {
            if (!form || !form.matches) {
                return false;
            }

            return String(form.method || '').toLowerCase() === 'post';
        };

        const adminValidationElementVisible = element => {
            if (!element || !element.closest) {
                return false;
            }
            if (element.hidden || element.closest('[hidden]')) {
                return false;
            }

            return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
        };

        const adminRequiredSelectionRows = form => {
            const rows = [];
            Array.prototype.slice.call(form.querySelectorAll('.sr-required-label')).forEach(requiredLabel => {
                if (!adminValidationElementVisible(requiredLabel)) {
                    return;
                }

                const row = requiredLabel.closest('[data-admin-required-selection-root], .form-row, .admin-setting-unit, fieldset, .form-field') || requiredLabel.parentElement;
                if (row && !rows.includes(row)) {
                    rows.push(row);
                }
            });

            return rows;
        };

        const adminRequiredSelectionLabel = row => {
            const label = row.querySelector('.form-label') || row.querySelector('legend') || row.querySelector('label') || row;
            const text = String(label.textContent || '').replace(/\(필수\)/g, '').replace(/\s+/g, ' ').trim();
            return text || '필수 항목';
        };

        const adminSelectHasValue = select => {
            if (select.multiple) {
                return Array.prototype.slice.call(select.selectedOptions || []).some(option => String(option.value || '').trim() !== '');
            }

            return String(select.value || '').trim() !== '';
        };

        const adminSelectionControlEnabled = control => control
            && !control.disabled
            && adminValidationElementVisible(control);

        const adminChoiceGroups = (row, form) => {
            const groups = new Map();
            Array.prototype.slice.call(row.querySelectorAll('input[type="checkbox"], input[type="radio"]')).filter(adminSelectionControlEnabled).forEach(control => {
                const key = control.name || control.id || '';
                if (key === '') {
                    return;
                }
                if (!groups.has(key)) {
                    groups.set(key, []);
                }
                groups.get(key).push(control);
            });

            return Array.prototype.slice.call(groups.entries()).map(entry => {
                const name = entry[0];
                const controls = entry[1];
                const escapedName = window.CSS && typeof window.CSS.escape === 'function' ? window.CSS.escape(name) : name.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                const sameNameControls = name !== ''
                    ? Array.prototype.slice.call(form.querySelectorAll('input[name="' + escapedName + '"]')).filter(adminSelectionControlEnabled)
                    : controls;
                return sameNameControls.length > controls.length ? sameNameControls : controls;
            });
        };

        const adminRequiredSelectionMissing = (row, form) => {
            const mode = row.getAttribute('data-admin-required-selection-mode') === 'any' ? 'any' : 'all';
            const selects = Array.prototype.slice.call(row.querySelectorAll('select')).filter(adminSelectionControlEnabled);
            const choiceGroups = adminChoiceGroups(row, form);
            const badgeValues = Array.prototype.slice.call(row.querySelectorAll('[data-admin-select-badge-value]')).filter(element => String(element.value || element.getAttribute('data-admin-select-badge-value') || '').trim() !== '');
            const checks = [];

            if (badgeValues.length > 0) {
                return false;
            }

            if (selects.length > 0) {
                if (mode === 'any') {
                    checks.push(selects.some(adminSelectHasValue));
                } else {
                    selects.forEach(select => {
                        checks.push(adminSelectHasValue(select));
                    });
                }
            }
            choiceGroups.forEach(group => {
                checks.push(group.some(control => control.checked));
            });

            return checks.length > 0 && !checks.every(Boolean);
        };

        const markAdminRequiredSelectionRow = row => {
            Array.prototype.slice.call(row.querySelectorAll('select')).filter(adminSelectionControlEnabled).forEach(control => {
                control.classList.add(validationInvalidClass(control));
                control.setAttribute('aria-invalid', 'true');
            });
            Array.prototype.slice.call(row.querySelectorAll('input[type="checkbox"], input[type="radio"]')).filter(adminSelectionControlEnabled).forEach(control => {
                control.classList.add(validationInvalidClass(control));
                control.setAttribute('aria-invalid', 'true');
            });
        };

        const clearAdminRequiredSelectionRows = form => {
            adminRequiredSelectionRows(form).forEach(row => {
                Array.prototype.slice.call(row.querySelectorAll('select, input[type="checkbox"], input[type="radio"]')).forEach(clearValidationState);
            });
        };

        const validateAdminRequiredSelections = form => {
            if (!adminFormShouldValidateRequiredSelection(form)) {
                return true;
            }

            clearAdminRequiredSelectionRows(form);

            const missingRow = adminRequiredSelectionRows(form).find(row => adminRequiredSelectionMissing(row, form));
            if (!missingRow) {
                return true;
            }

            markAdminRequiredSelectionRow(missingRow);
            const label = adminRequiredSelectionLabel(missingRow);
            window.alert(label + '을(를) 선택해 주세요.');
            const focusTarget = missingRow.querySelector('select:not(:disabled), input[type="checkbox"]:not(:disabled), input[type="radio"]:not(:disabled)');
            if (focusTarget && typeof focusTarget.focus === 'function') {
                focusTarget.focus({ preventScroll: true });
            }
            missingRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return false;
        };

        const cssNumberValue = value => {
            const number = parseFloat(String(value || '0'));
            return Number.isFinite(number) ? number : 0;
        };

        const cssLengthToPixels = value => {
            const rawValue = String(value || '').trim();
            const number = cssNumberValue(rawValue);
            if (rawValue.endsWith('rem')) {
                return number * cssNumberValue(window.getComputedStyle(document.documentElement).fontSize);
            }
            if (rawValue.endsWith('em')) {
                return number * cssNumberValue(window.getComputedStyle(document.body).fontSize);
            }

            return number;
        };

        const adminStickyOffset = tabs => {
            const rootStyle = window.getComputedStyle(document.documentElement);
            const shellBar = document.getElementById('hd_top');
            const shellBarHeight = shellBar ? shellBar.getBoundingClientRect().height : cssLengthToPixels(rootStyle.getPropertyValue('--admin-shell-bar-height'));
            const activeTabs = tabs && tabs.offsetParent !== null ? tabs : document.querySelector('.sticky-tabs.anchor-tabs');
            const tabsHeight = activeTabs ? activeTabs.getBoundingClientRect().height : (cssLengthToPixels(rootStyle.getPropertyValue('--config-tabs-height')) || 52);
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
            let lastDirectTabScrollAt = 0;
            let activeLink = null;
            const visiblePairs = () => pairs.filter(pair => {
                return !pair.link.hidden && !pair.section.hidden && pair.section.offsetParent !== null;
            });
            const markDirectTabScroll = () => {
                lastDirectTabScrollAt = Date.now();
            };
            const canAutoScrollTab = () => Date.now() - lastDirectTabScrollAt > 700;
            const scrollSectionIntoView = section => {
                const targetTop = Math.max(0, window.scrollY + section.getBoundingClientRect().top - adminStickyOffset(tabs));
                window.scrollTo({ top: targetTop, behavior: scrollBehavior });
            };
            const activePairFromScroll = () => {
                const availablePairs = visiblePairs();
                if (availablePairs.length === 0) {
                    return null;
                }
                const probeY = adminStickyOffset(tabs) + Math.min(96, window.innerHeight * 0.25);
                let activePair = availablePairs[0];
                availablePairs.forEach(pair => {
                    const rect = pair.section.getBoundingClientRect();
                    if (rect.top <= probeY) {
                        activePair = pair;
                    }
                });

                if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                    activePair = availablePairs[availablePairs.length - 1];
                }

                return activePair;
            };

            let ticking = false;
            const sync = () => {
                ticking = false;
                const activePair = activePairFromScroll();
                const nextLink = activePair ? activePair.link : null;
                if (!nextLink) {
                    return;
                }
                const changed = nextLink !== activeLink;
                activeLink = nextLink;
                setAnchorTabActive(tabs, nextLink, {
                    scrollTabIntoView: changed && canAutoScrollTab()
                });
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
                    activeLink = link;
                    setAnchorTabActive(tabs, link, { scrollTabIntoView: true });
                    scrollSectionIntoView(pair.section);
                });
            });
            tabs.addEventListener('wheel', markDirectTabScroll, { passive: true });
            tabs.addEventListener('touchstart', markDirectTabScroll, { passive: true });
            tabs.addEventListener('pointerdown', markDirectTabScroll);
            tabs.addEventListener('keydown', event => {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab'].indexOf(event.key) !== -1) {
                    markDirectTabScroll();
                }
            });
            tabs.addEventListener('focusin', event => {
                const link = event.target && event.target.closest ? event.target.closest('a[href^="#"]') : null;
                if (link && tabs.contains(link)) {
                    scrollAnchorTabIntoView(tabs, link);
                }
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

        const syncConditionalSection = root => {
            if (!root || !root.getAttribute) {
                return;
            }

            const form = root.closest('form') || document;
            const selectSelector = root.getAttribute('data-admin-visible-when-select') || '';
            const checkedSelector = root.getAttribute('data-admin-visible-when-checked') || '';
            const selectSource = selectSelector !== '' ? form.querySelector(selectSelector) || document.querySelector(selectSelector) : null;
            const checkedSource = checkedSelector !== '' ? form.querySelector(checkedSelector) || document.querySelector(checkedSelector) : null;
            const visible = (selectSelector === '' || !!(selectSource && selectSource.value))
                && (checkedSelector === '' || !!(checkedSource && checkedSource.checked));
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

        const syncBodyEditorGroup = group => {
            if (!group || !group.querySelectorAll) {
                return;
            }

            const checkedMode = group.querySelector('[data-admin-body-editor-mode]:checked');
            const activeMode = checkedMode ? checkedMode.value : '';
            Array.prototype.slice.call(group.querySelectorAll('[data-admin-body-editor-panel]')).forEach(panel => {
                const active = panel.getAttribute('data-admin-body-editor-panel') === activeMode;
                panel.hidden = !active;
                Array.prototype.slice.call(panel.querySelectorAll('textarea')).forEach(textarea => {
                    textarea.required = active;
                });
            });
        };

        const syncBodyEditorGroups = root => {
            if (!root || !root.querySelectorAll) {
                return;
            }

            Array.prototype.slice.call(root.querySelectorAll('[data-admin-body-editor-mode-group]')).forEach(syncBodyEditorGroup);
        };

        const syncBodyEditorValuesBeforeSubmit = form => {
            if (!form || !form.querySelectorAll) {
                return;
            }

            Array.prototype.slice.call(form.querySelectorAll('[data-admin-body-editor-mode-group]')).forEach(group => {
                const checkedMode = group.querySelector('[data-admin-body-editor-mode]:checked');
                if (!checkedMode || checkedMode.value !== 'ckeditor') {
                    return;
                }

                const textarea = group.querySelector('[data-admin-body-editor-panel="ckeditor"] textarea');
                if (!textarea || !textarea.id || !window.srCkeditorInstances || !window.srCkeditorInstances[textarea.id]) {
                    return;
                }

                const editor = window.srCkeditorInstances[textarea.id];
                if (editor && typeof editor.getData === 'function') {
                    textarea.value = editor.getData();
                }
            });
        };

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

        const normalizeColorScheme = scheme => {
            const normalized = String(scheme || '').trim().toLowerCase();
            return ['light', 'dark', 'system'].includes(normalized) ? normalized : 'light';
        };

        const storeColorScheme = scheme => {
            try {
                window.localStorage.setItem(colorSchemeStorageKey, normalizeColorScheme(scheme));
            } catch (error) {}
        };

        const syncColorSchemeControls = scheme => {
            const nextScheme = normalizeColorScheme(scheme);
            colorSchemeControls.forEach(control => {
                if (control.type === 'radio') {
                    control.checked = control.value === nextScheme;
                } else {
                    control.value = nextScheme;
                }
            });
        };

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
            const nextScheme = normalizeColorScheme(scheme);
            const nextTheme = resolvedThemeForScheme(nextScheme);
            document.documentElement.setAttribute('data-color-scheme', nextScheme);
            document.documentElement.setAttribute('data-theme', nextTheme);
            storeColorScheme(nextScheme);
            syncColorSchemeControls(nextScheme);
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

        const scrollCurrentMenuIntoView = () => {
            if (!menuScroll) {
                return;
            }

            const currentMenu = menuScroll.querySelector('.admin-nav-sub-item.is-current, .admin-nav-item.is-current, .admin-sidebar-auxiliary-link.is-current, [aria-current="page"]');
            if (!currentMenu) {
                return;
            }

            const target = currentMenu.closest('.admin-nav-sub-item, .admin-nav-item, li') || currentMenu;
            const menuRect = menuScroll.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const targetTop = targetRect.top - menuRect.top + menuScroll.scrollTop;
            const targetBottom = targetTop + targetRect.height;
            const viewportTop = menuScroll.scrollTop;
            const viewportBottom = viewportTop + menuScroll.clientHeight;
            const edgePadding = 24;

            if (targetTop >= viewportTop + edgePadding && targetBottom <= viewportBottom - edgePadding) {
                return;
            }

            const nextTop = Math.max(0, Math.round(targetTop - (menuScroll.clientHeight - targetRect.height) / 2));
            const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            menuScroll.scrollTo({
                top: nextTop,
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
            });
        };

        const positionSidebarFlyout = item => {
            if (!body.classList.contains('admin-sidebar-condensed')) return;
            const panel = item.querySelector('.admin-nav-panel');
            const trigger = item.querySelector('.admin-nav-trigger');
            if (!panel || !trigger) return;
            const anchor = trigger.getBoundingClientRect();
            const height = panel.getBoundingClientRect().height;
            const top = Math.max(8, Math.min(anchor.top, window.innerHeight - height - 8));
            panel.style.setProperty('--admin-flyout-top', `${top}px`);
            panel.style.setProperty('--admin-flyout-left', `${anchor.right}px`);
        };

        const positionVisibleSidebarFlyouts = () => {
            if (!navRoot) return;
            navRoot.querySelectorAll('.admin-nav-item:hover, .admin-nav-item:focus-within').forEach(positionSidebarFlyout);
        };

        const syncDesktopSidebarState = () => {
            if (!gnb || !container || !desktopToggle) {
                return;
            }

            const collapsed = gnb.classList.contains('gnb_small');
            const desktopCollapsed = !isMobileViewport() && collapsed;
            body.classList.toggle('admin-sidebar-condensed', desktopCollapsed);
            positionVisibleSidebarFlyouts();
            container.classList.toggle('container-small', desktopCollapsed);
            desktopToggle.classList.toggle('btn_gnb_open', desktopCollapsed);
            desktopToggle.setAttribute('aria-pressed', desktopCollapsed ? 'true' : 'false');
            const desktopToggleIcon = desktopToggle.querySelector('[data-sr-material-icon]');
            if (desktopToggleIcon) {
                desktopToggleIcon.textContent = desktopCollapsed ? 'keyboard_double_arrow_right' : 'keyboard_double_arrow_left';
            }
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
                positionVisibleSidebarFlyouts();
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
                bodyParams.set('admin_color_scheme', nextScheme);

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
                    const savedScheme = payload && payload.admin_color_scheme ? payload.admin_color_scheme : nextScheme;
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
            navItems.forEach(item => {
                item.addEventListener('mouseenter', () => positionSidebarFlyout(item));
                item.addEventListener('focusin', () => positionSidebarFlyout(item));
            });
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

            const confirmPhraseInput = event.target && event.target.closest
                ? event.target.closest('[data-confirm-phrase]')
                : null;
            if (confirmPhraseInput) {
                validateConfirmPhraseFields(confirmPhraseInput.closest('form') || confirmPhraseInput);
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

            const bodyEditorMode = event.target && event.target.closest
                ? event.target.closest('[data-admin-body-editor-mode]')
                : null;
            if (bodyEditorMode) {
                syncBodyEditorGroup(bodyEditorMode.closest('[data-admin-body-editor-mode-group]'));
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
            document.querySelectorAll('[data-admin-visible-when-select],[data-admin-visible-when-checked]').forEach(syncConditionalSection);
        });

        document.querySelectorAll('[data-filtering-toggle-group]').forEach(syncFilteringToggleGroup);
        syncBodyEditorGroups(document);
        document.querySelectorAll('[data-admin-asset-amount-sync]').forEach(root => syncAssetAmountGroup(root));
        document.querySelectorAll('[data-admin-asset-unit-group]').forEach(syncAssetUnitGroup);
        document.querySelectorAll('[data-admin-setting-source-group]').forEach(syncSettingSourceGroup);
        document.querySelectorAll('[data-admin-visible-when-select],[data-admin-visible-when-checked]').forEach(syncConditionalSection);

        document.addEventListener('submit', event => {
            const validationForm = event.target && event.target.closest
                ? event.target.closest('[data-sr-validate-form]')
                : null;
            syncBodyEditorValuesBeforeSubmit(event.target);
            syncBodyEditorGroups(event.target);
            validateConfirmPhraseFields(event.target);
            if (validationForm && (!validationForm.checkValidity() || !validateSrForm(validationForm, true))) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            if (!validateAdminRequiredSelections(event.target)) {
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

        layoutMenuLists.forEach(list => {
            const template = list.querySelector('[data-admin-layout-menu-template]');
            if (template) {
                template.hidden = true;
                layoutMenuRowFields(template).forEach(field => {
                    field.disabled = true;
                });
            }
            layoutMenuRows(list).forEach(row => {
                const select = layoutMenuRowSelect(row);
                if (select && select.value === '' && row.hasAttribute('data-admin-layout-menu-remove-empty')) {
                    hideLayoutMenuRow(row);
                } else {
                    showLayoutMenuRow(row);
                }
            });
            list.addEventListener('click', event => {
                const eventTarget = event.target instanceof Element ? event.target : event.target.parentElement;
                if (!eventTarget) {
                    return;
                }

                const addButton = eventTarget.closest('[data-admin-layout-menu-add]');
                if (addButton && list.contains(addButton)) {
                    event.preventDefault();
                    addLayoutMenuRow(list, { focus: true });
                    syncLayoutMenuList(list);
                    return;
                }

                const removeButton = eventTarget.closest('[data-admin-layout-menu-remove]');
                if (removeButton && list.contains(removeButton)) {
                    event.preventDefault();
                    hideLayoutMenuRow(removeButton.closest('[data-admin-layout-menu-row]'));
                    syncLayoutMenuList(list);
                }
            });
            syncLayoutMenuList(list);
        });

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
        applyColorScheme(document.documentElement.getAttribute('data-color-scheme') || 'light');
        document.querySelectorAll('.table-wrapper').forEach(wrapper => {
            if (wrapper.getAttribute('tabindex') === '0') {
                wrapper.removeAttribute('tabindex');
            }
            if (!wrapper.hasAttribute('aria-label') && !wrapper.hasAttribute('aria-labelledby')) {
                wrapper.setAttribute('aria-label', 'Scrollable table');
            }
        });
        updateMenuScrollbar();
        window.requestAnimationFrame(() => {
            updateMenuScrollbar();
            scrollCurrentMenuIntoView();
            window.setTimeout(updateMenuScrollbar, 280);
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AdminShell.init();
});
