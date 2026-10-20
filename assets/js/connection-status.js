(() => {
    'use strict';
    const script = document.querySelector('script[data-connection-status]');
    if (!script) return;
    const reloadOnRecovery = script.dataset.reloadOnRecovery === 'true';
    let screen = document.getElementById('connection-screen');
    let previousFocus = null;
    let checking = false;
    let failed = reloadOnRecovery;
    let failureKind = screen?.dataset.connectionKind || 'service';

    function showFailure(kind) {
        failed = true;
        failureKind = kind;
        if (!screen) {
            previousFocus = document.activeElement;
            screen = document.createElement('dialog');
            screen.id = 'connection-screen';
            screen.className = 'connection-screen';
            screen.setAttribute('aria-labelledby', 'connection-title');
            screen.innerHTML = '<section class="connection-card"><div class="connection-visual">'
                + '<div class="connection-brand"><img class="connection-brand-logo" alt="The Lewis College logo">ClearGo</div>'
                + '<div class="connection-art" aria-hidden="true"></div><div class="connection-school"><strong>The Lewis College</strong><span>Student Clearance Portal</span></div></div>'
                + '<div class="connection-content"><span class="connection-status-label">Please try again</span><h1 id="connection-title"></h1>'
                + '<p id="connection-message"></p><div class="connection-help"><span class="connection-help-icon" aria-hidden="true">i</span><p>If you were saving a change, check the record when ClearGo is available before trying again.</p></div>'
                + '<button type="button" id="connection-retry" class="connection-retry"><span class="connection-retry-icon" aria-hidden="true">&#8635;</span>Try again</button>'
                + '<p id="connection-feedback" class="connection-feedback" role="status" aria-live="polite"></p>'
                + '<div class="connection-footer"><span aria-hidden="true"></span>We\'ll try again automatically when ClearGo is available.</div></div></section>';
            screen.querySelector('.connection-brand-logo').src = script.dataset.logoUrl;
            screen.addEventListener('cancel', event => event.preventDefault());
            document.body.appendChild(screen);
            bindRetry();
            screen.showModal();
        }
        screen.dataset.connectionKind = kind;
        screen.querySelector('.connection-status-label').textContent = 'Please try again';
        screen.querySelector('#connection-title').textContent = 'ClearGo is temporarily unavailable';
        screen.querySelector('#connection-message').textContent = 'Please wait a moment, then try again.';
    }

    function recovered() {
        if (!failed) return;
        if (reloadOnRecovery) {
            // A fresh GET avoids replaying a form submission after an uncertain outcome.
            window.location.replace(window.location.href);
            return;
        }
        screen?.close();
        screen?.remove();
        screen = null;
        failed = false;
        if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
    }

    async function checkConnection(manual = false) {
        if (checking) return;
        checking = true;
        const retry = screen?.querySelector('#connection-retry');
        if (manual && retry) {
            retry.setAttribute('aria-disabled', 'true');
            screen.querySelector('#connection-feedback').textContent = 'Trying again...';
        }
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(script.dataset.statusUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal
            });
            if (!response.ok) {
                showFailure(response.headers.get('X-ClearGo-Service-Unavailable') === 'database' ? 'database' : 'service');
                return;
            }
            const status = await response.json();
            if (status.available === true) recovered();
            else showFailure('service');
        } catch (error) {
            showFailure('service');
        } finally {
            window.clearTimeout(timeout);
            checking = false;
            screen?.querySelector('#connection-retry').removeAttribute('aria-disabled');
            if (manual && screen) screen.querySelector('#connection-feedback').textContent = 'ClearGo is still unavailable. Please try again shortly.';
        }
    }

    function bindRetry() {
        screen.querySelector('#connection-retry').addEventListener('click', event => {
            event.preventDefault();
            checkConnection(true);
        });
    }
    if (screen) bindRetry();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkConnection(); });
    // Keep entered values in place and never queue or automatically replay staff actions.
    document.addEventListener('submit', event => {
        if (failed) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showFailure(failureKind);
        }
    }, true);
    window.setInterval(() => { if (!document.hidden) checkConnection(); }, 20000);
    checkConnection();

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(script.dataset.workerUrl).catch(() => { /* Live connection handling remains available. */ });
    }
})();
