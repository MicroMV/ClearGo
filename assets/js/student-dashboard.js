const notificationButton = document.getElementById('notificationButton');
const notificationBox = document.getElementById('notificationBox');
notificationButton.addEventListener('click', function (event) {
    event.stopPropagation();
    const willOpen = notificationBox.hidden;
    notificationBox.hidden = !willOpen;
    notificationButton.setAttribute('aria-expanded', String(willOpen));
});
notificationBox.addEventListener('click', function (event) { event.stopPropagation(); });
document.addEventListener('click', function () {
    notificationBox.hidden = true;
    notificationButton.setAttribute('aria-expanded', 'false');
});
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
});
