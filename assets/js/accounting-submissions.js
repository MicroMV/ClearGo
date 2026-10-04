const rejectAccountingModal = document.getElementById('rejectAccountingModal');
const rejectAccountingSubmissionId = document.getElementById('rejectAccountingSubmissionId');
const rejectAccountingLabel = document.getElementById('rejectAccountingLabel');

function closeRejectAccountingModal() {
    if (rejectAccountingModal === null) return;
    rejectAccountingModal.classList.remove('open');
    rejectAccountingModal.setAttribute('aria-hidden', 'true');
}

document.querySelectorAll('[data-reject-submission]').forEach(function (button) {
    button.addEventListener('click', function () {
        if (rejectAccountingModal === null || rejectAccountingSubmissionId === null) return;
        rejectAccountingSubmissionId.value = button.dataset.rejectSubmission || '';
        if (rejectAccountingLabel !== null) rejectAccountingLabel.textContent = button.dataset.rejectLabel || '';
        const reason = rejectAccountingModal.querySelector('textarea');
        if (reason !== null) reason.value = '';
        rejectAccountingModal.classList.add('open');
        rejectAccountingModal.setAttribute('aria-hidden', 'false');
        reason?.focus();
    });
});

document.querySelectorAll('[data-close-reject-modal]').forEach(function (button) {
    button.addEventListener('click', closeRejectAccountingModal);
});

rejectAccountingModal?.addEventListener('click', function (event) {
    if (event.target === rejectAccountingModal) closeRejectAccountingModal();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && rejectAccountingModal?.classList.contains('open')) closeRejectAccountingModal();
});
