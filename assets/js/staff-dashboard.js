(function () {
    'use strict';

    var body = document.body;
    var navigationScrollKey = 'cleargo.staff.navigation';
    var pendingNavigation = null;
    var restoreNavigation = null;
    var scrollContainers = '.table-wrap, .staff-sidebar, #facilities-item-content';
    try {
        restoreNavigation = JSON.parse(window.sessionStorage.getItem(navigationScrollKey) || 'null');
        window.sessionStorage.removeItem(navigationScrollKey);
    } catch (error) { /* Navigation still works when browser storage is unavailable. */ }

    function rememberStaffView(form) {
        var destination = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        if (destination.origin !== window.location.origin || form.target === '_blank' || form.matches('.logout-form')
            || destination.pathname.endsWith('/logout.php') || form.method.toLowerCase() === 'dialog') {
            pendingNavigation = null;
            return;
        }
        if (form.method.toLowerCase() === 'get' && destination.pathname !== window.location.pathname) { return; }
        if (form.method.toLowerCase() === 'post') {
            var context = form.querySelector('input[name="staff_return_view"]');
            if (!context) {
                context = document.createElement('input');
                context.type = 'hidden';
                context.name = 'staff_return_view';
                form.appendChild(context);
            }
            context.value = JSON.stringify({ path: window.location.pathname, query: window.location.search.slice(1) });
        }
        pendingNavigation = captureStaffScroll();
    }

    function captureStaffScroll() {
        return {
            path: window.location.pathname, hash: window.location.hash, time: Date.now(), x: window.scrollX, y: window.scrollY,
            containers: Array.from(document.querySelectorAll(scrollContainers)).map(function (element, index) {
                return { id: element.id, index: index, x: element.scrollLeft, y: element.scrollTop };
            })
        };
    }

    document.addEventListener('submit', function (event) {
        if (!event.defaultPrevented && event.target instanceof HTMLFormElement) { rememberStaffView(event.target); }
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
        pendingNavigation = null;
        var destination = new URL(link.href, window.location.href);
        if (link.target === '_blank' || link.hasAttribute('download') || !link.closest('main') || destination.origin !== window.location.origin
            || destination.pathname !== window.location.pathname || destination.hash || destination.searchParams.has('export')) { return; }
        pendingNavigation = captureStaffScroll();
    });
    window.addEventListener('pagehide', function () {
        try {
            if (pendingNavigation) { window.sessionStorage.setItem(navigationScrollKey, JSON.stringify(pendingNavigation)); }
            else { window.sessionStorage.removeItem(navigationScrollKey); }
        } catch (error) { /* Keep normal navigation if storage is unavailable. */ }
    });
    window.addEventListener('pageshow', function (event) {
        pendingNavigation = null;
        if (event.persisted || !restoreNavigation || restoreNavigation.path !== window.location.pathname
            || Date.now() - restoreNavigation.time > 10 * 60 * 1000
            || (window.location.hash && window.location.hash !== restoreNavigation.hash)) { return; }
        var saved = restoreNavigation;
        restoreNavigation = null;
        window.requestAnimationFrame(function () {
            var containers = document.querySelectorAll(scrollContainers);
            (saved.containers || []).forEach(function (position) {
                var element = (position.id && document.getElementById(position.id)) || containers[position.index];
                if (element) { element.scrollTo({ left: position.x, top: position.y, behavior: 'instant' }); }
            });
            window.scrollTo({ left: saved.x, top: saved.y, behavior: 'instant' });
            window.requestAnimationFrame(function () { window.scrollTo({ left: saved.x, top: saved.y, behavior: 'instant' }); });
        });
    });
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

    var toastTimer = null;
    var toastQueue = [];
    var toastOpen = false;

    function closeNotificationToast() {
        var toast = document.querySelector('.notification-toast');
        if (!toast || toast.dataset.closing === 'true') { return; }
        toast.dataset.closing = 'true';
        window.clearTimeout(toastTimer);
        toast.classList.remove('visible');
        toast.addEventListener('transitionend', function () { toast.remove(); }, { once: true });
        window.setTimeout(function () { if (toast.isConnected) { toast.remove(); } }, 500);
        window.setTimeout(function () {
            toastOpen = false;
            var next = toastQueue.shift();
            if (next) { showNotificationToast(next.notification, next.additionalCount); }
        }, 500);
    }

    function showNotificationToast(notification, additionalCount) {
        if (toastOpen) {
            toastQueue.push({ notification: notification, additionalCount: additionalCount });
            return;
        }
        toastOpen = true;
        var toast = document.createElement('aside');
        toast.className = 'notification-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        var copy = document.createElement('div');
        var title = document.createElement('strong');
        title.textContent = notification.title + (additionalCount > 0 ? ' (+' + additionalCount + ' more)' : '');
        var message = document.createElement('p');
        message.textContent = notification.message;
        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '\u00d7';
        close.addEventListener('click', closeNotificationToast, { once: true });
        copy.appendChild(title);
        copy.appendChild(message);
        toast.appendChild(copy);
        toast.appendChild(close);
        document.body.appendChild(toast);
        window.requestAnimationFrame(function () { toast.classList.add('visible'); });
        toastTimer = window.setTimeout(closeNotificationToast, 6000);
    }

    function updateNotificationBadge(center, unreadCount) {
        var toggle = center.querySelector('.notification-toggle');
        if (!toggle) { return; }
        var badge = toggle.querySelector('.notification-count');
        if (unreadCount < 1) {
            if (badge) { badge.remove(); }
        } else {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-count';
                toggle.appendChild(badge);
            }
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        }
        toggle.setAttribute('aria-label', 'View alerts and notifications' + (unreadCount > 0 ? ' (' + unreadCount + ' unread)' : ''));
    }

    function renderNotifications(center, notifications, unreadCount) {
        var menu = center.querySelector('.notification-menu');
        var list = menu && menu.querySelector('ul');
        var total = menu && menu.querySelector('.notification-heading span');
        if (!list) { return; }
        list.textContent = '';
        if (notifications.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'notification-empty';
            empty.textContent = 'No alerts or notifications yet.';
            list.appendChild(empty);
        }
        notifications.forEach(function (notification) {
            var item = document.createElement('li');
            item.className = 'notification-item' + (notification.is_read ? '' : ' unread');
            item.dataset.notificationId = String(notification.id);
            var title = document.createElement('strong');
            title.textContent = notification.title;
            var message = document.createElement('span');
            message.textContent = notification.message;
            var time = document.createElement('time');
            time.textContent = notification.created_at;
            item.appendChild(title);
            item.appendChild(message);
            item.appendChild(time);
            list.appendChild(item);
        });
        if (total) { total.textContent = notifications.length + ' total'; }
        updateNotificationBadge(center, unreadCount);
    }

    function refreshNotifications(center, announce) {
        var endpoint = center.dataset.notificationEndpoint;
        if (!endpoint || document.hidden) { return; }
        fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) { if (!response.ok) { throw new Error('Notification refresh failed'); } return response.json(); })
            .then(function (payload) {
                var previousId = Number(center.dataset.latestNotificationId || 0);
                var fresh = payload.notifications.filter(function (item) { return !item.is_read && item.id > previousId; });
                renderNotifications(center, payload.notifications, payload.unread_count);
                if (payload.notifications.length > 0) {
                    center.dataset.latestNotificationId = String(Math.max.apply(null, payload.notifications.map(function (item) { return item.id; })));
                }
                if (announce && fresh.length > 0) { showNotificationToast(fresh[0], fresh.length - 1); }
            })
            .catch(function () { /* Keep the last successfully rendered state. */ });
    }

    function markNotificationsRead(center) {
        var endpoint = center.dataset.notificationEndpoint;
        var token = center.dataset.csrfToken;
        if (!endpoint || !token || !center.querySelector('.notification-item.unread')) { return; }
        center.querySelectorAll('.notification-item.unread').forEach(function (item) { item.classList.remove('unread'); });
        updateNotificationBadge(center, 0);
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ action: 'mark_read', csrf_token: token })
        }).catch(function () { refreshNotifications(center, false); });
    }

    function bindNotificationCenter(center) {
        if (center.dataset.notificationBound === 'true') { return; }
        var toggle = center.querySelector('.notification-toggle');
        var menu = center.querySelector('.notification-menu');
        if (!toggle || !menu) { return; }
        center.dataset.notificationBound = 'true';
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var willOpen = menu.hasAttribute('hidden');
            document.querySelectorAll('.notification-menu').forEach(function (item) { item.setAttribute('hidden', ''); });
            document.querySelectorAll('.notification-toggle').forEach(function (item) { item.setAttribute('aria-expanded', 'false'); });
            if (willOpen) {
                menu.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                markNotificationsRead(center);
            }
        });
        window.setTimeout(function () { refreshNotifications(center, true); }, 500);
        window.setInterval(function () { refreshNotifications(center, true); }, 20000);
    }

    function initNotificationCenters() {
        document.querySelectorAll('.notification-center').forEach(bindNotificationCenter);
    }

    document.querySelectorAll('.staff-dashboard [data-flash-message]').forEach(function (message) {
        showNotificationToast({ title: 'ClearGo', message: message.textContent.trim() }, 0);
        message.remove();
    });

    initNotificationCenters();

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

    var remarksModal = document.getElementById('office-remarks-modal');
    if (remarksModal) {
        var remarksTrigger = null;
        var remarksText = remarksModal.querySelector('textarea[name="remarks"]');
        var remarksForm = remarksModal.querySelector('form');
        var remarksSubmit = remarksForm.querySelector('button[type="submit"]');
        var remarksScroll = '';
        document.querySelectorAll('[data-office-remarks-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.disabled || remarksModal.open) { return; }
                remarksTrigger = button;
                remarksForm.reset();
                remarksForm.elements.clearance_id.value = button.dataset.clearanceId;
                remarksText.value = button.dataset.remarks || '';
                remarksModal.querySelector('#office-remarks-title').textContent = remarksText.value.trim() ? 'Edit remarks' : 'Add remarks';
                remarksModal.querySelector('#office-remarks-student').textContent = button.dataset.studentName;
                remarksSubmit.disabled = false;
                remarksScroll = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                remarksModal.showModal();
                remarksText.focus({ preventScroll: true });
            });
        });
        remarksModal.querySelectorAll('[data-office-remarks-close]').forEach(function (button) {
            button.addEventListener('click', function () { remarksModal.close(); });
        });
        remarksModal.addEventListener('click', function (event) {
            if (event.target !== remarksModal) { return; }
            var bounds = remarksModal.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
                remarksModal.close();
            }
        });
        remarksModal.addEventListener('close', function () {
            document.body.style.overflow = remarksScroll;
            if (remarksTrigger) { remarksTrigger.focus({ preventScroll: true }); }
        });
        remarksForm.addEventListener('submit', function (event) {
            if (!remarksTrigger || remarksTrigger.disabled) {
                event.preventDefault();
                remarksModal.close();
                return;
            }
            remarksSubmit.disabled = true;
        });
    }

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
                rememberStaffView(form);
                form.submit();
            });
        });
    });

}());
