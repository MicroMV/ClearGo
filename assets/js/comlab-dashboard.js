const comlabModal = document.getElementById('comlab-violation-modal');
let comlabTrigger = null;
function openComlabViolationModal(clearanceId) {
    const template = document.getElementById('comlab-violations-' + clearanceId);
    if (!template || !comlabModal) return;
    comlabTrigger = document.activeElement;
    document.getElementById('comlab-violation-content').replaceChildren(template.content.cloneNode(true));
    comlabModal.classList.add('open');
    comlabModal.setAttribute('aria-hidden', 'false');
    comlabModal.querySelector('.modal').focus();
}
function closeComlabViolationModal() {
    comlabModal.classList.remove('open');
    comlabModal.setAttribute('aria-hidden', 'true');
    comlabTrigger?.focus();
}
comlabModal?.addEventListener('click', event => {
    if (event.target === comlabModal) closeComlabViolationModal();
});
document.addEventListener('keydown', event => {
    if (!comlabModal?.classList.contains('open')) return;
    if (event.key === 'Escape') closeComlabViolationModal();
    if (event.key !== 'Tab') return;
    const controls = comlabModal.querySelectorAll('button:not(:disabled), textarea:not(:disabled), a[href]');
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === comlabModal.querySelector('.modal'))) {
        event.preventDefault(); last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first?.focus();
    }
});
