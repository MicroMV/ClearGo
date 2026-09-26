(function () {
    'use strict';

    var body = document.body;
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButtons = document.querySelectorAll('[data-sidebar-close]');

    function setSidebar(open) {
        body.classList.toggle('sidebar-open', open);
        if (openButton) {
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    if (openButton) {
        openButton.addEventListener('click', function () { setSidebar(true); });
    }
    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () { setSidebar(false); });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { setSidebar(false); }
    });

    document.querySelectorAll('.notification-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var menu = this.nextElementSibling;
            var isOpen = !menu.hasAttribute('hidden');
            document.querySelectorAll('.notification-menu').forEach(function (item) { item.setAttribute('hidden', ''); });
            document.querySelectorAll('.notification-toggle').forEach(function (item) { item.setAttribute('aria-expanded', 'false'); });
            if (!isOpen) {
                menu.removeAttribute('hidden');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.notification-center')) {
            document.querySelectorAll('.notification-menu').forEach(function (menu) { menu.setAttribute('hidden', ''); });
            document.querySelectorAll('.notification-toggle').forEach(function (toggle) { toggle.setAttribute('aria-expanded', 'false'); });
        }
    });
}());
