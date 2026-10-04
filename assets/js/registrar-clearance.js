const registrarComplianceModal = document.getElementById('registrar-compliance-modal');
const registrarComplianceId = document.getElementById('registrar-compliance-clearance-id');
const registrarComplianceStudent = document.getElementById('registrar-compliance-student');
const registrarComplianceReason = document.getElementById('registrar-compliance-reason');
let registrarComplianceTrigger = null;

function closeRegistrarComplianceModal() {
    if (!registrarComplianceModal) return;
    registrarComplianceModal.classList.remove('open');
    registrarComplianceModal.setAttribute('aria-hidden', 'true');
    registrarComplianceTrigger?.focus();
}

document.querySelectorAll('[data-registrar-compliance]').forEach(function (button) {
    button.addEventListener('click', function () {
        if (!registrarComplianceModal || !registrarComplianceId || !registrarComplianceReason) return;
        registrarComplianceTrigger = button;
        registrarComplianceId.value = button.dataset.clearanceId || '';
        registrarComplianceReason.value = '';
        if (registrarComplianceStudent) {
            registrarComplianceStudent.textContent = button.dataset.studentName ? `for ${button.dataset.studentName}` : '';
        }
        registrarComplianceModal.classList.add('open');
        registrarComplianceModal.setAttribute('aria-hidden', 'false');
        registrarComplianceReason.focus();
    });
});

document.querySelectorAll('[data-close-registrar-compliance]').forEach(function (button) {
    button.addEventListener('click', closeRegistrarComplianceModal);
});

registrarComplianceModal?.addEventListener('click', function (event) {
    if (event.target === registrarComplianceModal) closeRegistrarComplianceModal();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && registrarComplianceModal?.classList.contains('open')) {
        closeRegistrarComplianceModal();
    }
});
