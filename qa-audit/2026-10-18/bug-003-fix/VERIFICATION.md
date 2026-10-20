# BUG-003 fix verification

Date: 18 October 2026, Asia/Manila.

## Protection

- `tests/.htaccess` denies HTTP access to the entire test directory and disables directory indexing on Apache.
- Added early CLI-only guards to 29 existing PHP test scripts. Five other scripts already had guards. The new HTTP regression test also has a guard, making 35 guarded PHP scripts in total.
- Guards return HTTP 403 before application includes, session initialization or database operations.
- `tests/README.md` documents command-line execution and requires excluding tests from production deployment packages. If retained, Apache must honor the directory denial rule; other web servers require equivalent directory access controls.

## Executed verification

| Check | Result |
|---|---|
| Local XAMPP Apache: HEAD requests to all 35 PHP tests, tests root, README and .htaccess | All 38 returned HTTP 403 |
| Temporary PHP server that ignores .htaccess: unauthenticated GET and POST to every PHP test | All 70 returned HTTP 403, with no test output or session cookies |
| PHP syntax checks | All 35 test scripts passed |
| Command-line staff navigation regression | Passed |
| Command-line student import unit regression | Passed |
| Command-line student dashboard history/completion regression | Passed |
| Command-line Registrar CBE initialization/repair regression in a disposable database | All 17 checks passed |

The PHP-only HTTP check uses an invalid database target in its isolated server environment. No test body or database connection is reached. Apache additionally denies non-PHP test artifacts, demonstrating directory-level protection.

Commands from the project root:

```powershell
& C:\xamppl\php\php.exe tests/http_test_access_test.php
& C:\xamppl\php\php.exe qa-audit/2026-10-18/bug-003-fix/verify-apache.php
```

Apache request details are saved in [apache-results.json](apache-results.json). The original audit evidence remains unchanged. This verification covers the local XAMPP host and the independent PHP guards; no external production host was accessed. No school records were modified by these HTTP checks or unit regressions.
