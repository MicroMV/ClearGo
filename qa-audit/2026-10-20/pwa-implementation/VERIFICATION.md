# PWA implementation verification - 20 October 2026

The local application now passes Edge's manifest installation eligibility checks. Actual operating-system installation on a phone and deployment-host HTTPS verification remain outstanding.

## Implemented

- A public `manifest.webmanifest` defines the ClearGo name, standalone display, school colors, app scope and login launch URL. Relative launch/scope/icon paths support root or subdirectory deployment. App identity defaults to the resolved login URL, avoiding an origin-root identity shared by unrelated apps.
- The existing college logo supplies 192px and 512px PNG icons, a 512px maskable icon with padding, and a 180px Apple touch icon.
- Shared student/staff page assets link the manifest and icons. Page templates declare UTF-8 before the shared head output so the expanded metadata cannot push the charset declaration past the initial 1024 bytes.
- Install controls appear on login, student Account and staff My Profile when Chromium offers installation. iPhone/iPad receive Share / Add to Home Screen guidance. Installed/standalone and embedded pages hide the controls. Accepted/dismissed/error prompt states are handled without reusing a consumed install event.
- Apache serves the manifest as `application/manifest+json` and revalidates the existing service worker script. Existing access denials remain in place.
- Attendance, proof submission, verification and clearance continue to require the server. The existing worker caches only public fallback HTML and the college logo, and does not queue offline POST requests.

## Results

| Check | Result |
|---|---|
| `node --test tests/pwa_install_test.mjs` | 9 passed |
| `node tests/pwa_browser_test.mjs` | 19 passed |
| Attendance/sanction integration in a disposable schema | 97 passed |
| Attendance/sanction layouts and interactions | 62 passed |
| QR scanner with real PHP routes and generated camera video | 19 passed in the completed run |
| QR database assertions and schema cleanup | 6 passed |
| PHP syntax for the 29 shared head/helper/view files | Passed |

The PWA browser suite queried `Page.getAppManifest` and `Page.getInstallabilityErrors`; the final manifest was parsed as standalone and the installability error list was empty. All declared icons loaded at their stated sizes. The installation control fit 1280px, 390px and 320px widths. Its prompt interactions were simulated browser events; the audit did not install an OS app.

Stopping a temporary read-only proxy disconnected both the page and service worker. Navigation displayed the public unavailable screen and cached logo, an offline POST failed, and Retry returned to login after the proxy restarted. Cache Storage contained only the two intended public resources.

## Scanner diagnostic limit

An additional regression run timed out decoding a frozen synthetic camera frame. The same source QR image decoded directly through the actual library; enlarging the frozen frame alone did not resolve camera decoding. Replaying the identical payload with regularly repainted frames at several sizes, modelling a phone moving toward the code, recorded Present through the normal application path for Organization and Council. The completed scanner run passed all 19 checks. No production QR scanner code was changed as part of this PWA implementation.

`scanner-frozen-frame.json` preserves the initial failure observations. `scanner-results.json`, `scanner-database-results.json` and `scanner-database-assertions.json` contain the final completed run. The `diagnostic_real_*` database assertion keys are legacy labels; the completed run used the normal application scanner without substituting a diagnostic scanner.

This demonstrates local browser behavior and sensitivity of that frozen-frame rendering, rather than guaranteeing recognition under physical phone focus, lighting or distance conditions. Real-device testing remains necessary.

## Safety and remaining device checks

Attendance writes used only synthetic users/events in disposable databases. Cleanup confirmed the temporary schemas were removed. Anonymous PWA browser checks used GET requests through a read-only proxy. Existing school records were not changed by testing.

Before school release, use the intended HTTPS host to install on Android and iPhone and verify standalone login/logout, session expiry, camera permission/retry, QR scanning, photo uploads, clearance preview and PDF download. HTTPS/proxy configuration and actual phone installation were not available to verify here.

See [setup and deployment instructions](../../../PWA_SETUP.md). The earlier [readiness audit](../pwa-readiness/REPORT.md) remains the historical pre-implementation snapshot.
