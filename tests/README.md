# Developer tests

Run `node tests/logout_confirmation_browser_test.mjs` to check logout confirmation on student and office layouts at desktop and mobile widths. It renders the real shared logout form through the CLI-only `tests/support/logout_fixture.php`, then uses a local HTTP receiver to verify cancellation, Escape, focus trapping, rapid reopening, and exactly one confirmed CSRF-protected POST. It uses the installed PHP CLI and Edge, submits no school session, and saves screenshots/results under `.tmp/logout-confirmation-check/`.

Run PHP tests from the command line, for example:

```powershell
& C:\xamppl\php\php.exe tests/staff_navigation_test.php
```

These scripts are development tools. Several integration tests write to their configured database. Use a disposable database with synthetic records when running them; do not run them against school data.

`php tests/auth_recovery_test.php` creates/removes a disposable schema and starts a loopback-only PHP server and SMTP receiver. Apply migration 037 first. It tests student-only OTP requests, encrypted delivery through the scheduled SMTP worker, expiry/attempt limits, browser binding, concurrent single-use reset, saved sign-in rotation/expiry/tampering, logout/password-change revocation, and atomic rate limiting. No messages leave the local receiver. It requires PHP CLI with PDO MySQL/OpenSSL/cURL, and Node (the Windows fixture uses the installed Node path).

For HTTP fixtures using an isolated Apache server, set `CLEAR_GO_TEST_BASE_URL` to its URL and `DB_DATABASE` to the matching disposable schema. Profile privacy checks require Apache access rules; PHP's built-in server does not enforce `.htaccess`. Notification fixtures require both current and archived periods. The phase-2 Dean test now creates its own PREPARING semester/schema. Login helpers fetch the new CSRF token after sign-in rotates it.

Migration `036_remove_student_section_and_retired_receipt_ai.sql` removes `students.section` and the retired `accounting_receipt_verifications` table. The student fixtures now target that schema. Run `php tests/student_section_removal_test.php` for an isolated migration, Registrar create/update, student dashboard/PDF and MySQL import check. It creates and removes its own disposable schema, reconstructing the old column/table only inside that schema when necessary. Do not run `student_import_mysql_test.php` directly against school data; the section-removal suite invokes it against its synthetic database.

PWA checks: run `node --test tests/pwa_install_test.mjs` for installation lifecycle, deployment-path and icon checks. With local Apache serving `http://localhost/ClearGo/`, run `node tests/pwa_browser_test.mjs` to check manifest eligibility, icon responses, mobile installation controls and offline/reconnect behavior using Edge. This browser test forwards anonymous GET requests through a temporary read-only proxy and does not install an OS app. Browser profiles, screenshots and results are written to `.tmp/pwa-check/`.

Exclude the entire `tests/` directory from production deployment packages. If it is present on an Apache host, keep `.htaccess` with it and allow that directory's access-control directives in the server configuration. Other web servers must deny `/tests/` and all descendants in their own configuration.

Every PHP entry point in this directory must reject non-CLI execution before including application code or touching files, sessions or databases. This guard is also required for new tests, even with the Apache denial rule present. Do not remove it to preview a test in a browser.

`attendance_sanctions_test.php` creates and removes its own disposable schema using the configured database as a schema template. Apply migration `034_attendance_sanctions.sql` first. It tests immediate QR check-in, absence assignment, separate Organization/Council photo batches, protected images, review and resubmission, and manual/bulk clearance. Then run `node tests/attendance_sanctions_browser_test.mjs` to check the synthetic response snapshots at desktop and mobile widths; screenshots and results are saved under `.tmp/attendance-sanctions-check/browser/`. The browser test uses the installed Microsoft Edge.

Also exclude `.tmp/` from production packages: it contains scratch scripts and generated previews. The application's root `.htaccess` denies both `.git` and `.tmp` path segments; keep that root file in deployments and enable Apache `mod_rewrite` with FileInfo overrides. Local `.tmp/.htaccess` provides an additional access denial. Any PHP helper added to `.tmp/` must reject non-CLI execution before doing any work. Other web servers need equivalent deny rules.
