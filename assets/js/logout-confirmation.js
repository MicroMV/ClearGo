(() => {
    'use strict';
    let dialog = null;
    let pendingForm = null;
    let pendingSubmitter = null;
    let confirmedForm = null;
    let previousFocus = null;

    function makeDialog() {
        if (dialog !== null) return;
        dialog = document.createElement('dialog');
        dialog.className = 'cleargo-logout-dialog';
        dialog.setAttribute('aria-labelledby', 'cleargo-logout-title');
        dialog.setAttribute('aria-describedby', 'cleargo-logout-description');
        dialog.innerHTML = '<span class="cleargo-logout-icon" aria-hidden="true">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5M14 8l4 4-4 4M8 12h10"/></svg></span>'
            + '<h2 id="cleargo-logout-title">Log out of ClearGo?</h2>'
            + '<p id="cleargo-logout-description">Your account will be signed out on this device.</p>'
            + '<div class="cleargo-logout-actions"><button type="button" data-logout-cancel>Cancel</button>'
            + '<button type="button" data-logout-confirm>Log out</button></div>';
        const cancel = dialog.querySelector('[data-logout-cancel]');
        const confirm = dialog.querySelector('[data-logout-confirm]');
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            if (dialog.open) return;
            document.body.classList.remove('cleargo-logout-open');
            pendingForm = null;
            pendingSubmitter = null;
            if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
            previousFocus = null;
        });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                dialog.close();
                return;
            }
            if (event.key !== 'Tab') return;
            if (event.shiftKey && document.activeElement === cancel) {
                event.preventDefault(); confirm.focus();
            } else if (!event.shiftKey && document.activeElement === confirm) {
                event.preventDefault(); cancel.focus();
            }
        });
        confirm.addEventListener('click', () => {
            if (!pendingForm?.isConnected || confirm.disabled) return;
            const form = pendingForm;
            const submitter = pendingSubmitter;
            pendingForm = null;
            cancel.disabled = true;
            confirm.disabled = true;
            confirmedForm = form;
            dialog.close();
            try {
                if (submitter?.isConnected && submitter.form === form) form.requestSubmit(submitter);
                else form.requestSubmit();
            } finally { confirmedForm = null; }
        });
        document.body.appendChild(dialog);
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-logout]') || event.defaultPrevented) return;
        if (form === confirmedForm) return;
        if (typeof HTMLDialogElement === 'undefined') {
            if (!window.confirm('Log out of ClearGo?')) event.preventDefault();
            return;
        }
        event.preventDefault();
        if (dialog?.open) return;
        makeDialog();
        pendingForm = form;
        pendingSubmitter = event.submitter;
        previousFocus = event.submitter || document.activeElement;
        dialog.querySelectorAll('button').forEach(button => { button.disabled = false; });
        dialog.showModal();
        document.body.classList.add('cleargo-logout-open');
        dialog.querySelector('[data-logout-cancel]').focus();
    });
})();
