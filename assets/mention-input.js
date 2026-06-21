(function () {
    'use strict';

    var active = null;
    var cache = new Map();
    var requestTimer = 0;
    var requestController = null;

    function closestSecretChecked(textarea) {
        var form = textarea.form;
        if (!form) {
            return false;
        }
        var secret = form.querySelector('input[type="checkbox"][name="is_secret"]');
        return !!(secret && secret.checked);
    }

    function tokenAtCaret(textarea) {
        var value = textarea.value;
        var end = textarea.selectionStart;
        if (typeof end !== 'number') {
            return null;
        }

        var start = end;
        while (start > 0 && !/[\s@:,.;!?()[\]{}<>]/.test(value.charAt(start - 1))) {
            start--;
        }
        if (start > 0 && value.charAt(start - 1) === '@') {
            start--;
        }
        if (value.charAt(start) !== '@') {
            return null;
        }
        if (start > 0 && /[^\s]/.test(value.charAt(start - 1))) {
            return null;
        }

        var token = value.slice(start + 1, end);
        if (token.length < 1 || /\s/.test(token)) {
            return null;
        }
        var hashIndex = token.lastIndexOf('#');
        var query = hashIndex >= 0 ? token.slice(0, hashIndex) : token;
        query = query.trim();
        if (query === '') {
            return null;
        }

        return { start: start, end: end, query: query };
    }

    function ensureList(textarea) {
        var list = textarea._srMentionList;
        if (list) {
            return list;
        }

        if (!textarea.id) {
            textarea.id = 'sr-mention-input-' + Math.random().toString(36).slice(2);
        }
        list = document.createElement('div');
        list.id = textarea.id + '-mention-list';
        list.className = 'dropdown-menu sr-mention-list sr-mention-dropdown';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        document.body.appendChild(list);
        textarea.setAttribute('aria-autocomplete', 'list');
        textarea.setAttribute('aria-controls', list.id);
        textarea._srMentionList = list;
        return list;
    }

    function closeList(textarea) {
        var list = textarea && textarea._srMentionList;
        if (list) {
            list.hidden = true;
            list.innerHTML = '';
        }
        if (textarea) {
            textarea.removeAttribute('aria-activedescendant');
            textarea.setAttribute('aria-expanded', 'false');
        }
        active = null;
    }

    function caretPosition(textarea, token) {
        var rect = textarea.getBoundingClientRect();
        var style = window.getComputedStyle(textarea);
        var mirror = document.createElement('div');
        var marker = document.createElement('span');
        var properties = [
            'boxSizing', 'width', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
            'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'fontFamily', 'fontSize', 'fontWeight',
            'fontStyle', 'letterSpacing', 'lineHeight', 'textTransform', 'textIndent', 'whiteSpace', 'wordBreak',
            'overflowWrap', 'tabSize'
        ];

        properties.forEach(function (property) {
            mirror.style[property] = style[property];
        });
        mirror.style.left = '-9999px';
        mirror.style.position = 'fixed';
        mirror.style.top = '0';
        mirror.style.visibility = 'hidden';
        mirror.style.whiteSpace = 'pre-wrap';
        mirror.style.overflow = 'hidden';
        mirror.textContent = textarea.value.slice(0, token.end);
        marker.textContent = '\u200b';
        mirror.appendChild(marker);
        document.body.appendChild(mirror);

        var lineHeight = parseFloat(style.lineHeight);
        if (!isFinite(lineHeight)) {
            lineHeight = parseFloat(style.fontSize) * 1.35;
        }
        var left = rect.left + marker.offsetLeft - textarea.scrollLeft;
        var top = rect.top + marker.offsetTop - textarea.scrollTop + lineHeight + 4;
        document.body.removeChild(mirror);

        return {
            left: Math.max(rect.left, Math.min(left, rect.right - 16)),
            top: Math.max(rect.top + 8, Math.min(top, rect.bottom + 6))
        };
    }

    function positionList(textarea, list, token) {
        var rect = textarea.getBoundingClientRect();
        var caret = token ? caretPosition(textarea, token) : { left: rect.left, top: rect.bottom + 6 };
        var viewportPadding = 12;
        var width = Math.max(240, Math.min(360, rect.width, window.innerWidth - (viewportPadding * 2)));
        var left = Math.max(viewportPadding, Math.min(caret.left, window.innerWidth - width - viewportPadding));
        var below = window.innerHeight - caret.top - viewportPadding;
        var above = caret.top - rect.top;
        var maxHeight = Math.max(120, Math.min(240, below > 140 ? below : Math.max(above, 120)));
        var top = caret.top;

        list.style.maxHeight = maxHeight + 'px';
        if (below < 140 && above > below) {
            top = Math.max(viewportPadding, caret.top - Math.min(list.offsetHeight || maxHeight, maxHeight) - 8);
        }

        list.style.left = left + 'px';
        list.style.top = Math.min(window.innerHeight - viewportPadding, top) + 'px';
        list.style.width = width + 'px';
    }

    function avatarClass(hash) {
        var value = 0;
        String(hash || '').split('').forEach(function (character) {
            value = (value + character.charCodeAt(0)) % 12;
        });
        return 'member-avatar-color-' + value;
    }

    function avatarInitial(name) {
        var value = String(name || '').trim();
        return value === '' ? 'M' : value.charAt(0).toUpperCase();
    }

    function selectItem(textarea, item) {
        var token = active && active.textarea === textarea ? active.token : tokenAtCaret(textarea);
        if (!token) {
            return;
        }
        var insertText = item.getAttribute('data-insert-text') || '';
        if (insertText === '') {
            return;
        }

        var value = textarea.value;
        textarea.value = value.slice(0, token.start) + insertText + value.slice(token.end);
        var next = token.start + insertText.length;
        textarea.focus();
        textarea.setSelectionRange(next, next);
        closeList(textarea);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function renderItems(textarea, token, items) {
        var list = ensureList(textarea);
        list.innerHTML = '';

        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'sr-mention-empty community-recipient-empty';
            empty.textContent = '일치하는 회원이 없습니다';
            list.appendChild(empty);
            list.hidden = false;
            positionList(textarea, list, token);
            textarea.setAttribute('aria-expanded', 'true');
            textarea.removeAttribute('aria-activedescendant');
            active = { textarea: textarea, token: token, index: -1, items: [] };
            return;
        }

        items.forEach(function (row, index) {
            var button = document.createElement('button');
            var publicHash = String(row.public_hash || '');
            var publicName = String(row.public_name || '');
            button.type = 'button';
            button.id = textarea.id + '-mention-option-' + index;
            button.className = 'dropdown-item sr-mention-option community-recipient-option' + (index === 0 ? ' active' : '');
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            button.setAttribute('data-insert-text', String(row.insert_text || ''));
            button.innerHTML = '<span class="dropdown-profile-avatar community-recipient-option-avatar" aria-hidden="true"></span><span class="community-recipient-option-text"><strong></strong><code></code></span>';
            button.querySelector('.community-recipient-option-avatar').classList.add(avatarClass(publicHash));
            button.querySelector('.community-recipient-option-avatar').textContent = avatarInitial(publicName);
            button.querySelector('strong').textContent = publicName;
            button.querySelector('code').textContent = '#' + String(row.hash_prefix || '');
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                selectItem(textarea, button);
            });
            list.appendChild(button);
        });

        list.hidden = false;
        positionList(textarea, list, token);
        active = { textarea: textarea, token: token, index: 0, items: Array.prototype.slice.call(list.querySelectorAll('.sr-mention-option')) };
        textarea.setAttribute('aria-expanded', 'true');
        textarea.setAttribute('aria-activedescendant', active.items[0].id);
    }

    function setActiveIndex(nextIndex) {
        if (!active || !active.items.length) {
            return;
        }
        active.index = (nextIndex + active.items.length) % active.items.length;
        active.items.forEach(function (item, index) {
            var selected = index === active.index;
            item.classList.toggle('active', selected);
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected) {
                active.textarea.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function requestItems(textarea) {
        if (textarea._srMentionComposing || closestSecretChecked(textarea)) {
            closeList(textarea);
            return;
        }

        var endpoint = textarea.getAttribute('data-sr-mention-endpoint') || '';
        var token = tokenAtCaret(textarea);
        if (!endpoint || !token) {
            closeList(textarea);
            return;
        }

        window.clearTimeout(requestTimer);
        requestTimer = window.setTimeout(function () {
            var cacheKey = endpoint + '\n' + token.query;
            if (cache.has(cacheKey)) {
                renderItems(textarea, token, cache.get(cacheKey));
                return;
            }
            if (requestController) {
                requestController.abort();
            }
            requestController = new AbortController();
            fetch(endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(token.query), {
                credentials: 'same-origin',
                signal: requestController.signal
            }).then(function (response) {
                if (!response.ok) {
                    return { items: [] };
                }
                return response.json();
            }).then(function (payload) {
                var items = Array.isArray(payload.items) ? payload.items : [];
                cache.set(cacheKey, items);
                renderItems(textarea, token, items);
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                closeList(textarea);
            });
        }, 180);
    }

    function bindTextarea(textarea) {
        if (textarea._srMentionBound) {
            return;
        }
        textarea._srMentionBound = true;
        textarea.addEventListener('compositionstart', function () {
            textarea._srMentionComposing = true;
        });
        textarea.addEventListener('compositionend', function () {
            textarea._srMentionComposing = false;
            requestItems(textarea);
        });
        textarea.addEventListener('input', function () {
            requestItems(textarea);
        });
        textarea.addEventListener('click', function () {
            requestItems(textarea);
        });
        textarea.addEventListener('blur', function () {
            window.setTimeout(function () {
                closeList(textarea);
            }, 120);
        });
        textarea.addEventListener('keydown', function (event) {
            if (!active || active.textarea !== textarea || ensureList(textarea).hidden) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveIndex(active.index + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveIndex(active.index - 1);
            } else if (event.key === 'Home' && active.items.length) {
                event.preventDefault();
                setActiveIndex(0);
            } else if (event.key === 'End' && active.items.length) {
                event.preventDefault();
                setActiveIndex(active.items.length - 1);
            } else if (event.key === 'Enter' && active.index >= 0 && active.items[active.index]) {
                event.preventDefault();
                selectItem(textarea, active.items[active.index]);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeList(textarea);
            }
        });

        if (textarea.form) {
            var secret = textarea.form.querySelector('input[type="checkbox"][name="is_secret"]');
            if (secret) {
                secret.addEventListener('change', function () {
                    if (secret.checked) {
                        closeList(textarea);
                    }
                });
            }
        }
    }

    function init() {
        document.querySelectorAll('textarea[data-sr-mention-input]').forEach(bindTextarea);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
