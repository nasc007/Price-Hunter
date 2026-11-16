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

    var productRows = document.getElementById('productRows');
    var addProductRow = document.getElementById('addProductRow');

    if (productRows && addProductRow) {
        function refreshProductRows() {
            var rows = productRows.querySelectorAll('[data-product-row]');
            rows.forEach(function (row, index) {
                var legend = row.querySelector('legend');
                var remove = row.querySelector('[data-remove-product]');
                if (legend) {
                    legend.textContent = 'Produto ' + (index + 1);
                }
                if (remove) {
                    remove.disabled = rows.length === 1;
                }
            });
            addProductRow.disabled = rows.length >= 10;
        }

        addProductRow.addEventListener('click', function () {
            var original = productRows.querySelector('[data-product-row]');
            if (!original || productRows.querySelectorAll('[data-product-row]').length >= 10) {
                return;
            }

            var index = Number(productRows.dataset.nextIndex || '1');
            productRows.dataset.nextIndex = String(index + 1);
            var row = original.cloneNode(true);
            row.dataset.index = String(index);

            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/produtos\[\d+\]/, 'produtos[' + index + ']');
                field.classList.remove('is-invalid');
                if (field.tagName === 'SELECT') {
                    field.value = field.dataset.productField === 'frequencia_horas' ? '24' : 'ativo';
                } else {
                    field.value = '';
                }
            });
            row.querySelectorAll('[id]').forEach(function (field) {
                field.id = field.id.replace(/-\d+$/, '-' + index);
            });
            row.querySelectorAll('label[for]').forEach(function (label) {
                label.htmlFor = label.htmlFor.replace(/-\d+$/, '-' + index);
            });
            row.querySelectorAll('.invalid-feedback').forEach(function (feedback) {
                feedback.remove();
            });
            productRows.appendChild(row);
            refreshProductRows();
        });

        productRows.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-remove-product]');
            if (!remove || productRows.querySelectorAll('[data-product-row]').length === 1) {
                return;
            }
            remove.closest('[data-product-row]').remove();
            refreshProductRows();
        });

        refreshProductRows();
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm || 'Confirma esta ação?')) {
                event.preventDefault();
            }
        });
    });

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
