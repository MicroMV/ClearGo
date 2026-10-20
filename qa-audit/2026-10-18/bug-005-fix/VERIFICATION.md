# BUG-005 fix verification

Date: 18 October 2026, Asia/Manila.

## Behavior

Any `borrowed_books` row still marked BORROWED prevents a new Library signature. All outstanding books count regardless of their borrowing date; this table has no semester field. Returned books and another student's loans do not block the student. Approved temporary exceptions cannot waive the return requirement.

The policy applies to list/summary readiness, individual generic approval, physical-card direct clearance, bulk clearance, workbook import and card-submission review. Approval functions recheck outstanding books under transaction locks. Valid card review can confirm a card while books remain outstanding, but does not clear the student or unlock SASO.

## Staff and student interface

The Library dashboard lists the outstanding titles and disables Clear. Review books opens the existing review panel, where staff can use Mark Returned for each book. The handler checks that the book belongs to the selected student, records the return date and staff verifier, and writes an audit entry. Recording returns does not automatically sign the clearance; staff can clear the student after every book is returned. Students can see their outstanding titles and due dates in the Library requirement panel. Titles are escaped as text.

## Executed verification

```powershell
& C:\xamppl\php\php.exe tests/library_book_return_policy_test.php
& C:\xamppl\php\php.exe tests/bulk_clearance_test.php
& C:\xamppl\php\php.exe tests/student_dashboard_history_test.php
& C:\xamppl\php\php.exe tests/dean_evaluation_prerequisite_test.php
```

All **32 focused Library checks passed**, including the existing Library workbook and dashboard summary suites in a disposable MySQL database. Tests covered both real workflow functions and authenticated HTTP requests against a temporary PHP server configured for that disposable database. All students, staff, book records and card-submission references were synthetic; private image bodies were not requested.

Checks included direct-request bypass attempts, individual/bulk/workbook approvals, card confirmation with and without outstanding books, non-waivable returns, role and book ownership, partial returns, dated and attributed returns, restored readiness, successful clearance and SASO unlocking. Existing student history rendering, the bulk suite for all eleven offices, and all 28 Dean prerequisite checks also passed. PHP lint passed for all nine changed/new PHP files. The HTTP test guards continued denying all 74 GET/POST requests across the current test scripts.

The tests removed their temporary databases, servers and authenticated sessions. No original school records were modified. Existing historical approvals were not revoked or mass-corrected. The original QA evidence and other unresolved findings remain historical records of the audit.
