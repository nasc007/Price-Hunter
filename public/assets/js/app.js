(function () {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var openBtn = document.getElementById('openSidebar');

    function closeMenu() {
        if (!sidebar) {
            return;
        }
        sidebar.classList.remove('is-open');
        if (backdrop) {
            backdrop.hidden = true;
        }
    }

    function openMenu() {
        if (!sidebar) {
            return;
        }
        sidebar.classList.add('is-open');
        if (backdrop) {
            backdrop.hidden = false;
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', openMenu);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeMenu);
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) {
                return;
            }
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
        });
    });
})();
