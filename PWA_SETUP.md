# ClearGo PWA

ClearGo can open as a standalone app after installation. The manifest is linked across the normal login, student and staff pages. It launches `login.php`, which routes an existing session to its role dashboard.

## Try locally

Open `http://localhost/ClearGo/login.php` in Chrome or Edge. Use **Install ClearGo** when the browser offers it, or the browser's app installation menu. The control also appears in student **Account** and staff **My Profile** when installation is available. It is hidden inside the installed app.

On iPhone/iPad, open the Share menu and choose **Add to Home Screen**. The page shows this guidance rather than a Chromium install prompt.

## Deploy

- Serve the application over HTTPS with a valid certificate. HTTP localhost/loopback works for development; a phone visiting the computer's plain HTTP LAN address does not receive that exemption.
- Include `manifest.webmanifest`, `assets/icons/`, `assets/css/pwa.css`, `assets/js/pwa.js`, `config/pwa.php`, `connection-worker.js` and `errors/offline.html` with the normal application files. Preserve the root `.htaccess` rules. Apache uses them to serve `.webmanifest` as `application/manifest+json` and revalidate the worker script. Configure equivalent behavior on another server.
- Manifest launch, scope and icon URLs are relative, so root and subdirectory deployments work. App identity defaults to the resolved `login.php` launch URL. Keep that launch URL stable after users install the app.
- Ensure PHP sees HTTPS when TLS is terminated by a proxy, so its existing session configuration sets the Secure cookie attribute. Do this in the trusted server/proxy configuration.
- Follow the existing deployment exclusions for `.git/`, `.tmp/`, `tests/` and `debug/`. Runtime storage remains protected by the application's access rules.

## Connection behavior

Attendance check-in, proof uploads, verification and office approval require the server. They are not queued or replayed offline. The service worker caches the public unavailable page and college logo; private records, signatures, uploaded evidence and PDFs are not stored in its cache. The unavailable screen retries and returns to the current route when the connection recovers.

The compatibility QR decoder is served locally from `assets/vendor/html5-qrcode/`. Include that folder in deployments; scanning still requires a live server for check-in.

## Verify before release

Run `node --test tests/pwa_install_test.mjs` and `node tests/pwa_browser_test.mjs` as described in `tests/README.md`. The browser suite checks installation eligibility and the page controls; it does not install an operating-system app.

On the actual HTTPS host, install with an Android phone and an iPhone. Check standalone launch, student and staff login/logout, expired sessions, QR camera permission/retry, photo capture/upload, clearance preview and PDF download. Also disconnect/reconnect the phone and verify the unavailable screen. In browser developer tools, confirm the manifest, icon requests and service worker scope cover the deployed application.

Reference: [MDN installation guidance](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable).
