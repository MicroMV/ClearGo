(() => {
    'use strict';
    // Embedded scanners and document previews use their parent's installation controls.
    if (window.top !== window.self || !window.isSecureContext) return;
    const controls = [...document.querySelectorAll('[data-pwa-install]')];
    if (!controls.length) return;
    const standalone = window.matchMedia('(display-mode: standalone)');
    let installed = standalone.matches || window.navigator.standalone === true;
    let installPrompt = null;
    let prompting = false;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function render() {
        controls.forEach(control => {
            const feedback = control.querySelector('[data-pwa-install-status]');
            control.hidden = installed || (!installPrompt && !isIOS && !feedback.textContent);
            const button = control.querySelector('[data-pwa-install-button]');
            button.hidden = !installPrompt;
            button.disabled = prompting;
            control.querySelector('[data-pwa-install-help]').textContent = isIOS && !installPrompt
                ? 'To install, open the Share menu and choose Add to Home Screen. An internet connection is required for transactions.'
                : 'Open ClearGo from your home screen. An internet connection is required for transactions.';
        });
    }
    window.addEventListener('beforeinstallprompt', event => {
        if (installed) return;
        event.preventDefault();
        installPrompt = event;
        controls.forEach(control => { control.querySelector('[data-pwa-install-status]').textContent = ''; });
        render();
    });
    controls.forEach(control => {
        control.querySelector('[data-pwa-install-button]').addEventListener('click', async () => {
            if (!installPrompt || installed || prompting) return;
            prompting = true;
            const prompt = installPrompt;
            render();
            try {
                await prompt.prompt();
                const choice = await prompt.userChoice;
                if (choice.outcome === 'accepted') installed = true;
                installPrompt = null; // A browser install event can be used only once.
            } catch (_) {
                installPrompt = null;
                // A used prompt cannot be reused; keep browser-menu guidance visible.
                control.querySelector('[data-pwa-install-status]').textContent = 'Could not open installation. Try again or use your browser menu.';
            } finally {
                prompting = false;
                render();
            }
        });
    });
    window.addEventListener('appinstalled', () => { installed = true; installPrompt = null; render(); });
    standalone.addEventListener('change', event => {
        installed = event.matches || window.navigator.standalone === true;
        render();
    });
    render();
})();
