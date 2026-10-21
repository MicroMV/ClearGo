# Attendance scanner fixes - 20 October 2026

ATT-001 and ATT-002 are resolved in the local working tree.

## Changes

- The compatibility scanner passes `{ facingMode: 'environment' }`, which the application's html5-qrcode version accepts. Unsupported native QR detection falls back to this decoder.
- Compatibility video keeps its natural aspect ratio so the decoder's scan coordinates match the displayed camera view, including embedded mobile layouts.
- Startup/configuration failures no longer display a misleading camera-permission error. Permission, missing-camera, insecure-context and missing-decoder guidance remains available.
- Retry waits for the old decoder and camera tracks to stop before starting a new session. Native scan timers are cancelled; late callbacks cannot submit from an obsolete session. Repeated clicks during startup cannot create extra camera requests.
- Valid scans submit once after cleanup. Page exit releases camera resources, including streams returned by a late permission result; restored pages can restart.
- Retry is visually hidden when its `hidden` attribute is set.

Production changes are limited to `assets/js/attendance-scanner.js` and `assets/css/attendance-scanner.css` for this fix. Attendance and sanction business rules were not changed.

## Verification

| Check | Result |
|---|---|
| `node --test tests/attendance_scanner_lifecycle_test.mjs` | 9 passed |
| Real PHP routes with headless Edge and simulated camera video | 19 passed |
| Database assertions and disposable schema cleanup | 6 passed |

The browser checks used the actual html5-qrcode 2.3.8 decoder against generated QR video frames. Both Organization and Student Council check-ins recorded Present through the normal application path. Native detector outputs were simulated. Checks also covered permission denial/retry, invalid QR retry with exactly one live camera track, 320px/390px layouts, duplicate scans, expired tokens, organization restrictions and guest/staff access restrictions.

The existing attendance/sanction integration suite (97 checks) and layout/interaction suite (62 checks) passed during the preceding audit before these scanner-only changes; those counts are not presented as new post-fix runs.

Saved post-fix evidence alongside this report:

- `browser-results.json`: all scanner checks and observations.
- `database-results.json`: synthetic attendance rows.
- `database-assertions.json`: duplicate/expiry/scope checks and confirmation that disposable schemas were removed. The two `diagnostic_real_*` keys are legacy assertion names; in this successful run, decoding used the normal application scanner without a diagnostic substitute.

Only synthetic accounts, students and events in disposable databases were used for attendance writes. Existing school records were not changed by these tests.

Physical phone cameras, printed QR codes, lighting/focus conditions and a deployed HTTPS host remain untested.
