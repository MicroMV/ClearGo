# PWA readiness - 20 October 2026

**Implementation update:** The manifest, icons and installation controls are now implemented. Edge reports no installation eligibility errors in the local browser test. See [implementation verification](../pwa-implementation/VERIFICATION.md). The original readiness snapshot below remains historical; actual phone installation and the deployment HTTPS host still need verification.

**Result: not ready for the manifest-based PWA installation experience.** The existing service worker and offline fallback work, but the application has no linked web app manifest. Headless Edge reported `no-manifest` through `Page.getInstallabilityErrors`.

No application code or school records were changed during this audit.

## Findings

| Area | Status | Evidence or next step |
|---|---|---|
| Web app manifest | Missing; installation blocker | No manifest found in source or linked in the live login HTML. Add app identity, name, start URL, scope, standalone display, colors and icon entries. |
| Install icons | Missing PWA declarations | Existing school logos are available, but no manifest declares 192px/512px app icons. Add suitable icons and preferably a maskable variant. An Apple touch icon is an additional platform improvement. |
| Service worker | Passed local browser checks | `connection-worker.js` activates with scope covering `/ClearGo/`. |
| Offline navigation | Passed | Public unavailable screen and college logo remain available after disconnecting the audit proxy. Retry recovers when it returns. |
| Service worker cache privacy | Passed sampled browser check and source review | Cache Storage contained only the public offline HTML and logo. Authenticated records, PDFs and uploaded proofs are not cached by this worker. This is not a full authentication/cache security audit. |
| Offline form handling | Passed sampled browser check and source review | Offline POST fails; the worker does not intercept POST requests or queue background writes. Existing connection handling blocks submissions during detected failure. |
| HTTPS deployment | Not verified | Localhost/loopback development is trusted. A real phone accessing a plain HTTP LAN address does not get the same exception. Validate on the intended HTTPS host. |
| Installed device behavior | Not verified | No manifest means standalone launching has not been tested. After setup, verify Android/iPhone installation, login/logout, camera, proof uploads and PDF preview/download. |

The scanner's compatibility decoder currently loads from unpkg. Its failure guidance works, but installation does not remove that network dependency. Attendance check-in, photo uploads, verification and clearance actions should continue to require a server connection; PWA installation need not make those operations available offline.

## Browser evidence

Eight targeted checks: seven passed and one found the expected missing-manifest gap. See `browser-results.json`.

The final browser run used a fresh Edge profile and a temporary read-only loopback proxy to the local Apache application. The proxy forwarded GET requests only, then was stopped to disconnect the worker as well as the page. This avoided the incomplete simulation produced when offline emulation affected only the page target. No real login credentials or student records were used.

The test checked registration, scope, manifest discovery, Cache Storage contents, rejection of an offline POST, fallback navigation, offline logo retrieval and reconnect recovery. It did not install an app or test a production host. Some browsers can install arbitrary websites as shortcuts/apps without a manifest; that is distinct from passing manifest-based installation criteria.

## Next implementation

1. Add and link one manifest across the app's normal entry pages, with an appropriate base path and standalone launch behavior. The existing login entry already redirects authenticated users to their role dashboard.
2. Add and declare 192px/512px app icons, a maskable variant and an Apple touch icon.
3. Verify the manifest, service worker scope, session cookies and camera permissions on the deployment HTTPS host.
4. Test actual installation and the student/staff flows on Android and iPhone. An in-app install button is optional and browser-dependent.

Reference: [MDN: Making PWAs installable](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable). The current guidance describes manifest members, secure hosting, and the distinction between PWA promotion and installing arbitrary websites.
