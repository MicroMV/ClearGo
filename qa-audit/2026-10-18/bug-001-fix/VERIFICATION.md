# BUG-001 Bookstore fix verification

Date: 18 October 2026, Asia/Manila.

## Behavior

Bookstore obligations and event fees can be prepared only while the semester is PREPARING and Bookstore is NOT_READY. Marking Bookstore Ready disables obligation creation, event fee creation and event closing, and the server rejects direct requests for those operations. Missing readiness configuration also blocks changes.

The existing Undo Ready action restores editing during preparation. Once clearance opens, Undo Ready cannot reopen preparation. Because students may already be signed during preparation, an individual signed student cannot receive a new obligation even after Undo Ready; adding a fee for the entire semester is also blocked once Bookstore has signed students. These rules preserve existing decisions rather than reopening completed clearances.

Recording obligation payments, confirming event receipts and importing payment/clearance workbooks remain available after Ready. Workbook import only settles payments, records remarks and clears students; it does not create obligations or edit event fee definitions. An existing repeated SQL parameter in workbook balance settlement was corrected so that path works with native PDO prepares.

Mutations and readiness transitions lock the period/readiness rows in transactions. Testing with two database connections confirmed that Mark Ready waits for an ongoing preparation transaction.

The student dashboard now checks the office approvals before displaying completion and shows In progress if a stored COMPLETED flag conflicts with unresolved offices. The Registrar completion helper also rejects unresolved office statuses.

## Verification

Run from the project root:

```powershell
& C:\xamppl\php\php.exe tests/bookstore_ready_lock_test.php
& C:\xamppl\php\php.exe tests/student_dashboard_history_test.php
```

All **30 Bookstore checks** passed, including three existing Bookstore regression suites. The new suite creates and removes its own MySQL schema, copies table definitions/reference configuration only, and uses synthetic students, staff and payments. Its authenticated HTTP checks run the actual handlers on a temporary local PHP server configured to use that disposable schema.

Executed checks include editing before Ready, serialized readiness transitions, direct POST bypass attempts, disabled controls, individual payment recording, receipt confirmation, workbook settlement/approval after Ready, Undo Ready, protection of signed/completed students, completion guards, and missing configuration. Student dashboard tests cover stale completion, normal completion and existing active/history rendering. See [verification.log](verification.log).

No original school records were changed by this fix or its tests. No bulk correction of historical status inconsistencies was applied. The original audit remains historical evidence; general downstream revocation/reapproval policy for other offices is outside this change.
