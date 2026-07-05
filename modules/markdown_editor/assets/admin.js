(function () {
    var form = document.querySelector('[data-markdown-editor-settings-form]');
    if (!form || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
        return;
    }

    var previewStyle = document.querySelector('[data-markdown-editor-preview-style]');
    var previewCards = [
        document.querySelector('[data-markdown-editor-preview-light]'),
        document.querySelector('[data-markdown-editor-preview-dark]')
    ].filter(Boolean);
    var previewTimer = null;
    var previewRequestId = 0;

    function syncNamedControls(source) {
        if (!source || !source.name) {
            return;
        }

        var controls = form.querySelectorAll('input, select, textarea');
        controls.forEach(function (control) {
            if (control !== source && control.name === source.name && (control.type === 'range' || control.type === 'number')) {
                control.value = source.value;
            }
        });
    }

    function updatePreview() {
        var requestId = previewRequestId + 1;
        previewRequestId = requestId;

        window.fetch(form.action.replace(/\/settings(?:\?.*)?$/, '/preview'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            },
            body: new window.FormData(form)
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }
            return response.json();
        }).then(function (payload) {
            if (!payload || payload.ok !== true || requestId !== previewRequestId) {
                return;
            }
            if (previewStyle) {
                previewStyle.textContent = String(payload.css || '');
            }
            previewCards.forEach(function (card) {
                card.innerHTML = String(payload.html || '');
            });
        }).catch(function () {
            return;
        });
    }

    function schedulePreview(event) {
        syncNamedControls(event.target);
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(updatePreview, 250);
    }

    form.addEventListener('input', schedulePreview);
    form.addEventListener('change', schedulePreview);
}());
