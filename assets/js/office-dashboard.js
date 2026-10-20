let facilitiesModalTrigger = null;
let facilitiesPreviousOverflow = '';
function openFacilitiesItemModal(clearanceId) {
    const template = document.getElementById('facilities-items-' + clearanceId);
    if (!template || !facilitiesItemModal) return;
    facilitiesModalTrigger = document.activeElement;
    const content = document.getElementById('facilities-item-content');
    content.replaceChildren(template.content.cloneNode(true));
    content.scrollTop = 0;
    const addForm = facilitiesItemModal.querySelector('[data-facilities-add-item]');
    const clearButton = facilitiesItemModal.querySelector('[data-facilities-clear]');
    if (addForm && clearButton) {
        const syncClearButtons = function (saving = false) {
            const hasDraft = Array.from(addForm.querySelectorAll('input:not([type="hidden"]), textarea')).some(input =>
                input.type === 'datetime-local' ? input.value !== input.defaultValue : input.value.trim() !== '');
            const blocked = saving || hasDraft || clearButton.dataset.clearEligible !== '1';
            clearButton.disabled = blocked;
            const warning = facilitiesItemModal.querySelector('[data-facilities-draft-warning]');
            if (warning) warning.hidden = !hasDraft;
            document.querySelectorAll('.facilities-row-actions form').forEach(function (form) {
                if (form.elements.clearance_id?.value === String(clearanceId)) {
                    const button = form.querySelector('button[value="approve"]');
                    if (button) button.disabled = blocked;
                }
            });
        };
        addForm.addEventListener('input', function () { syncClearButtons(); });
        addForm.addEventListener('submit', function () { syncClearButtons(true); });
        syncClearButtons();
    }
    if (!facilitiesItemModal.classList.contains('open')) facilitiesPreviousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    facilitiesItemModal.classList.add('open');
    facilitiesItemModal.setAttribute('aria-hidden', 'false');
    facilitiesItemModal.querySelector('.modal').focus({ preventScroll: true });
}

function closeFacilitiesItemModal() {
    document.getElementById('facilities-item-modal').classList.remove('open');
    facilitiesItemModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = facilitiesPreviousOverflow;
    facilitiesModalTrigger?.focus({ preventScroll: true });
}

const facilitiesItemModal = document.getElementById('facilities-item-modal');
facilitiesItemModal?.addEventListener('click', function (event) {
    if (event.target === facilitiesItemModal) {
        closeFacilitiesItemModal();
    }
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && facilitiesItemModal?.classList.contains('open')) closeFacilitiesItemModal();
    if (event.key !== 'Tab' || !facilitiesItemModal?.classList.contains('open')) return;
    const controls = facilitiesItemModal.querySelectorAll('button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href]');
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === facilitiesItemModal.querySelector('.modal'))) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
});
