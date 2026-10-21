(() => {
    const recovery = document.querySelector('#password-recovery-modal');
    const trigger = document.querySelector('[data-open-recovery]');
    if (recovery && trigger) {
        function openRecovery() {
            if (recovery.open) return;
            recovery.showModal();
            document.body.classList.add('recovery-open');
            recovery.querySelector('[data-recovery-focus]')?.focus();
        }
        trigger.addEventListener('click', openRecovery);
        recovery.querySelectorAll('[data-close-recovery]').forEach(button => {
            button.addEventListener('click', () => recovery.close());
        });
        recovery.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const controls = [...recovery.querySelectorAll('button:not(:disabled), input:not([type="hidden"]):not(:disabled), a[href]')]
                .filter(control => control.getClientRects().length > 0);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        });
        recovery.addEventListener('close', () => {
            document.body.classList.remove('recovery-open');
            const url = new URL(location.href);
            if (url.searchParams.has('recovery')) {
                url.searchParams.delete('recovery');
                history.replaceState(null, '', url.pathname + url.search + url.hash);
            }
            trigger.focus({ preventScroll: true });
        });
        if (recovery.dataset.autoOpen === 'true') openRecovery();
    }

    const toast = document.querySelector('.login-toast');
    if (!toast) return;

    let timer;
    function dismiss() {
        window.clearTimeout(timer);
        toast.classList.add('dismissed');
        window.setTimeout(() => toast.remove(), 500);
    }

    toast.querySelector('button').addEventListener('click', dismiss, { once: true });
    timer = window.setTimeout(dismiss, 6000);
})();
