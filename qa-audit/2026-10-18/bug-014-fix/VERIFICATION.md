# BUG-014 — Malformed CSRF input rejected safely

18 October 2026, Asia/Manila.

The shared `verifyCsrfToken` function now accepts untrusted input as `mixed`, rejects non-string and empty values before verification, checks that the session token is a string, and retains `hash_equals` for the valid comparison. Previously the `?string` parameter rejected an array before the function's own type check could run.

Login now rejects array-shaped email and password fields without casting them to strings. Invalid CSRF still produces the existing controlled feedback and 303 redirect to login. Invalid credentials retain the normal invalid-email/password response.

The web authentication bootstrap disables `display_errors` and `display_startup_errors` and enables `log_errors`; CLI behavior is unchanged. Root `.htaccess` also sets the web error-display/logging flags when Apache's PHP module is loaded. These changes are inside the project; the global XAMPP PHP configuration was not edited.

## Verification

| Check | Result |
|---|---|
| `tests/csrf_request_validation_test.php` | **47 passed** |
| `tests/registrar_staff_error_test.php` | **35 passed**, including valid login and account actions |
| `tests/ccs_completion_workbook_test.php` | **97 passed**, including valid login, CSRF-protected actions, both semesters' verification and individual/bulk clearing; existing Phase 2/3 regressions also passed |
| PHP test HTTP guards | **78** unauthenticated GET/POST requests denied before test initialization |
| PHP lint | All three changed/new PHP files passed |
| Apache configuration | Syntax OK |

The focused suite tests missing, array, associative/nested array, scalar, object, empty and mismatched tokens; valid tokens; malformed server-session state; and array-shaped credentials. HTTP cases verify controlled redirects, empty action bodies and safe feedback. A local PHP server deliberately started with unsafe error-display defaults then verified the application's web settings, confirmed a synthetic internal exception returned 500 without details, and confirmed its warning/exception markers appeared only in the server log. A nonexistent database and invalid database connection settings prevented accidental access; malformed requests did not reach a database. The isolated server, temporary router and log were cleaned up.

On running XAMPP, the original request:

```text
POST /ClearGo/auth/login_process.php
csrf_token[]=invalid
```

now returns **303 See Other**, redirecting to `/ClearGo/login.php`, with **0 response-body bytes**. It exposes no TypeError, stack trace or internal path. Login remained available (200), server status returned 200, and both `.git/config` and `.tmp/` remained blocked (403).

The staff and CCS regressions used disposable schemas and synthetic users, which were cleaned up. No real accounts, credentials, student records or approvals were changed.
