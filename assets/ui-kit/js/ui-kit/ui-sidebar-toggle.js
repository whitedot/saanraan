(function () {
    var sidebar = document.getElementById('ui-sidebar');
    var toggle = document.getElementById('ui-sidebar-toggle');
    var backdrop = document.getElementById('ui-sidebar-backdrop');

    if (!sidebar || !toggle || !backdrop) {
        return;
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('ui-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function openSidebar() {
        sidebar.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.classList.add('ui-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    backdrop.addEventListener('click', closeSidebar);

    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });
})();
