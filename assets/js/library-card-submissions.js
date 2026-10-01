const returnModal = document.getElementById('returnLibraryCardModal');
const returnId = document.getElementById('returnLibraryCardId');
const returnLabel = document.getElementById('returnLibraryCardLabel');

function closeLibraryCardModal() {
    if (!returnModal) return;
    returnModal.classList.remove('open');
    returnModal.setAttribute('aria-hidden', 'true');
}

document.querySelectorAll('[data-return-submission]').forEach(function (button) {
    button.addEventListener('click', function () {
        if (!returnModal || !returnId) return;
        returnId.value = button.dataset.returnSubmission || '';
        if (returnLabel) returnLabel.textContent = button.dataset.returnLabel || '';
        returnModal.classList.add('open');
        returnModal.setAttribute('aria-hidden', 'false');
        returnModal.querySelector('textarea')?.focus();
    });
});

document.querySelectorAll('[data-close-return-modal]').forEach(function (button) {
    button.addEventListener('click', closeLibraryCardModal);
});

returnModal?.addEventListener('click', function (event) {
    if (event.target === returnModal) closeLibraryCardModal();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeLibraryCardModal();
});
