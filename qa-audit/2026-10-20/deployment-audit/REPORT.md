# ClearGo deployment audit — 20 October 2026

**Decision: suitable for a restricted staging trial after deployment configuration; not ready for an unrestricted production release or a claim of 5,000 simultaneous users.**

The three college clearance journeys worked through all 11 approving offices and produced completed, signed clearance documents. The remaining concerns are authentication protection, publicly accessible development artifacts, the deployment runtime/configuration, a reproducible preview race, historical data reconciliation, and capacity that has not been established on a production host.

This was an audit. Application behavior, existing school records, and private school files were left unchanged. Tests wrote to disposable databases and an isolated application copy. SHA-256 comparison found no changes in the 237 source/assets/configuration files captured at the start. The only permanent additions are this report and its access-denied evidence directory. Test fixture adjustments described below were made only in the isolated copy.

**Coverage and evidence**

| Area | Result | Evidence |
|---|---|---|
| CCS, CBE and CTE clearance | Each student reached COMPLETED through 11 offices, with 11 saved signature snapshots | [Journey observations](simulation-results.json) |
| Semester preparation/opening | Normal signature setup, office Ready, Registrar opening and ordering exercised; PDF unavailable before OPEN | Journey observations; readiness and CCS suites |
| Office ordering | CCS/CTE Dean first; CBE Organization first; premature Registrar approval rejected | Journey observations; CBE suites |
| Student paper | Available during OPEN, includes signatures for already approved offices, supports partial completion; closed-period PDF denied as currently required | Journey observations; PDF/preview suites |
| CCS Dean | Requirements Completion workbook, multiple year sheets, names/course, blank-as-incomplete, graduation verification, unrestricted clearing and signature recovery | 244 CCS checks |
| Other offices | Individual/bulk clearing, books/obligations, medical workflow, remarks, receipt/assessment return and resubmission, exports | PHP workflow suites; 11-office journey |
| Attendance/sanctions | Immediate QR presence, duplicate scan, expired QR, college scope, Council scope, grouped before/after evidence and staff review | 97 PHP sanction checks; 62 browser checks; 19 fresh scanner checks |
| Accounts/uploads | Import validation, duplicate/error handling, private image ownership/replacement and role restrictions | Import/staff-error/profile suites |
| Authorization | 336 combinations: 12 roles against 28 protected pages; allowed role pages loaded and other roles were denied | [Apache checks](apache-results.json) |
| Request protection | Malformed login/array tokens handled without warnings or stack traces; invalid CSRF denied | 47 request checks; Apache malformed-login probes |
| Database integrity | 110 foreign-key relationships checked; zero orphan rows | [Database audit](database-integrity.json) |
| Backup recovery | Synthetic backup restored all 43 tables with matching row and binary-image fingerprints | [Restore result](backup-restore-results.json) |
| Server outage | Isolated unavailable-database scenario returned controlled 503 responses for health and login | [Follow-up checks](followup-results.json) |
| PWA | Install eligibility/control logic, public-only offline cache, retry/reconnection, no offline POST replay | 19 browser checks; install lifecycle unit tests |
| Syntax | All 154 application PHP/JavaScript files checked successfully | [Final checks](final-checks-results.json) |

The browser checks included desktop and 390px/320px layouts, signature drawing, live status updates, sanctions UI, clearance preview, and QR permission/retry behavior. QR tests used the actual compatibility decoder and generated video frames, plus real HTTP check-in against a disposable database. They did not use physical phone cameras. Some UI suites serve synthetic snapshots; the separate journeys exercise real PHP handlers.

**Open findings**

| ID | Priority | Finding and consequence | Required response |
|---|---|---|---|
| DEP-01 | High | No application login throttling. Twenty-five wrong-password requests against one synthetic valid account all received the ordinary 303 response. Source review of `auth/login_process.php` found no attempt counter, cooldown or rate-limit gate. Automated guessing and expensive password verification remain possible. | Add and test bounded account/IP attempt limits and recovery behavior; verify any edge limit on the deployed host. |
| DEP-02 | Medium; release blocker if artifacts are shipped | `/qa-audit/` and `/qa-audit/2026-10-20/` returned 200 directory listings without authentication. `/PWA_SETUP.md` is also public. The existing October 18 deny rule does not cover the whole audit tree. Only listings/metadata were requested; this does not assert that every listed file contains secrets. | Exclude audit/test/scratch/debug material from the release package and deny the entire audit tree. Verify direct child URLs as well as directory listing. This new report directory has its own deny rule. |
| DEP-03 | High deployment requirement | Local runtime is Apache 2.4.58, PHP 8.2.12 and MariaDB 10.4.32. MariaDB 10.4 is out of maintenance. Old development binaries should not become the public server. | Deploy patched, supported server/database/PHP versions and rerun migrations and workflows there. |
| DEP-04 | High deployment requirement | Database configuration falls back to local `root` and an empty password. These development defaults are insufficient as production configuration. No production credentials or host were supplied for review. | Use a dedicated password-protected application database user with only runtime privileges. Run schema migrations with separate administrative credentials. Keep the database private. |
| UI-01 | Medium | Closing the clearance preview with Escape inside its zoom controls and immediately reopening can leave an open dialog with `about:blank` and no loading feedback. Reproduced twice in Edge. | Make close cleanup complete synchronously or ignore an obsolete queued close event after reopening; add a regression check for rapid reopen. |
| DATA-01 | Medium historical reconciliation | 77 APPROVED office records have no signature snapshot. All 77 belong to CLOSED periods; no such active-period group was found. New synthetic approvals captured signatures normally. | Classify legacy/test records before migration. Preserve legitimate history; obtain authorized reapproval if historical signatures are required. Do not fabricate signatures or silently attach today's signer to an old approval. |
| DATA-02 | Low | 72 private profile images have no reference in the current database. There are 76 stored files and four current references; no referenced file is missing. | Reconcile against backups and retention requirements, then archive/delete only confirmed obsolete files. No files were deleted during this audit. |
| SEC-01 | Hardening | The student dashboard returned neither `X-Frame-Options` nor a CSP `frame-ancestors` directive in the isolated Apache check. A cross-origin clickjacking attack was not demonstrated; SameSite cookies provide some mitigation. | Apply an appropriate shared framing policy, preserving same-origin paper preview frames. Verify headers on the final host. |
| TEST-01 | Maintenance | Seven original PHP suites did not initially run cleanly with the isolated environment/current signature rules. Corrected audit fixtures passed, but repository test files remain unchanged. | Update the maintained test fixtures and instructions so the release checks are reproducible without audit-only adjustments. |

DEP-01 is separate from the previously accepted initial-password policy (original BUG-010). That policy was not changed or reopened as a new business requirement. Its residual exposure makes throttling and account protection more consequential. Login responses alone would not prove a missing limiter; the finding combines those observations with source review. OWASP describes bounded login attempts and account-aware throttling as protection against automated guessing. [OWASP authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html#login-throttling)

MariaDB lists 18 June 2024 as the maintenance end for 10.4. PHP 8.2 itself remains in security support until 31 December 2026; this does not mean the old 8.2.12 patch is current. Use a maintained, patched deployment runtime and test compatibility. [MariaDB maintenance dates](https://mariadb.org/about/#maintenance-policy), [PHP supported versions](https://www.php.net/supported-versions.php)

UI-01 evidence is retained in [rapid-reopen failure](preview-rapid-reopen-failure.json). Waiting for complete dialog cleanup in the test copy allowed all 66 remaining preview checks to pass at desktop/mobile widths. That test wait does not fix the application race. The application close listener in `assets/js/student-dashboard.js` resets the iframe and loading state; a queued old close event can run after the next open.

DATA-01 breakdown: Organization 13, Clinic 11, Library 11, Bookstore 9, SASO 9, Facilities 8, Accounting 6, Comlab 6 and Dean 4. These are archived office approvals, not 77 students and not a failure reproduced in the new approval flow.

**Database cleanliness**

The schema contains 43 base tables. Each has literal references in application PHP outside the disabled debug tools. No retained table was proven unused. The removed student `section` column and the retired evaluation/receipt-verification tables checked by the audit are absent. All checked foreign keys have valid parents; there are no completed clearances with an unapproved required office, no signature snapshots attached to nonapproved offices, and no student login without a student profile.

Several tables are empty because their features have not been used in the current data: document requirements/submissions, accounting obligations, clearance exceptions, event sanctions, sanction submissions and signature recovery grants. They are referenced by active workflows. Dropping them would break those features. Empty does not establish unused. Literal-reference analysis is not proof that every column is necessary; no speculative column/table deletion was performed. File cleanup remains DATA-02 above.

The restore test covered a synthetic database, including binary signatures. It did not prove restoration of the school's production backup plus private uploads on another server. That full restore rehearsal, an off-server backup schedule, and a retention policy are still release requirements.

**5,000 simultaneous users: unproven**

The user clarified that the requirement means 5,000 people using the system at the same time. A database with 5,000 accounts is only one part of that requirement.

The capacity fixture contained 5,000 synthetic students/sessions, 55,000 office-clearance records and 62,084 requirement records. Tests used a separate loopback-only Apache instance with 150 worker threads and a separate database. The following are short local measurements, not production sizing guarantees:

| Workload | Requests | Maximum in flight | Throughput | p95 response time | HTTP errors |
|---|---:|---:|---:|---:|---:|
| Mixed dashboard/health/notification requests | 50 | 25 | 333/sec | 112 ms | 0 |
| Mixed dashboard/health/notification requests | 100 | 50 | 331/sec | 247 ms | 0 |
| Mixed dashboard/health/notification requests | 200 | 100 | 294/sec | 647 ms | 0 |
| Health/notification polling offered at 500 requests/sec for eight seconds | 3,998 | 100 cap | 483/sec including drain | 54 ms | 0 |

The polling probe had no dropped requests in the load generator. Two 20-second background polls per visible session imply approximately `5,000 × 2 / 20 = 500 requests/second` before clicks, PDFs, uploads or sign-ins. Browser scheduling and hidden-tab behavior affect the real load. The short polling result is encouraging but cannot establish sustained capacity.

All 11 staff office pages loaded against the 5,000-student fixture without visible PHP errors. Single-request times ranged from 270 ms to 1,146 ms; Dean was approximately 1,103 ms and Organization approximately 1,146 ms. These were serial samples, not office-page concurrency percentiles. The synthetic completed PDF was approximately 792 KB; a burst of 5,000 such downloads transfers about 4 GB before transport overhead.

Local database settings included `max_connections=151`, a 16 MiB InnoDB buffer pool, a 1 MiB maximum packet, and no slow-query logging. CLI OPcache was not loaded; web OPcache was not measured. These observations do not justify simply raising limits or configuring 5,000 PHP workers. Worker memory, database connections, query cost, upload storage and bandwidth must be budgeted together.

Before claiming the requested capacity, test on the intended HTTPS hosting environment from external load generators: ramp 100 → 500 → 1,000 → 2,500 → 5,000 distinct authenticated sessions with realistic think times; hold representative mixed activity for at least 30–60 minutes; separately test synchronized sign-in, QR arrival bursts, uploads, PDFs, Excel import/bulk approval and retry behavior. Record CPU/RAM, worker queues, connection counts, database locks/deadlocks, disk space, p95/p99 latency and error rates, then recheck duplicate prevention and clearance/signature integrity. Agree acceptance targets before that test. No hosting specifications were provided, so no production capacity approval is possible from this workstation audit.

**Test accounting and reproducibility**

The initial PHP run produced 37 passes, seven failures and one deliberately omitted suite. All seven failures passed after correcting only the isolated harness/fixture:

- Bookstore, Library and CBE-order fixtures needed the newly mandatory selected signatures before readiness/approval.
- Phase 2 Dean setup needed PREPARING rather than an already Ready baseline.
- The workbook design assertion expected old Registrar instruction wording; the current workbook uses Ctrl+F instructions.
- Private profile storage checks needed Apache access rules; PHP's built-in server does not enforce `.htaccess`.
- Semester notifications needed a closed period, valid synthetic session files and release of CLI session locks before HTTP calls. The final rerun passed current/archive isolation, unread ownership, CSRF, tampered period and inactive-account checks.

The disabled debug-console suite was not enabled or run. Its real application URL returned 403. One isolated Apache probe returned 500 because that deliberately minimal server lacked the `Require local` authorization provider used by the debug folder. This harness configuration error is retained in raw evidence; it is not evidence that the real debug console was accessible.

Browser results: 92 signature checks, 22 live-clearance checks, 62 sanctions UI checks, 19 PWA checks and 19 fresh scanner checks passed. The original preview suite failed as UI-01; all 66 checks passed after an audit-test cleanup wait. Scanner/install lifecycle unit tests also passed. [Consolidated results](summary.json), [initial suite results](suite-results.json), [notification rerun](notification-rerun-results.json), [preview with test wait](preview-with-cleanup-wait.json)

The audit did not test every possible attack or every row combination. It did not perform an internet-facing penetration test, multi-node failover, long-running saturation, physical phone installation/camera tests, email/SMS delivery, or a production-host restore. Existing accepted workflow decisions were retained, including closed-period PDF availability and the initial-password policy.

**Release sequence**

1. Resolve DEP-01/DEP-02 and UI-01; update the maintained tests, then rerun the affected checks.
2. Build a restricted staging release with synthetic accounts. Exclude `.git`, `.tmp`, `qa-audit`, tests, debug tools, private backups and workstation artifacts. Preserve required application assets and access rules. Keep necessary runtime uploads private and provide writable session/upload directories with appropriate permissions.
3. Configure supported patched runtimes, a private least-privilege database connection, HTTPS, proxy awareness for Secure cookies, private error logging, upload/request limits, monitoring, and scheduled recoverable backups. Verify deny rules on the actual web server; Apache rules require equivalent configuration on another server.
4. Reconcile historical approvals and unreferenced files according to an approved retention/migration decision. Rehearse database-plus-upload restore and rollback on the target platform.
5. Install/test on actual Android and iPhone devices: login/logout/session expiry, camera permission and retry, before/after capture, assessment upload, signed paper preview/PDF download, and offline/reconnect. HTTPS is required outside the localhost exception. [PWA installation requirements](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable)
6. Run the sustained 5,000-session mixed-workload test on the chosen host, tune using measurements, and repeat integrity checks before accepting school-wide traffic.

The QR compatibility path currently loads its decoder from unpkg. Native QR and failure guidance were tested, but third-party script availability remains a dependency; decide whether to serve a vetted local copy before relying on it during busy attendance windows.

Audit cleanup completed: both persistent audit schemas were removed; no matching deployment/load/restore/camera/journey audit schemas remained. Only the two pre-existing Apache processes remained running. Final checks returned 403 for this report/evidence directory and its direct report URL, runtime storage, debug tools and scratch artifacts, while login remained available. [Cleanup evidence](cleanup-results.json), [18 lifecycle unit checks](unit-results.log)
