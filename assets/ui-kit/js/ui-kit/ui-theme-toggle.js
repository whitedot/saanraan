(function () {
    var storageKey = 'g5aif-ui-theme';
    var root = document.documentElement;
    var toggleButton = document.getElementById('theme-toggle');
    var toggleIcon = document.getElementById('theme-toggle-icon');
    var toggleLabel = document.getElementById('theme-toggle-label');

    if (!toggleButton || !toggleIcon) {
        return;
    }

    function setTheme(theme, persist) {
        var nextTheme = theme === 'dark' ? 'dark' : 'light';
        var isDark = nextTheme === 'dark';

        root.setAttribute('data-theme', nextTheme);

        if (persist) {
            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (error) {
                // Ignore storage exceptions (private mode, quota exceeded, etc.)
            }
        }

        toggleIcon.setAttribute('data-icon', isDark ? 'tabler:sun' : 'tabler:moon');
        toggleButton.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        toggleButton.setAttribute('aria-label', isDark ? '라이트 모드로 전환' : '다크 모드로 전환');
        toggleButton.setAttribute('title', isDark ? '라이트 모드로 전환' : '다크 모드로 전환');

        if (toggleLabel) {
            toggleLabel.textContent = isDark ? '라이트모드' : '다크모드';
        }

        if (window.Iconify && typeof window.Iconify.scan === 'function') {
            window.Iconify.scan(toggleButton);
        }
    }

    var currentTheme = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    setTheme(currentTheme, false);

    toggleButton.addEventListener('click', function () {
        var activeTheme = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        var nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
        setTheme(nextTheme, true);
    });
})();
