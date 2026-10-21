# Attendance workflow recheck - 20 October 2026

No new failures were found in the tested attendance and sanction flows. No application changes were made during this recheck.

| Suite | Result |
|---|---|
| Attendance and sanction integration (`tests/attendance_sanctions_test.php`) | 97 passed |
| Student/staff layouts and interactions at 1280, 390 and 320px (`tests/attendance_sanctions_browser_test.mjs`) | 62 passed |
| Scanner against real PHP routes with a simulated camera | 19 passed |
| Scanner lifecycle (`tests/attendance_scanner_lifecycle_test.mjs`) | 9 passed |
| Scanner database assertions and disposable schema cleanup | 6 passed |

## Confirmed behavior

- A valid QR records Present immediately; duplicate scans do not create another attendance record.
- Expired check-in and out-of-organization scans are rejected. Council events accept enrolled students from different colleges.
- Expired events assign one sanction per absence, including the configured work, hours and monitor. Attendees are not sanctioned.
- One Before/After photo pair covers the outstanding Organization sanctions captured in that submission. Council requires its own pair. A later absence remains outstanding.
- Missing, extra, identical, fake and oversized photos, unauthorized access and invalid CSRF are rejected. Pending proof cannot be duplicated or replaced silently.
- Upload alone does not satisfy sanctions. Office approval requires confirmation of work and hours with the assigned monitor. Returned proof can be resubmitted, with the superseded files removed.
- Verified sanctions enable manual or bulk clearance without approving the office automatically. Unresolved sanctions block clearance.
- Ready locks policy editing and deletion; after the Registrar opens clearance, offices cannot undo Ready.
- Camera retry leaves exactly one active camera track. Permission, unavailable camera, insecure context and missing decoder errors provide feedback.
- The actual compatibility QR decoder read generated camera video and recorded Present for both Organization and Council through the normal application routes. Native detector outputs were simulated.

## Safety and limits

Only synthetic accounts, students and events in disposable databases were used for attendance writes. Cleanup checks confirmed the test schemas were removed. Browser layout checks used snapshots generated from the synthetic HTTP requests with the current application assets.

This does not test a physical phone camera, printed QR codes, lighting/focus conditions or a deployed HTTPS host. Those remain the next device checks.

The JSON evidence beside this report contains the scanner checks, attendance rows, database assertions and layout/interaction results. The two database keys beginning `diagnostic_real_` are legacy test labels; the successful browser run used the normal application scanner.
