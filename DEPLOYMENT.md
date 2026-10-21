# ClearGo deployment and student password recovery

The application now supports student-only email OTP recovery and an optional 30-day saved sign-in for students and staff. Office accounts cannot use email recovery; the Registrar's existing password controls remain available. Local SMTP delivery was verified during setup. The target host still needs its own configuration and a recurring mail worker.

## Database migration

Take a private database-plus-upload backup, then apply `database/migrations/037_student_password_recovery_and_remember.sql` before deploying this code. It adds `users.auth_version` and four actively used tables for rate limits, reset requests, remembered devices and encrypted outgoing email. Existing passwords, students, school periods and approvals are preserved. The migration is safe to repeat.

Migration 037 was applied locally after a private database backup and a disposable-schema repeatability check. All 43 existing table row counts were preserved. The private backup location is recorded in `.tmp/auth-hardening-2026-10-20/migration-results.json`; keep backups outside the public release package.

For an existing installation, restore/migrate the current database; do not blindly replay historical migrations that seed accounts or reset passwords. Test the exact deployment/restore procedure against a separate database first. Use separate administrator credentials for migrations; the application runtime account does not need CREATE/DROP/ALTER privileges.

## SMTP setup after implementation

Supply hosting environment variables, or copy `config/environment.example.php` to the ignored, HTTP-denied `config/environment.local.php` on the target server. Hosting environment variables take precedence. Configure:

- `APP_ENV=production` and `APP_PUBLIC_URL=https://your-real-host/ClearGo` (use your actual path).
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. A staging/production environment rejects the local root/blank-password defaults.
- `APP_KEY`: generate once with `php -r "echo 'base64:'.base64_encode(random_bytes(32)),PHP_EOL;"`. Keep it private and stable across web requests and the scheduled mail worker. Back it up securely with configuration. Changing it makes pending encrypted email unreadable; outstanding OTPs should then be cancelled and requested again.
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION=tls` for STARTTLS (usually port 587), or `smtps` (usually port 465).
- `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and optionally `MAIL_FROM_NAME`. Use the provider's approved SMTP credentials. Google Workspace may require an app password or an approved SMTP relay under the institution's policy; a normal Google account password may not be accepted. [Google Workspace guidance](https://support.google.com/a/answer/176600)

Do not put credentials in Git or send them through chat. TLS peer verification is enabled. Plaintext/no-auth SMTP is permitted only for a development receiver bound to loopback. No codes are displayed in the UI or written as plaintext to the application database/logs.

Schedule this command **every minute** using the hosting scheduler/cron or Windows Task Scheduler, with the same environment/configuration as the web application:

```text
php /absolute/path/to/ClearGo/tools/auth_mail_worker.php --limit=100
```

The worker takes a database-specific lock, delivers encrypted queued messages through PHPMailer, retries transient delivery failures up to three attempts, cancels stale reset messages, purges expired authentication records, and records a heartbeat for its database. A delivery error produces a nonzero process exit; monitor and alert on these failures. Run the worker at least once after configuring SMTP. Test an actual student mailbox and its spam folder before enabling the feature for students. Verify sender-domain SPF/DKIM/DMARC with the mail provider.

The mail queue keeps the public response independent of SMTP latency and account existence. Missing SMTP configuration makes email recovery unavailable. If SMTP is configured but the worker is not running, messages can remain queued and students will not receive their codes. Normal sign-in and the Registrar's existing password reset continue to work.

## Current target: Hostinger Web Hosting

The user selected Hostinger Web Hosting on 21 October 2026. The exact tier and account resource limits are not yet known. The local test results remain valid; the InfinityFree-specific restrictions below describe the earlier target and are not a Hostinger deployment verdict. No Hostinger account has been accessed or deployed to during this audit.

Hostinger documents [PHP/custom cron jobs for Web Hosting](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/), so the current queued OTP design has a supported scheduling route. In the site's dashboard, open **Cron Jobs**, schedule the CLI worker every minute (`* * * * *`), and use the actual installed application path and the PHP CLI binary/version for that account. The required command is the mail-worker command above. Verify its output and worker heartbeat, then test delivery to an actual student mailbox. Do not point cron at a public worker URL. Keep authenticated SMTP configured; check the selected mail provider's quotas separately from web-hosting resources.

Before opening the site to students:

1. Create a separate staging database and restore the current database plus private uploads together. Apply only missing migrations, including migration 037. Verify signatures and attachments.
2. Upload the release package, preserve all `.htaccess` rules, and configure production settings privately: the actual HTTPS URL, database credentials, stable application key and SMTP settings. Keep debug tools and audit artifacts excluded.
3. Select a supported patched PHP version meeting the deployment check's minimum and enable the required extensions and OPcache. Verify CLI and web runtime compatibility. Check writable private storage and keep PHP errors out of responses.
4. Run `tools/deployment_check.php` through CLI, resolve failures, and verify HTTPS, private-path denial and authenticated-page cache exclusion on the real site. Test login/logout, student-only OTP reset, a complete clearance journey, uploads and PDFs.
5. Test the PWA and QR camera on actual Android and iPhone browsers over the deployed HTTPS address. Recheck offline/reconnect behavior and that private pages/files are not cached for another account.
6. Measure a staged mixed workload within the hosting provider's testing rules before any claim of 5,000 simultaneous users. Record CPU/RAM, PHP-worker usage, database connections, latency and error rates. Check the exact account limits in hPanel; [Hostinger limits vary by plan](https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/). Monthly visitor estimates and unlimited bandwidth do not establish concurrent-user capacity. Current polling alone implies approximately 750 recurring requests/second at 5,000 visible active student tabs during open clearance.

**Status: candidate for a controlled staging deployment after configuration, not approved for 5,000-user production traffic.** No InfinityFree-specific PWA adaptation should be added solely because it was needed for the previously proposed host.

## Earlier target: InfinityFree free hosting

The 21 October 2026 [deployment audit](qa-audit/2026-10-21/DEPLOYMENT_READINESS.md) passed the local application checks but did not approve a full deployment on InfinityFree. Its [cron restrictions](https://forum.infinityfree.com/t/cron-job-feature-is-now-disabled/80495) prevent running the current scheduled OTP worker. Its mandatory [browser challenge](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353) affects static PWA manifests and service workers; a provider-specific adaptation and actual-device tests would be required. Its [50,000 daily hits allowance](https://forum.infinityfree.com/t/what-does-the-hits-limit-mean/49330/) is unsuitable for the requested 5,000 simultaneously active users with the current polling behavior. A small synthetic-data browser demonstration still requires target-host HTTPS, database restore, private-file access checks and workflow validation. Do not expose CLI tools over HTTP to work around hosting restrictions.

## Authentication behavior

Codes are six random digits, expire 10 minutes after request, permit at most five failed verification attempts and are bound to the requesting browser. Resending invalidates the prior code. A verified code creates a single-use, 10-minute reset grant. New passwords require confirmation and 8–72 bytes (the application uses bcrypt). Successful recovery does not automatically sign the student in. It revokes existing sessions, all remembered devices and outstanding codes, records an audit event, and queues a password-change email without the password.

The public recovery response is the same for active students, office accounts, inactive accounts and unknown addresses. Eligibility is checked again when verifying, resetting and delivering the email. Office accounts never receive recovery codes or reset grants.

Login permits five attempts per normalized account in 15 minutes and 20 per browser in 15 minutes. A successful login clears the account bucket. `AUTH_LOGIN_IP_LIMIT` defaults to 10,000 attempts per IP per 15 minutes to accommodate a shared campus network; tune it with host-level protection and measurements. It is an aggregate ceiling, not a capacity guarantee. Reset requests allow three per account per hour, one resend per minute and ten per browser per hour. `AUTH_RESET_IP_LIMIT` defaults to 300 per hour. Verification/reset also have browser/IP limits. Requests beyond a limit return HTTP 429 and Retry-After. Counters are enforced atomically across concurrent workers. The application uses REMOTE_ADDR; configure trusted proxy client-address handling in the server rather than trusting arbitrary forwarded headers in PHP.

Saved sign-in uses random rotating tokens with only verifier hashes stored in the database, a fixed 30-day expiry, up to five devices per account, and HttpOnly/SameSite cookies (Secure over HTTPS). It does not store passwords. Logout removes that device's token; a password change/reset revokes all devices and older sessions. A normal signed-in student password change keeps only the current session active. Existing pre-migration sessions are treated as version 1 and are invalidated by a subsequent password change.

## Release package and web server

Use `php tools/build_release.php` to build a ZIP and SHA-256 inventory under `.tmp/releases/`. It uses the existing ZIP writer if the optional PHP zip extension is unavailable. The package excludes audit reports, tests, debug tools, Git/scratch files, local credentials, real uploads, sessions, database dumps and private backups. It includes the current CLI mail/preflight/maintenance tools; their directory and PHP entry points deny web execution. Database migrations are included in a denied directory; application data is transferred separately through a private database restore.

Preserve the root `.htaccess`, storage denial rules, and required assets. Apache needs `AllowOverride All` for this application directory and the relevant rewrite/headers/access-control modules, or equivalent rules installed in the virtual-host configuration. For Nginx or another server, configure equivalent denial of `/storage/`, `/qa-audit/`, `/tests/`, `/tools/`, `/lib/`, `/debug/`, hidden credential files, source dumps and development documentation. PHP guards provide another layer, but storage and static files require server rules.

The application and Apache rules set SAMEORIGIN/frame-ancestors 'self', nosniff and a same-origin referrer policy. The framing policy preserves the same-origin paper preview. The preview now cleans up synchronously and ignores an old close event after reopening. QR compatibility code is served from `assets/vendor/html5-qrcode/`, including the license, so scanning does not require unpkg availability.

Use a supported, patched server/PHP/database, enable and size web OPcache, keep the database private, configure appropriate request/upload/time limits, and allow writes to private session/upload/backup directories. HTTPS and correct trusted proxy TLS configuration are required for Secure cookies, PWA installation and phone camera access. Run:

```text
php tools/deployment_check.php
```

This command exits nonzero for missing configuration, outdated minimum runtime versions, missing migration/security tables, unhealthy mail-worker status, inaccessible storage, or active approvals without signatures. It does not replace actual certificate/header/permission checks or the 5,000-session host test.

## Data retention and historical approvals

Run `php tools/maintenance.php` to inspect unreferenced profile images and approvals without signature snapshots. `--archive-unreferenced-profiles` moves only unreferenced images at least a day old into protected `storage/backups/profile-retention/`, retaining their original names and a SHA-256 manifest. It rechecks current references under a database lock, validates source/destination paths and verifies each archived file. No current profile or database record is deleted. Use `--min-age=SECONDS` only for an explicit retention decision (minimum one hour).

The local run archived 72 old, unreferenced profile images; all bytes remain available in the private retention archive. Seventy-seven approvals from closed semesters lack signature snapshots. No active approval was affected. Their private review list was saved with the archive. The Registrar must classify these as legitimate historical approvals or disposable test history. Do not manufacture signatures, reset legitimate clearances or silently copy a current signatory onto old approvals.

All retained database tables have active application references. Empty sanction, obligation, document or recovery tables support existing features. Migration 037's new tables are likewise used immediately by authentication and its mail worker.

## Remaining release acceptance

After SMTP configuration, test a real student's request → inbox code → reset → sign-in, and verify that an office address never receives a code. Test saved sign-in across closing/reopening the browser, logout, account deactivation, and password change.

Install and test on actual Android and iPhone devices over the real HTTPS host: session expiry, camera permission/retry, sanctions photos, assessment upload, paper preview/PDF download and offline/reconnect. Restore the database and private files together on the target host and verify signatures and attachments. Monitor disk space, delivery failures, worker status and availability.

Capacity for **5,000 simultaneously active users is still unproven**. Run a realistic sustained mixed workload on the chosen host, including login/QR bursts, PDFs/uploads, imports and bulk approval, with CPU/RAM, worker queues, database connections/locks, errors and p95/p99 latency monitored. Local short tests cannot approve that production capacity.

Security design references: [OWASP password recovery](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html), [OWASP session management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [PHPMailer official release](https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1).
