(function () {
    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var button = target.closest('[data-toy-popup-layer-close]');
        if (!button) {
            return;
        }

        var popup = button.closest('[data-toy-popup-layer]');
        if (!popup) {
            return;
        }

        var popupId = popup.getAttribute('data-popup-id');
        var days = parseInt(popup.getAttribute('data-cookie-days') || '0', 10);
        if (popupId && days > 0) {
            var expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = 'toy_popup_layer_' + popupId + '_dismissed=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
        }

        popup.remove();
    });
}());
