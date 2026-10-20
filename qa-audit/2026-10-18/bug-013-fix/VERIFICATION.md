# BUG-013 — Staff account database errors handled safely

18 October 2026, Asia/Manila.

`registrar/staff_action.php` now handles `PDOException` before the general validation-error branch. MySQL duplicate-key error 1062 during account creation or email updates displays **That school email already has an account.** Other database failures display **Unable to update the staff account. Please try again.** Technical exception details continue to go to `error_log`. Existing transaction rollback and readable input-validation messages are preserved.

## Verification

`tests/registrar_staff_error_test.php` completed **35 checks** using a disposable MySQL schema, copied table definitions/reference configuration, synthetic accounts and actual HTTP form submissions. It verified:

- Duplicate staff creation produces the friendly message, preserves the existing password, creates no extra account and records no false success audit.
- Uppercase and whitespace variants of an existing email are rejected safely.
- Updating a staff email to another account's email preserves the original address and shows the same clear feedback.
- Ordinary invalid-input feedback remains readable.
- An unrelated foreign-key failure produces generic feedback and rolls back account creation.
- Normal creation and unique email updates still succeed, with the expected audit event.
- Invalid CSRF and non-Registrar requests cannot create accounts.
- Neither the redirect response nor the rendered Staff Accounts page exposes SQLSTATE, duplicate-entry details, integrity-constraint messages or exception traces.
- Technical duplicate-key and foreign-key error details are present in the isolated server log.

The synthetic sessions were logged out, the local test server stopped, the temporary log removed and the test database dropped. No real accounts or school records were modified.

Both changed/new PHP files passed lint. The dynamic HTTP test guard suite also passed **76 unauthenticated GET/POST denials**, including the new test, without executing test bodies or connecting to a database.

Scope: Registrar staff-account actions. Similar catch patterns in unrelated handlers are not declared fixed by this test. The account initial-password policy is unchanged; BUG-010 is separate.
