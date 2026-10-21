# ClearGo deployment readiness — 21 October 2026

**Verdict: local functional checks passed; full deployment on InfinityFree free hosting is not approved. Capacity for 5,000 simultaneous users is not validated.**

**Hosting update, 21 October 2026:** The user subsequently selected **Hostinger Web Hosting**. The InfinityFree findings below remain a record of the original assessment. Hostinger supports PHP/custom cron jobs, providing a scheduling route for the existing OTP worker. Its exact Web Hosting tier is unknown; no target-host test or deployment has been performed. Current status is a candidate for controlled staging after configuration, with production capacity still unapproved. Follow the Hostinger checklist in [DEPLOYMENT.md](../../DEPLOYMENT.md). [Hostinger cron documentation](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/), [plan resource limits](https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/).

The application was exercised through a copied installation, disposable databases, loopback Apache, browser automation and synthetic accounts/uploads. The school database was inspected for integrity; existing school records and approvals were not reset. No product behavior was changed during this audit. Deployment documentation was corrected and verified leftover test images were archived.

## Executed checks

| Area | Result | Practical coverage |
|---|---|---|
| PHP regression suites | 45 passed; 1 intentionally not run | Authentication, role/ownership restrictions, malformed CSRF, college workflows, Excel imports, individual/bulk approval, signature/readiness locks, attendance/sanctions, office submissions, history, notifications and retired schema removal. Debug suite skipped because debugging is disabled. |
| Browser regression | 7 suites, 417 checks passed | Recovery modal, attendance/sanctions, live clearance, PDF preview, logout confirmation, signature drawing and PWA behavior locally. |
| Student account/design | 132 checks passed | Five viewport widths, profile photo preview/cancel, account details, password modal, logout form and navigation. Rendered synthetic account; no actual profile update posted. |
| QR camera simulation | 19 checks passed | Native and compatibility decoders, expiry, duplicate scan, college scope, access control, permission/error/retry states; four attendance rows persisted in disposable data. Simulated camera, not physical phone testing. |
| JavaScript unit tests | 18 passed | PWA installation and scanner lifecycle. |
| Syntax | 233 files passed | Application, tests and dependency PHP/JS/MJS syntax; private credentials and runtime data excluded. |
| Actual localhost Apache | 26 route checks passed | Private directories/configuration/source exports denied, authenticated routes protected, public assets served; security headers present. Additional malformed CSRF POST returned a controlled 303 without stack trace. |
| Database/file restore | Passed locally | All 47 tables restored with matching row/BLOB fingerprints; one synthetic private image and its database reference restored with matching hash. Target-host restore remains untested. |
| Release ZIP | Passed | 267 entries, matching inventory hashes, no forbidden files or missing current features. |

The 5,000-row import test validates a batch operation, not 5,000 simultaneous visitors. Tests provide evidence for the exercised behavior, not a guarantee that every possible defect or attack has been excluded.

## Full student journeys

CCS, CBE and CTE each reached COMPLETED through the actual handlers, with 11 approved offices and 11 saved signature snapshots per student. CCS/CTE began with the Dean; CBE began with the Organization. Premature Registrar approval was rejected. The CCS Accounting rejection, resubmission and approval path passed.

The PDF was unavailable before clearance opened and available while partially and fully signed after opening. After archival, history remained available and PDF access stopped, matching the current open-clearance-only rule. Preview and download tests passed locally.

## Database integrity and cleanup

- 47 tables; 113 foreign key relationships checked with zero orphan rows.
- One open period; zero completed clearances with unapproved offices, zero snapshots on unapproved offices and zero active approvals missing signatures.
- Four referenced uploaded files exist; zero missing or unreferenced files after cleanup.
- No student section column or retired evaluation screenshot/receipt-AI tables remain. Retained empty feature tables support existing functionality and were not dropped merely for having no rows.
- Twelve unreferenced images exactly matched known synthetic profile-test fixtures. They were moved into the protected local audit archive after reference and hash checks; no referenced student file was altered.
- **77 approvals in closed semesters lack historical signature snapshots.** Review whether they are legitimate history or disposable test history before moving real records. Do not invent signatures or attach a current signatory retroactively. No active approval is affected.
- BUG-010's shared initial-password policy remains an explicitly accepted user decision, as recorded in the 18 October report. This audit did not change that policy; the accepted security risk remains.

## Hosting blockers and unverified release requirements

| Item | Finding and required action |
|---|---|
| Queued OTP delivery | ClearGo requires the CLI-only mail worker every minute. InfinityFree prohibits scheduled background requests and has disabled cron on free hosting. The current delivery design therefore cannot operate there as intended. Choose hosting with a supported scheduler/CLI or explicitly redesign and retest delivery. Do not publish the worker as a web endpoint. [Provider announcement](https://forum.infinityfree.com/t/cron-job-feature-is-now-disabled/80495) |
| PWA installation | InfinityFree's mandatory browser challenge can substitute HTML for manifest/service-worker requests. Its documentation acknowledges a workaround; ClearGo has no tested provider-specific adaptation. Local PWA success does not establish installation/offline support on that host. [Provider limitations](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353) |
| 5,000 active users | InfinityFree permits 50,000 daily hits, counting assets and background requests. During an open clearance, one visible student tab makes roughly three recurring requests every 20 seconds: health, notifications and a notification-triggered clearance refresh. At 5,000 active tabs this implies about 750 requests/second before user actions. This is source-based arithmetic, not a measured load test, and is incompatible with that daily allowance. [Provider hits policy](https://forum.infinityfree.com/t/what-does-the-hits-limit-mean/49330/) |
| Production configuration | Local preflight correctly fails for development mode, missing public HTTPS URL, development database credentials, old PHP 8.2.12/MariaDB 10.4.32 and missing/stale scheduled mail-worker heartbeat. Configure the target environment and verify a supported patched runtime, required extensions, actual worker health, storage permissions and error logging. Never upload local secrets or an unrestricted workspace copy. |
| Actual target acceptance | No InfinityFree account was deployed to or tested. Verify real TLS, redirects, private-path denial, database migration/restore, upload/signature access and recovery in the target environment. Test actual Android/iPhone installation, camera permissions, reconnect and PDF behavior. |
| Capacity and recovery | Sustained mixed traffic, latency/error targets, CPU/RAM, PHP/database concurrency, backups and restore on the actual production host remain unmeasured. No destructive 5,000-user load was sent to shared hosting or this workstation. |

Polling references: `assets/js/connection-status.js:102`, `assets/js/student-dashboard.js:104` and `:140`. Worker entry guard: `tools/auth_mail_worker.php:3`.

A small browser demonstration with synthetic data may be possible after target setup and smoke testing. Full OTP delivery, PWA operation and real-student production use are not approved on the proposed free host as-is. No new reproducible application workflow defect was found in the executed checks; the unresolved items above are hosting compatibility, release configuration, historical data review and unverified operating conditions.

## Release artifact and evidence

Verified package: `.tmp/releases/ClearGo-20261021-093207-e3199563.zip` (5,223,333 bytes).

SHA-256: `1404dce9d233e90571385be17d7b2e3e6ebfba0cf54dab63d07b8391e31984d2`.

The package excludes local credentials, real uploads, sessions, backups, dumps, tests, debugging and audit artifacts. Data and private configuration must be transferred separately. It is a verified build, not production approval.

Safe aggregate evidence is recorded in [results.json](results.json). Detailed private evidence remains in `.tmp/deploy-audit-2026-10-21/`; student account screenshots/results are in `.tmp/student-design-check/`. Do not upload either scratch directory.
