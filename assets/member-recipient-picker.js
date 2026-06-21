(function () {
    'use strict';

    var activePicker = null;
    var cache = new Map();
    var requestTimer = 0;
    var requestController = null;

    function selectedHashes(picker) {
        var hashes = {};
        picker.querySelectorAll('input[type="hidden"][name="recipient_account_hashes[]"]').forEach(function (input) {
            hashes[input.value] = true;
        });
        return hashes;
    }

    function ensureList(input) {
        var list = input._srRecipientList;
        if (list) {
            return list;
        }
        if (!input.id) {
            input.id = 'sr-recipient-input-' + Math.random().toString(36).slice(2);
        }
        list = document.createElement('div');
        list.id = input.id + '-list';
        list.className = 'dropdown-menu community-recipient-dropdown';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        document.body.appendChild(list);
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');
        input._srRecipientList = list;
        return list;
    }

    function closeList(input) {
        var list = input && input._srRecipientList;
        if (list) {
            list.hidden = true;
            list.innerHTML = '';
        }
        if (input) {
            input.removeAttribute('aria-activedescendant');
            input.setAttribute('aria-expanded', 'false');
        }
        activePicker = null;
    }

    function positionList(input, list) {
        var rect = input.getBoundingClientRect();
        var viewportPadding = 12;
        var width = Math.max(240, Math.min(360, rect.width, window.innerWidth - (viewportPadding * 2)));
        list.style.left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - width - viewportPadding)) + 'px';
        list.style.top = Math.min(window.innerHeight - viewportPadding, rect.bottom + 6) + 'px';
        list.style.width = width + 'px';
        list.style.maxHeight = Math.max(120, Math.min(260, window.innerHeight - rect.bottom - 24)) + 'px';
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

    function syncRequired(picker) {
        var input = picker.querySelector('[data-sr-recipient-picker-input]');
        if (!input) {
            return;
        }
        input.required = picker.querySelectorAll('input[type="hidden"][name="recipient_account_hashes[]"]').length < 1;
    }

    function addRecipient(picker, hash, label) {
        hash = String(hash || '').trim();
        label = String(label || '').trim();
        if (!hash || !label || selectedHashes(picker)[hash]) {
            syncRequired(picker);
            return;
        }

        var chips = picker.querySelector('[data-sr-recipient-picker-selected]');
        var chip = document.createElement('span');
        var text = document.createElement('span');
        var remove = document.createElement('button');
        var hidden = document.createElement('input');

        chip.className = 'community-recipient-chip';
        chip.setAttribute('data-recipient-hash', hash);
        text.textContent = label;
        remove.type = 'button';
        remove.className = 'community-recipient-chip-remove';
        remove.setAttribute('aria-label', label + ' 제거');
        remove.textContent = '×';
        hidden.type = 'hidden';
        hidden.name = 'recipient_account_hashes[]';
        hidden.value = hash;
        remove.addEventListener('click', function () {
            chip.remove();
            syncRequired(picker);
        });
        chip.appendChild(text);
        chip.appendChild(remove);
        chip.appendChild(hidden);
        chips.appendChild(chip);
        syncRequired(picker);
    }

    function selectItem(input, item) {
        var picker = input.closest('[data-sr-recipient-picker]');
        if (!picker) {
            return;
        }
        addRecipient(picker, item.getAttribute('data-public-hash'), item.getAttribute('data-public-name'));
        input.value = '';
        input.focus();
        closeList(input);
    }

    function setActiveIndex(nextIndex) {
        if (!activePicker || !activePicker.items.length) {
            return;
        }
        activePicker.index = (nextIndex + activePicker.items.length) % activePicker.items.length;
        activePicker.items.forEach(function (item, index) {
            var selected = index === activePicker.index;
            item.classList.toggle('active', selected);
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected) {
                activePicker.input.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function renderItems(input, items) {
        var picker = input.closest('[data-sr-recipient-picker]');
        var list = ensureList(input);
        var selected = picker ? selectedHashes(picker) : {};
        var visibleItems = items.filter(function (row) {
            return row && row.public_hash && !selected[String(row.public_hash)];
        });
        list.innerHTML = '';

        if (!visibleItems.length) {
            var empty = document.createElement('div');
            empty.className = 'community-recipient-empty';
            empty.textContent = '일치하는 회원이 없습니다';
            list.appendChild(empty);
            list.hidden = false;
            positionList(input, list);
            input.setAttribute('aria-expanded', 'true');
            activePicker = { input: input, index: -1, items: [] };
            return;
        }

        visibleItems.forEach(function (row, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.id = input.id + '-recipient-option-' + index;
            var publicHash = String(row.public_hash || '');
            var publicName = String(row.public_name || '');
            button.className = 'dropdown-item community-recipient-option' + (index === 0 ? ' active' : '');
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
            button.setAttribute('data-public-hash', publicHash);
            button.setAttribute('data-public-name', publicName);
            button.innerHTML = '<span class="dropdown-profile-avatar community-recipient-option-avatar" aria-hidden="true"></span><span class="community-recipient-option-text"><strong></strong><code></code></span>';
            button.querySelector('.community-recipient-option-avatar').classList.add(avatarClass(publicHash));
            button.querySelector('.community-recipient-option-avatar').textContent = avatarInitial(publicName);
            button.querySelector('strong').textContent = publicName;
            button.querySelector('code').textContent = '#' + String(row.hash_prefix || '');
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                selectItem(input, button);
            });
            list.appendChild(button);
        });

        list.hidden = false;
        positionList(input, list);
        activePicker = { input: input, index: 0, items: Array.prototype.slice.call(list.querySelectorAll('.community-recipient-option')) };
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-activedescendant', activePicker.items[0].id);
    }

    function requestItems(input) {
        if (input._srRecipientComposing) {
            closeList(input);
            return;
        }
        var endpoint = input.getAttribute('data-sr-recipient-endpoint') || '';
        var query = input.value.trim();
        if (!endpoint || query.length < 1) {
            closeList(input);
            return;
        }

        window.clearTimeout(requestTimer);
        requestTimer = window.setTimeout(function () {
            var cacheKey = endpoint + '\n' + query;
            if (cache.has(cacheKey)) {
                renderItems(input, cache.get(cacheKey));
                return;
            }
            if (requestController) {
                requestController.abort();
            }
            requestController = new AbortController();
            fetch(endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query), {
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
                renderItems(input, items);
            }).catch(function (error) {
                if (!error || error.name !== 'AbortError') {
                    closeList(input);
                }
            });
        }, 180);
    }

    function bindPicker(picker) {
        var input = picker.querySelector('[data-sr-recipient-picker-input]');
        if (!input || input._srRecipientBound) {
            return;
        }
        input._srRecipientBound = true;
        picker.querySelectorAll('.community-recipient-chip-remove').forEach(function (button) {
            button.addEventListener('click', function () {
                var chip = button.closest('.community-recipient-chip');
                if (chip) {
                    chip.remove();
                }
                syncRequired(picker);
            });
        });
        syncRequired(picker);
        input.addEventListener('compositionstart', function () {
            input._srRecipientComposing = true;
        });
        input.addEventListener('compositionend', function () {
            input._srRecipientComposing = false;
            requestItems(input);
        });
        input.addEventListener('input', function () {
            requestItems(input);
        });
        input.addEventListener('focus', function () {
            requestItems(input);
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                closeList(input);
            }, 120);
        });
        input.addEventListener('keydown', function (event) {
            if (!activePicker || activePicker.input !== input || ensureList(input).hidden) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveIndex(activePicker.index + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveIndex(activePicker.index - 1);
            } else if (event.key === 'Enter' && activePicker.index >= 0 && activePicker.items[activePicker.index]) {
                event.preventDefault();
                selectItem(input, activePicker.items[activePicker.index]);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeList(input);
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-sr-recipient-picker]').forEach(bindPicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
