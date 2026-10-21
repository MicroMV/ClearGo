# Student recovery and deployment fixes — 20 October 2026

**Implemented and tested. Real email delivery is intentionally pending SMTP configuration. Public production release and 5,000-user capacity are not yet approved.**

Student password recovery is available through **Forgot password?** on the login page. Only active student accounts with active student profiles can receive or redeem an email code. Office accounts, inactive accounts and unknown addresses receive the same generic response and get no reset code. The Registrar's office-password controls remain in place.

Codes are random six-digit values, expire in 10 minutes, allow five incorrect attempts, are bound to the requesting browser and become unusable after verification or resend. Verification creates a separate, short-lived single-use reset grant. Password reset requires confirmation, invalidates prior sessions and saved devices, records an audit event, queues a password-change notice and returns to normal sign-in. Codes are hashed; outgoing email payloads are encrypted using a private APP_KEY.

Email is delivered by a scheduled CLI worker using the pinned official PHPMailer SMTP library. Queuing keeps the public response independent of SMTP latency/account existence. The worker claims jobs safely, cancels obsolete codes, retries failures, purges expired authentication data and records health per database. No real student or staff mail was sent during testing. Until SMTP and the worker are configured, the UI directs students to the Registrar rather than claiming a code was sent.

**Keep me signed in** is now an unchecked optional login checkbox for students and staff. Saved sign-in lasts at most 30 days, rotates random secrets, stores only verifier hashes and uses HttpOnly/SameSite cookies, with Secure enabled over HTTPS. Logout revokes that device's saved token. Password changes and resets revoke all saved tokens and old session versions. Normal student password change retains the current session only. Account/address changes made through the updated Registrar/import paths also invalidate affected old identities.

| Previous finding | Outcome |
|---|---|
| DEP-01: missing login throttling | Fixed. Account/browser/IP limits return 429 with Retry-After; concurrent requests cannot exceed the atomic allowance. Shared-campus IP ceilings are configurable. |
| DEP-02: public audit artifacts | Fixed locally. The entire audit tree, tests, CLI tools, dependency PHP, hidden credentials, development documents and backups are denied. Release packaging excludes sensitive development/runtime material. Directory indexing is disabled. |
| DEP-03: old deployment runtime | Host action remains. Local PHP 8.2.12/MariaDB 10.4.32 were not replaced. The deployment preflight flags the old stack; deploy supported, patched runtimes and retest there. |
| DEP-04: root/blank database defaults | Added staging/production fail-closed enforcement and private configuration support. Actual dedicated host credentials remain to be configured. |
| UI-01: preview rapid reopen | Fixed with synchronous close cleanup and protection against stale close events. The original 66-check preview browser suite passes without an added cleanup delay. |
| DATA-01: 77 historical approvals without snapshots | Preserved for authorized review. All are in CLOSED periods; zero active approvals lack signatures. A private review list was saved with the retention archive. No signatures were invented and no old approvals were reset. |
| DATA-02: 72 unreferenced profile images | Resolved by reversible archival. All 72 old unreferenced images were moved to protected retention storage with original filenames and SHA-256 manifests. Current referenced images are unchanged; the follow-up scan finds zero unreferenced uploads and zero missing referenced files. |
| SEC-01: framing headers | Fixed. SAMEORIGIN/frame-ancestors 'self', nosniff and same-origin referrer policy are present on the actual Apache application. Same-origin paper frames continue to work. |
| TEST-01: stale fixtures | Updated signature prerequisites, signed-in CSRF retrieval, isolated phase-2 setup, current workbook instructions, synthetic session handling and configurable isolated HTTP base URLs. All active PHP suites now pass. |
| External QR script availability | The existing pinned decoder and license are now served from local application assets. |

**Validation**

- **45 active PHP suites passed**; the disabled debug-console suite was deliberately not enabled. This includes 55 new authentication/SMTP/worker checks, 244 CCS completion/signature checks, 97 sanctions checks and the existing workflow/import/privacy checks.
- Authentication tests cover all office roles being ineligible for recovery, inactive/unknown accounts, CSRF, actual SMTP delivery to a loopback receiver, encrypted payload integrity, wrong/expired codes, browser binding, resend, confirmation, atomic single-use reset, saved-token rotation/expiry/tampering, logout and password-change revocation, and concurrent rate limiting.
- Browser checks passed: 42 authentication UI checks, 66 preview checks, 92 signature checks, 22 live-clearance checks, 62 sanctions UI checks and 19 PWA checks. The generated-camera scanner test passed all 19 checks on rerun, including the actual locally hosted compatibility decoder and Council/Organization scope.
- During parallel browser simulations one signature input run lost pointer capture; the isolated rerun passed all 92 checks. One generated Council camera run timed out; a fresh isolated run passed all 19 checks. The initial scanner result is retained. These simulations do not certify physical phone camera reliability; Android/iPhone testing remains required.
- Fresh real-handler journeys for CCS, CBE and CTE reached COMPLETED through all 11 offices with 11 signature snapshots each. Partial/complete paper availability and Accounting return/resubmission still work.
- **228 PHP/JS/MJS files** passed syntax checks. Final Apache probes returned 200 for login/recovery/local QR assets, 303 for the root login redirect and 403 for protected development/storage/credential paths.
- Database: **47 active tables, 113 foreign-key relationships, zero orphaned relationships**. Four current uploaded-file references match four existing files; no uploaded file is missing or unreferenced. The four new authentication tables are actively used by recovery, saved sign-in and worker cleanup, and contain no synthetic test rows in the school database.
- Migration 037 was applied after a private backup, preserved all 43 pre-existing table row counts and passed repeatability checks on a disposable schema. Existing passwords were not changed by migration.
- All disposable test schemas were removed. Only the original two Apache processes remained running.

**Release artifact and remaining configuration**

A release ZIP was built under `.tmp/releases/` with 255 files and a SHA-256 inventory. ZIP contents were checked against the inventory and for forbidden audit/test/debug/Git/scratch/credential/session entries. Database migrations and vetted CLI worker/preflight/maintenance tools are included in denied directories. Real school database dumps, uploads, sessions, backups and local secrets are excluded.

See [DEPLOYMENT.md](../../../DEPLOYMENT.md) for SMTP variables, APP_KEY generation, the every-minute mail worker, least-privilege database configuration, private storage, server access rules, retention and acceptance steps. Run `php tools/deployment_check.php` on the target host. The current local preflight correctly fails the still-unconfigured production environment/HTTPS URL/database credentials/SMTP/worker and outdated deployment-runtime requirements. Those failures are not being reported as fixed by the application changes.

Before public release: configure SMTP and its scheduler; verify a real student inbox and password-reset flow; deploy supported patched runtimes with private credentials/HTTPS; resolve the historical approval classification; rehearse database-plus-file restore; test real Android/iPhone behavior; and complete a sustained 5,000-session mixed-workload test on the intended host. The earlier short workstation performance probe is not a capacity certification.

Evidence: [PHP suites](regression-results.json), [authentication checks](auth-final.log), [actual Apache and syntax checks](final-results.json), [integrity](database-integrity.json), [migration](migration-results.json), [archive result](maintenance-results.json), [archive follow-up](maintenance-after.json), [journeys](simulation-results.json), [preflight](preflight-results.json), [release inventory verification](release-verification.json), [cleanup](cleanup-results.json).
