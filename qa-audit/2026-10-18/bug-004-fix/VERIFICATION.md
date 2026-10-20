# BUG-004 fix verification

**Historical workflow:** The user subsequently replaced screenshot review with an official Excel completion list. See [the current implementation and verification](../ccs-excel-completion/VERIFICATION.md). The screenshot test suite described below was replaced by `tests/ccs_completion_workbook_test.php`.

Date: 18 October 2026, Asia/Manila.

## Confirmed policy

The user confirmed that proof must be submitted and checked before approval. When Faculty Evaluation applies to a CCS student, Dean clearance now requires:

- An applicable evaluation requirement record marked VERIFIED.
- An evaluation submission marked VERIFIED, with a reviewer, review time and at least one submitted screenshot reference.

The existing Submitted Requirements review action records verification separately from clearance approval. Missing proof, pending review, returned proof, missing requirement records and replaced submissions block clearance. A legacy verification flag alone is insufficient. Screenshots remain subject to the Dean's review; these checks do not establish evaluation authenticity independently.

## Implementation

`clearance/dean_evaluation_policy.php` defines evaluation applicability and readiness. Individual approval rechecks current requirement, submission and image records under transaction locks. The same policy drives bulk eligibility and the Dean dashboard's ready/action state. The automatic CCS evaluation-verification update was removed from approval.

The review handler rejects empty submissions and reviews against unavailable/closed or already signed clearance records. The dashboard directs the Dean to Submitted Requirements and no longer says evaluation will be confirmed during approval. Reports use the current submitted-proof status rather than the legacy automated-verification table.

Existing college/year/semester applicability is preserved: fourth-year CCS evaluation applies in the first semester; the second semester retains its separate graduation requirement. CBE and CTE do not acquire CCS proof requirements. Existing signed records are preserved; this change does not revoke historical approvals or modify school records.

## Verification

```powershell
& C:\xamppl\php\php.exe tests/dean_evaluation_prerequisite_test.php
& C:\xamppl\php\php.exe tests/bulk_clearance_test.php
& C:\xamppl\php\php.exe tests/registrar_cbe_initial_order_test.php
```

The focused test creates and removes a disposable MySQL schema using table definitions and reference configuration only. It runs actual multipart student uploads, Dean verification/return handlers, individual approval and bulk approval through a temporary local PHP server using synthetic accounts. Only its generated test images and sessions are cleaned up.

All **28 focused checks** passed, including the updated Phase 2 and Phase 3 integration suites in the disposable database. They cover blocked approvals leaving requirement metadata untouched, stale verified flags, empty proof, cross-college review denial, pending/returned/resubmitted proof, successful reviewed individual/bulk clearance, preservation of separate review metadata, duplicate approval, missing requirement records and semester applicability. Bulk regression tests and all 17 CBE initialization/repair checks also passed.

See [verification.log](verification.log). Original audit evidence remains historical; unrelated findings remain unresolved.
