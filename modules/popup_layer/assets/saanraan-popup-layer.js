(function () {
    function functionalCookieAllowed() {
        return document.cookie.split(';').some(function (part) {
            var cookie = part.trim();
            var prefix = 'sr_cookie_consent=items:';
            if (cookie === 'sr_cookie_consent=functional') {
                return true;
            }
            if (cookie.indexOf(prefix) !== 0) {
                return false;
            }

            return cookie.substring(prefix.length).split(',').indexOf('popup_dismissal') !== -1;
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var button = target.closest('[data-sr-popup-layer-close], [data-sr-popup-layer-dismiss]');
        if (!button) {
            return;
        }

        var popup = button.closest('[data-sr-popup-layer]');
        if (!popup) {
            return;
        }

        if (button.hasAttribute('data-sr-popup-layer-dismiss') && functionalCookieAllowed()) {
            var popupId = popup.getAttribute('data-popup-id');
            var days = parseInt(popup.getAttribute('data-cookie-days') || '0', 10);
            if (popupId && days > 0) {
                var expires = new Date();
                expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
                document.cookie = 'sr_popup_layer_' + popupId + '_dismissed=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
            }
        }

        popup.remove();
    });
}());
