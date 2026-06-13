(function () {
    function setMessage(widget, message) {
        var messageNode = widget.querySelector('[data-sr-reaction-message]');
        if (!messageNode) {
            return;
        }
        messageNode.textContent = message || '';
        messageNode.hidden = !message;
    }

    function setBusy(widget, busy) {
        widget.querySelectorAll('[data-reaction-key]').forEach(function (button) {
            if (!button.hasAttribute('data-sr-original-disabled')) {
                button.setAttribute('data-sr-original-disabled', button.disabled ? '1' : '0');
            }
            button.disabled = busy || button.getAttribute('data-sr-original-disabled') === '1';
        });
    }

    function updateWidget(widget, payload) {
        var activeKey = payload.my_reaction_key || '';
        var counts = payload.counts || {};
        widget.querySelectorAll('[data-reaction-key]').forEach(function (button) {
            var key = button.getAttribute('data-reaction-key') || '';
            var active = key === activeKey;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            var countNode = widget.querySelector('[data-reaction-count="' + key + '"]');
            if (countNode) {
                countNode.textContent = String(counts[key] || 0);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-reaction-key]') : null;
        if (!button || button.disabled) {
            return;
        }
        var widget = button.closest('[data-sr-reaction-widget]');
        if (!widget) {
            return;
        }

        setMessage(widget, '');
        setBusy(widget, true);

        var formData = new FormData();
        formData.append('csrf_token', widget.getAttribute('data-csrf-token') || '');
        formData.append('target_module', widget.getAttribute('data-target-module') || '');
        formData.append('target_type', widget.getAttribute('data-target-type') || '');
        formData.append('target_id', widget.getAttribute('data-target-id') || '');
        formData.append('reaction_key', button.getAttribute('data-reaction-key') || '');
        formData.append('intent', 'toggle');

        fetch(widget.getAttribute('data-action') || '/reaction/write', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw payload;
                }
                return payload;
            });
        }).then(function (payload) {
            updateWidget(widget, payload);
        }).catch(function (error) {
            var message = '리액션을 저장하지 못했습니다.';
            if (error && error.error === 'login_required') {
                message = '로그인 후 반응할 수 있습니다.';
            } else if (error && error.error === 'self_reaction_not_allowed') {
                message = '내가 작성한 대상에는 반응할 수 없습니다.';
            } else if (error && error.error === 'target_not_writable') {
                message = '현재 반응할 수 없는 대상입니다.';
            } else if (error && error.error === 'rate_limited') {
                message = '잠시 후 다시 시도해 주세요.';
            }
            setMessage(widget, message);
        }).finally(function () {
            setBusy(widget, false);
        });
    });
}());
