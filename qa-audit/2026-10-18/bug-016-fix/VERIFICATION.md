# BUG-016 — Runtime storage HTTP access denied

Implemented and tested on 18 October 2026; reconfirmed and test-picture cleanup completed on 19 October 2026, Asia/Manila.

The application root `.htaccess` now denies requests beginning with the `storage` path segment, including case variants and descendants. `storage/.htaccess` adds `Require all denied` and `Options -Indexes`. PHP handlers outside the storage directory continue reading authorized files internally through filesystem functions; those reads are not HTTP requests to `/storage/`.

## Executed checks

Before the change, `HEAD /ClearGo/storage/` returned 200. All 13 storage HEAD checks below returned **403** after the change; no listing or private file contents were requested.

| Path under `/ClearGo` | Result |
|---|---|
| `/storage` | 403 |
| `/storage/` | 403 |
| `/storage/private/` | 403 |
| `/storage/sessions/` | 403 |
| `/storage/backups/` | 403 |
| `/storage/private/profile-pictures/` | 403 |
| `/storage/private/bookstore-receipts/` | 403 |
| `/storage/private/library-cards/` | 403 |
| `/storage/private/accounting-receipts/` | 403 |
| `/STORAGE/` | 403 |
| `/%73torage/` | 403 |
| `/storage/private/not-a-real-file.png` | 403 |
| `/storage/?listing=1` | 403 |

Login, shared dashboard CSS and server status still returned 200. Unauthenticated requests to the profile, Bookstore receipt, Library card and Accounting assessment image handlers still redirected to login (303). Apache configuration syntax passed.

`tests/profile_picture_test.php` passed its upload, replacement, ownership and audit-context checks for **all 12 account roles** against local Apache using temporary test accounts and generated pictures. It also confirmed authorized `/auth/profile_image.php` responses returned the expected PNG bytes while direct storage URLs remained forbidden. No real user picture was used for these checks.

The existing test removed its synthetic accounts and sessions. Windows permissions initially prevented its image-file cleanup, and a subsequent automatic approval review could not complete because of a usage limit. Cleanup was completed on 19 October after rechecking the exact test-file paths, generated-image hashes and absence of database references. All 12 remaining generated pictures were removed; real user pictures were excluded. The storage root and backups were reconfirmed as 403 and login as 200 on that date.

No existing student records, approvals, uploads, sessions or backups were removed or changed by the security fix.

## Deployment

Keep both `.htaccess` files. The root rewrite requires Apache `mod_rewrite` and FileInfo overrides; the storage denial requires AuthConfig/Options overrides. Local XAMPP already permits these. Other web servers need an equivalent direct-access denial for `/storage` and every descendant. Keep protected image handlers outside that tree and retain their authentication/authorization checks.
