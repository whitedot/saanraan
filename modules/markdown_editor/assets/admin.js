(function () {
    var form = document.querySelector('[data-markdown-editor-settings-form]');
    if (!form || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
        return;
    }

    var previewStyle = form.querySelector('[data-markdown-editor-preview-style]');
    var stylesheet = form.querySelector('[data-markdown-stylesheet]');
    var cssPreviewStatus = form.querySelector('[data-markdown-css-preview-status]');
    var defaultStylesheet = form.querySelector('[data-markdown-default-stylesheet]');
    var previewStatus = form.querySelector('[data-markdown-editor-preview-status]');
    var markdownSource = form.querySelector('[data-markdown-source]');
    var renderedPreview = form.querySelector('[data-markdown-rendered-preview]');
    var renderPane = form.querySelector('[data-markdown-render-pane]');
    var editorSurface = form.querySelector('[data-markdown-editor-surface]');
    var inspectorTarget = form.querySelector('[data-markdown-inspector-target]');
    var selectedLabel = form.querySelector('[data-markdown-selected-label]');
    var styleSourceModes = form.querySelectorAll('[data-markdown-style-source-mode]');
    var styleSourceHelp = form.querySelector('[data-markdown-style-source-help]');
    var contextToolbar = form.querySelector('[data-markdown-context-toolbar]');
    var toolbarGroups = form.querySelector('[data-markdown-toolbar-groups]');
    var toolbarControls = form.querySelector('[data-markdown-toolbar-controls]');
    var previewTimer = null;
    var previewRequestId = 0;
    var stylesheetPreviewPending = false;
    var selectedTarget = 'global';

    var targetSelectors = {
        paragraph: 'p, strong, em, mark, del',
        heading_common: 'h1, h2, h3, h4, h5, h6',
        h1: 'h1',
        h2: 'h2',
        h3: 'h3',
        h4: 'h4',
        h5: 'h5',
        h6: 'h6',
        link: 'a',
        list: 'ul, ol, li',
        blockquote: 'blockquote',
        inline_code: ':not(pre) > code, kbd, tt',
        code_block: 'pre',
        table: 'table, thead, tbody, tr, th, td',
        hr: 'hr'
    };

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function controlsForKey(key) {
        return Array.prototype.filter.call(form.querySelectorAll('[data-markdown-style-key]'), function (control) {
            return String(control.dataset.markdownStyleKey || '') === String(key || '');
        });
    }

    function syncRelatedControls(source) {
        if (!source || !source.dataset.markdownStyleKey) {
            return;
        }
        controlsForKey(source.dataset.markdownStyleKey).forEach(function (control) {
            if (control !== source) {
                control.value = source.value;
            }
        });
    }

    function stylesheetControlValue(control) {
        var kind = String(control.dataset.markdownStyleKind || 'number');
        if (kind === 'token') {
            return 'var(' + control.value + ')';
        }
        if (kind === 'length_or_none' && Number(control.value) === 0) {
            return 'none';
        }
        return String(control.value) + String(control.dataset.markdownStyleUnit || '');
    }

    function controlPattern(control, global) {
        var key = escapeRegExp(control.dataset.markdownStyleKey || '');
        var property = escapeRegExp(control.dataset.markdownStyleProperty || '');
        return new RegExp('(\\/\\*\\s*sr-control:\\s*' + key + '\\s*\\*\\/\\s*' + property + '\\s*:\\s*)([^;\\n]+)(;)', global ? 'g' : '');
    }

    function updateStylesheetControl(control) {
        if (!stylesheet || !control || !control.dataset.markdownStyleKey) {
            return;
        }
        var value = stylesheetControlValue(control);
        stylesheet.value = stylesheet.value.replace(controlPattern(control, true), function (_match, declarationStart, _oldValue, declarationEnd) {
            return declarationStart + value + declarationEnd;
        });
    }

    function syncControlsFromStylesheet() {
        if (!stylesheet) {
            return;
        }
        var handled = {};
        form.querySelectorAll('[data-markdown-style-key]').forEach(function (control) {
            var key = String(control.dataset.markdownStyleKey || '');
            if (key === '' || handled[key]) {
                return;
            }
            handled[key] = true;
            var match = stylesheet.value.match(controlPattern(control, false));
            if (!match) {
                return;
            }

            var kind = String(control.dataset.markdownStyleKind || 'number');
            var value = String(match[2] || '').trim();
            if (kind === 'token') {
                var tokenMatch = value.match(/^var\((--sr-[a-z-]+)\)$/);
                if (!tokenMatch) {
                    return;
                }
                value = tokenMatch[1];
            } else if (kind === 'choice') {
                value = String(value).trim();
            } else if (kind === 'length_or_none' && value === 'none') {
                value = '0';
            } else {
                var unit = String(control.dataset.markdownStyleUnit || '');
                if (unit !== '' && value.slice(-unit.length) === unit) {
                    value = value.slice(0, -unit.length);
                }
                if (value === '' || !Number.isFinite(Number(value))) {
                    return;
                }
            }

            controlsForKey(key).forEach(function (relatedControl) {
                relatedControl.value = value;
            });
        });
    }

    function setPreviewStatus(message, isError) {
        if (!previewStatus) {
            return;
        }
        previewStatus.textContent = String(message || '');
        previewStatus.classList.toggle('markdown-editor-preview-status-error', Boolean(isError));
    }

    function setCssPreviewStatus(message, isError) {
        if (!cssPreviewStatus) {
            return;
        }
        cssPreviewStatus.textContent = String(message || '');
        cssPreviewStatus.classList.toggle('markdown-editor-preview-status-error', Boolean(isError));
    }

    function setStyleSourceMode(mode, refreshPreview) {
        var normalized = mode === 'default' ? 'default' : 'custom';
        styleSourceModes.forEach(function (control) {
            control.checked = control.value === normalized;
        });
        form.dataset.markdownStyleSourceMode = normalized;
        if (styleSourceHelp) {
            styleSourceHelp.textContent = normalized === 'default'
                ? '원본을 출력 중입니다. 속성을 바꾸면 변경값으로 전환됩니다.'
                : '변경한 스타일시트를 출력 중입니다.';
        }
        if (refreshPreview) {
            scheduleServerPreview(90);
        }
    }

    function firstElementForTarget(targetKey) {
        var selector = targetSelectors[targetKey];
        if (!renderedPreview || !selector) {
            return null;
        }
        return renderedPreview.querySelector(selector);
    }

    function highlightSelectedElement(element) {
        if (!renderedPreview) {
            return;
        }
        renderedPreview.querySelectorAll('[data-markdown-style-selected]').forEach(function (selected) {
            selected.removeAttribute('data-markdown-style-selected');
        });
        if (element && renderedPreview.contains(element)) {
            element.setAttribute('data-markdown-style-selected', 'true');
        }
    }

    function inspectorLabel(targetKey) {
        var option = inspectorTarget ? inspectorTarget.querySelector('option[value="' + targetKey + '"]') : null;
        return option ? String(option.textContent || '') : '전체 스타일';
    }

    function prepareToolbarControlClone(clone) {
        clone.querySelectorAll('.markdown-editor-property').forEach(function (property) {
            var label = property.querySelector('.form-label');
            var labelText = label ? String(label.textContent || '').trim() : '속성 값';
            property.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.setAttribute('aria-label', labelText);
                control.title = labelText;
            });
        });
        clone.querySelectorAll('[id]').forEach(function (element) {
            element.removeAttribute('id');
        });
        clone.querySelectorAll('[for]').forEach(function (element) {
            element.removeAttribute('for');
        });
        clone.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.removeAttribute('name');
            control.removeAttribute('required');
            control.dataset.markdownToolbarControl = 'true';
        });
    }

    function toolbarIconForGroup(groupLabel, group) {
        if (groupLabel === 'Layout' && group && group.querySelector('[data-markdown-style-key^="content_padding_"]')) {
            return 'padding';
        }
        var icons = {
            Margin: 'margin',
            Padding: 'padding',
            Border: 'border_outer',
            Text: 'text_fields',
            Typography: 'text_fields',
            Fill: 'format_color_fill',
            Layout: 'width_full',
            Spacing: 'format_line_spacing',
            Stroke: 'border_style',
            'Fill & Stroke': 'palette',
            'Paragraph details': 'format_indent_increase',
            'Link details': 'link',
            Markers: 'format_list_bulleted',
            'List items': 'checklist',
            Header: 'format_bold',
            Cells: 'grid_on'
        };
        return icons[groupLabel] || 'tune';
    }

    function createToolbarIcon(iconName) {
        var icon = document.createElement('span');
        icon.className = 'sr-icon material-symbols-outlined';
        icon.dataset.srMaterialIcon = '';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = iconName;
        return icon;
    }

    function hideContextToolbarMenu(restoreFocus) {
        if (!toolbarControls || !toolbarGroups) {
            return;
        }
        var activeButton = toolbarGroups.querySelector('[data-markdown-toolbar-group].active');
        toolbarControls.hidden = true;
        toolbarControls.innerHTML = '';
        toolbarGroups.querySelectorAll('[data-markdown-toolbar-group]').forEach(function (button) {
            button.classList.remove('active');
            button.setAttribute('aria-expanded', 'false');
        });
        if (restoreFocus && activeButton) {
            activeButton.focus();
        }
    }

    function showContextToolbarGroup(panel, groupIndex) {
        if (!panel || !toolbarControls || !toolbarGroups) {
            return;
        }
        var groups = panel.querySelectorAll('.markdown-editor-property-group');
        var group = groups[groupIndex] || groups[0];
        toolbarControls.innerHTML = '';
        toolbarGroups.querySelectorAll('[data-markdown-toolbar-group]').forEach(function (button) {
            var active = Number(button.dataset.markdownToolbarGroup) === Number(groupIndex);
            button.classList.toggle('active', active);
            button.setAttribute('aria-expanded', active ? 'true' : 'false');
        });
        if (!group) {
            return;
        }
        var fields = group.querySelector('.markdown-editor-inspector-fields');
        if (!fields) {
            return;
        }
        var clone = fields.cloneNode(true);
        clone.classList.add('markdown-editor-context-fields');
        if (group.classList.contains('markdown-editor-box-group')) {
            clone.classList.add('markdown-editor-context-box-fields');
        }
        if (group.classList.contains('markdown-editor-text-group')) {
            clone.classList.add('markdown-editor-context-text-fields');
        }
        prepareToolbarControlClone(clone);
        toolbarControls.appendChild(clone);
        toolbarControls.hidden = false;
    }

    function updateContextToolbar(targetKey) {
        if (!contextToolbar || !toolbarGroups || !toolbarControls) {
            return;
        }
        var panel = form.querySelector('[data-markdown-inspector-panel="' + targetKey + '"]');
        var groups = panel ? panel.querySelectorAll('.markdown-editor-property-group') : [];
        toolbarGroups.innerHTML = '';
        toolbarControls.innerHTML = '';
        toolbarControls.hidden = true;
        contextToolbar.hidden = groups.length === 0;
        groups.forEach(function (group, index) {
            var summary = group.querySelector('summary');
            var groupLabel = summary ? String(summary.textContent || '').trim() : '속성';
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-icon';
            button.dataset.markdownToolbarGroup = String(index);
            button.setAttribute('aria-label', groupLabel);
            button.setAttribute('aria-haspopup', 'true');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', 'markdown_editor_context_toolbar_menu');
            button.title = groupLabel;
            button.appendChild(createToolbarIcon(toolbarIconForGroup(groupLabel, group)));
            button.addEventListener('click', function () {
                if (button.classList.contains('active') && !toolbarControls.hidden) {
                    hideContextToolbarMenu(false);
                    return;
                }
                showContextToolbarGroup(panel, index);
            });
            toolbarGroups.appendChild(button);
        });
        if (targetKey !== 'global') {
            var separator = document.createElement('span');
            separator.className = 'markdown-editor-toolbar-separator';
            separator.setAttribute('aria-hidden', 'true');
            toolbarGroups.appendChild(separator);

            var resetButton = document.createElement('button');
            resetButton.type = 'button';
            resetButton.className = 'btn btn-sm btn-icon markdown-editor-toolbar-reset';
            resetButton.setAttribute('aria-label', '선택 요소 초기화');
            resetButton.title = '선택 요소 초기화';
            resetButton.appendChild(createToolbarIcon('restart_alt'));
            resetButton.addEventListener('click', function () {
                resetInspectorTarget(targetKey);
                hideContextToolbarMenu(false);
            });
            toolbarGroups.appendChild(resetButton);
        }
    }

    function selectInspectorTarget(targetKey, element) {
        var panel = form.querySelector('[data-markdown-inspector-panel="' + targetKey + '"]');
        var normalized = panel ? targetKey : 'global';
        var targetChanged = selectedTarget !== normalized;
        selectedTarget = normalized;

        form.querySelectorAll('[data-markdown-inspector-panel]').forEach(function (candidate) {
            candidate.hidden = candidate.dataset.markdownInspectorPanel !== normalized;
        });
        if (inspectorTarget) {
            inspectorTarget.value = normalized;
        }
        if (selectedLabel) {
            selectedLabel.textContent = inspectorLabel(normalized);
        }
        if (targetChanged || !toolbarGroups || toolbarGroups.children.length === 0) {
            updateContextToolbar(normalized);
        }
        highlightSelectedElement(element || firstElementForTarget(normalized));
    }

    function inspectorTargetForElement(element) {
        if (!element || element === renderedPreview || element.classList.contains('markdown-editor-body')) {
            return 'global';
        }
        if (element.closest('h1')) { return 'h1'; }
        if (element.closest('h2')) { return 'h2'; }
        if (element.closest('h3')) { return 'h3'; }
        if (element.closest('h4')) { return 'h4'; }
        if (element.closest('h5')) { return 'h5'; }
        if (element.closest('h6')) { return 'h6'; }
        if (element.closest('a')) { return 'link'; }
        if (element.closest('pre')) { return 'code_block'; }
        if (element.closest('code, kbd, tt')) { return 'inline_code'; }
        if (element.closest('table, thead, tbody, tr, th, td')) { return 'table'; }
        if (element.closest('ul, ol, li')) { return 'list'; }
        if (element.closest('blockquote')) { return 'blockquote'; }
        if (element.closest('hr')) { return 'hr'; }
        if (element.closest('p, strong, em, mark, del')) { return 'paragraph'; }
        return 'global';
    }

    function updatePreview() {
        var requestId = previewRequestId + 1;
        previewRequestId = requestId;
        setPreviewStatus('미리보기를 갱신하는 중입니다.', false);

        window.fetch(form.action.replace(/\/settings(?:\?.*)?$/, '/preview'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            },
            body: new window.FormData(form)
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            });
        }).then(function (payload) {
            if (!payload || requestId !== previewRequestId) {
                return;
            }
            if (payload.ok !== true) {
                var errors = Array.isArray(payload.errors) ? payload.errors : [];
                setPreviewStatus(errors[0] || '미리보기를 적용할 수 없습니다.', true);
                if (stylesheetPreviewPending) {
                    setCssPreviewStatus(errors[0] || 'CSS 변경을 미리보기에 반영할 수 없습니다.', true);
                    stylesheetPreviewPending = false;
                }
                return;
            }
            if (previewStyle) {
                previewStyle.textContent = String(payload.css || '');
            }
            if (renderedPreview) {
                renderedPreview.innerHTML = String(payload.html || '');
            }
            selectInspectorTarget(selectedTarget);
            setPreviewStatus('', false);
            if (stylesheetPreviewPending) {
                setCssPreviewStatus('CSS 변경을 미리보기에 반영했습니다.', false);
                stylesheetPreviewPending = false;
            }
        }).catch(function () {
            if (requestId === previewRequestId) {
                setPreviewStatus('미리보기를 갱신하지 못했습니다.', true);
                if (stylesheetPreviewPending) {
                    setCssPreviewStatus('서버 오류로 CSS 변경을 미리보기에 반영하지 못했습니다.', true);
                    stylesheetPreviewPending = false;
                }
            }
        });
    }

    function scheduleServerPreview(delay) {
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(updatePreview, Number(delay) >= 0 ? Number(delay) : 280);
    }

    function setScheme(scheme) {
        var normalized = scheme === 'dark' ? 'dark' : 'light';
        if (renderPane) {
            renderPane.setAttribute('data-color-scheme', normalized);
        }
        form.querySelectorAll('[data-markdown-scheme]').forEach(function (button) {
            var active = button.dataset.markdownScheme === normalized;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setViewMode(viewMode) {
        var normalized = ['editor', 'split', 'preview'].indexOf(viewMode) !== -1 ? viewMode : 'split';
        if (editorSurface) {
            editorSurface.dataset.viewMode = normalized;
        }
        form.querySelectorAll('[data-markdown-pane-toggle]').forEach(function (button) {
            var targetMode = button.dataset.markdownPaneToggle === 'preview' ? 'preview' : 'editor';
            var expanded = normalized === targetMode;
            var label = expanded
                ? '분할 보기로 돌아가기'
                : (targetMode === 'preview' ? '미리보기만 펼치기' : '편집 영역만 펼치기');
            var icon = button.querySelector('[data-sr-material-icon]');
            button.classList.toggle('active', expanded);
            button.setAttribute('aria-pressed', expanded ? 'true' : 'false');
            button.setAttribute('aria-label', label);
            button.title = label;
            if (icon) {
                icon.textContent = expanded
                    ? 'vertical_split'
                    : (targetMode === 'preview' ? 'left_panel_close' : 'right_panel_close');
            }
        });
    }

    function setSidebarTab(tabKey) {
        var normalized = tabKey === 'css' ? 'css' : 'visual';
        form.querySelectorAll('[data-markdown-sidebar-tab]').forEach(function (button) {
            var active = button.dataset.markdownSidebarTab === normalized;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        form.querySelectorAll('[data-markdown-sidebar-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.markdownSidebarPanel !== normalized;
        });
    }

    function resetInspectorTarget(targetKey) {
        var panel = form.querySelector('[data-markdown-inspector-panel="' + targetKey + '"]');
        if (!panel) {
            return;
        }
        var handled = {};
        panel.querySelectorAll('[data-markdown-style-key]').forEach(function (control) {
            var key = String(control.dataset.markdownStyleKey || '');
            if (key === '' || handled[key] || control.dataset.defaultValue === undefined) {
                return;
            }
            handled[key] = true;
            controlsForKey(key).forEach(function (relatedControl) {
                relatedControl.value = control.dataset.defaultValue;
            });
            updateStylesheetControl(control);
        });
        setStyleSourceMode('custom', false);
        scheduleServerPreview(90);
    }

    function resetStyles() {
        if (stylesheet && defaultStylesheet) {
            stylesheet.value = defaultStylesheet.value;
        }
        syncControlsFromStylesheet();
        setStyleSourceMode('default', false);
        selectInspectorTarget('global');
        stylesheetPreviewPending = true;
        setCssPreviewStatus('원본 CSS를 미리보기에 반영하는 중입니다.', false);
        updatePreview();
    }

    form.addEventListener('input', function (event) {
        if (event.target === markdownSource) {
            scheduleServerPreview(280);
            return;
        }
        if (event.target === stylesheet) {
            setStyleSourceMode('custom', false);
            syncControlsFromStylesheet();
            stylesheetPreviewPending = true;
            setCssPreviewStatus('CSS 변경을 확인하고 미리보기에 반영하는 중입니다.', false);
            scheduleServerPreview(220);
            return;
        }
        if (event.target.dataset && event.target.dataset.markdownStyleKey) {
            setStyleSourceMode('custom', false);
            syncRelatedControls(event.target);
            updateStylesheetControl(event.target);
            scheduleServerPreview(90);
        }
    });

    form.addEventListener('change', function (event) {
        if (event.target === inspectorTarget) {
            selectInspectorTarget(event.target.value);
            return;
        }
        if (event.target.matches && event.target.matches('[data-markdown-style-source-mode]')) {
            stylesheetPreviewPending = true;
            setCssPreviewStatus('선택한 CSS를 미리보기에 반영하는 중입니다.', false);
            setStyleSourceMode(event.target.value, true);
            return;
        }
        if (event.target.dataset && event.target.dataset.markdownStyleKey) {
            setStyleSourceMode('custom', false);
            syncRelatedControls(event.target);
            updateStylesheetControl(event.target);
            scheduleServerPreview(90);
            return;
        }
        if (event.target === stylesheet) {
            return;
        }
    });

    if (renderedPreview) {
        renderedPreview.addEventListener('click', function (event) {
            var element = event.target instanceof Element ? event.target : event.target.parentElement;
            var link = element && element.closest ? element.closest('a') : null;
            if (link) {
                event.preventDefault();
            }
            selectInspectorTarget(inspectorTargetForElement(element), element);
        });
    }

    form.querySelectorAll('[data-markdown-sidebar-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            setSidebarTab(button.dataset.markdownSidebarTab);
        });
    });
    form.querySelectorAll('[data-markdown-scheme]').forEach(function (button) {
        button.addEventListener('click', function () {
            setScheme(button.dataset.markdownScheme);
        });
    });
    form.querySelectorAll('[data-markdown-pane-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetMode = button.dataset.markdownPaneToggle === 'preview' ? 'preview' : 'editor';
            var currentMode = editorSurface ? String(editorSurface.dataset.viewMode || 'split') : 'split';
            setViewMode(currentMode === targetMode ? 'split' : targetMode);
        });
    });
    form.querySelectorAll('[data-markdown-select-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            selectInspectorTarget(button.dataset.markdownSelectTarget);
        });
    });
    form.querySelectorAll('[data-markdown-reset-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetInspectorTarget(button.dataset.markdownResetTarget);
        });
    });
    var resetButton = form.querySelector('[data-markdown-reset-all]');
    if (resetButton) {
        resetButton.addEventListener('click', resetStyles);
    }
    document.addEventListener('click', function (event) {
        if (contextToolbar && !contextToolbar.contains(event.target)) {
            hideContextToolbarMenu(false);
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && toolbarControls && !toolbarControls.hidden) {
            hideContextToolbarMenu(true);
        }
    });

    syncControlsFromStylesheet();
    setScheme('light');
    setViewMode('split');
    setSidebarTab('visual');
    var initialSourceMode = form.querySelector('[data-markdown-style-source-mode]:checked');
    setStyleSourceMode(initialSourceMode ? initialSourceMode.value : 'custom', false);
    selectInspectorTarget('global');
}());
