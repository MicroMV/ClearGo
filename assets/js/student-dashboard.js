const notificationButton = document.getElementById('notificationButton');
const notificationBox = document.getElementById('notificationBox');
const notificationWrap = notificationButton?.closest('.notification-wrap');
let notificationToastTimer = null;

function updateStudentNotificationBadge(unreadCount) {
    let badge = notificationButton.querySelector('.notification-count');
    if (unreadCount < 1) {
        badge?.remove();
    } else {
        if (badge === null) {
            badge = document.createElement('span');
            badge.className = 'notification-count';
            notificationButton.appendChild(badge);
        }
        badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    }
    notificationButton.setAttribute('aria-label', 'Notifications' + (unreadCount > 0 ? ' (' + unreadCount + ' unread)' : ''));
}

function closeStudentNotificationToast() {
    const toast = document.querySelector('.student-notification-toast');
    if (toast === null) return;
    window.clearTimeout(notificationToastTimer);
    toast.classList.remove('visible');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    window.setTimeout(() => { if (toast.isConnected) toast.remove(); }, 500);
}

function showStudentNotificationToast(notification, additionalCount = 0) {
    closeStudentNotificationToast();
    const toast = document.createElement('aside');
    toast.className = 'student-notification-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    const copy = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = notification.title + (additionalCount > 0 ? ` (+${additionalCount} more)` : '');
    const message = document.createElement('p');
    message.textContent = notification.message;
    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '\u00d7';
    close.addEventListener('click', closeStudentNotificationToast);
    copy.append(title, message);
    toast.append(copy, close);
    document.body.appendChild(toast);
    window.requestAnimationFrame(() => toast.classList.add('visible'));
    notificationToastTimer = window.setTimeout(closeStudentNotificationToast, 6000);
}

function renderStudentNotifications(notifications, unreadCount) {
    const list = notificationBox.querySelector('.notification-list');
    notificationBox.querySelector('.notification-empty')?.remove();
    list.textContent = '';
    if (notifications.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'notification-empty muted';
        empty.textContent = 'You have no notifications yet.';
        notificationBox.insertBefore(empty, list);
    }
    notifications.forEach((notification) => {
        const item = document.createElement('li');
        item.className = notification.is_read ? '' : 'unread';
        item.dataset.notificationId = String(notification.id);
        const title = document.createElement('strong');
        title.textContent = notification.title;
        const message = document.createElement('div');
        message.textContent = notification.message;
        const time = document.createElement('div');
        time.className = 'muted';
        time.textContent = notification.created_at;
        item.append(title, message, time);
        list.appendChild(item);
    });
    updateStudentNotificationBadge(unreadCount);
}

function refreshStudentNotifications(announce = true) {
    if (!notificationWrap?.dataset.notificationEndpoint || document.hidden) return;
    fetch(notificationWrap.dataset.notificationEndpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => { if (!response.ok) throw new Error('Notification refresh failed'); return response.json(); })
        .then((payload) => {
            const previousId = Number(notificationWrap.dataset.latestNotificationId || 0);
            const fresh = payload.notifications.filter((item) => !item.is_read && item.id > previousId);
            renderStudentNotifications(payload.notifications, payload.unread_count);
            if (payload.notifications.length > 0) {
                notificationWrap.dataset.latestNotificationId = String(Math.max(...payload.notifications.map((item) => item.id)));
            }
            if (announce && fresh.length > 0) showStudentNotificationToast(fresh[0], fresh.length - 1);
        })
        .catch(() => {});
}

function markStudentNotificationsRead() {
    if (!notificationWrap?.dataset.notificationEndpoint || !notificationBox.querySelector('.unread')) return;
    notificationBox.querySelectorAll('.unread').forEach((item) => item.classList.remove('unread'));
    updateStudentNotificationBadge(0);
    fetch(notificationWrap.dataset.notificationEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ action: 'mark_read', csrf_token: notificationWrap.dataset.csrfToken })
    }).catch(() => refreshStudentNotifications(false));
}

notificationButton.addEventListener('click', function (event) {
    event.stopPropagation();
    const willOpen = notificationBox.hidden;
    notificationBox.hidden = !willOpen;
    notificationButton.setAttribute('aria-expanded', String(willOpen));
    if (willOpen) markStudentNotificationsRead();
});
notificationBox.addEventListener('click', function (event) { event.stopPropagation(); });
document.addEventListener('click', function () {
    notificationBox.hidden = true;
    notificationButton.setAttribute('aria-expanded', 'false');
});

const newestUnreadNotification = notificationBox.querySelector('.notification-list li.unread');
if (newestUnreadNotification !== null) {
    showStudentNotificationToast({
        title: newestUnreadNotification.querySelector('strong').textContent,
        message: newestUnreadNotification.querySelector('div').textContent
    }, Math.max(0, notificationBox.querySelectorAll('.notification-list li.unread').length - 1));
}
window.setTimeout(() => refreshStudentNotifications(true), 500);
window.setInterval(refreshStudentNotifications, 20000);
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        notificationBox.hidden = true;
        notificationButton.setAttribute('aria-expanded', 'false');
        notificationButton.focus();
    }
});

let activeRequirementsModal = null;
let requirementsModalTrigger = null;
function closeRequirementsModal() {
    if (activeRequirementsModal === null) return;
    activeRequirementsModal.hidden = true;
    activeRequirementsModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('requirements-modal-open');
    requirementsModalTrigger?.focus();
    activeRequirementsModal = null;
    requirementsModalTrigger = null;
}
document.querySelectorAll('[data-requirements-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
        const modal = document.getElementById(button.dataset.requirementsModal);
        if (modal === null) return;
        activeRequirementsModal = modal;
        requirementsModalTrigger = button;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('requirements-modal-open');
        modal.querySelector('.requirements-modal__dialog').focus();
    });
});
document.querySelectorAll('[data-close-requirements-modal]').forEach(function (button) {
    button.addEventListener('click', closeRequirementsModal);
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && activeRequirementsModal !== null) {
        event.preventDefault();
        closeRequirementsModal();
    }

    if (event.key === 'Tab' && activeRequirementsModal !== null) {
        const focusable = Array.from(activeRequirementsModal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'))
            .filter((element) => element.offsetParent !== null);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const dialog = activeRequirementsModal.querySelector('.requirements-modal__dialog');
        if (document.activeElement === dialog) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
        } else if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});

document.querySelectorAll('[data-file-input]').forEach(function (input) {
    const form = input.closest('form');
    const status = form?.querySelector('[data-file-status]');
    input.addEventListener('change', function () {
        const maximumFiles = Number(input.dataset.maximumFiles || 0);
        if (maximumFiles > 0 && input.files.length > maximumFiles) {
            input.value = '';
            if (status !== null) {
                status.textContent = `Choose no more than ${maximumFiles} files.`;
                status.classList.remove('has-file');
            }
            input.focus();
            return;
        }

        if (status === null) return;
        if (input.files.length === 0) {
            status.textContent = 'No file selected';
            status.classList.remove('has-file');
            return;
        }

        const names = Array.from(input.files, (file) => file.name);
        status.textContent = input.files.length === 1 ? `Selected: ${names[0]}` : `${input.files.length} files selected: ${names.join(', ')}`;
        status.classList.add('has-file');
    });
});
