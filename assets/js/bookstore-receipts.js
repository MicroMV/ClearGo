const returnModal = document.getElementById('returnReceiptModal');
const returnSubmissionId = document.getElementById('returnSubmissionId');
const returnReceiptLabel = document.getElementById('returnReceiptLabel');

function closeReturnModal() {
    if (returnModal === null) return;
    returnModal.classList.remove('open');
    returnModal.setAttribute('aria-hidden', 'true');
}

document.querySelectorAll('[data-return-submission]').forEach(function (button) {
    button.addEventListener('click', function () {
        if (returnModal === null || returnSubmissionId === null) return;
        returnSubmissionId.value = button.dataset.returnSubmission || '';
        if (returnReceiptLabel !== null) returnReceiptLabel.textContent = button.dataset.returnLabel || '';
        returnModal.classList.add('open');
        returnModal.setAttribute('aria-hidden', 'false');
        returnModal.querySelector('textarea')?.focus();
    });
});

document.querySelectorAll('[data-close-return-modal]').forEach(function (button) {
    button.addEventListener('click', closeReturnModal);
});

returnModal?.addEventListener('click', function (event) {
    if (event.target === returnModal) closeReturnModal();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && returnModal?.classList.contains('open')) closeReturnModal();
});
