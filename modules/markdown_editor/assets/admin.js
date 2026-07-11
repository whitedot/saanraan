(function () {
    var form = document.querySelector('[data-markdown-editor-settings-form]');
    if (!form || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
        return;
    }

    var previewStyle = form.querySelector('[data-markdown-editor-preview-style]');
    var stylesheet = form.querySelector('[data-markdown-stylesheet]');
    var previewStatus = form.querySelector('[data-markdown-editor-preview-status]');
    var markdownSource = form.querySelector('[data-markdown-source]');
    var renderedPreview = form.querySelector('[data-markdown-rendered-preview]');
    var renderPane = form.querySelector('[data-markdown-render-pane]');
    var editorSurface = form.querySelector('[data-markdown-editor-surface]');
    var inspectorTarget = form.querySelector('[data-markdown-inspector-target]');
    var styleSourceModes = form.querySelectorAll('[data-markdown-style-source-mode]');
    var styleSourceHelp = form.querySelector('[data-markdown-style-source-help]');
    var propertiesSidebar = form.querySelector('[data-markdown-properties-sidebar]');
    var inspectorControls = form.querySelector('[data-markdown-control-templates]');
    var previewTimer = null;
    var previewRequestId = 0;
    var selectedTarget = 'global';
    var globalTextToken = '--md-text';
    var inheritedTextTokenKeys = [
        'text_paragraph_token',
        'text_h1_token',
        'text_h2_token',
        'text_h3_token',
        'text_h4_token',
        'text_h5_token',
        'text_list_token',
        'text_inline_code_token',
        'text_code_block_token',
        'text_table_token'
    ];

    if (propertiesSidebar && inspectorControls) {
        inspectorControls.hidden = false;
        propertiesSidebar.appendChild(inspectorControls);
    }

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

    function cascadeGlobalTextToken(source) {
        if (!source || source.dataset.markdownStyleKey !== 'text_token') {
            return;
        }
        var nextToken = String(source.value || '--md-text');
        inheritedTextTokenKeys.forEach(function (key) {
            controlsForKey(key).forEach(function (control) {
                if (control.value === globalTextToken || control.value === '--md-text') {
                    control.value = nextToken;
                    updateStylesheetControl(control);
                }
            });
        });
        globalTextToken = nextToken;
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
                var tokenMatch = value.match(/^var\((--md-[a-z-]+)\)$/);
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
        var globalTextControl = controlsForKey('text_token')[0];
        globalTextToken = globalTextControl ? String(globalTextControl.value || '--md-text') : '--md-text';
    }

    function setPreviewStatus(message, isError) {
        if (!previewStatus) {
            return;
        }
        previewStatus.textContent = String(message || '');
        previewStatus.classList.toggle('markdown-editor-preview-status-error', Boolean(isError));
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

    function selectInspectorTarget(targetKey, element) {
        var panel = form.querySelector('[data-markdown-inspector-panel="' + targetKey + '"]');
        var normalized = panel ? targetKey : 'global';
        selectedTarget = normalized;

        form.querySelectorAll('[data-markdown-inspector-panel]').forEach(function (candidate) {
            candidate.hidden = candidate.dataset.markdownInspectorPanel !== normalized;
        });
        if (inspectorTarget) {
            inspectorTarget.value = normalized;
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
        }).catch(function () {
            if (requestId === previewRequestId) {
                setPreviewStatus('미리보기를 갱신하지 못했습니다.', true);
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
        form.querySelectorAll('[data-markdown-scheme-toggle]').forEach(function (button) {
            var darkMode = normalized === 'dark';
            var label = darkMode ? '라이트 모드로 전환' : '다크 모드로 전환';
            var icon = button.querySelector('[data-sr-material-icon]');
            button.setAttribute('aria-pressed', darkMode ? 'true' : 'false');
            button.setAttribute('aria-label', label);
            button.title = label;
            if (icon) {
                icon.textContent = darkMode ? 'light_mode' : 'dark_mode';
            }
        });
    }

    function initialRenderScheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
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
            cascadeGlobalTextToken(control);
            updateStylesheetControl(control);
        });
        setStyleSourceMode('custom', false);
        scheduleServerPreview(90);
    }

    form.addEventListener('input', function (event) {
        if (event.target === markdownSource) {
            scheduleServerPreview(280);
            return;
        }
        if (event.target === stylesheet) {
            setStyleSourceMode('custom', false);
            syncControlsFromStylesheet();
            scheduleServerPreview(220);
            return;
        }
        if (event.target.dataset && event.target.dataset.markdownStyleKey) {
            setStyleSourceMode('custom', false);
            syncRelatedControls(event.target);
            cascadeGlobalTextToken(event.target);
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
            setStyleSourceMode(event.target.value, true);
            return;
        }
        if (event.target.dataset && event.target.dataset.markdownStyleKey) {
            setStyleSourceMode('custom', false);
            syncRelatedControls(event.target);
            cascadeGlobalTextToken(event.target);
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

    form.querySelectorAll('[data-markdown-scheme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var currentScheme = renderPane ? String(renderPane.getAttribute('data-color-scheme') || 'light') : 'light';
            setScheme(currentScheme === 'dark' ? 'light' : 'dark');
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
    syncControlsFromStylesheet();
    setScheme(initialRenderScheme());
    setViewMode('split');
    var initialSourceMode = form.querySelector('[data-markdown-style-source-mode]:checked');
    setStyleSourceMode(initialSourceMode ? initialSourceMode.value : 'custom', false);
    selectInspectorTarget('global');
}());
