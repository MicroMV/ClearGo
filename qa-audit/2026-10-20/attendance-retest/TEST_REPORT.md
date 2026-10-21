# Attendance and scanner retest — 20 October 2026

**Fix update:** Both findings below are now resolved. The post-fix run passed all 19 scanner browser checks and all 9 scanner lifecycle tests. See [fix verification](../attendance-scanner-fix/VERIFICATION.md).

The sections below describe the earlier test-only snapshot, when two scanner defects were open and application code had not been modified.

## Results

| Test | Result |
|---|---|
| Attendance/sanction integration | 97 passed |
| Student and staff attendance/sanction layouts at 1280, 390 and 320px | 62 passed |
| Focused scanner and camera simulation | 18 of 21 checks passed; three failures represent the two findings below |

The existing integration suite verified immediate Present recording, duplicate and late-scan handling, organization restrictions, sanctions after expiry, separate Organization/Council photo pairs, returned-proof resubmission, monitor confirmation and subsequent clearance eligibility.

Additional browser checks used real login and scanner PHP routes against a disposable database. Camera permission denial, permission retry, no-camera errors, HTTPS guidance, missing decoder feedback, guest/staff restrictions, server rejection of expired/out-of-scope tokens and immediate native-path submission passed. The native detector results were simulated; the compatibility decoder was the actual `html5-qrcode@2.3.8` script referenced by the application, supplied from a local test cache.

## ATT-001 — High: compatibility camera startup rejects its configuration

- **Location:** `assets/js/attendance-scanner.js`, `startCompatibilityScanner()`.
- **Trigger:** The browser has no native `BarcodeDetector`, so the application calls `Html5Qrcode.start()` with `{ facingMode: { ideal: 'environment' } }`.
- **Observed:** The actual decoder library rejected the configuration with `'facingMode' should be string or object with exact as key.` This happened before requesting any camera stream. The page misleadingly displayed **Camera access is blocked** instead.
- **Scope:** Both the Organization and Student Council scanning paths failed this way in the focused browser test. This prevents check-in through the compatibility path; it is not an observed camera-permission denial.
- **Diagnostic confirmation:** Without changing application files, a separate test-only scanner instance used the supported `{ facingMode: 'environment' }` option. It decoded generated QR video frames through the actual library and the real server recorded Present for both an organization event and a Council event. The Council test used an enrolled student from another college.
- **Suggested fix:** Pass a camera configuration accepted by this library. Report startup/configuration errors separately from permission denial, and verify the compatibility path with actual QR decoding.
- **Status:** Fixed after this test-only run; see the fix verification above.

## ATT-002 — Medium usability: Retry leaves the previous camera running

- **Location:** `assets/js/attendance-scanner.js`, invalid-code handling and the Retry listener.
- **Trigger:** Decode a non-ClearGo QR through the native path, then press **Enable camera and retry**.
- **Observed:** The first camera stream remained live and Retry created another. The simulated camera reported **two live video tracks** after the retry. Replacing the video element does not stop the old stream or its scan loop.
- **Impact:** Unnecessary camera/decoder activity. Effects such as camera contention or battery drain on a physical phone were not measured.
- **Suggested fix:** Stop the active scanner/stream and cancel its pending scan loop before restarting; serialize retries so only one scanner runs.
- **Status:** Fixed after this test-only run; reproduced and retested with a simulated camera, not physical phone hardware.

## Evidence and safety

- `.tmp/attendance-retest-2026-10-20.log`: 97 integration results.
- `.tmp/attendance-sanctions-check/browser/results.json`: 62 layout/interaction results.
- `.tmp/attendance-device-audit-2026-10-20/browser-results.json`: the test runner's latest scanner results, now updated by the post-fix run. The original observations are recorded above.
- `database-results.json` and `database-assertions.json` in that directory: actual disposable attendance records, duplicate/expiry/scope checks and cleanup verification.
- `source-comparison.json`: SHA-256 comparison of 186 application PHP/JS/CSS/access-control files found zero changes.

Only generated users, students and events were used. Disposable schemas were removed. School accounts and existing student records were not used for attendance writes. The diagnostic camera option was applied only to a temporary browser instance; it is not an application fix.

A real phone's hardware camera, physical QR print, lighting/focus conditions and a deployed HTTPS host remain untested.
