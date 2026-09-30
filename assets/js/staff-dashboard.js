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

    document.querySelectorAll('.submission .proofs').forEach(function (proofs) {
        var items = Array.prototype.slice.call(proofs.querySelectorAll('a'));
        if (items.length < 2) { return; }
        var current = 0;
        var previous = document.createElement('button'); previous.type = 'button'; previous.className = 'proof-carousel-arrow previous'; previous.textContent = '‹';
        var next = document.createElement('button'); next.type = 'button'; next.className = 'proof-carousel-arrow next'; next.textContent = '›';
        function show(index) { current = (index + items.length) % items.length; items.forEach(function (item, position) { item.hidden = position !== current; }); }
        previous.addEventListener('click', function () { show(current - 1); }); next.addEventListener('click', function () { show(current + 1); });
        proofs.appendChild(previous); proofs.appendChild(next); show(0);
    });

    document.querySelectorAll('.submission .return[name="action"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            var form = button.closest('form');
            if (!form) { return; }
            event.preventDefault();
            var comment = form.querySelector('textarea[name="reason"]');
            if (!comment) { return; }
            var modal = document.createElement('div');
            modal.className = 'return-comment-modal';
            modal.innerHTML = '<div class="return-comment-dialog" role="dialog" aria-modal="true" aria-label="Return for resubmission"><h2>Return for resubmission</h2><p>Tell the student what needs to be corrected.</p><textarea required maxlength="1000" placeholder="Write your comment"></textarea><div><button type="button" class="cancel">Cancel</button><button type="button" class="confirm">Return submission</button></div></div>';
            document.body.appendChild(modal);
            var modalComment = modal.querySelector('textarea');
            modalComment.focus();
            modal.querySelector('.cancel').addEventListener('click', function () { modal.remove(); });
            modal.addEventListener('click', function (clickEvent) { if (clickEvent.target === modal) { modal.remove(); } });
            modal.querySelector('.confirm').addEventListener('click', function () {
                if (modalComment.value.trim() === '') { modalComment.reportValidity(); return; }
                comment.value = modalComment.value.trim();
                var action = document.createElement('input'); action.type = 'hidden'; action.name = 'action'; action.value = 'return'; form.appendChild(action);
                form.submit();
            });
        });
    });

    document.querySelectorAll('.muted').forEach(function (item) {
        if (item.textContent.trim() === 'Will be confirmed when the CCS Dean approves') { item.remove(); }
    });

    var submittedPage = document.querySelector('main.page');
    if (submittedPage && !document.querySelector('.submitted-page-topbar')) {
        var account = document.querySelector('.sidebar-user-copy strong');
        var header = document.createElement('header');
        header.className = 'staff-topbar submitted-page-topbar';
        header.innerHTML = '<div class="topbar-title"><h1>CCS Dean Clearance</h1><p>Review academic requirements and process eligible student clearances.</p></div><div class="topbar-actions"><div class="topbar-account"><strong></strong><span>Dean staff account</span></div><div class="notification-center"><button class="notification-toggle" type="button" aria-label="Notifications" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></button><div class="notification-menu" hidden></div></div></div>';
        header.querySelector('.topbar-account strong').textContent = account ? account.textContent : '';
        header.querySelector('.notification-toggle').addEventListener('click', function () {
            var dashboard = document.querySelector('.staff-nav a[href*="/dean/dashboard.php"]');
            var menu = header.querySelector('.notification-menu');
            if (!dashboard || !menu) { return; }
            if (menu.dataset.loaded === 'true') { menu.toggleAttribute('hidden'); return; }
            fetch(dashboard.href, { credentials: 'same-origin' }).then(function (response) { return response.text(); }).then(function (html) {
                var source = new DOMParser().parseFromString(html, 'text/html').querySelector('.notification-menu');
                menu.innerHTML = source ? source.innerHTML : '<p class="notification-empty">No alerts or notifications yet.</p>';
                menu.dataset.loaded = 'true'; menu.removeAttribute('hidden');
            });
        });
        submittedPage.parentNode.insertBefore(header, submittedPage);
    }
}());
