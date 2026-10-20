# BUG-009 — Developer scratch files blocked from HTTP

Verified on local XAMPP on 18 October 2026, Asia/Manila.

Before the fix, `HEAD /ClearGo/.tmp/` returned **200**. The application root `.htaccess` now forbids `.tmp` path segments, including descendants and case variants. `.tmp/.htaccess` adds `Require all denied` and disables indexing. All four PHP scratch helpers now reject non-CLI execution before including application code or touching a database, filesystem or session:

- `apply-office-remarks.php`
- `clinic-remarks-debug.php`
- `dean-manual-clear-check.php`
- `remarks-preview-render.php`

## Apache access checks

These used HEAD requests after the deny rules were in place. Maintenance code was not executed through Apache.

| Path under `/ClearGo` | Result |
|---|---|
| `/.tmp` | 403 |
| `/.tmp/` | 403 |
| `/.tmp/apply-office-remarks.php` | 403 |
| `/.tmp/clinic-remarks-debug.php` | 403 |
| `/.tmp/dean-manual-clear-check.php` | 403 |
| `/.tmp/remarks-preview-render.php` | 403 |
| `/.tmp/ccs-dean-check/profile.html` | 403 |
| `/.TMP/apply-office-remarks.php` | 403 |
| `/.%74mp/` | 403 |
| `/.tmp%2fapply-office-remarks.php` | 404 |
| `/nested/.tmp/probe.php` | 403 |
| `/.tmp/apply-office-remarks.php?run=1` | 403 |

Login, shared dashboard CSS, Dean completion CSS and the server-status endpoint still returned **200**. Git configuration remained blocked with **403**. Apache configuration syntax passed, and all four helpers passed PHP lint.

## Independent PHP guard checks

An isolated PHP development server, which does not process Apache `.htaccess`, received GET and POST requests for each helper. All **8 requests** returned **403** with exactly `Forbidden`. The server was configured with a nonexistent database and invalid database user as a precaution; no database access occurred. It was stopped and its temporary log removed afterward.

The guards preserve command-line usage. The maintenance operations themselves were not run, and no school records or migrations were changed as part of this fix.

## Deployment

Exclude `.tmp/` from production packages. Its `.gitignore` entry does not restrict web access. Keep the application root `.htaccess` in the deployment; it requires Apache `mod_rewrite` and FileInfo overrides. Local XAMPP already permits these. Other server types must deny `.tmp` and descendants in their own configuration. Add the CLI guard before any work in future PHP scratch scripts. The deployment instructions in `tests/README.md` now cover this.
